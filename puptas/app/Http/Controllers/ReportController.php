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

class ReportController extends Controller
{
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
        $applicants = $query->with(['user', 'program', 'processes'])->get();

        $formatted = $applicants->map(function ($app) {
            return [
                'id' => $app->user_id,
                'student_number' => $app->user->student_number ?? 'N/A',
                'name' => trim(($app->user->firstname ?? '') . ' ' . ($app->user->lastname ?? '')),
                'email' => $app->user->email ?? 'N/A',
                'program' => $app->program->code ?? 'N/A',
                'status' => $this->determineStatus($app),
                'date' => $app->updated_at->format('Y-m-d')
            ];
        });

        return response()->json($formatted);
    }

    public function exportPdf(Request $request)
    {
        $query = $this->buildReportQuery($request);
        $applicants = $query->with(['user', 'program', 'processes'])->get();

        $data = $applicants->map(function ($app) {
            return [
                'student_number' => $app->user->student_number ?? 'N/A',
                'name' => trim(($app->user->firstname ?? '') . ' ' . ($app->user->lastname ?? '')),
                'program' => $app->program->code ?? 'N/A',
                'status' => $this->determineStatus($app),
                'date' => $app->updated_at->format('Y-m-d')
            ];
        });

        $pdf = Pdf::loadView('reports.applicants', ['applicants' => $data, 'reportType' => $request->type]);
        return $pdf->download('applicant_report.pdf');
    }

    public function exportExcel(Request $request)
    {
        $query = $this->buildReportQuery($request);
        $applicants = $query->with(['user', 'program', 'processes'])->get();

        $data = $applicants->map(function ($app) {
            return [
                'Student Number' => $app->user->student_number ?? 'N/A',
                'Name' => trim(($app->user->firstname ?? '') . ' ' . ($app->user->lastname ?? '')),
                'Program' => $app->program->code ?? 'N/A',
                'Status' => $this->determineStatus($app),
                'Date' => $app->updated_at->format('Y-m-d')
            ];
        });

        return Excel::download(new ApplicantsExport($data), 'applicant_report.xlsx');
    }

    private function buildReportQuery(Request $request)
    {
        $query = Application::query();

        // Filter by type
        $type = $request->input('type');
        if ($type === 'interview') {
            $query->whereHas('processes', function ($q) {
                $q->where('stage', 'interview')->where('status', 'completed');
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

    private function determineStatus($application)
    {
        if ($application->enrollment_status === 'officially_enrolled') {
            return 'Enrolled';
        }

        $medical = $application->processes->where('stage', 'medical')->where('status', 'completed')->first();
        if ($medical) {
            return 'Medical Cleared';
        }

        $interview = $application->processes->where('stage', 'interview')->where('status', 'completed')->first();
        if ($interview) {
            return 'Interview Finished';
        }

        return ucfirst(str_replace('_', ' ', $application->status));
    }
}
