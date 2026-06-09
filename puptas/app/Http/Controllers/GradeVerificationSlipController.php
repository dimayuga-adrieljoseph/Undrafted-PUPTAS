<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\AuditLog;
use App\Models\Grade;
use App\Models\GvsGeneration;
use App\Services\AuditLogService;
use App\Services\GradeVerificationSlipService;

/**
 * Grade Verification Slip Controller
 *
 * Handles the self-service download of the Grade Verification Slip
 * directly from the Applicant Dashboard.
 *
 * On every download:
 *   - PDF is generated fresh from the current database state
 *   - PDF is saved to the 'gvs_tmp' storage disk
 *   - A record is created (first download) or updated (subsequent downloads)
 *     in gvs_generations: download_count++, last_downloaded_at = now()
 *   - An AUDIT log entry is written (action_type = DOWNLOAD)
 *
 * Security rules:
 *   - Only role_id = 1 (applicants) can access this endpoint
 *   - The authenticated user's session is the sole data source
 *   - No URL parameter is accepted that could reference another applicant
 */
class GradeVerificationSlipController extends Controller
{
    public function __construct(
        private GradeVerificationSlipService $slipService,
        private AuditLogService $auditLogService
    ) {}

    /**
     * Generate, store, and stream the Grade Verification Slip PDF.
     */
    public function download(Request $request)
    {
        $user = Auth::user();

        // Role guard — only applicants (role_id = 1)
        if ($user->role_id !== 1) {
            abort(403, 'Only applicants can download the Grade Verification Slip.');
        }

        // Require a submitted (non-draft) application
        $application = $user->currentApplication;
        if (!$application || $application->status === 'draft') {
            abort(403, 'The Grade Verification Slip is only available after submitting your application.');
        }

        // Require grades to be present
        $grades = Grade::where('user_id', (string) $user->id)->first();
        if (!$grades) {
            abort(403, 'No grade data found. Please complete your grade input first.');
        }

        try {
            $pdfContent = $this->slipService->generate($user);
        } catch (\Exception $e) {
            \Log::error('Grade Verification Slip generation failed', [
                'user_id'    => $user->id,
                'error_type' => get_class($e),
                'message'    => $e->getMessage(),
            ]);
            abort(500, 'Unable to generate Grade Verification Slip. Please try again later.');
        }

        // Build safe filename
        $testPasser      = $user->testPasser;
        $referenceNumber = $testPasser?->reference_number ?? ('USR' . $user->id);
        $safeRef         = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $referenceNumber);
        $filename        = "GVS_{$safeRef}.pdf";
        $storagePath     = "gvs/{$filename}";

        // Save PDF to gvs_tmp disk (overwrites previous copy for this applicant)
        Storage::disk('gvs_tmp')->put($storagePath, $pdfContent);

        // Atomic upsert — unique constraint on user_id prevents duplicate rows even
        // under concurrent requests. increment() is atomic at the DB level.
        $record = GvsGeneration::firstOrCreate(
            ['user_id' => $user->id],
            [
                'reference_number'   => $referenceNumber,
                'filename'           => $filename,
                'file_path'          => $storagePath,
                'download_count'     => 0,
                'last_downloaded_at' => now(),
            ]
        );

        $record->increment('download_count');
        $record->update([
            'filename'           => $filename,
            'file_path'          => $storagePath,
            'reference_number'   => $referenceNumber,
            'last_downloaded_at' => now(),
        ]);

        $downloadCount = $record->fresh()->download_count;

        // Write audit log — log user_id and action only, no PII in the message
        $this->auditLogService->logActivity(
            AuditLog::ACTION_DOWNLOAD,
            'Grade Verification Slip',
            "Applicant (user_id: {$user->id}) downloaded their Grade Verification Slip (download #{$downloadCount}).",
            $user,
            AuditLog::CATEGORY_ADMISSION_DATA
        );

        return response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Grade_Verification_Slip_' . $safeRef . '.pdf"',
            'Content-Length'      => strlen($pdfContent),
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'Pragma'              => 'no-cache',
        ]);
    }
}
