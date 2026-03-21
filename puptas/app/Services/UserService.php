<?php

namespace App\Services;

use App\Models\User;
use App\Models\ApplicantProfile;
use App\Models\Program;
use App\Models\AuditLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * User Service
 * 
 * Handles business logic for user management.
 * Centralizes user-related queries and operations.
 */
class UserService
{
    /**
     * Get all applicants with their applications and programs
     *
     * @return Collection
     */
    public function getApplicantsWithApplications(): Collection
    {
        return ApplicantProfile::with('currentApplication.program')
            ->whereHas('currentApplication')
            ->get()
            ->map(function ($profile) {
                return [
                    'id' => $profile->user_id,
                    'firstname' => $profile->firstname,
                    'lastname' => $profile->lastname,
                    'course' => $profile->course ?? null,
                    'status' => $profile->currentApplication->status ?? null,
                    'email' => $profile->email,
                    'username' => $profile->email,
                    'phone' => $profile->contactnumber,
                    'company' => $profile->company ?? null,
                    'program' => $profile->currentApplication->program ?? null,
                ];
            });
        return ApplicantProfile::with(['currentApplication' => function ($query) {
            $query->select('applications.id', 'applications.user_id', 'applications.status', 'applications.created_at', 'applications.program_id');
        }, 'currentApplication.program' => function ($query) {
            $query->select('id', 'code', 'name');
        }, 'currentApplication.processes' => function ($query) use ($stage) {
            $query->where('stage', $stage)
                ->orderBy('created_at', 'desc')
                ->select('id', 'application_id', 'stage', 'status', 'action', 'created_at');
        }])
            ->whereHas('applications', function ($query) use ($stage) {
                $query->whereNotIn('status', ['accepted'])
                    ->whereHas('processes', function ($q) use ($stage) {
                        $q->where('stage', $stage)
                            ->whereIn('status', ['in_progress', 'returned']);
                    })
                    ->whereDoesntHave('processes', function ($q) use ($stage) {
                        $q->where('stage', $stage)
                            ->where('status', 'completed')
                            ->whereIn('action', ['passed', 'transferred']);
                    })
                    ->whereRaw('applications.id = (SELECT MAX(a.id) FROM applications a WHERE a.user_id = applications.user_id AND a.deleted_at IS NULL)');
            })
            ->get()
            ->map(function ($profile) use ($stage) {
                $application = $profile->currentApplication;
                $stageProcess = $application && $application->processes ?
                    $application->processes->first() : null;

                return [
                    'id' => $profile->user_id,
                    'firstname' => $profile->firstname,
                    'lastname' => $profile->lastname,
                    'course' => $profile->course ?? null,
                    'status' => $application->status ?? null,
                    'email' => $profile->email,
                    'username' => $profile->email,
                    'phone' => $profile->contactnumber,
                    'company' => $profile->company ?? null,
                    'program' => $application && $application->program ? [
                        'id' => $application->program->id,
                        'code' => $application->program->code,
                        'name' => $application->program->name,
                    ] : null,
                    'application' => $application ? [
                        'id' => $application->id,
                        'status' => $application->status,
                        'created_at' => $application->created_at,
                        'program' => $application->program ? [
                            'id' => $application->program->id,
                            'code' => $application->program->code,
                            'name' => $application->program->name,
                        ] : null,
                    ] : null,
                    'process_status' => $stageProcess ? $stageProcess->status : 'in_progress',
                    'process_action' => $stageProcess ? $stageProcess->action : null,
                    'is_evaluation_completed' => $stageProcess && $stageProcess->status === 'completed',
                ];
            });
    }

    /**
     * Get all applicants by stage including completed
     * Returns all applicants who have reached the specified stage (in_progress, returned, or completed)
     *
     * @param string $stage The application stage (evaluator, interviewer, medical, records)
     * @return Collection
     */
    public function getAllApplicantsByStage(string $stage): Collection
    {
        return ApplicantProfile::with(['currentApplication' => function ($query) {
            $query->select('applications.id', 'applications.user_id', 'applications.status', 'applications.created_at', 'applications.program_id');
        }, 'currentApplication.program' => function ($query) {
            $query->select('id', 'code', 'name');
        }, 'currentApplication.processes' => function ($query) use ($stage) {
            $query->where('stage', $stage)
                ->orderBy('created_at', 'desc')
                ->select('id', 'application_id', 'stage', 'status', 'action', 'created_at');
        }])
            ->whereHas('currentApplication', function ($query) use ($stage) {
                $query->whereHas('processes', function ($q) use ($stage) {
                    $q->where('stage', $stage)
                        ->whereIn('status', ['in_progress', 'returned', 'completed']);
                });
            })
            ->get()
            ->map(function ($profile) use ($stage) {
                $application = $profile->currentApplication;
                $stageProcess = $application && $application->processes ?
                    $application->processes->first() : null;

                return [
                    'id' => $profile->user_id,
                    'firstname' => $profile->firstname,
                    'lastname' => $profile->lastname,
                    'course' => $profile->course ?? null,
                    'status' => $application->status ?? null,
                    'email' => $profile->email,
                    'username' => $profile->email,
                    'phone' => $profile->contactnumber,
                    'company' => $profile->company ?? null,
                    'program' => $application && $application->program ? [
                        'id' => $application->program->id,
                        'code' => $application->program->code,
                        'name' => $application->program->name,
                    ] : null,
                    'application' => $application ? [
                        'id' => $application->id,
                        'status' => $application->status,
                        'created_at' => $application->created_at,
                        'program' => $application->program ? [
                            'id' => $application->program->id,
                            'code' => $application->program->code,
                            'name' => $application->program->name,
                        ] : null,
                    ] : null,
                    'process_status' => $stageProcess ? $stageProcess->status : 'in_progress',
                    'process_action' => $stageProcess ? $stageProcess->action : null,
                    'is_evaluation_completed' => $stageProcess && $stageProcess->status === 'completed',
                ];
            });
    }

    /**
     * Get applicants for record staff
     * Returns applicants who have completed medical stage OR are officially enrolled
     *
     * @return Collection
     */
    public function getApplicantsForRecordStaff(): Collection
    {
        return ApplicantProfile::with('currentApplication.program')
            ->whereHas('currentApplication', function ($query) {
                $query->where(function ($q) {
                    // Get applications that have completed medical stage
                    $q->whereHas('processes', function ($process) {
                        $process->where('stage', 'medical')
                            ->where('status', 'completed');
                    })
                        // OR applications that are officially enrolled
                        ->orWhere('enrollment_status', 'officially_enrolled');
                });
            })
            ->get()
            ->map(function ($profile) {
                $app = $profile->currentApplication;
                $program = $app?->program;

                return [
                    'id'               => $profile->user_id,
                    'firstname'        => $profile->firstname,
                    'lastname'         => $profile->lastname,
                    'course'           => $profile->course ?? null,
                    'email'            => $profile->email,
                    'username'         => $profile->email,
                    'phone'            => $profile->contactnumber,
                    'company'          => $profile->company ?? null,
                    'status'           => $app?->status ?? null,
                    'enrollment_status' => $app?->enrollment_status ?? null,
                    // Top-level program property for Applications/Records.vue
                    'program'          => $program,
                    // Nested application object for Dashboard/Records.vue
                    'application'      => $app ? [
                        'id'               => $app->id,
                        'status'           => $app->status,
                        'enrollment_status' => $app->enrollment_status,
                        'program_id'       => $app->program_id,
                        'created_at'       => $app->created_at,
                        'program'          => $program ? [
                            'id'   => $program->id,
                            'code' => $program->code,
                            'name' => $program->name,
                        ] : null,
                    ] : null,
                ];
            });
    }

    /**
     * Get all users with detailed information
     *
     * @return Collection
     */
    public function getAllUsersWithDetails(): Collection
    {
        // Get all staff profiles
        $staff = \App\Models\StaffProfile::with(['programs:id,name,code'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($staff) {
                // Approximate first/last name from full name
                $parts = explode(' ', $staff->name);
                $firstname = array_shift($parts);
                $lastname = count($parts) > 0 ? implode(' ', $parts) : '';

                return (object) [
                    'id' => $staff->user_id,
                    'firstname' => $firstname,
                    'middlename' => null,
                    'lastname' => $lastname,
                    'extension_name' => null,
                    'email' => $staff->email,
                    'contactnumber' => null,
                    'role_id' => $staff->role_id,
                    'created_at' => $staff->created_at,
                    'role' => (object) ['name' => $staff->role_name],
                    'programs' => $staff->programs,
                    'applicantProfile' => null,
                    'currentApplication' => null,
                    'officiallyEnrolledApplication' => null,
                ];
            });

        // Get all applicant profiles
        $applicants = ApplicantProfile::with([
            'firstChoiceProgram:id,name,code',
            'currentApplication' => function ($query) {
                $query->select('applications.id', 'applications.user_id', 'applications.program_id', 'applications.enrollment_status');
            },
            'currentApplication.program:id,name,code',
            'officiallyEnrolledApplication' => function ($query) {
                $query->select('applications.id', 'applications.user_id', 'applications.program_id', 'applications.enrollment_status');
            },
            'officiallyEnrolledApplication.program:id,name,code'
        ])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($applicant) {
                return (object) [
                    'id' => $applicant->user_id,
                    'firstname' => $applicant->firstname,
                    'middlename' => $applicant->middlename,
                    'lastname' => $applicant->lastname,
                    'extension_name' => $applicant->extension_name,
                    'email' => $applicant->email,
                    'contactnumber' => $applicant->contactnumber,
                    'role_id' => 1,
                    'created_at' => $applicant->created_at,
                    'role' => (object) ['name' => 'Applicant'],
                    'programs' => collect(), // Applicants don't manage programs this way
                    'applicantProfile' => $applicant,
                    'currentApplication' => $applicant->currentApplication,
                    'officiallyEnrolledApplication' => $applicant->officiallyEnrolledApplication,
                ];
            });

        // Merge them and return as a collection
        return $staff->concat($applicants)->sortByDesc('created_at')->values();
    }

    /**
     * Get user counts grouped by role
     *
     * @return array
     */
    public function getUserCountsByRole(): array
    {
        $staffCounts = \App\Models\StaffProfile::select('role_id', DB::raw('count(*) as total'))
            ->groupBy('role_id')
            ->pluck('total', 'role_id')
            ->toArray();

        $applicantCount = ApplicantProfile::count();

        $staffCounts[1] = $applicantCount; // Role 1 is Applicant

        return $staffCounts;
    }

    /**
     * Get total user count
     *
     * @return int
     */
    public function getTotalUserCount(): int
    {
        return \App\Models\StaffProfile::count() + ApplicantProfile::count();
    }

    /**
     * Create a new user
     *
     * @param array $data
     * @return User
     */
    public function createUser(array $data)
    {
        throw new \Exception('User creation is now managed fully via the external IDP. Local creation is disabled.');
    }

    /**
     * Update user information
     *
     * @param int $userId
     * @param array $data
     * @return User
     */
    public function updateUser(int $userId, array $data): User
    {
        return DB::transaction(function () use ($userId, $data) {
            $user = User::findOrFail($userId);

            // Capture old values for audit trail
            $oldValues = [
                'email' => $user->email,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'middlename' => $user->middlename,
                'contactnumber' => $user->contactnumber,
                'role_id' => $user->role_id,
            ];

            $updateData = [
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'middlename' => $data['middlename'] ?? null,
                'email' => $data['email'],
                'contactnumber' => $data['contactnumber'],
            ];

            if (!empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            $user->update($updateData);

            // Capture new values for audit trail
            $newValues = [
                'email' => $user->email,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'middlename' => $user->middlename,
                'contactnumber' => $user->contactnumber,
                'role_id' => $user->role_id,
                'password_changed' => !empty($data['password']),
                'updated_by' => auth()->user()->email ?? 'system',
            ];

            // Audit log for user update — handled in UserController via AuditLogService

            return $user->fresh();
        });
    }

    /**
     * Delete a user
     *
     * @param int $userId
     * @return bool
     */
    public function deleteUser(int $userId): bool
    {
        return DB::transaction(function () use ($userId) {
            $user = User::findOrFail($userId);

            // Capture user data before deletion for audit trail
            $deletedUserData = [
                'email' => $user->email,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'middlename' => $user->middlename,
                'role_id' => $user->role_id,
                'contactnumber' => $user->contactnumber,
                'deleted_by' => auth()->user()->email ?? 'system',
            ];

            $deleted = $user->delete();

            // Audit log for deletion — handled in UserController via AuditLogService

            return $deleted;
        });
    }

    /**
     * Log user listing view to audit log
     *
     * @param int $actorId
     * @param int $totalUsersViewed
     * @return void
     */
    public function logUserListingView(int $actorId, int $totalUsersViewed): void
    {
        // Intentionally left as a no-op.
        // VIEW events are not part of the new audit trail schema.
        // CRUD events are logged directly via AuditLogService in the controller.
    }

    /**
     * Get role definitions
     *
     * @return array
     */
    public function getRoleDefinitions(): array
    {
        return [
            1 => 'Applicant',
            2 => 'Admin',
            3 => 'Evaluator',
            4 => 'Interviewer',
            5 => 'Medical',
            6 => 'Registrar',
            7 => 'Superadmin',
        ];
    }
}
