<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    /**
     * Determine if the user can change the course for the given application.
     *
     * Authorization rules:
     * - Admins (role_id=2) and Superadmins (role_id=7): unrestricted access
     * - Interviewers (role_id=4): authorized only when enrollment_status != 'officially_enrolled'
     * - All other roles: denied
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Application  $application
     * @return bool
     */
    public function changeCourse(User $user, Application $application): bool
    {
        // Admins and Superadmins: unrestricted access
        if (in_array($user->role_id, [2, 7], true)) {
            return true;
        }
        
        // Interviewers: restricted by enrollment_status
        if ($user->role_id === 4) {
            return $application->enrollment_status !== 'officially_enrolled';
        }
        
        // All other roles: denied
        return false;
    }
}
