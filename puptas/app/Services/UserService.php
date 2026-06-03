<?php

namespace App\Services;

use App\Models\ApplicantProfile;
use App\Models\Program;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
        // Cache for 5 minutes (300 seconds) to avoid re-running this heavy query
        // on every dashboard page load. Uses the default file cache driver.
        return Cache::remember('applicants_with_applications', 300, function () {
            return ApplicantProfile::with(['currentApplication.program', 'currentApplication.processes:id,application_id,stage,status,action,created_at'])
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
                        'company' => $profile->company ?? null,
                        'program' => $profile->currentApplication->program ?? null,
                        'processes' => $profile->currentApplication->processes ?? [],
                    ];
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
        return ApplicantProfile::with(['currentApplication' => function ($query) {
            $query->select('applications.id', 'applications.user_id', 'applications.status', 'applications.created_at', 'applications.program_id');
        }, 'currentApplication.program' => function ($query) {
            $query->select('id', 'code', 'name');
        }, 'currentApplication.processes' => function ($query) use ($stage) {
            $query->where('stage', $stage)
                ->orderBy('created_at', 'desc')
                ->select('id', 'application_id', 'stage', 'status', 'action', 'created_at');
        }])
            ->whereHas('applications', function ($query) use ($stage, $programIds) {
                $query->whereNotIn('status', ['accepted', 'cleared_for_enrollment'])
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

                if (!empty($programIds)) {
                    $query->whereIn('program_id', $programIds);
                }
            })
            ->get()
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
        return ApplicantProfile::with(['currentApplication' => function ($query) {
                $query->select('applications.id', 'applications.user_id', 'applications.status', 'applications.enrollment_status', 'applications.created_at', 'applications.program_id', 'applications.second_choice_id', 'applications.third_choice_id', 'applications.requires_promissory_note');
            }, 'currentApplication.program' => function ($query) {
                $query->select('id', 'code', 'name', 'slots');
            }, 'currentApplication.secondChoice' => function ($query) {
                $query->select('id', 'code', 'name', 'slots');
            }, 'currentApplication.thirdChoice' => function ($query) {
                $query->select('id', 'code', 'name', 'slots');
            }, 'currentApplication.processes' => function ($query) {
                // Load ALL stages so derivePipelineStatus() has full context
                $query->orderBy('created_at', 'desc')
                    ->select('id', 'application_id', 'stage', 'status', 'action', 'created_at');
            }])
            ->whereHas('applications', function ($query) use ($stage, $programIds) {
                // Pin to the latest non-deleted application only.
                // This prevents matching old applications for students who have since
                // been enrolled or moved past this stage on a newer application.
                $query->whereRaw('applications.id = (SELECT MAX(a.id) FROM applications a WHERE a.user_id = applications.user_id AND a.deleted_at IS NULL)')
                    ->whereNull('applications.deleted_at')
                    ->whereHas('processes', function ($q) use ($stage) {
                        $q->where('stage', $stage)
                            ->whereIn('status', ['in_progress', 'returned', 'completed']);
                    });

                if (!empty($programIds)) {
                    $query->whereIn('program_id', $programIds);
                }
            })
            ->get()
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
                        'requires_promissory_note' => $application->requires_promissory_note ?? false,
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
     *   officially_enrolled → for_records → medical_cleared → medical_rejected
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
            // Medical cleared — check if records process exists
            $recordsProcess = $application->processes
                ->where('stage', 'records')
                ->first();
            if ($recordsProcess) {
                return 'for_records';
            }
            return 'medical_cleared';
        }

        // Walk the processes collection (already eager-loaded with ALL stages)
        $processes = $application->processes->keyBy('stage');

        // Medical stage
        $medical = $processes->get('medical');
        if ($medical) {
            if ($medical->status === 'completed') {
                if ($medical->action === 'passed') return 'medical_cleared';
                if ($medical->action === 'failed')  return 'medical_rejected';
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

        // Evaluator stage
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
        $userIds = \Illuminate\Support\Facades\DB::table('applications as a')
            ->join('application_processes as p', 'p.application_id', '=', 'a.id')
            ->whereNull('a.deleted_at')
            ->where('p.stage', 'medical')
            ->where('p.status', 'completed')
            ->whereRaw('a.id = (SELECT MAX(a2.id) FROM applications a2 WHERE a2.user_id = a.user_id AND a2.deleted_at IS NULL)')
            ->pluck('a.user_id')
            ->map(fn($id) => (string) $id)
            ->toArray();

        // Also include officially enrolled
        $enrolledIds = \Illuminate\Support\Facades\DB::table('applications')
            ->whereNull('deleted_at')
            ->where('enrollment_status', 'officially_enrolled')
            ->whereRaw('id = (SELECT MAX(a2.id) FROM applications a2 WHERE a2.user_id = applications.user_id AND a2.deleted_at IS NULL)')
            ->pluck('user_id')
            ->map(fn($id) => (string) $id)
            ->toArray();

        $allUserIds = array_unique(array_merge($userIds, $enrolledIds));

        if (empty($allUserIds)) {
            return collect();
        }

        // Load only what we need - no deep eager loading
        $allUserIdsStrings = array_map('strval', $allUserIds);
        $profiles = ApplicantProfile::whereIn('user_id', $allUserIdsStrings)->get(['user_id', 'firstname', 'lastname', 'email', 'student_number']);

        // Load applications separately
        $applications = \App\Models\Application::whereIn('user_id', $allUserIds)
            ->whereNull('deleted_at')
            ->whereRaw('id = (SELECT MAX(a2.id) FROM applications a2 WHERE a2.user_id = applications.user_id AND a2.deleted_at IS NULL)')
            ->with(['program:id,code,name', 'processes:id,application_id,stage,status,action,created_at'])
            ->get()
            ->keyBy('user_id');

        return $profiles->map(function ($profile) use ($applications) {
            $app = $applications->get($profile->user_id);
            $program = $app?->program;

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
                'pipeline_status'   => $this->derivePipelineStatus($app),
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
                    'created_at'        => $app->created_at,
                    'processes'         => $app->processes ?? [],
                    'program'           => $program ? [
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
        // Get all staff profiles natively from Users table
        $staff = \App\Models\User::with(['programs:id,name,code', 'role'])
            ->where('role_id', '>', 1)
            ->orderBy('created_at', 'desc')
            ->get()
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
        $staffCounts = \App\Models\User::where('role_id', '>', 1)
            ->select('role_id', DB::raw('count(*) as total'))
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
        return \App\Models\User::where('role_id', '>', 1)->count() + ApplicantProfile::count();
    }

    /**
     * Create a new user (Staff or generic account) natively
     *
     * @param array $data
     * @return \App\Models\User
     */
    public function createUser(array $data): \App\Models\User
    {
        return \App\Models\User::create([
            'idp_user_id' => (string) \Illuminate\Support\Str::uuid(), // Assign standalone IDP uuid format locally as falback
            'firstname' => $data['firstname'] ?? 'Pending IDP Sync',
            'middlename' => $data['middlename'] ?? null,
            'lastname' => $data['lastname'] ?? 'Pending IDP Sync',
            'email' => $data['email'],
            'role_id' => $data['role_id'] ?? 1,
            'salutation' => $data['salutation'] ?? null,
            'sex' => $data['sex'] ?? null,
            'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(12)), // IDP handles real passwords
        ]);
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
    /**
     * Search and paginate users (staff + applicants) at the DB level.
     *
     * Returns a plain array shaped like a Laravel paginator so the frontend
     * can drive pagination controls without loading all records into memory.
     *
     * @param  string|null  $search   Optional search term (name / email)
     * @param  int          $page     1-indexed current page
     * @param  int          $perPage  Records per page (default 15)
     * @return array
     */
    public function searchUsers(?string $search = null, int $page = 1, int $perPage = 15): array
    {
        $term = $search ? '%' . $search . '%' : null;

        // --- Staff query (role_id > 1) ---
        $staffQuery = \App\Models\User::with(['programs:id,name,code', 'role'])
            ->where('role_id', '>', 1);

        if ($term) {
            $staffQuery->where(function ($q) use ($term) {
                $q->where('firstname', 'like', $term)
                  ->orWhere('lastname', 'like', $term)
                  ->orWhere('email', 'like', $term);
            });
        }

        $totalStaff = $staffQuery->count();

        // --- Applicant query ---
        $applicantQuery = \App\Models\ApplicantProfile::with([
            'firstChoiceProgram:id,name,code',
            'currentApplication' => function ($q) {
                $q->select('applications.id', 'applications.user_id', 'applications.program_id', 'applications.enrollment_status');
            },
            'currentApplication.program:id,name,code',
            'officiallyEnrolledApplication' => function ($q) {
                $q->select('applications.id', 'applications.user_id', 'applications.program_id', 'applications.enrollment_status');
            },
            'officiallyEnrolledApplication.program:id,name,code',
        ]);

        if ($term) {
            $applicantQuery->where(function ($q) use ($term) {
                $q->where('firstname', 'like', $term)
                  ->orWhere('lastname', 'like', $term)
                  ->orWhere('email', 'like', $term);
            });
        }

        $totalApplicants = $applicantQuery->count();
        $total = $totalStaff + $totalApplicants;
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $offset = ($page - 1) * $perPage;

        $staff = $staffQuery->orderBy('created_at', 'desc')->get()->map(function ($u) {
            return (object) [
                'id'             => $u->idp_user_id ?: $u->id,
                'firstname'      => $u->firstname,
                'middlename'     => $u->middlename,
                'lastname'       => $u->lastname,
                'extension_name' => $u->extension_name,
                'email'          => $u->email,
                'role_id'        => $u->role_id,
                'created_at'     => $u->created_at,
                'role'           => (object) ['name' => $u->role ? $u->role->name : 'Staff'],
                'programs'       => $u->programs,
                'applicant_profile'               => null,
                'current_application'             => null,
                'officially_enrolled_application' => null,
            ];
        });

        $applicants = $applicantQuery->orderBy('created_at', 'desc')->get()->map(function ($a) {
            return (object) [
                'id'             => $a->user_id,
                'firstname'      => $a->firstname,
                'middlename'     => $a->middlename,
                'lastname'       => $a->lastname,
                'extension_name' => $a->extension_name,
                'email'          => $a->email,
                'role_id'        => 1,
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
            ];
        });

        $merged = $staff->concat($applicants)
            ->sortByDesc('created_at')
            ->values()
            ->slice($offset, $perPage)
            ->values();

        return [
            'data'         => $merged->toArray(),
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $lastPage,
        ];
    }
}
