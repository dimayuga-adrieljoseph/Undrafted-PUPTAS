<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Grade;
use App\Models\Application;
use App\Services\GradeVerificationSlipService;

/**
 * Grade Verification Slip Controller
 *
 * Handles the self-service download of the Grade Verification Slip
 * directly from the Applicant Dashboard.
 *
 * Security rules:
 * - Only the authenticated applicant may download their own slip.
 * - The authenticated user's ID is used as the sole data source.
 * - No URL parameter is accepted that could reference another applicant.
 *
 * Preconditions:
 * - Applicant has submitted their application (status !== 'draft').
 * - Grades have been entered and qualification results are available.
 */
class GradeVerificationSlipController extends Controller
{
    public function __construct(
        private GradeVerificationSlipService $slipService
    ) {}

    /**
     * Generate and stream the Grade Verification Slip PDF for the authenticated applicant.
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
                'user_id'     => $user->id,
                'error_type'  => get_class($e),
                'message'     => $e->getMessage(),
            ]);

            abort(500, 'Unable to generate Grade Verification Slip. Please try again later.');
        }

        // Build safe filename: Grade_Verification_Slip_<ReferenceNumber>.pdf
        $testPasser = $user->testPasser;
        $referenceNumber = $testPasser?->reference_number ?? ('USR' . $user->id);
        $safeRef  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $referenceNumber);
        $filename = "Grade_Verification_Slip_{$safeRef}.pdf";

        return response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => strlen($pdfContent),
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'Pragma'              => 'no-cache',
        ]);
    }
}
