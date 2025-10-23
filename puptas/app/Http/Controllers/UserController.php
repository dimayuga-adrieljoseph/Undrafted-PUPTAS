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
            'extension_name' => ['nullable', 'string', 'max:255'],
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
            'extension_name' => $request->extension_name,
            'program' => $request->role_id == 1 ? $request->program : null,
            
            'email' => $request->email,
            'contactnumber' => $request->contactnumber,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
        ]);

        event(new Registered($user));

        return redirect()->route('legacy.add_user')->with('status', 'User added successfully!');
    }
}
