<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\TestPasser;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TestPassersExport;

class TestPasserReportController extends Controller
{
    public function index()
    {
        // Get unique batches, strands, and school years for filters
        $batches = TestPasser::select('batch_number')->distinct()->whereNotNull('batch_number')->pluck('batch_number');
        $schoolYears = TestPasser::select('school_year')->distinct()->whereNotNull('school_year')->pluck('school_year');
        $strands = TestPasser::select('strand')->distinct()->whereNotNull('strand')->pluck('strand');

        return Inertia::render('Reports/TestPassers', [
            'batches' => $batches,
            'schoolYears' => $schoolYears,
            'strands' => $strands,
        ]);
    }

    private function buildReportQuery(Request $request)
    {
        $query = TestPasser::query()->with('passerStatus');

        if ($request->filled('status')) {
            // "qualified" is id 1, "waitlisted" is id 2 based on the migration
            $statusId = $request->status === 'waitlisted' ? 2 : 1;
            $query->where('passer_status_id', $statusId);
        }

        if ($request->filled('batch')) {
            $query->where('batch_number', $request->batch);
        }

        if ($request->filled('school_year')) {
            $query->where('school_year', $request->school_year);
        }

        if ($request->filled('strand')) {
            $query->where('strand', $request->strand);
        }

        // Rank by score
        $query->orderBy('pupcet_total_score', 'desc');
        $query->orderBy('surname', 'asc');
        
        return $query;
    }

    public function getReportData(Request $request)
    {
        $query = $this->buildReportQuery($request);
        $paginator = $query->paginate(15);
        
        // Calculate rank based on pagination
        $currentPage = $paginator->currentPage();
        $perPage = $paginator->perPage();
        
        $paginator->getCollection()->transform(function ($passer, $key) use ($currentPage, $perPage) {
            $passer->rank = ($currentPage - 1) * $perPage + $key + 1;
            $passer->full_name = trim($passer->first_name . ' ' . $passer->middle_name . ' ' . $passer->surname);
            $passer->passer_status_name = $passer->passerStatus ? $passer->passerStatus->status : 'Unknown';
            return $passer;
        });

        return response()->json($paginator);
    }

    public function exportPdf(Request $request)
    {
        $query = $this->buildReportQuery($request);
        // Hard limit of 1000 for PDF to prevent memory exhaustion
        $passers = $query->limit(1000)->get();
        
        // Add rank
        $passers->each(function ($passer, $key) {
            $passer->rank = $key + 1;
            $passer->full_name = trim($passer->first_name . ' ' . $passer->middle_name . ' ' . $passer->surname);
        });

        $pdf = Pdf::loadView('reports.test_passers', [
            'passers' => $passers,
            'reportType' => $request->input('status') === 'waitlisted' ? 'Waitlisted Applicants' : 'Qualified Applicants',
            'date' => now()->format('F d, Y')
        ])->setPaper('a4', 'landscape');

        return $pdf->download('test_passers_report.pdf');
    }

    public function exportExcel(Request $request)
    {
        $query = $this->buildReportQuery($request);
        
        return Excel::download(new TestPassersExport($query), 'test_passers_report.xlsx');
    }
}
