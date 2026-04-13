<?php

namespace App\Http\Controllers\Admin\Assign;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Program;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use Inertia\Inertia;

class AssignController extends Controller
{
    public function __construct(private AuditLogService $auditLogService) {}    

    public function createUserForm()
    {
        $programs = Program::all();

        $assignedUsers = User::whereIn('role_id', [3, 4])
            ->with('programs')->get();

        return Inertia::render('UserManagement/Assign', [
            'programs' => $programs,
            'assignedUsers' => $assignedUsers,
        ]);
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'salutation' => 'required|string|max:5',
            'lastname' => 'required|string|max:255',
            'firstname' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:3,4',
            'programs' => 'required|array',
        ]);

        DB::beginTransaction();

        try {
            $userId = (string) Str::uuid();

            $user = User::create([
                'idp_user_id' => $userId,
                'salutation' => $request->salutation,
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'email' => $request->email,
                'role_id' => $request->role,
                'contactnumber' => '00000000000',
                'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(12)),
            ]);

            // Assign the user to the selected programs
            $syncData = [];
            foreach ($request->programs as $programId) {
                $syncData[$programId] = ['role_id' => $request->role];
            }
            $user->programs()->sync($syncData);

            DB::commit();

            $this->auditLogService->logActivity(
                'CREATE',
                'Users',
                "Created staff {$user->firstname} {$user->lastname} ({$user->email}) with role ID {$user->role_id}.",
                null,
                'USER_MANAGEMENT'
            );

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
        $user = User::where('idp_user_id', $id)->orWhere('id', $id)->firstOrFail();
        $programs = Program::all();

        $assignedPrograms = $user->programs->pluck('id')->toArray();    

        return Inertia::render('UserManagement/EditAssignedUser', [
            'user' => $user,
            'programs' => $programs,
            'assignedPrograms' => $assignedPrograms,
        ]);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::where('idp_user_id', $id)->orWhere('id', $id)->firstOrFail();

        $request->validate([
            'salutation' => 'required|string|max:5',
            'lastname' => 'required|string|max:255',
            'firstname' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:3,4',
            'programs' => 'required|array',
        ]);

        $user->update([
            'salutation' => $request->salutation,
            'firstname' => $request->firstname,
            'lastname' => $request->lastname,
            'email' => $request->email,
            'role_id' => $request->role,
        ]);

        // Explicitly format syncData matching standard belongsToMany expectations
        $syncData = [];
        foreach ($request->programs as $programId) {
            $syncData[$programId] = ['role_id' => $request->role];
        }

        $user->programs()->sync($syncData);

        $this->auditLogService->logActivity(
            'UPDATE',
            'Users',
            "Updated staff {$user->firstname} {$user->lastname} ({$user->email}) (ID: {$user->id}).",
            null,
            'USER_MANAGEMENT'
        );

        return redirect()->route('admin.users.create')
            ->with('success', 'User updated successfully.');
    }

    public function deleteUser($id)
    {
        $user = User::where('idp_user_id', $id)->orWhere('id', $id)->firstOrFail();

        $user->programs()->detach();

        $this->auditLogService->logActivity(
            'DELETE',
            'Users',
            "Deleted staff {$user->firstname} {$user->lastname} ({$user->email}) (ID: {$user->id}).",
            null,
            'USER_MANAGEMENT'
        );

        $user->delete();

        return redirect()->route('admin.users.create')
            ->with('success');
    }
}

