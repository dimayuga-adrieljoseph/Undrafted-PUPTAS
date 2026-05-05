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

        $roleId = $user->role_id;

        // Assign to multiple programs for staff
        if (in_array($roleId, [3, 4]) && $request->filled('program') && is_array($request->program)) {
            $programs = Program::whereIn('code', $request->program)->get();
            $syncData = [];
            foreach ($programs as $prog) {
                $syncData[$prog->id] = ['role_id' => $roleId];
            }
            $user->programs()->sync($syncData);
        }

        // For Applicants added manually
        if ($roleId == 1 && $request->filled('applicant_program')) {
            $program = Program::where('code', $request->applicant_program)->first();
            if ($program) {
                ApplicantProfile::create([
                    'user_id' => $user->idp_user_id ?: $user->id, // Use string uuid fallback
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'middlename' => $user->middlename,
                    'extension_name' => $user->extension_name,
                    'email' => $user->email,
                    'contactnumber' => $user->contactnumber,
                    'first_choice_program' => $program->id
                ]);
            }
        }

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
        // Fetch the user model primarily to check role. Fallback to ID match.
        $userModel = \App\Models\User::where('idp_user_id', $id)->orWhere('id', $id)->firstOrFail();

        if ($userModel->role_id > 1) {
            $userModel->load('programs');
            $user = (object) [
                'id' => $userModel->idp_user_id ?: $userModel->id,
                'firstname' => $userModel->firstname,
                'middlename' => $userModel->middlename,
                'lastname' => $userModel->lastname,
                'extension_name' => $userModel->extension_name,
                'email' => $userModel->email,
                'contactnumber' => $userModel->contactnumber,
                'role_id' => $userModel->role_id,
                'programs' => $userModel->programs,
            ];
        } else {
            $applicant = \App\Models\ApplicantProfile::where('user_id', $id)->orWhere('user_id', $userModel->id)->firstOrFail();
            $user = (object) [
                'id' => $applicant->user_id,
                'firstname' => $applicant->firstname,
                'middlename' => $applicant->middlename,
                'lastname' => $applicant->lastname,
                'extension_name' => $applicant->extension_name,
                'email' => $applicant->email,
                'contactnumber' => $applicant->contactnumber,
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
        $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'middlename' => 'nullable|string|max:255',
            'extension_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'contactnumber' => 'nullable|string|max:20',
            'role_id' => 'required|integer',
        ]);

        // If Role is 1 (Applicant), we find them in ApplicantProfile. Else StaffProfile.
        $roleId = $request->role_id;

        return DB::transaction(function () use ($request, $id, $roleId) {
            
            $user = \App\Models\User::where('idp_user_id', $id)->orWhere('id', $id)->first();
            $applicantProfile = \App\Models\ApplicantProfile::where('user_id', $id)->orWhere('user_id', optional($user)->id)->first();
            
            if ($user) {
                $user->update([
                    'firstname' => $request->firstname,
                    'middlename' => $request->middlename,
                    'lastname' => $request->lastname,
                    'extension_name' => $request->extension_name,
                    'email' => $request->email,
                    'contactnumber' => $request->contactnumber,
                    'role_id' => $roleId,
                ]);

                if ($request->filled('password')) {
                    $user->update(['password' => Hash::make($request->password)]);
                }
            }

            if ($applicantProfile) {
                $applicantProfile->update([
                    'firstname' => $request->firstname,
                    'middlename' => $request->middlename,
                    'lastname' => $request->lastname,
                    'extension_name' => $request->extension_name,
                    'email' => $request->email,
                    'contactnumber' => $request->contactnumber,
                ]);
            }

            $userEmail = $request->email;
            $actionDetails = "Updated User {$userEmail}";

            // Handle program assignments based on role
            if ($roleId == 1 && $request->filled('applicant_program')) {
                // For Applicants: use applicant_program field (using program code)
                $program = Program::where('code', $request->applicant_program)->first();
                if ($program && $applicantProfile) {
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

                    $actionDetails = "Updated Applicant {$userEmail} program to {$program->code}";
                }
            } elseif (in_array($roleId, [3, 4]) && $request->filled('program') && is_array($request->program) && $user) {
                // For Evaluators (3) and Interviewers (4): handle program arrays (using program code)
                $programs = Program::whereIn('code', $request->program)->get();
                if ($programs->count() > 0) {
                    $syncData = [];
                    $programCodes = [];
                    foreach ($programs as $prog) {
                        $syncData[$prog->id] = ['role_id' => $roleId];
                        $programCodes[] = $prog->code;
                    }
                    $user->programs()->sync($syncData);
                    $actionDetails = "Updated Staff {$userEmail} programs to: " . implode(', ', $programCodes);
                } else {
                    $user->programs()->detach();
                    $actionDetails = "Updated Staff {$userEmail} programs to None";
                }
            } elseif ($user) {
                $user->programs()->detach();
            }

            $this->auditLogService->logActivity(
                'UPDATE',
                'Users',
                $actionDetails,
                null,
                'USER_MANAGEMENT'
            );

            return redirect()->route('users.index')->with('status', 'User details updated successfully!');
        });
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy($id)
    {
        // For IDP, users are not deleted locally, but we might want to drop their profiles locally
        $staff = \App\Models\User::where('idp_user_id', $id)->orWhere('id', $id)->first();
        if ($staff && $staff->role_id > 1) {
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
