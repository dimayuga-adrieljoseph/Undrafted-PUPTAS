<?php

namespace App\Http\Controllers;

use App\Services\KpiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * KPI Controller
 *
 * Handles the KPI JSON API endpoint and the KPI PDF export.
 * All routes are protected by the EnsureAdmin middleware (role_id 2 or 7).
 */
class KpiController extends Controller
{
    public function __construct(protected KpiService $kpiService) {}

    /**
     * GET /dashboard/kpi
     *
     * Returns a JSON response containing all KPI data, a summary, and a generated_at timestamp.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $result = $this->kpiService->compute();

            $kpis = $result['kpis'];

            $kpisMet    = count(array_filter($kpis, fn (array $kpi) => $kpi['met'] === true));
            $kpisFailed = count($kpis) - $kpisMet;

            return response()->json([
                'generated_at' => now()->toIso8601String(),
                'summary'      => [
                    'total_kpis'  => count($kpis),
                    'kpis_met'    => $kpisMet,
                    'kpis_failed' => $kpisFailed,
                ],
                'kpis' => $kpis,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('KpiController: computation failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Failed to compute KPI data.'], 500);
        }
    }

    /**
     * GET /dashboard/kpi/export/pdf
     *
     * Computes KPI data, renders the `kpi.report` Blade view via dompdf, and returns
     * a downloadable PDF response with an appropriate Content-Disposition filename.
     *
     * @return Response|JsonResponse
     */
    public function exportPdf(): Response|JsonResponse
    {
        try {
            $result = $this->kpiService->compute();

            $kpis       = $result['kpis'];
            $perProgram = $result['per_program'];

            $kpisMet    = count(array_filter($kpis, fn (array $kpi) => $kpi['met'] === true));
            $kpisFailed = count($kpis) - $kpisMet;

            $summary = [
                'total_kpis'  => count($kpis),
                'kpis_met'    => $kpisMet,
                'kpis_failed' => $kpisFailed,
            ];

            // Process Efficiency KPIs — average service time per admissions step (all-time)
            $serviceTimeKpi = app(\App\Http\Controllers\AdmissionLogbookController::class)
                ->serviceTimeKpi(request())
                ->getData(true); // decode JSON to array

            $generatedAt = now();

            $pdf = Pdf::loadView('kpi.report', [
                'kpis'           => $kpis,
                'perProgram'     => $perProgram,
                'summary'        => $summary,
                'serviceTimeKpi' => $serviceTimeKpi['steps'] ?? [],
                'generatedAt'    => $generatedAt,
            ]);

            $filename = 'puptas_kpi_report_' . now()->format('Y-m-d') . '.pdf';

            return response($pdf->output(), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            Log::error('KpiController: PDF export failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Failed to generate KPI report.'], 500);
        }
    }
}
