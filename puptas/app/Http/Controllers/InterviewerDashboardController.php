<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\ApplicationProcess;
use App\Models\Program;
use App\Models\Grade;
use Illuminate\Support\Facades\DB;
use App\Helpers\FileMapper;
use App\Http\Traits\ManagesApplicationFiles;
use App\Services\ApplicationService;
use App\Services\ApplicationProcessService;
use App\Services\DashboardService;
use App\Services\UserService;

class InterviewerDashboardController extends Controller
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

        if (!$this->dashboardService->verifyRoleAccess($user, 4)) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $dashboardData = $this->dashboardService->getCommonDashboardData();

        return Inertia::render('Dashboard/Interviewer', [
            'user' => $user,
            'allUsers' => $dashboardData['allUsers'],
            'summary' => $dashboardData['summary'],
        ]);
    }

    protected function getCurrentStage(): string
    {
        return 'interviewer';
    }

    protected function getRoleId(): int
    {
        return 4;
    }

    protected function checkPrerequisiteStage($application)
    {
        // Check if evaluator stage is completed
        $this->applicationService->ensureStageCompleted(
            $application, 
            'evaluator', 
            "Cannot proceed - prerequisite verification not completed."
        );
    }

    // getUserFiles() method provided by ManagesApplicationFiles trait

    public function getUsers()
    {
        return response()->json($this->userService->getApplicantsWithApplications());
    }

    public function accept($userId)
    {
        $this->ensureRole(4);

        $application = $this->applicationService->getApplicationByUserId($userId);

        // Check if evaluator stage is completed
        $this->applicationService->ensureStageCompleted(
            $application, 
            'evaluator', 
            "Cannot accept - evaluator stage not completed."
        );

        // Block repeat accepts or already-finalized applications
        if (!in_array($application->status, ['submitted', 'returned', 'transferred'], true)) {
            return response()->json([
                'message' => 'Application is no longer available for interviewer approval.',
            ], 409);
        }

        // Ensure there is an interviewer in-progress record to close
        $interviewerInProgress = $application->processes()
            ->where('stage', 'interviewer')
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        if (!$interviewerInProgress) {
            return response()->json([
                'message' => 'This action has already been completed or is not available.',
            ], 409);
        }

        $grades = Grade::where('user_id', $userId)->first();

        if (!$grades) {
            return response()->json(['message' => 'User has no grades recorded.'], 400);
        }

        try {
            DB::transaction(function () use ($application, $grades, $userId, $interviewerInProgress) {
                $program = Program::lockForUpdate()->findOrFail($application->program_id);

                if ($program->slots <= 0) {
                    \Log::warning("❌ No slots left in program {$program->id}");
                    abort(400, 'No available slots in the selected program.');
                }

                if (
                    $grades->mathematics < $program->math ||
                    $grades->science < $program->science ||
                    $grades->english < $program->english
                ) {
                    \Log::warning("📉 User {$userId} does not meet grade requirements for program {$program->id}");
                    abort(400, 'User does not meet the grade requirements for this program.');
                }

                $application->status = 'accepted';
                $application->save();

                $program->slots -= 1;
                $program->save();

                // Close current interviewer in-progress process
                $interviewerInProgress->update([
                    'status' => 'completed',
                    'action' => 'passed',
                    'reviewer_notes' => 'Accepted by interviewer',
                    'performed_by' => auth()->id(),
                ]);

                // Create next stage (medical)
                ApplicationProcess::create([
                    'application_id' => $application->id,
                    'stage' => 'medical',
                    'status' => 'in_progress',
                    'performed_by' => null,
                ]);
            });

            return response()->json(['message' => 'Application accepted.']);
        } catch (\Throwable $e) {
            \Log::error("❌ Accept failed: " . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }


    public function getPrograms()
    {
        $programs = Program::where('slots', '>', 0)->get();

        return response()->json([
            'programs' => $programs
        ]);
    }


    public function transferToProgram(Request $request, $userId)
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
        ]);

        $this->ensureRole(4);

        \Log::info("🚀 Transfer requested for user {$userId} to program {$validated['program_id']}");

        $application = $this->applicationService->getApplicationByUserId($userId);

        // Check if evaluator stage is completed
        $this->applicationService->ensureStageCompleted(
            $application, 
            'evaluator', 
            "Cannot transfer - evaluator stage not completed."
        );

        $grades = Grade::where('user_id', $userId)->first();

        if (!$grades) {
            \Log::warning("⚠️ No grades found for user {$userId}");
            return response()->json(['message' => 'User has no grades recorded.'], 400);
        }

        DB::transaction(function () use ($application, $validated, $grades, $userId) {
            $program = Program::lockForUpdate()->findOrFail($validated['program_id']);

            \Log::info("📦 Fetched program: {$program->id}, current slots: {$program->slots}");

            if ($program->slots <= 0) {
                \Log::warning("❌ No slots left in program {$program->id}");
                throw new \Exception("No available slots in the selected program.");
            }

            if (
                $grades->mathematics < $program->math ||
                $grades->science < $program->science ||
                $grades->english < $program->english
            ) {
                \Log::warning("📉 User {$userId} does not meet grade requirements for program {$program->id}");
                throw new \Exception("User does not meet the grade requirements for this program.");
            }

            $application->program_id = $program->id;
            $application->status = 'transferred';
            $application->save();
            \Log::info("✅ Application updated with program_id {$program->id}");

            $program->slots -= 1;
            $program->save();
            \Log::info("📉 Program slots updated. New slots: {$program->slots}");

            // Close current interviewer in-progress process
            $inProgress = $application->processes()
                ->where('stage', 'interviewer')
                ->where('status', 'in_progress')
                ->latest()
                ->first();

            if (!$inProgress) {
                return response()->json([
                    'message' => 'This action has already been completed or is not available.',
                ], 409);
            }

            $inProgress->update([
                'status' => 'completed',
                'action' => 'transferred',
                'reviewer_notes' => 'Transferred to program ID ' . $program->id,
                'performed_by' => auth()->id(),
            ]);

            // Create next stage (medical)
            ApplicationProcess::create([
                'application_id' => $application->id,
                'stage' => 'medical',
                'status' => 'in_progress',
                'performed_by' => null,
            ]);

            \Log::info("📝 ApplicationProcess logged for application {$application->id}");
        });

        \Log::info("🎉 Transfer completed for user {$userId}");

        return response()->json(['message' => 'Transferred successfully.']);
    }

    private function ensureRole(int $roleId): void
    {
        if (!Auth::user() || Auth::user()->role_id !== $roleId) {
            abort(403, 'Unauthorized access.');
        }
    }
}
