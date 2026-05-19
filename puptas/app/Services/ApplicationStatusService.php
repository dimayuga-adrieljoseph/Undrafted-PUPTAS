<?php

namespace App\Services;

class ApplicationStatusService
{
    /**
     * Determine the user-friendly status string for a given application.
     */
    public function determineStatus($application): string
    {
        if ($application->enrollment_status === 'officially_enrolled') {
            return 'Enrolled';
        }

        $medical = $application->processes->where('stage', 'medical')->where('status', 'completed')->first();
        if ($medical) {
            return 'Medical Cleared';
        }

        $interview = $application->processes->where('stage', 'interviewer')->where('status', 'completed')->sortByDesc('created_at')->first();
        if ($interview) {
            if ($interview->action === 'rejected') {
                // Rejected by interviewer — applicant is still in interview stage
                return 'For Interview';
            }
            if ($interview->action === 'transferred') {
                return 'Interview Finished (Transferred)';
            } elseif ($interview->action === 'passed') {
                return 'Interview Finished (Passed)';
            }
            return 'Interview Finished';
        }

        return ucfirst(str_replace('_', ' ', $application->status));
    }
}
