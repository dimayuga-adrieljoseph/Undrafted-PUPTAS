<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\Application;
use App\Models\UserFile;
use App\Models\ApplicationProcess;
use Illuminate\Support\Facades\DB;
use App\Helpers\FileMapper;
use App\Http\Traits\ManagesApplicationFiles;
use App\Services\ApplicationService;
use App\Services\ApplicationProcessService;
use App\Services\DashboardService;
use App\Services\UserService;

class MedicalDashboardController extends Controller
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

        if (!$this->dashboardService->verifyRoleAccess($user, 5)) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $dashboardData = $this->dashboardService->getCommonDashboardData();

        return Inertia::render('Dashboard/Medical', [
            'user' => $user,
            'allUsers' => $dashboardData['allUsers'],
            'summary' => $dashboardData['summary'],
        ]);
    }

    public function getUsers()
    {
        return response()->json($this->userService->getApplicantsWithApplications());
    }

    protected function getCurrentStage(): string
    {
        return 'medical';
    }

    protected function getRoleId(): int
    {
        return 5;
    }

    protected function checkPrerequisiteStage($application)
    {
        // Check if interviewer stage is completed
        $this->applicationService->ensureStageCompleted(
            $application, 
            'interviewer', 
            "Cannot proceed - prerequisite verification not completed."
        );
    }

    // getUserFiles() method provided by ManagesApplicationFiles trait

    // returnFiles() method provided by ManagesApplicationFiles trait

    // returnApplication() method provided by ManagesApplicationFiles trait

    public function accept($userId)
    {
        $this->ensureRole(5);

        $application = $this->applicationService->getApplicationByUserId($userId);

        // Check if interviewer stage is completed
        $this->applicationService->ensureStageCompleted(
            $application, 
            'interviewer', 
            "Cannot clear medically - interviewer stage not completed."
        );

        try {
            DB::transaction(function () use ($application, $userId) {

                // Close current medical process (can be in_progress or returned)
                $inProgress = $application->processes()
                    ->where('stage', 'medical')
                    ->whereIn('status', ['in_progress', 'returned'])
                    ->latest()
                    ->first();

                if (!$inProgress) {
                    throw new \Exception('This action has already been completed or is not available.');
                }

                $inProgress->update([
                    'status' => 'completed',
                    'action' => 'passed',
                    'reviewer_notes' => 'Medically cleared',
                    'performed_by' => auth()->id(),
                ]);

                // Update file statuses from 'returned' to 'approved' when accepting the application
                $updatedCount = UserFile::where('user_id', $userId)
                    ->where('status', 'returned')
                    ->update(['status' => 'approved', 'comment' => null]);

                \Log::info("Updated {$updatedCount} files from 'returned' to 'approved' for user {$userId}");

                // Update application status back to submitted
                $statusUpdated = Application::where('id', $application->id)
                    ->update(['status' => 'submitted']);

                \Log::info("Updated application status to 'submitted' for application {$application->id}, result: {$statusUpdated}");

                // Create next stage (records)
                ApplicationProcess::create([
                    'application_id' => $application->id,
                    'stage' => 'records',
                    'status' => 'in_progress',
                    'performed_by' => null,
                ]);
            });

            return response()->json(['message' => 'Medical Cleared.']);
        } catch (\Throwable $e) {
            \Log::error("❌ Accept failed: " . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
    private function ensureRole(int $roleId): void
    {
        if (!Auth::user() || Auth::user()->role_id !== $roleId) {
            abort(403, 'Unauthorized access.');
        }
    }
}
