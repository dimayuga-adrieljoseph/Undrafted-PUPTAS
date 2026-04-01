<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ApplicantProfile;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use App\Models\Program;
use App\Rules\ValidationRules;
use Inertia\Inertia;
use App\Services\AuditLogService;
use App\Services\UserService;

class UserController extends Controller
{
    protected UserService $userService;
    protected AuditLogService $auditLogService;

    public function __construct(UserService $userService, AuditLogService $auditLogService)
    {
        $this->userService     = $userService;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Display the user addition form.
     */
    public function create()
    {
        $userCountsByRole = $this->userService->getUserCountsByRole();
        $roles = $this->userService->getRoleDefinitions();
        $totalUsers = $this->userService->getTotalUserCount();
        $programs = Program::all();

        return Inertia::render('UserManagement/AddUser', [
            'userCountsByRole' => $userCountsByRole,
            'roles' => $roles,
            'totalUsers' => $totalUsers,
            'programs' => $programs,
        ]);
    }

    /**
     * Handle an incoming user creation request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $request->validate(ValidationRules::userStore());

        $user = $this->userService->createUser($request->all());

        event(new Registered($user));

        $this->auditLogService->logActivity(
            'CREATE',
            'Users',
            "Created user account for {$user->firstname} {$user->lastname} ({$user->email}) with role ID {$user->role_id}.",
            null,
            'USER_MANAGEMENT'
        );

        return redirect()->route('users.index')->with('status', 'User added successfully!');
    }

    /**
     * Display a listing of all users.
     */
    public function index()
    {
        $users = $this->userService->getAllUsersWithDetails();
        $userCountsByRole = $this->userService->getUserCountsByRole();
        $roles = $this->userService->getRoleDefinitions();
        $totalUsers = $this->userService->getTotalUserCount();

        return Inertia::render('UserManagement/ManageUsers', [
            'users' => $users,
            'userCountsByRole' => $userCountsByRole,
            'roles' => $roles,
            'totalUsers' => $totalUsers,
        ]);
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit($id)
    {
        // Try staff first, then applicant
        $staff = \App\Models\StaffProfile::where('user_id', $id)->first();
        if ($staff) {
            $user = (object) [
                'id' => $staff->user_id,
                'firstname' => $staff->name,
                'lastname' => '',
                'email' => $staff->email,
                'role_id' => $staff->role_id,
                'programs' => $staff->programs
            ];
        } else {
            $applicant = \App\Models\ApplicantProfile::where('user_id', $id)->firstOrFail();
            $user = (object) [
                'id' => $applicant->user_id,
                'firstname' => $applicant->firstname,
                'lastname' => $applicant->lastname,
                'email' => $applicant->email,
                'role_id' => 1,
                'applicant_profile' => $applicant
            ];
        }

        $programs = Program::all();
        $roles = $this->userService->getRoleDefinitions();

        return Inertia::render('UserManagement/EditUser', [
            'user' => $user,
            'programs' => $programs,
            'roles' => $roles,
        ]);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, $id)
    {
        // If Role is 1 (Applicant), we find them in ApplicantProfile. Else StaffProfile.
        $roleId = $request->role_id;

        return DB::transaction(function () use ($request, $id, $roleId) {
            // Handle program assignments based on role
            $programsToSync = [];

            if ($roleId == 1 && $request->filled('applicant_program')) {
                // For Applicants: use applicant_program field (using program code)
                $program = Program::where('code', $request->applicant_program)->first();
                if ($program) {
                    $applicantProfile = ApplicantProfile::where('user_id', $id)->firstOrFail();

                    // Update the applicant profile with the first choice program
                    $applicantProfile->update(['first_choice_program' => $program->id]);

                    // Update existing applications
                    $officiallyEnrolled = $applicantProfile->applications()
                        ->where('enrollment_status', 'officially_enrolled')
                        ->first();

                    if ($officiallyEnrolled) {
                        $officiallyEnrolled->update(['program_id' => $program->id]);
                    } else {
                        $latestApplication = $applicantProfile->applications()
                            ->orderBy('created_at', 'desc')
                            ->first();

                        if ($latestApplication) {
                            $latestApplication->update(['program_id' => $program->id]);
                        }
                    }

                    $actionDetails = "Updated Applicant's program to {$program->code}";
                    $userEmail = $applicantProfile->email;
                }
            } elseif (in_array($roleId, [3, 4]) && $request->filled('program')) {
                // For Evaluators (3) and Interviewers (4): use program field (using program code)
                $staff = \App\Models\StaffProfile::where('user_id', $id)->firstOrFail();
                $program = Program::where('code', $request->program)->first();
                if ($program) {
                    $staff->programs()->sync([$program->id => ['role_id' => $roleId]]);
                } else {
                    $staff->programs()->detach();
                }
                $actionDetails = "Updated Staff program to " . ($program ? $program->code : 'None');
                $userEmail = $staff->email;
            }

            if (isset($actionDetails)) {
                $this->auditLogService->logActivity(
                    'UPDATE',
                    'Users',
                    "Updated assigned program for user {($userEmail)}.",
                    null,
                    'USER_MANAGEMENT'
                );
            }

            return redirect()->route('users.index')->with('status', 'Program assignment updated successfully!');
        });
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy($id)
    {
        // For IDP, users are not deleted locally, but we might want to drop their profiles locally
        $staff = \App\Models\StaffProfile::find($id);
        if ($staff) {
            $staff->delete();
        }
        $app = \App\Models\ApplicantProfile::where('user_id', $id)->first();
        if ($app) {
            $app->delete();
        }

        $this->auditLogService->logActivity(
            'DELETE',
            'Users',
            "Deleted local user profile for {$id}.",
            null,
            'USER_MANAGEMENT'
        );

        return redirect()->route('users.index')->with('status', 'User localized details removed successfully!');
    }
}
