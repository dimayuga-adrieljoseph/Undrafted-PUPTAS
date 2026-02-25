<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AuditLog;
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
use App\Services\UserService;

class UserController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
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

        // Debug log to see applicant profiles
        foreach ($users as $user) {
            if ($user->role_id == 1) {
                \Log::info('Applicant user in index response', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'has_applicant_profile' => $user->applicantProfile !== null,
                    'applicant_profile_data' => $user->applicantProfile ? [
                        'id' => $user->applicantProfile->id,
                        'user_id' => $user->applicantProfile->user_id,
                        'first_choice_program' => $user->applicantProfile->first_choice_program,
                    ] : null,
                    'first_choice_program_data' => $user->applicantProfile?->firstChoiceProgram ? [
                        'id' => $user->applicantProfile->firstChoiceProgram->id,
                        'name' => $user->applicantProfile->firstChoiceProgram->name,
                    ] : null,
                ]);
            }
        }

        // Audit log for viewing sensitive user data
        $this->userService->logUserListingView(auth()->id(), $users->count());

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
        $user = User::findOrFail($id);
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
        $user = User::findOrFail($id);

        $request->validate(ValidationRules::userUpdate($id));

        \Log::info('User update request', [
            'user_id' => $id,
            'role_id' => $request->role_id,
            'program' => $request->program,
            'applicant_program' => $request->applicant_program,
            'all_fields' => $request->all()
        ]);

        $user->firstname = $request->firstname;
        $user->lastname = $request->lastname;
        $user->middlename = $request->middlename;
        $user->extension_name = $request->extension_name; // Added
        $user->email = $request->email;
        $user->contactnumber = $request->contactnumber;
        $user->role_id = $request->role_id;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        // Handle program assignments based on role
        $programsToSync = [];

        if ($request->role_id == 1 && $request->filled('applicant_program')) {
            // For Applicants: use applicant_program field (using program code)
            $program = Program::where('code', $request->applicant_program)->first();
            \Log::info('Looking up applicant program', [
                'code' => $request->applicant_program,
                'found_program' => $program ? $program->id : null
            ]);
            if ($program) {
                $programsToSync[$program->id] = ['role_id' => $request->role_id];

                // Ensure applicant profile exists
                $applicantProfile = $user->applicantProfile;
                if (!$applicantProfile) {
                    $applicantProfile = ApplicantProfile::create([
                        'user_id' => $user->id,
                    ]);
                    \Log::info('Created new ApplicantProfile for user', ['user_id' => $user->id]);
                }

                // Update the applicant profile with the first choice program
                $applicantProfile->update(['first_choice_program' => $program->id]);
                \Log::info('Updated ApplicantProfile', [
                    'user_id' => $user->id,
                    'first_choice_program' => $program->id
                ]);

                // IMPORTANT: Also update any existing applications to the new program
                // This ensures the ManageUsers display will show the updated program
                // The ManageUsers component displays in this priority:
                // 1. officially_enrolled_application.program
                // 2. current_application.program
                // 3. applicant_profile.first_choice_program
                $existingApplications = $user->applications()->orderBy('created_at', 'desc')->get();
                if ($existingApplications->count() > 0) {
                    // Update the most recent application to the new program
                    $latestApplication = $existingApplications->first();
                    $latestApplication->update(['program_id' => $program->id]);
                    \Log::info('Updated latest application program', [
                        'user_id' => $user->id,
                        'application_id' => $latestApplication->id,
                        'new_program_id' => $program->id
                    ]);
                }
            }
        } elseif (in_array($request->role_id, [3, 4]) && $request->filled('program')) {
            // For Evaluators (3) and Interviewers (4): use program field (using program code)
            $program = Program::where('code', $request->program)->first();
            if ($program) {
                $programsToSync[$program->id] = ['role_id' => $request->role_id];
            }
        }

        // Sync the programs
        $user->programs()->sync($programsToSync);

        // Always redirect - Inertia will handle re-fetching data
        return redirect()->route('users.index')->with('status', 'User updated successfully!');
    }
    /**
     * Remove the specified user from storage.
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Prevent deleting your own account
        if ($user->id === auth()->user()->id) {
            return redirect()->route('users.index')->with('error', 'You cannot delete your own account!');
        }

        // Log deletion for audit purposes
        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'model_type' => 'User',
                'model_id' => $user->id,
                'action' => 'deleted',
                'old_values' => [
                    'email' => $user->email,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'role_id' => $user->role_id,
                    'deleted_by' => auth()->user()->email,
                ],
                'new_values' => null,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            logger()->error('Failed to create audit log during user deletion', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', 'User deleted successfully!');
    }
}
