<?php

namespace App\Http\Controllers\Admin\Assign;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use App\Models\StaffProfile;
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

        $assignedUsers = StaffProfile::whereIn('role_id', [3, 4])
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
            'email' => 'required|email|unique:staff_profiles,email',
            'role' => 'required|in:3,4',
            'programs' => 'required|array',
        ]);

        DB::beginTransaction();

        try {
            $userId = (string) Str::uuid();

            $user = StaffProfile::create([
                'user_id' => $userId,
                'name' => $request->salutation . ' ' . $request->firstname . ' ' . $request->lastname,
                'email' => $request->email,
                'role_id' => $request->role,
                'role_name' => $request->role == 3 ? 'Evaluator' : 'Interviewer',
            ]);

            // Assign the user to the selected programs
            foreach ($request->programs as $programId) {
                DB::table('program_user')->insert([
                    'user_id' => $user->user_id,
                    'program_id' => $programId,
                    'role_id' => $request->role,
                ]);
            }

            DB::commit();

            $this->auditLogService->logActivity(
                'CREATE',
                'Users',
                "Created staff $user->name ($user->email) with role ID $user->role_id.",
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
        $user = StaffProfile::findOrFail($id);
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
        $user = StaffProfile::findOrFail($id);

        $request->validate([
            'salutation' => 'required|string|max:5',
            'lastname' => 'required|string|max:255',
            'firstname' => 'required|string|max:255',
            'email' => 'required|email|unique:staff_profiles,email,' . $user->user_id . ',user_id',
            'role' => 'required|in:3,4',
            'programs' => 'required|array',
        ]);

        $user->update([
            'name' => $request->salutation . ' ' . $request->firstname . ' ' . $request->lastname,
            'email' => $request->email,
            'role_id' => $request->role,
            'role_name' => $request->role == 3 ? 'Evaluator' : 'Interviewer',
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
            "Updated staff $user->name ($user->email) (ID: $user->user_id).",
            null,
            'USER_MANAGEMENT'
        );

        return redirect()->route('admin.users.create')
            ->with('success', 'User updated successfully.');
    }

    public function deleteUser($id)
    {
        $user = StaffProfile::findOrFail($id);

        $user->programs()->detach();

        $this->auditLogService->logActivity(
            'DELETE',
            'Users',
            "Deleted staff $user->name ($user->email) (ID: $user->user_id).",
            null,
            'USER_MANAGEMENT'
        );

        $user->delete();

        return redirect()->route('admin.users.create')
            ->with('success');
    }
}

