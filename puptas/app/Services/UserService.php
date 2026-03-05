<?php

namespace App\Services;

use App\Models\User;
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
        return User::with('currentApplication.program')
            ->where('role_id', 1)
            ->whereHas('currentApplication')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'course' => $user->course,
                    'status' => $user->currentApplication->status ?? null,
                    'email' => $user->email,
                    'username' => $user->username,
                    'phone' => $user->phone,
                    'company' => $user->company,
                    'program' => $user->currentApplication->program ?? null,
                ];
            });
    }

    /**
     * Get applicants filtered by application process stage
     * Only returns applicants whose applications are currently at the specified stage
     *
     * @param string $stage The application stage (evaluator, interviewer, medical, records)
     * @return Collection
     */
    public function getApplicantsByStage(string $stage): Collection
    {
        return User::with('currentApplication.program')
            ->where('role_id', 1)
            ->whereHas('currentApplication', function ($query) use ($stage) {
                $query->whereHas('processes', function ($q) use ($stage) {
                    $q->where('stage', $stage)
                        ->whereIn('status', ['in_progress', 'returned']);
                });
            })
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'course' => $user->course,
                    'status' => $user->currentApplication->status ?? null,
                    'email' => $user->email,
                    'username' => $user->username,
                    'phone' => $user->phone,
                    'company' => $user->company,
                    'program' => $user->currentApplication->program ?? null,
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
        return User::with('currentApplication.program')
            ->where('role_id', 1)
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
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'course' => $user->course,
                    'status' => $user->currentApplication->status ?? null,
                    'email' => $user->email,
                    'username' => $user->username,
                    'phone' => $user->phone,
                    'company' => $user->company,
                    'program' => $user->currentApplication->program ?? null,
                    'enrollment_status' => $user->currentApplication->enrollment_status ?? null,
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
        return User::select('id', 'firstname', 'middlename', 'lastname', 'email', 'contactnumber', 'role_id', 'created_at')
            ->with([
                'role:id,name',
                'programs:id,name,code',
                'applicantProfile' => function ($query) {
                    $query->select('id', 'user_id', 'first_choice_program');
                },
                'applicantProfile.firstChoiceProgram:id,name,code',
                // Use deterministic relationships instead of plain application()
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
            ->get();
    }

    /**
     * Get user counts grouped by role
     *
     * @return array
     */
    public function getUserCountsByRole(): array
    {
        return User::select('role_id', DB::raw('count(*) as total'))
            ->groupBy('role_id')
            ->pluck('total', 'role_id')
            ->toArray();
    }

    /**
     * Get total user count
     *
     * @return int
     */
    public function getTotalUserCount(): int
    {
        return User::count();
    }

    /**
     * Create a new user
     *
     * @param array $data
     * @return User
     */
    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'middlename' => $data['middlename'] ?? null,
                'email' => $data['email'],
                'contactnumber' => $data['contactnumber'],
                'password' => Hash::make($data['password']),
                'role_id' => $data['role_id'],
            ]);

            // Create ApplicantProfile for applicant users
            if ($data['role_id'] == 1) {
                $programId = null;
                // Look up program by code to get the ID if applicant_program is provided
                if (!empty($data['applicant_program'])) {
                    $program = Program::where('code', $data['applicant_program'])->first();
                    if ($program) {
                        $programId = $program->id;
                    }
                }

                $user->applicantProfile()->create([
                    'first_choice_program' => $programId,
                ]);
            }

            // Attach program if role is Applicant and program is provided
            if ($data['role_id'] == 1 && !empty($data['applicant_program'])) {
                // Look up program by code to get the ID
                $program = Program::where('code', $data['applicant_program'])->first();
                if ($program) {
                    $user->programs()->attach($program->id, ['role_id' => $data['role_id']]);
                }
            }

            // Attach program for Evaluators (3) and Interviewers (4)
            if (in_array($data['role_id'], [3, 4]) && !empty($data['program'])) {
                // Look up program by code to get the ID
                $program = Program::where('code', $data['program'])->first();
                if ($program) {
                    $user->programs()->attach($program->id, ['role_id' => $data['role_id']]);
                }
            }

            return $user;
        });
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
        ];
    }
}
