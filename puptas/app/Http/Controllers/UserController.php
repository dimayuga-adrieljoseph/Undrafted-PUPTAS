<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use App\Models\Program;

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

    return view('legacy.add_user', compact('userCountsByRole', 'roles', 'totalUsers', 'programs'));
}

    /**
     * Handle an incoming user creation request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $request->validate([
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'middlename' => ['nullable', 'string', 'max:255'],
            'role_id' => ['required', Rule::in([1, 2, 3, 4, 5, 6])],
            'program' => ['nullable', 'string', Rule::requiredIf($request->role_id == 1)],

            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'contactnumber' => ['required', 'string', 'max:15'],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.]).+$/',
            ],
        ]);

        $user = User::create([
            'firstname' => $request->firstname,
            'lastname' => $request->lastname,
            'middlename' => $request->middlename,
            'program' => $request->role_id == 1 ? $request->program : null,
            
            'email' => $request->email,
            'contactnumber' => $request->contactnumber,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
        ]);

        event(new Registered($user));

        return redirect()->route('legacy.add_user')->with('status', 'User added successfully!');
    }

    /**
     * Display a listing of all users.
     */
    public function index()
    {
        $users = User::with('role')->orderBy('created_at', 'desc')->get();
        
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

        return view('legacy.manage_users', compact('users', 'userCountsByRole', 'roles', 'totalUsers'));
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

        return view('legacy.edit_user_management', compact('user', 'programs', 'roles'));
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'middlename' => ['nullable', 'string', 'max:255'],
            'role_id' => ['required', Rule::in([1, 2, 3, 4, 5, 6])],
            'program' => ['nullable', 'string'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'contactnumber' => ['required', 'string', 'max:15'],
            'password' => [
                'nullable',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.]).+$/',
            ],
        ]);

        $user->firstname = $request->firstname;
        $user->lastname = $request->lastname;
        $user->middlename = $request->middlename;
        $user->program = $request->role_id == 1 ? $request->program : null;
        $user->email = $request->email;
        $user->contactnumber = $request->contactnumber;
        $user->role_id = $request->role_id;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return redirect()->route('users.index')->with('status', 'User updated successfully!');
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        // Prevent deleting your own account
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')->with('error', 'You cannot delete your own account!');
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', 'User deleted successfully!');
    }
}
