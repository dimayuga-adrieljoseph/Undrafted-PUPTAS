<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Application;
use App\Models\UserFile;
use App\Models\ApplicationProcess;
use App\Helpers\FileMapper;
use App\Http\Traits\ManagesApplicationFiles;
use App\Services\ApplicationService;
use App\Services\ApplicationProcessService;
use App\Services\DashboardService;
use App\Services\UserService;

class EvaluatorDashboardController extends Controller
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

        if (!$this->dashboardService->verifyRoleAccess($user, 3)) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $dashboardData = $this->dashboardService->getCommonDashboardData();

        return Inertia::render('Dashboard/Evaluator', [
            'user' => $user,
            'allUsers' => $dashboardData['allUsers'],
            'summary' => $dashboardData['summary'],
        ]);
    }

    protected function getCurrentStage(): string
    {
        return 'evaluator';
    }

    protected function getRoleId(): int
    {
        return 3;
    }

    // returnFiles() method provided by ManagesApplicationFiles trait

    // returnApplication() method provided by ManagesApplicationFiles trait

    public function getUsers()
    {
        return response()->json($this->userService->getApplicantsWithApplications());
    }

    public function passApplication(Request $request, $userId)
    {
        $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        $this->ensureRole(3);

        $application = $this->applicationService->getApplicationByUserId($userId);

        DB::transaction(function () use ($application, $userId, $request) {
            // Update existing evaluator process (can be in_progress or returned status)
            $evaluatorProcess = ApplicationProcess::where('application_id', $application->id)
                ->where('stage', 'evaluator')
                ->whereIn('status', ['in_progress', 'returned'])
                ->first();

            if (!$evaluatorProcess) {
                throw new \Exception('This action has already been completed or is not available.');
            }

            $evaluatorProcess->update([
                'status' => 'completed',
                'action' => 'passed',
                'reviewer_notes' => $request->note,
                'performed_by' => auth()->id(),
            ]);

            // Update file statuses from 'returned' to 'approved' when passing the application
            $updatedCount = UserFile::where('user_id', $userId)
                ->where('status', 'returned')
                ->update(['status' => 'approved', 'comment' => null]);

            \Log::info("Updated {$updatedCount} files from 'returned' to 'approved' for user {$userId}");

            // Update application status back to submitted
            $statusUpdated = Application::where('id', $application->id)
                ->update(['status' => 'submitted']);

            \Log::info("Updated application status to 'submitted' for application {$application->id}, result: {$statusUpdated}");

            // Create next stage process
            ApplicationProcess::create([
                'application_id' => $application->id,
                'stage' => 'interviewer',
                'status' => 'in_progress',
                'performed_by' => null,
            ]);
        });

        return response()->json([
            'message' => 'Application successfully passed to the next step.',
        ]);
    }

    private function ensureRole(int $roleId): void
    {
        if (!Auth::user() || Auth::user()->role_id !== $roleId) {
            abort(403, 'Unauthorized access.');
        }
    }
}
