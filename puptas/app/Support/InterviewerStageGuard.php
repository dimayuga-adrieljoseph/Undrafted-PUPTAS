<?php

namespace App\Support;

use App\Enums\RoleId;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class InterviewerStageGuard
{
    /**
     * Enforce interview-stage requirement for role 4 (Interviewer).
     * Roles 2, 3, 5, 6, 7, 8 are unchanged.
     * Uses the same current-application lookup as getUserFiles.
     * Zero applications returns 404 (matching getUserFiles).
     */
    public static function check($userId): ?JsonResponse
    {
        $authUser = auth()->user();
        if (!$authUser || (int) $authUser->role_id !== RoleId::Interviewer->value) {
            return null;
        }

        $user = User::with([
            'currentApplication' => function ($query) {
                $query->select('applications.id', 'applications.user_id', 'applications.status', 'applications.created_at', 'applications.program_id', 'applications.second_choice_id', 'applications.third_choice_id', 'applications.enrollment_status', 'applications.enrollment_position', 'applications.submitted_at', 'applications.requires_guidance_office', 'applications.requires_admission_office', 'applications.is_waivered');
            },
        ])
        ->where(function ($q) use ($userId) {
            $q->where('idp_user_id', $userId);
            if (is_numeric($userId)) {
                $q->orWhere('id', $userId);
            }
        })
        ->first();

        if (!$user) {
            return response()->json(['message' => 'Applicant not found'], 404);
        }

        $application = $user->currentApplication;
        if (!$application) {
            return response()->json(['message' => 'Application not found'], 404);
        }

        $hasAccess = $application->processes()
            ->where('stage', 'interviewer')
            ->whereIn('status', ['in_progress', 'returned', 'completed'])
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'message' => 'Unauthorized access. Application is not at the interviewer stage.'
            ], 403);
        }

        return null;
    }
}
