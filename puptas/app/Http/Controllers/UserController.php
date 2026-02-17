<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use App\Models\Program;
use App\Rules\ValidationRules;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Display the user addition form.
     */
    public function create()
    {
        $userCountsByRole = User::select('role_id', \DB::raw('count(*) as total'))
            ->groupBy('role_id')
            ->pluck('total', 'role_id')
            ->toArray();

        $roles = [
            1 => 'Applicant',
            2 => 'Admin',
            3 => 'Evaluator',
            4 => 'Interviewer',
            5 => 'Medical',
            6 => 'Registrar',
        ];

        $totalUsers = User::count();
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

        $user = User::create([
            'firstname' => $request->firstname,
            'lastname' => $request->lastname,
            'middlename' => $request->middlename,
            'email' => $request->email,
            'contactnumber' => $request->contactnumber,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
        ]);

        // Attach program if role is Applicant and program is provided
        if ($request->role_id == 1 && $request->filled('program')) {
            $user->programs()->attach($request->program, ['role_id' => $request->role_id]);
        }

        event(new Registered($user));

        return redirect()->route('users.index')->with('status', 'User added successfully!');
    }

    /**
     * Display a listing of all users.
     */
    public function index()
    {
       $users = User::select('id', 'firstname', 'middlename', 'lastname', 'email', 'contactnumber', 'role_id', 'created_at')
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

        $userCountsByRole = User::select('role_id', \DB::raw('count(*) as total'))
            ->groupBy('role_id')
            ->pluck('total', 'role_id')
            ->toArray();

        $roles = [
            1 => 'Applicant',
            2 => 'Admin',
            3 => 'Evaluator',
            4 => 'Interviewer',
            5 => 'Medical',
            6 => 'Registrar',
        ];

        $totalUsers = User::count();

        // Audit log for viewing sensitive user data
        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'model_type' => 'User',
                'model_id' => null,
                'action' => 'viewed_user_listing',
                'old_values' => null,
                'new_values' => [
                    'total_users_viewed' => $users->count(),
                    'includes_applicant_profiles' => true,
                    'timestamp' => now()->toIso8601String(),
                ],
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            logger()->error('Failed to create audit log for user listing view', [
                'actor_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
        }

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

        $roles = [
            1 => 'Applicant',
            2 => 'Admin',
            3 => 'Evaluator',
            4 => 'Interviewer',
            5 => 'Medical',
            6 => 'Registrar',
        ];

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
            if ($program) {
                $programsToSync[$program->id] = ['role_id' => $request->role_id];
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
