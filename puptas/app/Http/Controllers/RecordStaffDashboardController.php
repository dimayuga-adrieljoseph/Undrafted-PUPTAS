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
use App\Services\DashboardService;
use App\Services\UserService;

class RecordStaffDashboardController extends Controller
{
    use ManagesApplicationFiles;

    protected ApplicationService $applicationService;
    protected ApplicationProcessService $processService;
    protected DashboardService $dashboardService;
    protected UserService $userService;

    public function __construct(
        ApplicationService $applicationService,
        ApplicationProcessService $processService,
        DashboardService $dashboardService,
        UserService $userService
    ) {
        $this->applicationService = $applicationService;
        $this->processService = $processService;
        $this->dashboardService = $dashboardService;
        $this->userService = $userService;
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
            'address' => $user->address,
            'birthday' => $user->birthday,
            'sex' => $user->sex,
            'created_at' => $user->created_at,
            'files' => $user->files,
            'grades' => $user->grades,
            // Map currentApplication to application for frontend compatibility
            'application' => [
                'id' => $application->id,
                'status' => $application->status,
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

            return response()->json(['message' => 'Reverted to temporary enrollment.']);
        } catch (\Throwable $e) {
            \Log::error("❌ Untag failed: " . $e->getMessage());
            return response()->json(['message' => 'An error occurred while reverting the enrollment.'], 400);
        }
    }

    private function ensureRole(int $roleId): void
    {
        if (!Auth::user() || Auth::user()->role_id !== $roleId) {
            abort(403, 'Unauthorized access.');
        }
    }
}
