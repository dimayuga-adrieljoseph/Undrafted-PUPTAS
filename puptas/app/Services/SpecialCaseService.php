<?php

namespace App\Services;

use App\Models\ApplicantProfile;
use App\Models\TestPasser;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SpecialCaseService
{
    /**
     * Handle the first-time IDP login intercept to create or retrieve the profile.
     * Evaluates whether the user is a PASSER or requires SPECIAL_REVIEW.
     *
     * @param array $idpUser The IDP user data array
     * @param string|null $firstName The determined first name
     * @param string|null $lastName The determined last name
     * @return ApplicantProfile
     */
    public function handleIdpIntercept(array $idpUser, ?string $firstName, ?string $lastName): ApplicantProfile
    {
        $profile = ApplicantProfile::where('user_id', $idpUser['id'])->first();

        if (!$profile) {
            // Check if they are a regular test passer based on email
            $isPasser = TestPasser::where('email', $idpUser['email'])->exists();

            $admissionDecision = $isPasser ? 'PASSED' : 'FAILED';
            $applicantStatus = $isPasser ? 'REGULAR' : 'FOR_SPECIAL_REVIEW';

            // 1. On first login (from IDP): Create applicant record with FAILED and FOR_SPECIAL_REVIEW logic
            $profile = ApplicantProfile::create([
                'user_id' => $idpUser['id'],
                'email' => $idpUser['email'] ?? null,
                'firstname' => $firstName,
                'lastname' => $lastName,
                'admission_decision' => $admissionDecision,
                'applicant_status' => $applicantStatus,
                'source' => 'IDP',
            ]);

            Log::info('Created initial profile stub for IDP applicant', [
                'id' => $idpUser['id'],
                'decision' => $admissionDecision
            ]);
        }

        return $profile;
    }

    /**
     * Determines if an applicant profile is eligible to proceed to registration.
     *
     * @param ApplicantProfile $profile
     * @return bool
     */
    public function canRegister(ApplicantProfile $profile): bool
    {
        // 5. Allow registration if PASSED or SPECIAL_CASE_APPROVED
        return in_array($profile->admission_decision, ['PASSED', 'SPECIAL_CASE_APPROVED']);
    }

    /**
     * Admin action to manually approve a special case applicant.
     *
     * @param int $applicantProfileId
     * @param int $adminUserId The User ID of the admin approving the applicant
     * @param string|null $reason Optional audit reason
     * @return ApplicantProfile
     */
    public function approveSpecialCase(int $applicantProfileId, int $adminUserId, ?string $reason = null): ApplicantProfile
    {
        $profile = ApplicantProfile::findOrFail($applicantProfileId);

        if ($profile->admission_decision === 'PASSED') {
            throw new \Exception('Applicant is already a regular passer.');
        }

        // 3. If approved as special case: update tracking flags
        $profile->update([
            'admission_decision' => 'SPECIAL_CASE_APPROVED',
            'applicant_status' => 'APPROVED_FOR_REGISTRATION',
            'is_special_case' => true,
            'special_case_reason' => $reason,
            'special_case_approved_by' => $adminUserId,
            'special_case_approved_at' => now(),
        ]);

        return $profile;
    }

    /**
     * Process workflow overrides for special case applicants during application review.
     * Call this when moving an application forward from the Evaluator stage.
     *
     * @param Application $application
     * @return void
     */
    public function applyWorkflowOverrides($application): void
    {
        $profile = ApplicantProfile::where('user_id', $application->user_id)->first();

        if ($profile && $profile->is_special_case) {
            // 4. Rule overrides: Remove interview requirement & force DOMT
            // Set the program directly to DOMT if not already
            $domtProgram = \App\Models\Program::where('code', 'DOMT')->first();
            
            if ($domtProgram && $application->program_id !== $domtProgram->id) {
                // Force into DOMT
                $application->program_id = $domtProgram->id;
                $application->save();
            }

            // Note: The caller (e.g. EvaluatorDashboardController) should 
            // set the NEXT application process stage exclusively to 'medical' instead of 'interview'
            Log::info('Applied workflow overrides for special case applicant', ['application_id' => $application->id]);
        }
    }
}
