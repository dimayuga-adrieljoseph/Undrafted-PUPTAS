<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Helpers\FileMapper;
use App\Http\Traits\ManagesApplicationFiles;
use App\Services\ApplicationService;
use App\Services\ApplicationProcessService;
use App\Services\AuditLogService;
use App\Services\DashboardService;
use App\Services\UserService;

class RecordStaffDashboardController extends Controller
{
    use ManagesApplicationFiles;

    protected ApplicationService $applicationService;
    protected ApplicationProcessService $processService;
    protected DashboardService $dashboardService;
    protected UserService $userService;
    protected AuditLogService $auditLogService;

    public function __construct(
        ApplicationService $applicationService,
        ApplicationProcessService $processService,
        DashboardService $dashboardService,
        UserService $userService,
        AuditLogService $auditLogService
    ) {
        $this->applicationService = $applicationService;
        $this->processService = $processService;
        $this->dashboardService = $dashboardService;
        $this->userService = $userService;
        $this->auditLogService = $auditLogService;
    }

    public function index()
    {
        $user = Auth::user();

        if (!$this->dashboardService->verifyRoleAccess($user, 6)) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $dashboardData = $this->dashboardService->getRecordsDashboardData();

        return Inertia::render('Dashboard/Records', [
            'user' => $user,
            'allUsers' => $dashboardData['allUsers'],
            'programs' => $dashboardData['programs'],
            'summary' => $dashboardData['summary'],
        ]);
    }

    protected function getCurrentStage(): string
    {
        return 'records';
    }

    protected function getRoleId(): int
    {
        return 6;
    }

    public function getUsers()
    {
        // Ensure user has record staff role
        $this->ensureRole($this->getRoleId());

        // Return applicants who completed medical stage or are officially enrolled
        return response()->json($this->userService->getApplicantsForRecordStaff());
    }

    public function getStats()
    {
        $this->ensureRole($this->getRoleId());

        return response()->json([
            'summary'  => $this->applicationService->getApplicationSummary(),
            'programs' => \App\Models\Program::withCount('applications')->get(),
        ]);
    }

    protected function checkPrerequisiteStage($application)
    {
        // Check if medical stage is completed
        $this->applicationService->ensureStageCompleted(
            $application,
            'medical',
            "Cannot proceed - prerequisite verification not completed."
        );
    }

    /**
     * Override getUserFiles to allow record staff to access applications
     * that have completed medical stage or are officially enrolled
     */
    public function getUserFiles($id)
    {
        $user = User::with([
            'currentApplication.program',
            'currentApplication.processes.performedBy:id,firstname,lastname',
            'files',
            'grades'
        ])->findOrFail($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $application = $user->currentApplication;

        if (!$application) {
            return response()->json(['message' => 'Application not found'], 404);
        }

        // Check if application has completed medical stage or is officially enrolled
        $hasMedicalCompleted = $application->processes()
            ->where('stage', 'medical')
            ->where('status', 'completed')
            ->exists();

        $isOfficiallyEnrolled = $application->enrollment_status === 'officially_enrolled';

        if (!$hasMedicalCompleted && !$isOfficiallyEnrolled) {
            return response()->json([
                'message' => 'Unauthorized access. Application has not completed medical stage.'
            ], 403);
        }

        $files = $user->files->keyBy('type');

        // Transform the response to map currentApplication to application for frontend compatibility
        $userData = [
            'id' => $user->id,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'contactnumber' => $user->contactnumber,
            'street_address' => $user->street_address,
            'barangay' => $user->barangay,
            'city' => $user->city,
            'province' => $user->province,
            'postal_code' => $user->postal_code,
            'birthday' => $user->birthday,
            'sex' => $user->sex,
            'created_at' => $user->created_at,
            'files' => $user->files,
            'grades' => $user->grades,
            // Map currentApplication to application for frontend compatibility
            'application' => [
                'id' => $application->id,
                'status' => $application->status,
                'enrollment_status' => $application->enrollment_status,
                'created_at' => $application->created_at,
                'program' => $application->program,
                'processes' => $application->processes,
            ],
        ];

        return response()->json([
            'user' => $userData,
            'uploadedFiles' => FileMapper::formatFilesUrls($files),
        ]);
    }

    // returnFiles() method provided by ManagesApplicationFiles trait

    // returnApplication() method provided by ManagesApplicationFiles trait

    public function tag($userId)
    {
        $this->ensureRole(6);

        $application = $this->applicationService->getApplicationByUserId($userId);

        // Check if medical stage is completed
        $this->applicationService->ensureStageCompleted(
            $application,
            'medical',
            "Cannot tag as enrolled - medical stage not completed."
        );

        try {
            // Update the application enrollment status
            Application::where('id', $application->id)
                ->update([
                    'status' => 'accepted',
                    'enrollment_status' => 'officially_enrolled',
                ]);

            // Update or create the records process entry
            $recordsProcess = $application->processes()
                ->where('stage', 'records')
                ->latest()
                ->first();

            if ($recordsProcess) {
                $recordsProcess->update([
                    'status' => 'completed',
                    'action' => 'transferred',
                    'decision_reason' => 'officially_enrolled',
                    'performed_by' => auth()->id(),
                ]);
            } else {
                // Create new records process if it doesn't exist
                ApplicationProcess::create([
                    'application_id' => $application->id,
                    'stage' => 'records',
                    'status' => 'completed',
                    'action' => 'transferred',
                    'decision_reason' => 'officially_enrolled',
                    'performed_by' => auth()->id(),
                ]);
            }

            $this->auditLogService->logActivity('UPDATE', 'Applications', "Registrar tagged applicant ID {$userId} as officially enrolled.", null, 'ADMISSION_DATA');

            return response()->json(['message' => 'Tagged as officially enrolled.']);
        } catch (\Throwable $e) {
            \Log::error("❌ Tag failed: " . $e->getMessage());
            return response()->json(['message' => 'An error occurred while tagging the enrollment.'], 400);
        }
    }

    public function untag($userId)
    {
        $this->ensureRole(6);

        $application = $this->applicationService->getApplicationByUserId($userId);

        // Check if application is accepted
        if ($application->status !== 'accepted') {
            abort(409, "Cannot untag - application must be accepted.");
        }

        try {
            // Update the application enrollment status
            Application::where('id', $application->id)
                ->update([
                    'status' => 'waitlist',
                    'enrollment_status' => 'temporary',
                ]);

            // Update the records process entry
            $recordsProcess = $application->processes()
                ->where('stage', 'records')
                ->latest()
                ->first();

            if ($recordsProcess) {
                $recordsProcess->update([
                    'status' => 'in_progress',
                    'action' => 'returned',
                    'decision_reason' => 'temporary',
                    'performed_by' => auth()->id(),
                ]);
            }

            $this->auditLogService->logActivity('UPDATE', 'Applications', "Registrar reverted enrollment for applicant ID {$userId} to temporary status.", null, 'ADMISSION_DATA');

            return response()->json(['message' => 'Reverted to temporary enrollment.']);
        } catch (\Throwable $e) {
            \Log::error("❌ Untag failed: " . $e->getMessage());
            return response()->json(['message' => 'An error occurred while reverting the enrollment.'], 400);
        }
    }

    public function getPrograms()
    {
        $this->ensureRole(2, 6, 7);

        return response()->json([
            'programs' => \App\Models\Program::select('id', 'code', 'name', 'slots')->orderBy('code')->get(),
        ]);
    }

    public function changeCourse(Request $request, $userId)
    {
        $this->ensureRole(2, 6, 7);

        // Validate the incoming program_id strictly
        $validated = $request->validate([
            'program_id' => ['required', 'integer', 'exists:programs,id'],
        ]);

        $newProgramId = (int) $validated['program_id'];

        $application = $this->applicationService->getApplicationByUserId((int) $userId);

        // Only allow course change for officially enrolled applicants
        if ($application->enrollment_status !== 'officially_enrolled') {
            abort(409, 'Course can only be changed for officially enrolled applicants.');
        }

        // No change needed if the program is already the same
        if ((int) $application->program_id === $newProgramId) {
            abort(422, 'The selected program is the same as the current program.');
        }

        try {
            DB::transaction(function () use ($application, $newProgramId, $userId) {
                $oldProgramId = $application->program_id;
                
                $newProgram = \App\Models\Program::findOrFail($newProgramId);
                if ($newProgram->slots <= 0) {
                    abort(409, 'The selected program has no available slots.');
                }

                $oldProgram = \App\Models\Program::find($oldProgramId);

                // Update program_id while preserving enrollment status
                Application::where('id', $application->id)
                    ->update([
                        'program_id' => $newProgramId,
                    ]);

                // Decrement the new program slots
                $newProgram->slots -= 1;
                $newProgram->save();

                // Increment the old program slots
                if ($oldProgram) {
                    $oldProgram->slots += 1;
                    $oldProgram->save();
                }

                // Create an immutable audit process row for this change
                ApplicationProcess::create([
                    'application_id'  => $application->id,
                    'stage'           => 'records',
                    'status'          => 'completed',
                    'action'          => 'course_changed',
                    'decision_reason' => 'program_change',
                    'reviewer_notes'  => "Program changed from ID {$oldProgramId} to ID {$newProgramId} for applicant ID {$userId}.",
                    'performed_by'    => auth()->id(),
                    'ip_address'      => request()->ip(),
                ]);

                $this->auditLogService->logActivity(
                    'UPDATE',
                    'Applications',
                    "Registrar changed course for applicant ID {$userId} from program ID {$oldProgramId} to program ID {$newProgramId}.",
                    null,
                    'ADMISSION_DATA'
                );
            });

            // Reload the fresh program name to confirm in response
            $application->refresh();
            $program = $application->program;

            return response()->json([
                'message' => 'Course updated successfully.',
                'program' => $program ? [
                    'id'   => $program->id,
                    'code' => $program->code,
                    'name' => $program->name,
                ] : null,
            ]);
        } catch (\Throwable $e) {
            \Log::error('❌ Course change failed: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred while changing the course.'], 500);
        }
    }

    private function ensureRole(int ...$roleIds): void
    {
        if (!Auth::user() || !in_array(Auth::user()->role_id, $roleIds)) {
            abort(403, 'Unauthorized access.');
        }
    }
}
