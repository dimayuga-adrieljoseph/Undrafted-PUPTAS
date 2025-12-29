<?php

namespace App\Http\Controllers\Admin\Assign;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Program;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Mail\UserCreated;
use Illuminate\Support\Facades\DB;
use Exception;

class AssignController extends Controller
{
    public function createUserForm()
    {
        $programs = Program::all();

        $assignedUsers = User::whereIn('role_id', [3, 4])
            ->with('programs')->get();

        return view(
            'legacy.assignment',
            compact('programs', 'assignedUsers')
        );
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'salutation' => 'required|string|max:5',
            'lastname' => 'required|string|max:255',
            'firstname' => 'required|string|max:255',
            'contactnumber' => ['required', 'string', 'max:15'],
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:3,4',
            'programs' => 'required|array',
        ]);

        DB::beginTransaction();

        try {
            // Random password
            $password = Str::random(10);

            $user = User::create([
                'salutation' => $request->salutation,
                'lastname' => $request->lastname,
                'firstname' => $request->firstname,
                'email' => $request->email,
                'contactnumber' => $request->contactnumber,
                'password' => Hash::make($password),
                'role_id' => $request->role,
            ]);

            // Assign the user to the selected programs
            foreach ($request->programs as $programId) {
                DB::table('program_user')->insert([
                    'user_id' => $user->id,
                    'program_id' => $programId,
                    'role_id' => $request->role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            // Try to send email, but don't fail the entire operation if it fails
            try {
                Mail::to($user->email)->send(new UserCreated($user, $password));
            } catch (Exception $emailException) {
                Log::warning('Failed to send welcome email: ' . $emailException->getMessage());
            }

            return redirect()->route('admin.users.create')->with('success', 'User created successfully.');
        } catch (Exception $e) {
            DB::rollBack();

            Log::error($e->getMessage());

            return redirect()->back()->withInput()->with(
                'error',
                'Failed to create user. Please try again.'
            );
        }
    }

    public function editUser($id)
    {

        $user = User::findOrFail($id);
        $programs = Program::all();

        $assignedPrograms = $user->programs->pluck('program_id')->toArray();

        return view(
            'legacy.edit_user',
            compact('user', 'programs', 'assignedPrograms')
        );
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'salutation' => 'required|string|max:5',
            'lastname' => 'required|string|max:255',
            'firstname' => 'required|string|max:255',
            'contactnumber' => 'required|string|max:15',
            'email' => 'required|email|unique:users,email,' . $user->id . ',id',
            'role' => 'required|in:3,4',
            'programs' => 'required|array',
        ]);

        $user->update([
            'salutation' => $request->salutation,
            'lastname' => $request->lastname,
            'firstname' => $request->firstname,
            'contactnumber' => $request->contactnumber,
            'email' => $request->email,
            'role_id' => $request->role,
        ]);

        $syncData = [];
        foreach ($request->programs as $programId) {
            $syncData[$programId] = ['role_id' => $request->role];
        }

        $user->programs()->sync($syncData);

        return redirect()->route('admin.users.create')
            ->with('success', 'User updated successfully.');
    }

    public function deleteUser($id)
    {

        $user = User::findOrFail($id);

        $user->programs()->detach();

        $user->delete();

        return redirect()->route('admin.users.create')
            ->with('success');
    }
}
