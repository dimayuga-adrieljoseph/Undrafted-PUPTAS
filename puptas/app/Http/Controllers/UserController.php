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
    public function create(Request $request)
    {
        // Only SuperAdmin (role_id 7) can access the Add User form
        if ($request->user()->role_id !== 7) {
            return redirect()->route('users.index')->with('error', 'You do not have permission to create users.');
        }

        $userCountsByRole = $this->userService->getUserCountsByRole();
        $roles = $this->userService->getRoleDefinitions();
        $totalUsers = $this->userService->getTotalUserCount();
        $programs = Program::all();

        return Inertia::render('UserManagement/AddUser', [
            'userCountsByRole' => $userCountsByRole,
            'roles' => $roles,
            'totalUsers' => $totalUsers,
            'programs' => $programs,
            'currentUserRoleId' => $request->user()->role_id,
        ]);
    }

    /**
     * Handle an incoming user creation request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        // Only SuperAdmin (role_id 7) can create users
        if ($request->user()->role_id !== 7) {
            return redirect()->route('users.index')->with('error', 'You do not have permission to create users.');
        }

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
    public function index(Request $request)
    {
        // Only load the first page here (15 records).
        // Subsequent pages and search results are fetched via GET /users/search (JSON).
        $page1 = $this->userService->searchUsers(null, 1, 15);
        $userCountsByRole = $this->userService->getUserCountsByRole();
        $roles = $this->userService->getRoleDefinitions();
        $totalUsers = $this->userService->getTotalUserCount();
        $currentUserRoleId = $request->user()->role_id;

        return Inertia::render('UserManagement/ManageUsers', [
            'users'           => $page1['data'],
            'pagination'      => [
                'total'        => $page1['total'],
                'per_page'     => $page1['per_page'],
                'current_page' => $page1['current_page'],
                'last_page'    => $page1['last_page'],
            ],
            'userCountsByRole' => $userCountsByRole,
            'roles'            => $roles,
            'totalUsers'       => $totalUsers,
            'currentUserRoleId' => $currentUserRoleId,
        ]);
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(Request $request, $id)
    {
        // SuperAdmin (7) can edit. Admin (2) can view read-only.
        $currentRoleId = $request->user()->role_id;
        if (!in_array($currentRoleId, [2, 7])) {
            return redirect()->route('users.index')->with('error', 'You do not have permission to view this user.');
        }

        $userModel = \App\Models\User::where('idp_user_id', $id)->orWhere('id', $id)->first();
        if (!$userModel) {
            abort(404, 'User not found.');
        }

        if ($userModel->role_id > 1) {
            $userModel->load('programs');
            $user = (object) [
                'id' => $userModel->idp_user_id ?: $userModel->id,
                'firstname' => $userModel->firstname,
                'middlename' => $userModel->middlename,
                'lastname' => $userModel->lastname,
                'extension_name' => $userModel->extension_name,
                'email' => $userModel->email,
                'role_id' => $userModel->role_id,
                'programs' => $userModel->programs,
            ];
        } else {
            $applicant = \App\Models\ApplicantProfile::where('user_id', $id)->orWhere('user_id', $userModel->id)->firstOrFail();
            $applicant->load(
                'firstChoiceProgram',
                'secondChoiceProgram',
                'thirdChoiceProgram',
                'grades',
                'graduateTypes',
                'documentStatuses',
                'testPasser'
            );

            // Load current application with program details
            $currentApp = \App\Models\Application::where('user_id', $applicant->user_id)
                ->whereNull('deleted_at')
                ->with(['program', 'secondChoice', 'thirdChoice', 'processes'])
                ->orderByDesc('id')
                ->first();

            // Load uploaded files
            $files = \App\Models\UserFile::where('user_id', $applicant->user_id)
                ->whereNull('deleted_at')
                ->get()
                ->map(fn($f) => [
                    'id'            => $f->id,
                    'type'          => $f->type,
                    'original_name' => $f->original_name,
                    'status'        => $f->status,
                    'comment'       => $f->comment,
                    'created_at'    => $f->created_at,
                ]);

            $user = (object) [
                'id'                  => $applicant->user_id,
                'firstname'           => $applicant->firstname,
                'middlename'          => $applicant->middlename,
                'lastname'            => $applicant->lastname,
                'extension_name'      => $applicant->extension_name,
                'email'               => $applicant->email,
                'sex'                 => $applicant->sex,
                'salutation'          => $applicant->salutation,
                'student_number'      => $applicant->student_number,
                'school'              => $applicant->school,
                'strand'              => $applicant->strand,
                'track'               => $applicant->track,
                'date_graduated'      => $applicant->date_graduated,
                'role_id'             => 1,
                'applicant_profile'   => $applicant,
                'program'             => $applicant->firstChoiceProgram,
                'second_program'      => $applicant->secondChoiceProgram,
                'third_program'       => $applicant->thirdChoiceProgram,
                'grades'              => $applicant->grades,
                'graduate_types'      => $applicant->graduateTypes,
                'document_statuses'   => $applicant->documentStatuses,
                'test_passer'         => $applicant->testPasser,
                'current_application' => $currentApp,
                'files'               => $files,
            ];
        }

        $programs = Program::all();
        $roles = $this->userService->getRoleDefinitions();

        $isSuperAdmin = $currentRoleId === 7;

        return Inertia::render('UserManagement/EditUser', [
            'user'     => $user,
            'programs' => $programs,
            'roles'    => $roles,
            'currentUserRoleId' => $currentRoleId,
            'readOnly' => !$isSuperAdmin,
        ]);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, $id)
    {
        // Only SuperAdmin (role_id 7) can update users
        if ($request->user()->role_id !== 7) {
            return redirect()->route('users.index')->with('error', 'You do not have permission to update users.');
        }

        $userModel = \App\Models\User::where('idp_user_id', $id)->orWhere('id', $id)->first();
        if (!$userModel) {
            abort(404, 'User not found.');
        }

        // Resolve linked TestPasser by IDP UUID first, fall back to numeric id
        // This is necessary because test_passers.user_id may be an IDP UUID (string)
        // rather than the numeric users.id, so ignoring by user_id = $userModel->id can fail.
        $testPasser = \App\Models\TestPasser::where('user_id', $userModel->idp_user_id)
            ->orWhere('user_id', (string) $userModel->id)
            ->first();

        $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'middlename' => 'nullable|string|max:255',
            'extension_name' => 'nullable|string|max:255',
            'email' => [
                'required', 
                'email', 
                'max:255', 
                \Illuminate\Validation\Rule::unique('users')->ignore($userModel->id),
                \Illuminate\Validation\Rule::unique('test_passers', 'email')
                    ->ignore($testPasser?->test_passer_id, 'test_passer_id'),
            ],
            'role_id' => 'required|integer',
            'strand' => 'nullable|string|max:255',
            'school' => 'nullable|string|max:255',
            'date_graduated' => 'nullable|date',
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
                    'role_id' => $roleId,
                ]);

                if ($request->filled('password')) {
                    $user->update(['password' => Hash::make($request->password)]);
                }
            }

            if ($applicantProfile) {
                $profileData = [
                    'firstname' => $request->firstname,
                    'middlename' => $request->middlename,
                    'lastname' => $request->lastname,
                    'extension_name' => $request->extension_name,
                    'email' => $request->email,
                ];
                // Save academic fields (allow clearing via empty string → null)
                $profileData['strand'] = $request->has('strand') ? ($request->strand !== '' ? $request->strand : null) : ($applicantProfile->strand ?? null);
                $profileData['school'] = $request->has('school') ? ($request->school !== '' ? $request->school : null) : ($applicantProfile->school ?? null);
                $profileData['date_graduated'] = $request->has('date_graduated') ? ($request->date_graduated !== '' ? $request->date_graduated : null) : ($applicantProfile->date_graduated ?? null);
                $applicantProfile->update($profileData);
            }

            // Sync updated info to the linked test_passers record
            $testPasser = \App\Models\TestPasser::where('user_id', $id)
                ->orWhere('user_id', optional($user)->id)
                ->first();
            if ($testPasser) {
                $tpData = [
                    'surname'    => $request->lastname,
                    'first_name' => $request->firstname,
                    'middle_name' => $request->middlename,
                    'email'       => $request->email,
                ];
                // Sync academic fields to test passer (allow clearing via empty string → null)
                if ($request->has('strand')) {
                    $tpData['strand'] = $request->strand !== '' ? $request->strand : null;
                }
                if ($request->has('school')) {
                    $tpData['shs_school'] = $request->school !== '' ? $request->school : null;
                }
                if ($request->has('date_graduated')) {
                    $tpData['year_graduated'] = $request->date_graduated !== '' ? substr($request->date_graduated, 0, 4) : null;
                }
                $testPasser->update($tpData);
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

                    // Resolve 2nd choice program
                    $secondProgram = $request->filled('applicant_second_program')
                        ? Program::where('code', $request->applicant_second_program)->first()
                        : null;
                    $applicantProfile->update(['second_choice_program' => $secondProgram?->id]);

                    // Resolve 3rd choice program
                    $thirdProgram = $request->filled('applicant_third_program')
                        ? Program::where('code', $request->applicant_third_program)->first()
                        : null;
                    $applicantProfile->update(['third_choice_program' => $thirdProgram?->id]);

                    // Update existing applications
                    $officiallyEnrolled = $applicantProfile->applications()
                        ->where('enrollment_status', 'officially_enrolled')
                        ->first();

                    if ($officiallyEnrolled) {
                        $officiallyEnrolled->update([
                            'program_id'       => $program->id,
                            'second_choice_id' => $secondProgram?->id,
                            'third_choice_id'  => $thirdProgram?->id,
                        ]);
                    } else {
                        $latestApplication = $applicantProfile->applications()
                            ->orderBy('created_at', 'desc')
                            ->first();

                        if ($latestApplication) {
                            $latestApplication->update([
                                'program_id'       => $program->id,
                                'second_choice_id' => $secondProgram?->id,
                                'third_choice_id'  => $thirdProgram?->id,
                            ]);
                        }
                    }

                    $actionDetails = "Updated Applicant {$userEmail} 1st choice to {$program->code}"
                        . ($secondProgram ? ", 2nd choice to {$secondProgram->code}" : '')
                        . ($thirdProgram  ? ", 3rd choice to {$thirdProgram->code}"  : '');
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

            return redirect()->route('users.index')->with('success', 'User details updated successfully!');
        });
    }

    /**
     * Update grades for an applicant user and recompute category averages.
     */
    public function updateGrades(Request $request, $id)
    {
        // Only SuperAdmin (role_id 7) can update grades
        if ($request->user()->role_id !== 7) {
            return redirect()->route('users.index')->with('error', 'You do not have permission to update grades.');
        }

        $individualFields = [
            'g12_first_sem', 'g12_second_sem',
            'g11_oral_communication', 'g11_21st_century_lit', 'g11_academic_professional',
            'g11_reading_writing', 'g11_general_mathematics', 'g11_statistics_probability',
            'g11_earth_life_science', 'g11_physical_science', 'g11_business_mathematics',
            'g11_pre_calculus', 'g11_basic_calculus', 'g11_earth_science',
            'g11_general_chemistry_1', 'g12_21st_century_lit', 'g12_academic_professional',
            'g12_general_physics_1', 'g12_general_physics_2', 'g12_general_biology_1',
            'g12_general_biology_2', 'g12_general_chemistry_2', 'g12_earth_life_science',
            'g12_physical_science',
        ];

        $rules = [];
        foreach ($individualFields as $field) {
            $rules[$field] = 'nullable|numeric|min:0|max:100';
        }
        $rules['dynamic_subjects']              = 'nullable|array';
        $rules['dynamic_subjects.*.subject']    = 'required|string|max:100';
        $rules['dynamic_subjects.*.grade']      = 'nullable|numeric|min:0|max:100';
        $rules['dynamic_subjects.*.category']   = 'required|string|in:math,english,science';

        $validated = $request->validate($rules);

        $user = \App\Models\User::where('idp_user_id', $id)->orWhere('id', $id)->firstOrFail();

        // Load applicant strand for category-aware average computation
        $profile = \App\Models\ApplicantProfile::where('user_id', $user->id)->first();
        $strand  = strtoupper($profile->strand ?? 'GAS');

        $validationService  = app(\App\Services\GradeValidationService::class);
        $computationService = app(\App\Services\GradeComputationService::class);

        // Normalise dynamic subjects: map 'subject' key → 'name' for GradeComputationService
        $dynamicSubjects = collect($validated['dynamic_subjects'] ?? [])
            ->map(fn($s) => [
                'name'     => $s['subject'] ?? '',
                'grade'    => $s['grade'] ?? null,
                'category' => $s['category'],
                'subject'  => $s['subject'] ?? '',
            ])
            ->values()
            ->toArray();

        // Recompute category averages server-side
        foreach (['math', 'english', 'science'] as $cat) {
            $defaultFields  = $validationService->getSubjectsForStrand($strand, $cat);
            $defaultGrades  = array_map(fn($f) => $validated[$f] ?? null, $defaultFields);
            $catDynamic     = array_values(array_filter($dynamicSubjects, fn($s) => $s['category'] === $cat));
            ${'computed_' . $cat} = $computationService->computeCategoryAverage($defaultGrades, $catDynamic);
        }

        // Build grade record
        $gradeData = ['user_id' => $user->id];
        foreach ($individualFields as $field) {
            $gradeData[$field] = $validated[$field] ?? null;
        }
        $gradeData['mathematics']      = $computed_math;
        $gradeData['english']          = $computed_english;
        $gradeData['science']          = $computed_science;
        $gradeData['dynamic_subjects'] = $dynamicSubjects;

        \App\Models\Grade::updateOrCreate(
            ['user_id' => $user->id],
            $gradeData
        );

        $this->auditLogService->logActivity(
            'UPDATE',
            'Grades',
            "Updated grades for user {$user->email} (math avg: {$computed_math}, english avg: {$computed_english}, science avg: {$computed_science})",
            null,
            'USER_MANAGEMENT'
        );

        return back()->with('status', 'Grades updated successfully!');
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(Request $request, $id)
    {
        // Only SuperAdmin (role_id 7) can delete users
        if ($request->user()->role_id !== 7) {
            return redirect()->route('users.index')->with('error', 'You do not have permission to delete users.');
        }

        // For IDP, users are not deleted locally, but we might want to drop their profiles locally
        $staff = \App\Models\User::where('idp_user_id', $id)->orWhere('id', $id)->first();
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

        return redirect()->route('users.index')->with('success', 'User localized details removed successfully!');
    }
    /**
     * JSON search endpoint for ManageUsers.
     * Called via GET /users/search?q=...&page=N
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $search  = $request->input('q');
        $page    = (int) $request->input('page', 1);
        $perPage = 15;

        $result = $this->userService->searchUsers($search, $page, $perPage);

        return response()->json($result);
    }
}
