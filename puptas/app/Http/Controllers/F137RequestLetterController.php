<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\AuditLog;
use App\Models\F137Generation;
use App\Services\AuditLogService;
use App\Services\F137RequestLetterService;

/**
 * F137 Request Letter Controller
 *
 * Handles self-service download of the F137 Request Letter
 * directly from the Applicant Dashboard.
 *
 * On every download:
 *   - PDF is generated fresh using the current Philippine date
 *   - PDF is saved to the 'f137_tmp' storage disk
 *   - A record is created (first download) or updated (subsequent downloads)
 *     in f137_generations: download_count++, last_downloaded_at = now()
 *   - An AUDIT log entry is written (action_type = DOWNLOAD)
 *
 * Security rules:
 *   - Only role_id = 1 (applicants) can access this endpoint
 *   - The authenticated user's session is the sole data source
 *   - No URL parameter is accepted that could reference another applicant
 *   - Requires `school` (existing field) and former_school_address to be filled in profile
 */
class F137RequestLetterController extends Controller
{
    public function __construct(
        private F137RequestLetterService $letterService,
        private AuditLogService $auditLogService
    ) {}

    /**
     * Generate, store, and stream the F137 Request Letter PDF.
     */
    public function download(Request $request)
    {
        $user = Auth::user();

        // Role guard — only applicants (role_id = 1)
        if ($user->role_id !== 1) {
            abort(403, 'Only applicants can download the F137 Request Letter.');
        }

        // Load profile
        $user->load('applicantProfile');
        $profile = $user->applicantProfile;

        // Guard: required former school fields must be present
        $formerSchoolName    = trim($profile?->school             ?? '');
        $formerSchoolAddress = trim($profile?->former_school_address ?? '');

        if ($formerSchoolName === '' || $formerSchoolAddress === '') {
            abort(403, 'Please complete your Former School Information in your Profile before generating the F137 Request Letter.');
        }

        try {
            $pdfContent = $this->letterService->generate($user);
        } catch (\Exception $e) {
            \Log::error('F137 Request Letter generation failed', [
                'user_id'    => $user->id,
                'error_type' => get_class($e),
                'message'    => $e->getMessage(),
            ]);
            abort(500, 'Unable to generate F137 Request Letter. Please try again later.');
        }

        // Build safe filename using reference number if available
        $testPasser      = $user->testPasser;
        $referenceNumber = $testPasser?->reference_number ?? ('USR' . $user->id);
        $safeRef         = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $referenceNumber);
        $filename        = "F137_{$safeRef}.pdf";
        $storagePath     = "f137/{$filename}";

        // Save PDF to f137_tmp disk (overwrites previous copy for this applicant)
        Storage::disk('f137_tmp')->put($storagePath, $pdfContent);

        // Atomic upsert — one row per applicant.
        // increment() is atomic at the DB level.
        $record = F137Generation::firstOrCreate(
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

        // Write audit log
        $this->auditLogService->logActivity(
            AuditLog::ACTION_DOWNLOAD,
            'F137 Request Letter',
            "Applicant (user_id: {$user->id}) downloaded their F137 Request Letter (download #{$downloadCount}).",
            $user,
            AuditLog::CATEGORY_ADMISSION_DATA
        );

        return response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="F137_Request_Letter_' . $safeRef . '.pdf"',
            'Content-Length'      => strlen($pdfContent),
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'Pragma'              => 'no-cache',
        ]);
    }

    /**
     * Update the applicant's former school information.
     *
     * Validates and saves former_school_address,
     * and (optional) former_school_principal to the applicant's profile.
     * School name is sourced from the existing `school` field.
     */
    public function updateFormerSchool(Request $request)
    {
        $user = Auth::user();

        if ($user->role_id !== 1) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'school'                  => ['required', 'string', 'max:255'],
            'former_school_address'   => ['required', 'string', 'max:255'],
            'former_school_principal' => ['nullable', 'string', 'max:255'],
        ]);

        $user->load('applicantProfile');
        $profile = $user->applicantProfile;

        if (!$profile) {
            abort(404, 'Applicant profile not found.');
        }

        $profile->update([
            'school'                  => $validated['school'],
            'former_school_address'   => $validated['former_school_address'],
            'former_school_principal' => $validated['former_school_principal'] ?? null,
        ]);

        return back()->with('success', 'Former school information saved successfully.');
    }
}
