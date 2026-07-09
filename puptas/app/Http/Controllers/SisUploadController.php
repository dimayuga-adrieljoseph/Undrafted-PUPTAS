<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\TestPasser;
use App\Services\AuditLogService;
use App\Services\SisUploadExportService;
use Illuminate\Http\Request;

class SisUploadController extends Controller
{
    public function __construct(
        private SisUploadExportService $exportService,
        private AuditLogService $auditLogService,
    ) {}

    /**
     * Export Passers SIS Upload XLSX.
     *
     * Passers = applicants who completed the interview step
     * (ApplicationProcess stage=interviewer, status=completed, action in passed/accepted).
     *
     * Query param: school_year (optional) — e.g. "2026-2027"
     */
    public function exportPassers(Request $request)
    {
        $schoolYear = $request->input('school_year');

        $user = auth()->user();
        if ($user) {
            $this->auditLogService->logActivity(
                AuditLog::ACTION_DOWNLOAD,
                'Reports',
                'Exported SIS Upload Passers XLSX' . ($schoolYear ? " for school year {$schoolYear}" : ''),
                $user,
                AuditLog::CATEGORY_SYSTEM_OPERATION
            );
        }

        return $this->exportService->generatePassers($schoolYear ?: null);
    }

    /**
     * Export Recon (On Probation) SIS Upload XLSX.
     *
     * Recon = TestPassers with passer_status_id = 5 (On Probation / Waiver applicants).
     *
     * Query param: school_year (optional) — e.g. "2026-2027"
     */
    public function exportRecon(Request $request)
    {
        $schoolYear = $request->input('school_year');

        $user = auth()->user();
        if ($user) {
            $this->auditLogService->logActivity(
                AuditLog::ACTION_DOWNLOAD,
                'Reports',
                'Exported SIS Upload Recon (On Probation) XLSX' . ($schoolYear ? " for school year {$schoolYear}" : ''),
                $user,
                AuditLog::CATEGORY_SYSTEM_OPERATION
            );
        }

        return $this->exportService->generateRecon($schoolYear ?: null);
    }

    /**
     * Return a JSON list of available school years for the frontend dropdown.
     * Derived from the test_passers table.
     */
    public function schoolYears()
    {
        $years = TestPasser::select('school_year')
            ->whereNotNull('school_year')
            ->distinct()
            ->orderByDesc('school_year')
            ->pluck('school_year');

        return response()->json(['school_years' => $years]);
    }
}
