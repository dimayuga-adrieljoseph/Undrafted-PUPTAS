<?php

namespace App\Services;

use App\Models\User;
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
        return User::with('application.program')
            ->where('role_id', 1)
            ->whereHas('application')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'course' => $user->course,
                    'status' => $user->application->status ?? null,
                    'email' => $user->email,
                    'username' => $user->username,
                    'phone' => $user->phone,
                    'company' => $user->company,
                    'program' => $user->application->program ?? null,
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
                'applicantProfile' => function($query) {
                    $query->select('user_id', 'first_choice_program');
                },
                'applicantProfile.firstChoiceProgram:id,name,code'
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

            // Attach program if role is Applicant and program is provided
            if ($data['role_id'] == 1 && !empty($data['program'])) {
                $user->programs()->attach($data['program'], ['role_id' => $data['role_id']]);
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
        $user = User::findOrFail($userId);
        return $user->delete();
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
        try {
            AuditLog::create([
                'user_id' => $actorId,
                'model_type' => 'User',
                'model_id' => null,
                'action' => 'viewed_user_listing',
                'old_values' => null,
                'new_values' => [
                    'total_users_viewed' => $totalUsersViewed,
                    'includes_applicant_profiles' => true,
                    'timestamp' => now()->toIso8601String(),
                ],
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            logger()->error('Failed to create audit log for user listing view', [
                'actor_id' => $actorId,
                'error' => $e->getMessage()
            ]);
        }
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
