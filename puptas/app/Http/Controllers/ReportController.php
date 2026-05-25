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
use App\Exports\MasterlistExport;
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
        $paginator = $query->with(['user.testPasser', 'program', 'processes'])->paginate(15);

        $paginator->getCollection()->transform(function ($app) {
            return [
                'id' => $app->id,
                'user_id' => $app->user_id,
                'reference_number' => $app->user->testPasser->reference_number ?? 'N/A',
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
        $applicants = $query->with(['user.testPasser', 'program', 'processes'])->limit(1000)->get();

        $data = $applicants->map(function ($app) {
            return [
                'reference_number' => $app->user->testPasser->reference_number ?? 'N/A',
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
        $query->with(['user.testPasser', 'program', 'processes']);

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
            })
            ->whereDoesntHave('processes', function ($q) {
                $q->where('stage', 'medical')->where('status', 'completed');
            })
            ->where(function($q) {
                $q->where('enrollment_status', '!=', 'officially_enrolled')
                  ->orWhereNull('enrollment_status');
            });
        } elseif ($type === 'medical') {
            $query->whereHas('processes', function ($q) {
                $q->where('stage', 'medical')->where('status', 'completed');
            })
            ->where(function($q) {
                $q->where('enrollment_status', '!=', 'officially_enrolled')
                  ->orWhereNull('enrollment_status');
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

    /*
     * ----------------------------------------------------
     * ACCEPTED MASTERLIST METHODS
     * ----------------------------------------------------
     */

    public function masterlistIndex()
    {
        $programs = Program::all(['id', 'code', 'name']);
        
        return Inertia::render('Reports/Masterlist', [
            'programs' => $programs
        ]);
    }

    private function buildMasterlistQuery(Request $request)
    {
        // Scope to the latest application per user that has passed the interview stage
        $query = Application::query()
            ->whereIn('id', function ($sub) {
                $sub->selectRaw('MAX(id)')
                    ->from('applications')
                    ->whereNull('deleted_at')
                    ->groupBy('user_id');
            })
            ->whereHas('processes', function ($q) {
                $q->where('stage', 'interviewer')
                  ->where('status', 'completed')
                  ->where('action', 'passed');
            });

        // Filter by Program
        if ($request->filled('program_id')) {
            $query->where('program_id', $request->program_id);
        }

        // Filter by Search Query
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('firstname', 'like', "%{$search}%")
                      ->orWhere('lastname', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('student_number', 'like', "%{$search}%");
                })->orWhereHas('user.testPasser', function ($tpq) use ($search) {
                    $tpq->where('reference_number', 'like', "%{$search}%");
                });
            });
        }

        // Filter by Date
        if ($request->filled('date_filter')) {
            $date = $request->date_filter;
            $query->whereHas('processes', function ($q) use ($date) {
                $q->where('stage', 'interviewer')
                  ->where('status', 'completed')
                  ->where('action', 'passed')
                  ->whereDate('updated_at', $date);
            });
        }

        // Filter by Month
        if ($request->filled('month_filter')) {
            $monthFilter = $request->month_filter; // e.g., '2023-10'
            $year = substr($monthFilter, 0, 4);
            $month = substr($monthFilter, 5, 2);
            $query->whereHas('processes', function ($q) use ($year, $month) {
                $q->where('stage', 'interviewer')
                  ->where('status', 'completed')
                  ->where('action', 'passed')
                  ->whereYear('updated_at', $year)
                  ->whereMonth('updated_at', $month);
            });
        }

        return $query;
    }

    public function masterlistData(Request $request)
    {
        $query = $this->buildMasterlistQuery($request);
        
        $paginator = $query->with(['user.testPasser', 'program', 'processes'])->paginate(15);

        $paginator->getCollection()->transform(function ($app) {
            $interviewProcess = $app->processes
                ->where('stage', 'interviewer')
                ->where('status', 'completed')
                ->where('action', 'passed')
                ->first();
            $dateAccepted = $interviewProcess ? $interviewProcess->updated_at->format('Y-m-d') : $app->updated_at->format('Y-m-d');

            return [
                'id' => $app->id,
                'user_id' => $app->user_id,
                'student_number' => $app->user->student_number ?? 'N/A',
                'reference_number' => $app->user->testPasser->reference_number ?? 'N/A',
                'name' => trim(($app->user->firstname ?? '') . ' ' . ($app->user->lastname ?? '')),
                'email' => $app->user->email ?? 'N/A',
                'program' => $app->program->code ?? 'N/A',
                'date_accepted' => $dateAccepted
            ];
        });

        // Compute program statistics: total passers per program
        $programStats = Program::select('id', 'code', 'name')
            ->withCount(['applications' => function ($q) {
                $q->whereIn('id', function ($sub) {
                    $sub->selectRaw('MAX(id)')
                        ->from('applications')
                        ->whereNull('deleted_at')
                        ->groupBy('user_id');
                })
                ->whereHas('processes', function ($subQ) {
                    $subQ->where('stage', 'interviewer')
                         ->where('status', 'completed')
                         ->where('action', 'passed');
                });
            }])
            ->get()
            ->map(function ($program) {
                return [
                    'id' => $program->id,
                    'code' => $program->code,
                    'name' => $program->name,
                    'count' => $program->applications_count
                ];
            });

        // Compute overall accepted count
        $overallCount = Application::query()
            ->whereIn('id', function ($sub) {
                $sub->selectRaw('MAX(id)')
                    ->from('applications')
                    ->whereNull('deleted_at')
                    ->groupBy('user_id');
            })
            ->whereHas('processes', function ($q) {
                $q->where('stage', 'interviewer')
                  ->where('status', 'completed')
                  ->where('action', 'passed');
            })
            ->count();

        return response()->json([
            'paginator' => $paginator,
            'programStats' => $programStats,
            'overallCount' => $overallCount
        ]);
    }

    public function masterlistExportPdf(Request $request)
    {
        $query = $this->buildMasterlistQuery($request);
        $applications = $query->with(['user.testPasser', 'program', 'processes'])->limit(1000)->get();

        $data = $applications->map(function ($app) {
            $interviewProcess = $app->processes
                ->where('stage', 'interviewer')
                ->where('status', 'completed')
                ->where('action', 'passed')
                ->first();
            $dateAccepted = $interviewProcess ? $interviewProcess->updated_at->format('Y-m-d') : $app->updated_at->format('Y-m-d');

            return [
                'student_number' => $app->user->student_number ?? 'N/A',
                'reference_number' => $app->user->testPasser->reference_number ?? 'N/A',
                'name' => trim(($app->user->firstname ?? '') . ' ' . ($app->user->lastname ?? '')),
                'email' => $app->user->email ?? 'N/A',
                'program' => $app->program->code ?? 'N/A',
                'date_accepted' => $dateAccepted
            ];
        });

        $pdf = Pdf::loadView('reports.masterlist', [
            'applicants' => $data,
            'date' => now()->format('F d, Y')
        ])->setPaper('a4', 'landscape');

        return $pdf->download('accepted_applicants_masterlist.pdf');
    }

    public function masterlistExportExcel(Request $request)
    {
        $query = $this->buildMasterlistQuery($request);
        $query->with(['user.testPasser', 'program', 'processes']);

        return Excel::download(new MasterlistExport($query), 'accepted_applicants_masterlist.xlsx');
    }
}
