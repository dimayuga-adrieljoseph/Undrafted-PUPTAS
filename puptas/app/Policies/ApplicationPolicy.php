<?php

namespace App\Policies;

use App\Models\Application;
use Illuminate\Contracts\Auth\Authenticatable;

class ApplicationPolicy
{
    /**
     * Determine if the user can change the course for the given application.
     *
     * Authorization rules:
     * - Admins (role_id=2) and Superadmins (role_id=7): unrestricted access
     * - Interviewers (role_id=4): authorized only when enrollment_status != 'officially_enrolled' AND status != 'accepted'
     * - All other roles: denied
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  \App\Models\Application  $application
     * @return bool
     */
    public function changeCourse(Authenticatable $user, Application $application): bool
    {
        // Admins and Superadmins: unrestricted access
        if (in_array($user->role_id, [2, 7], true)) {
            return true;
        }
        
        // Interviewers: restricted by enrollment_status and status
        if ($user->role_id === 4) {
            // Allow transfer if NOT officially enrolled AND NOT accepted
            $enrollmentStatus = $application->enrollment_status ?? '';
            $applicationStatus = $application->status ?? '';
            
            return $enrollmentStatus !== 'officially_enrolled'
                && $applicationStatus !== 'accepted';
        }
        
        // All other roles: denied
        return false;
    }
}
