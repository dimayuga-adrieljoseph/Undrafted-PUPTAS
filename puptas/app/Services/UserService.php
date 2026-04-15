<?php

namespace App\Services;

use App\Models\ApplicantProfile;
use App\Models\Program;
use Illuminate\Support\Collection;
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
    }

    /**
     * Get applicants pending for a specified stage
     *
     * @param string $stage The application stage (evaluator, interviewer, medical)
     * @return Collection
     */
    public function getApplicantsByStage(string $stage): Collection
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
        return ApplicantProfile::with(['currentApplication' => function ($query) {
                $query->select('id', 'user_id', 'status', 'enrollment_status', 'program_id', 'created_at');
            }, 'currentApplication.program' => function ($query) {
                $query->select('id', 'code', 'name');
            }])
            ->whereHas('currentApplication', function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('processes', function ($process) {
                        $process->where('stage', 'medical')
                            ->where('status', 'completed');
                    })
                    ->orWhere('enrollment_status', 'officially_enrolled');
                });
            })
            ->select('user_id', 'firstname', 'lastname', 'email', 'contactnumber', 'student_number')
            ->get()
            ->map(function ($profile) {
                $app = $profile->currentApplication;
                $program = $app?->program;

                return [
                    'id'               => $profile->user_id,
                    'firstname'        => $profile->firstname,
                    'lastname'         => $profile->lastname,
                    'course'           => null,
                    'email'            => $profile->email,
                    'username'         => $profile->email,
                    'phone'            => $profile->contactnumber,
                    'company'          => null,
                    'status'           => $app?->status ?? null,
                    'enrollment_status' => $app?->enrollment_status ?? null,
                    'program'          => $program ? [
                        'id'   => $program->id,
                        'code' => $program->code,
                        'name' => $program->name,
                    ] : null,
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
                    'contactnumber' => $staff->contactnumber,
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
            'contactnumber' => $data['contactnumber'] ?? '0000000000',
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
}
