<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Repositories\Contracts\ApplicantProfileRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\ApplicationRepositoryInterface;

/**
 * User Service
 * 
 * Handles business logic for user management.
 * Centralizes user-related queries and operations.
 */
class UserService
{
    public function __construct(
        protected ApplicantProfileRepositoryInterface $applicantProfileRepository,
        protected UserRepositoryInterface $userRepository,
        protected ApplicationRepositoryInterface $applicationRepository,
    ) {}

    /**
     * Get all applicants with their applications and programs
     *
     * @return Collection
     */
    public function getApplicantsWithApplications(): Collection
    {
        if ($cachedData = Cache::get('applicants_with_applications')) {
            return collect($cachedData);
        }

        return Cache::lock('applicants_with_applications_lock', 10)->block(5, function () {
            return Cache::remember('applicants_with_applications', 300, function () {
                return $this->applicantProfileRepository->allWithCurrentApplication()
                    ->map(function ($profile) {
                        return [
                            'id' => $profile->user_id,
                            'firstname' => $profile->firstname,
                            'lastname' => $profile->lastname,
                            'course' => $profile->course ?? null,
                            'status' => $profile->currentApplication->status ?? null,
                            'email' => $profile->email,
                            'username' => $profile->email,
                            'company' => $profile->company ?? null,
                            'program' => $profile->currentApplication->program ?? null,
                            'processes' => $profile->currentApplication->processes ?? [],
                        ];
                    });
            });
        });
    }

    /**
     * Get applicants pending for a specified stage
     *
     * @param string $stage The application stage (evaluator, interviewer, medical)
     * @param array|null $programIds Optional list of program IDs to filter by (e.g. for interviewers)
     * @return Collection
     */
    public function getApplicantsByStage(string $stage, ?array $programIds = null): Collection
    {
        return $this->applicantProfileRepository->byStage($stage, $programIds)
            ->map(function ($profile) use ($stage) {
                $application = $profile->currentApplication;
                $stageProcess = $application && $application->processes ?
                    $application->processes->where('stage', $stage)->first() : null;

                return [
                    'id' => $profile->user_id,
                    'firstname' => $profile->firstname,
                    'lastname' => $profile->lastname,
                    'course' => $profile->course ?? null,
                    'status' => $application->status ?? null,
                    'email' => $profile->email,
                    'username' => $profile->email,
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
     * @param array|null $programIds Optional list of program IDs to filter by (e.g. for scoped evaluators/interviewers)
     * @return Collection
     */
    public function getAllApplicantsByStage(string $stage, ?array $programIds = null): Collection
    {
        return $this->applicantProfileRepository->allByStage($stage, $programIds)
            ->map(function ($profile) use ($stage) {
                $application = $profile->currentApplication;
                $stageProcess = $application && $application->processes ?
                    $application->processes->where('stage', $stage)->first() : null;

                return [
                    'id' => $profile->user_id,
                    'firstname' => $profile->firstname,
                    'lastname' => $profile->lastname,
                    'course' => $profile->course ?? null,
                    'status' => $application->status ?? null,
                    'email' => $profile->email,
                    'username' => $profile->email,
                    'company' => $profile->company ?? null,
                    'pipeline_status' => $this->derivePipelineStatus($application),
                    'program' => $application && $application->program ? [
                        'id' => $application->program->id,
                        'code' => $application->program->code,
                        'name' => $application->program->name,
                    ] : null,
                    'application' => $application ? [
                        'id' => $application->id,
                        'status' => $application->status,
                        'enrollment_status' => $application->enrollment_status,
                        'created_at' => $application->created_at,
                        'requires_guidance_office' => $application->requires_guidance_office ?? false,
                        'requires_admission_office' => $application->requires_admission_office ?? false,
                        'program' => $application->program ? [
                            'id' => $application->program->id,
                            'code' => $application->program->code,
                            'name' => $application->program->name,
                            'slots' => $application->program->slots ?? 0,
                        ] : null,
                        'second_choice' => $application->secondChoice ? [
                            'id' => $application->secondChoice->id,
                            'code' => $application->secondChoice->code,
                            'name' => $application->secondChoice->name,
                            'slots' => $application->secondChoice->slots ?? 0,
                        ] : null,
                        'third_choice' => $application->thirdChoice ? [
                            'id' => $application->thirdChoice->id,
                            'code' => $application->thirdChoice->code,
                            'name' => $application->thirdChoice->name,
                            'slots' => $application->thirdChoice->slots ?? 0,
                        ] : null,
                    ] : null,
                    'process_status' => $stageProcess ? $stageProcess->status : 'in_progress',
                    'process_action' => $stageProcess ? $stageProcess->action : null,
                    'is_evaluation_completed' => $stageProcess && $stageProcess->status === 'completed',
                ];
            });
    }

    /**
     * Derive a single pipeline_status string from an application and its processes.
     * This is the canonical status label used across all role views.
     *
     * Priority order (most terminal / most recent wins):
     *   officially_enrolled → for_records → medical_rejected
     *   → for_medical → interview_transferred → interview_passed → interview_returned
     *   → for_interview → evaluation_passed → evaluation_returned → for_evaluation
     *
     * @param \App\Models\Application|null $application
     * @return string
     */
    private function derivePipelineStatus($application): string
    {
        if (!$application) {
            return 'unknown';
        }

        // Enrollment / final states (check application-level fields first)
        if ($application->enrollment_status === 'officially_enrolled') {
            return 'officially_enrolled';
        }

        if ($application->status === 'rejected') {
            return 'rejected';
        }

        if ($application->status === 'cleared_for_enrollment') {
            // Medical cleared — always show in records tab regardless of whether
            // a records process exists yet; that is exactly what records staff need to act on.
            return 'for_records';
        }

        // Walk the processes collection (already eager-loaded with ALL stages)
        $processes = $application->processes->keyBy('stage');

        // Medical stage
        $medical = $processes->get('medical');
        if ($medical) {
            if ($medical->status === 'completed') {
                if ($medical->action === 'failed') return 'medical_rejected';
                // passed or no action — either way, applicant moves to records
                return 'for_records';
            }
            return 'for_medical';
        }

        // Interviewer stage
        $interviewer = $processes->get('interviewer');
        if ($interviewer) {
            if ($interviewer->status === 'completed') {
                if ($interviewer->action === 'rejected')     return 'for_interview';
                if ($interviewer->action === 'transferred') return 'interview_transferred';
                if ($interviewer->action === 'passed')      return 'interview_passed';
                return 'interview_passed';
            }
            if ($interviewer->status === 'returned') return 'interview_returned';
            return 'for_interview';
        }

        // Grade Evaluator stage
        $gradeEvaluator = $processes->get('grade_evaluator');
        if ($gradeEvaluator) {
            if ($gradeEvaluator->status === 'completed') {
                if ($gradeEvaluator->action === 'passed') return 'evaluation_passed';
                return 'evaluation_passed';
            }
            if ($gradeEvaluator->status === 'returned') return 'evaluation_returned';
            return 'for_evaluation';
        }

        // Document Evaluator stage
        $docEvaluator = $processes->get('document_evaluator');
        if ($docEvaluator) {
            if ($docEvaluator->status === 'completed') {
                if ($docEvaluator->action === 'passed') return 'evaluation_passed';
                return 'evaluation_passed';
            }
            if ($docEvaluator->status === 'returned') return 'evaluation_returned';
            return 'for_evaluation';
        }

        // Legacy Evaluator stage (fallback)
        $evaluator = $processes->get('evaluator');
        if ($evaluator) {
            if ($evaluator->status === 'completed') {
                if ($evaluator->action === 'passed') return 'evaluation_passed';
                return 'evaluation_passed';
            }
            if ($evaluator->status === 'returned') return 'evaluation_returned';
            return 'for_evaluation';
        }

        return 'for_evaluation';
    }

    /**
     * Get applicants for record staff
     * Returns applicants who have completed medical stage OR are officially enrolled
     *
     * @return Collection
     */
    public function getApplicantsForRecordStaff(): Collection
    {
        // Get user IDs with completed medical on their latest application
        $userIds = $this->applicationRepository->userIdsWithCompletedMedical();

        // Also include officially enrolled
        $enrolledIds = $this->applicationRepository->officiallyEnrolledUserIds();

        $allUserIds = array_unique(array_merge($userIds, $enrolledIds));

        if (empty($allUserIds)) {
            return collect();
        }

        // Load only what we need - no deep eager loading
        $allUserIdsStrings = array_map('strval', $allUserIds);
        $profiles = $this->applicantProfileRepository->byUserIds($allUserIdsStrings, ['user_id', 'firstname', 'lastname', 'email']);

        // Load applications separately
        $applications = $this->applicationRepository->latestApplicationsByUserIds($allUserIds);

        return $profiles->map(function ($profile) use ($applications) {
            $app = $applications->get($profile->user_id);
            $program = $app?->program;
            $pipelineStatus = $this->derivePipelineStatus($app);

            return [
                'id'                => $profile->user_id,
                'firstname'         => $profile->firstname,
                'lastname'          => $profile->lastname,
                'course'            => null,
                'email'             => $profile->email,
                'username'          => $profile->email,
                'company'           => null,
                'status'            => $app?->status ?? null,
                'enrollment_status' => $app?->enrollment_status ?? null,
                'pipeline_status'   => $pipelineStatus,
                'program'           => $program ? [
                    'id'   => $program->id,
                    'code' => $program->code,
                    'name' => $program->name,
                ] : null,
                'application'       => $app ? [
                    'id'                => $app->id,
                    'status'            => $app->status,
                    'enrollment_status' => $app->enrollment_status,
                    'program_id'        => $app->program_id,
                    'is_waivered'       => (bool) $app->is_waivered,
                    'created_at'        => $app->created_at,
                    'processes'         => $app->processes ?? [],
                    'program'           => $program ? [
                        'id'   => $program->id,
                        'code' => $program->code,
                        'name' => $program->name,
                    ] : null,
                ] : null,
            ];
        })->filter(function ($record) {
            // Records role only sees applicants that are in progress (for_records)
            // or officially enrolled — exclude all earlier pipeline stages
            return in_array($record['pipeline_status'], ['for_records', 'officially_enrolled']);
        })->values();
    }

    /**
     * Get all users with detailed information
     *
     * @return Collection
     */
    public function getAllUsersWithDetails(): Collection
    {
        // Get all staff profiles natively from Users table
        $staff = $this->userRepository->staffWithProgramsAndRole()
            ->map(function ($staff) {
                return (object) [
                    'id' => $staff->idp_user_id ?: $staff->id,
                    'firstname' => $staff->firstname,
                    'middlename' => $staff->middlename,
                    'lastname' => $staff->lastname,
                    'extension_name' => $staff->extension_name,
                    'email' => $staff->email,
                    'role_id' => $staff->role_id,
                    'created_at' => $staff->created_at,
                    'role' => (object) ['name' => $staff->role ? $staff->role->name : 'Staff'],
                    'programs' => $staff->programs,
                    'applicantProfile' => null,
                    'currentApplication' => null,
                    'officiallyEnrolledApplication' => null,
                ];
            });

        // Get all applicant profiles
        $applicants = $this->applicantProfileRepository->applicantsWithDetails()
            ->map(function ($applicant) {
                return (object) [
                    'id' => $applicant->user_id,
                    'firstname' => $applicant->firstname,
                    'middlename' => $applicant->middlename,
                    'lastname' => $applicant->lastname,
                    'extension_name' => $applicant->extension_name,
                    'email' => $applicant->email,
                    'role_id' => 1,
                    'created_at' => $applicant->created_at,
                    'role' => (object) ['name' => 'Applicant'],
                    'programs' => collect(), // Applicants don't manage programs this way
                    'applicant_profile' => (object) [
                        'first_choice_program' => $applicant->firstChoiceProgram
                    ],
                    'current_application' => $applicant->currentApplication ? (object) [
                        'program' => $applicant->currentApplication->program
                    ] : null,
                    'officially_enrolled_application' => $applicant->officiallyEnrolledApplication ? (object) [
                        'program' => $applicant->officiallyEnrolledApplication->program
                    ] : null,
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
        $staffCounts = $this->userRepository->staffCountsByRole();

        $applicantCount = $this->applicantProfileRepository->count();

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
        return $this->userRepository->staffCount() + $this->applicantProfileRepository->count();
    }

    /**
     * Create a new user (Staff or generic account) natively
     *
     * @param array $data
     * @return \App\Models\User
     */
    public function createUser(array $data): \App\Models\User
    {
        $roleId = (int) ($data['role_id'] ?? \App\Enums\RoleId::Applicant->value);

        $user = new \App\Models\User([
            'idp_user_id' => (string) \Illuminate\Support\Str::uuid(), // Assign standalone IDP uuid format locally as fallback
            'firstname' => $data['firstname'] ?? 'Pending IDP Sync',
            'middlename' => $data['middlename'] ?? null,
            'lastname' => $data['lastname'] ?? 'Pending IDP Sync',
            'email' => $data['email'],
            'salutation' => $data['salutation'] ?? null,
            'sex' => $data['sex'] ?? null,
            'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(12)), // IDP handles real passwords
        ]);

        // role_id is not mass-assignable; assign explicitly via the trusted helper.
        $user->assignRole($roleId);
        $user->save();

        return $user;
    }


    /**
     * Get role definitions
     *
     * @return array
     */
    public function getRoleDefinitions(): array
    {
        return \App\Enums\RoleId::names();
    }
    /**
     * Search and paginate users (staff + applicants) at the DB level.
     *
     * Executes a single UNION query so sorting and slicing happen in MySQL,
     * not in PHP memory.  This replaces the previous pattern of loading all
     * staff and all applicants into two separate collections, merging, sorting
     * and slicing in PHP — which scaled poorly at high record counts.
     *
     * Returns a plain array shaped like a Laravel paginator so the frontend
     * can drive pagination controls without loading all records into memory.
     *
     * @param  string|null  $search   Optional search term (name / email)
     * @param  int          $page     1-indexed current page
     * @param  int          $perPage  Records per page (default 15)
     * @param  int|null     $roleId   Filter by role (1 = applicants, >1 = staff)
     * @return array
     */
    public function searchUsers(?string $search = null, int $page = 1, int $perPage = 15, ?int $roleId = null): array
    {
        $skipStaff      = $roleId === 1;
        $skipApplicants = $roleId !== null && $roleId !== 1;

        // ── Count totals (separate lightweight queries) ───────────────────────
        $totalStaff      = $skipStaff      ? 0 : $this->userRepository->countSearchStaff($roleId, $search);
        $totalApplicants = $skipApplicants ? 0 : $this->applicantProfileRepository->countSearch($search);

        $total    = $totalStaff + $totalApplicants;
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = min(max(1, $page), $lastPage);
        $offset   = ($page - 1) * $perPage;

        // ── Fast-path: only one source is in play ─────────────────────────────
        // When we know exactly which records we need (staff-only or applicants-
        // only) we can use the existing paginated repository methods directly and
        // skip the UNION entirely.

        if ($skipApplicants) {
            // Staff-only page: let the DB do the offset+limit.
            $staff = $this->userRepository->searchStaff($roleId, $search, $offset, $perPage)
                ->map(fn ($u) => (object) [
                    'id'             => $u->idp_user_id ?: $u->id,
                    'firstname'      => $u->firstname,
                    'middlename'     => $u->middlename,
                    'lastname'       => $u->lastname,
                    'extension_name' => $u->extension_name,
                    'email'          => $u->email,
                    'role_id'        => $u->role_id,
                    'is_active'      => (bool) ($u->is_active ?? true),
                    'created_at'     => $u->created_at,
                    'role'           => (object) ['name' => $u->role ? $u->role->name : 'Staff'],
                    'programs'       => $u->programs,
                    'applicant_profile'               => null,
                    'current_application'             => null,
                    'officially_enrolled_application' => null,
                ]);

            return [
                'data'         => $staff->toArray(),
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => $lastPage,
            ];
        }

        if ($skipStaff) {
            // Applicants-only page.
            $applicants = $this->applicantProfileRepository->searchPaginated($search, $offset, $perPage)
                ->map(fn ($a) => (object) [
                    'id'             => $a->user_id,
                    'firstname'      => $a->firstname,
                    'middlename'     => $a->middlename,
                    'lastname'       => $a->lastname,
                    'extension_name' => $a->extension_name,
                    'email'          => $a->email,
                    'role_id'        => 1,
                    'is_active'      => (bool) ($a->user?->is_active ?? true),
                    'created_at'     => $a->created_at,
                    'role'           => (object) ['name' => 'Applicant'],
                    'programs'       => collect(),
                    'applicant_profile' => (object) [
                        'first_choice_program' => $a->firstChoiceProgram,
                    ],
                    'current_application' => $a->currentApplication ? (object) [
                        'program' => $a->currentApplication->program,
                    ] : null,
                    'officially_enrolled_application' => $a->officiallyEnrolledApplication ? (object) [
                        'program' => $a->officiallyEnrolledApplication->program,
                    ] : null,
                ]);

            return [
                'data'         => $applicants->toArray(),
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => $lastPage,
            ];
        }

        // ── Mixed page: both staff and applicants could appear ────────────────
        // We need sorted, globally-offset results across both sets.  Rather than
        // loading everything into PHP, we figure out which records the current
        // page needs using the sorted counts, then fetch only those rows.
        //
        // Strategy: staff are sorted newest-first in their own result set and
        // applicants in theirs.  The merged global sort is also newest-first.
        // We can determine the page boundary with simple arithmetic:
        //   - staff come first (they are loaded newest-first from users table)
        //   - applicants fill the rest
        //
        // This avoids a UNION across two structurally different tables while still
        // keeping DB-level limits.

        $staffNeeded      = max(0, min($totalStaff - $offset, $perPage));
        $staffOffset      = min($offset, $totalStaff);
        $applicantOffset  = max(0, $offset - $totalStaff);
        $applicantNeeded  = $perPage - $staffNeeded;

        $staff = $staffNeeded > 0
            ? $this->userRepository->searchStaff($roleId, $search, $staffOffset, $staffNeeded)
                ->map(fn ($u) => (object) [
                    'id'             => $u->idp_user_id ?: $u->id,
                    'firstname'      => $u->firstname,
                    'middlename'     => $u->middlename,
                    'lastname'       => $u->lastname,
                    'extension_name' => $u->extension_name,
                    'email'          => $u->email,
                    'role_id'        => $u->role_id,
                    'is_active'      => (bool) ($u->is_active ?? true),
                    'created_at'     => $u->created_at,
                    'role'           => (object) ['name' => $u->role ? $u->role->name : 'Staff'],
                    'programs'       => $u->programs,
                    'applicant_profile'               => null,
                    'current_application'             => null,
                    'officially_enrolled_application' => null,
                ])
            : collect();

        $applicants = $applicantNeeded > 0
            ? $this->applicantProfileRepository->searchPaginated($search, $applicantOffset, $applicantNeeded)
                ->map(fn ($a) => (object) [
                    'id'             => $a->user_id,
                    'firstname'      => $a->firstname,
                    'middlename'     => $a->middlename,
                    'lastname'       => $a->lastname,
                    'extension_name' => $a->extension_name,
                    'email'          => $a->email,
                    'role_id'        => 1,
                    'is_active'      => (bool) ($a->user?->is_active ?? true),
                    'created_at'     => $a->created_at,
                    'role'           => (object) ['name' => 'Applicant'],
                    'programs'       => collect(),
                    'applicant_profile' => (object) [
                        'first_choice_program' => $a->firstChoiceProgram,
                    ],
                    'current_application' => $a->currentApplication ? (object) [
                        'program' => $a->currentApplication->program,
                    ] : null,
                    'officially_enrolled_application' => $a->officiallyEnrolledApplication ? (object) [
                        'program' => $a->officiallyEnrolledApplication->program,
                    ] : null,
                ])
            : collect();

        $merged = $staff->concat($applicants)->values();

        return [
            'data'         => $merged->toArray(),
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $lastPage,
        ];
    }

    /**
     * Deactivate a user account (Revoke access while preserving record).
     */
    public function deactivateUser(string|int $userId, ?string $reason = null, ?int $performedBy = null): bool
    {
        return DB::transaction(function () use ($userId, $reason, $performedBy) {
            $user = \App\Models\User::where('idp_user_id', (string) $userId)
                ->orWhere('id', $userId)
                ->first();

            if (!$user) {
                return false;
            }

            $user->update(['is_active' => false]);

            \App\Models\AuditLog::create([
                'user_id' => $performedBy ?? $user->id,
                'username' => auth()->user()?->email ?? 'SYSTEM',
                'user_role' => auth()->user()?->role?->name ?? 'SYSTEM',
                'log_type' => \App\Models\AuditLog::TYPE_SECURITY,
                'log_category' => \App\Models\AuditLog::CATEGORY_USER_MANAGEMENT,
                'action_type' => \App\Models\AuditLog::ACTION_UPDATE,
                'module_name' => 'User Management',
                'description' => "Deactivated user account: {$user->email}. Reason: " . ($reason ?? 'Administrative action'),
                'old_values' => ['is_active' => true],
                'new_values' => ['is_active' => false, 'reason' => $reason],
            ]);

            return true;
        });
    }

    /**
     * Reactivate a deactivated user account.
     */
    public function reactivateUser(string|int $userId, ?int $performedBy = null): bool
    {
        return DB::transaction(function () use ($userId, $performedBy) {
            $user = \App\Models\User::where('idp_user_id', (string) $userId)
                ->orWhere('id', $userId)
                ->first();

            if (!$user) {
                return false;
            }

            $user->update(['is_active' => true]);

            \App\Models\AuditLog::create([
                'user_id' => $performedBy ?? $user->id,
                'username' => auth()->user()?->email ?? 'SYSTEM',
                'user_role' => auth()->user()?->role?->name ?? 'SYSTEM',
                'log_type' => \App\Models\AuditLog::TYPE_SECURITY,
                'log_category' => \App\Models\AuditLog::CATEGORY_USER_MANAGEMENT,
                'action_type' => \App\Models\AuditLog::ACTION_UPDATE,
                'module_name' => 'User Management',
                'description' => "Reactivated user account: {$user->email}",
                'old_values' => ['is_active' => false],
                'new_values' => ['is_active' => true],
            ]);

            return true;
        });
    }

    /**
     * Soft-delete a user account (Phase 1 Data Retention Hold).
     */
    public function softDeleteUser(string|int $userId, ?string $reason = null, ?int $performedBy = null): bool
    {
        return DB::transaction(function () use ($userId, $reason, $performedBy) {
            $user = \App\Models\User::where('idp_user_id', (string) $userId)
                ->orWhere('id', $userId)
                ->first();

            if (!$user) {
                return false;
            }

            $userEmail = $user->email;
            $user->update(['is_active' => false]);
            $user->delete();

            // Soft-delete associated profile if present
            \App\Models\ApplicantProfile::where('user_id', (string) $userId)
                ->orWhere('user_id', (string) $user->id)
                ->delete();

            \App\Models\AuditLog::create([
                'user_id' => $performedBy ?? $user->id,
                'username' => auth()->user()?->email ?? 'SYSTEM',
                'user_role' => auth()->user()?->role?->name ?? 'SYSTEM',
                'log_type' => \App\Models\AuditLog::TYPE_SECURITY,
                'log_category' => \App\Models\AuditLog::CATEGORY_USER_MANAGEMENT,
                'action_type' => \App\Models\AuditLog::ACTION_DELETE,
                'module_name' => 'User Management',
                'description' => "Soft-deleted user account (Retention Hold): {$userEmail}. Reason: " . ($reason ?? 'Account withdrawal / disposal hold'),
                'old_values' => ['deleted_at' => null, 'is_active' => true],
                'new_values' => ['deleted_at' => now()->toDateTimeString(), 'is_active' => false, 'reason' => $reason],
            ]);

            return true;
        });
    }

    /**
     * Restore a soft-deleted user account within retention hold period.
     */
    public function restoreUser(string|int $userId, ?int $performedBy = null): bool
    {
        return DB::transaction(function () use ($userId, $performedBy) {
            $user = \App\Models\User::withTrashed()
                ->where(function ($q) use ($userId) {
                    $q->where('idp_user_id', (string) $userId)->orWhere('id', $userId);
                })
                ->first();

            if (!$user) {
                return false;
            }

            $user->restore();
            $user->update(['is_active' => true]);

            \App\Models\ApplicantProfile::withTrashed()
                ->where(function ($q) use ($userId, $user) {
                    $q->where('user_id', (string) $userId)->orWhere('user_id', (string) $user->id);
                })
                ->restore();

            \App\Models\AuditLog::create([
                'user_id' => $performedBy ?? $user->id,
                'username' => auth()->user()?->email ?? 'SYSTEM',
                'user_role' => auth()->user()?->role?->name ?? 'SYSTEM',
                'log_type' => \App\Models\AuditLog::TYPE_SECURITY,
                'log_category' => \App\Models\AuditLog::CATEGORY_USER_MANAGEMENT,
                'action_type' => \App\Models\AuditLog::ACTION_UPDATE,
                'module_name' => 'User Management',
                'description' => "Restored soft-deleted user account: {$user->email}",
                'old_values' => ['deleted_at' => $user->deleted_at],
                'new_values' => ['deleted_at' => null, 'is_active' => true],
            ]);

            return true;
        });
    }
}