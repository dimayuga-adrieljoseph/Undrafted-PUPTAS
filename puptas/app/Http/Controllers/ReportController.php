<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Application;
use App\Models\Program;
use App\Models\ApplicantProfile;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ApplicantsExport;
use App\Services\ApplicationStatusService;

class ReportController extends Controller
{
    protected ApplicationStatusService $statusService;

    public function __construct(ApplicationStatusService $statusService)
    {
        $this->statusService = $statusService;
    }

    public function index()
    {
        $programs = Program::all(['id', 'code', 'name']);
        
        return Inertia::render('Reports/Index', [
            'programs' => $programs
        ]);
    }

    public function getReportData(Request $request)
    {
        $query = $this->buildReportQuery($request);
        $paginator = $query->with(['user', 'program', 'processes'])->paginate(15);

        $paginator->getCollection()->transform(function ($app) {
            return [
                'id' => $app->id,
                'user_id' => $app->user_id,
                'student_number' => $app->user->student_number ?? 'N/A',
                'name' => trim(($app->user->firstname ?? '') . ' ' . ($app->user->lastname ?? '')),
                'email' => $app->user->email ?? 'N/A',
                'program' => $app->program->code ?? 'N/A',
                'status' => $this->statusService->determineStatus($app),
                'date' => $app->updated_at->format('Y-m-d')
            ];
        });

        return response()->json($paginator);
    }

    public function exportPdf(Request $request)
    {
        $query = $this->buildReportQuery($request);
        // Limit PDF export to prevent memory exhaustion and extremely slow generation
        $applicants = $query->with(['user', 'program', 'processes'])->limit(1000)->get();

        $data = $applicants->map(function ($app) {
            return [
                'student_number' => $app->user->student_number ?? 'N/A',
                'name' => trim(($app->user->firstname ?? '') . ' ' . ($app->user->lastname ?? '')),
                'program' => $app->program->code ?? 'N/A',
                'status' => $this->statusService->determineStatus($app),
                'date' => $app->updated_at->format('Y-m-d')
            ];
        });

        $pdf = Pdf::loadView('reports.applicants', ['applicants' => $data, 'reportType' => $request->type]);
        return $pdf->download('applicant_report.pdf');
    }

    public function exportExcel(Request $request)
    {
        $query = $this->buildReportQuery($request);
        // Pass the builder instance to the export class for chunked query execution
        $query->with(['user', 'program', 'processes']);

        return Excel::download(new ApplicantsExport($query, $this->statusService), 'applicant_report.xlsx');
    }

    private function sanitizeExcelValue($value)
    {
        if (is_string($value) && in_array(substr($value, 0, 1), ['=', '+', '-', '@'])) {
            return "'" . $value;
        }
        return $value;
    }

    private function buildReportQuery(Request $request)
    {
        // Scope to the latest application per user
        $query = Application::query()
            ->whereIn('id', function ($sub) {
                $sub->selectRaw('MAX(id)')
                    ->from('applications')
                    ->whereNull('deleted_at')
                    ->groupBy('user_id');
            });

        // Filter by type
        $type = $request->input('type');
        if ($type === 'interview') {
            $query->whereHas('processes', function ($q) {
                $q->where('stage', 'interviewer')
                  ->where('status', 'completed')
                  ->whereIn('action', ['passed', 'transferred']);
            });
        } elseif ($type === 'medical') {
            $query->whereHas('processes', function ($q) {
                $q->where('stage', 'medical')->where('status', 'completed');
            });
        } elseif ($type === 'enrollment') {
            $query->where('enrollment_status', 'officially_enrolled');
        }

        // Filter by Program
        if ($request->filled('program_id')) {
            $query->where('program_id', $request->program_id);
        }

        // Filter by Date
        if ($request->filled('date_filter')) {
            $dateFilter = $request->date_filter; // e.g., '2023-10-15'
            $query->whereDate('updated_at', $dateFilter);
        }

        // Filter by Month
        if ($request->filled('month_filter')) {
            $monthFilter = $request->month_filter; // e.g., '2023-10'
            $year = substr($monthFilter, 0, 4);
            $month = substr($monthFilter, 5, 2);
            $query->whereYear('updated_at', $year)
                  ->whereMonth('updated_at', $month);
        }

        return $query;
    }
}
