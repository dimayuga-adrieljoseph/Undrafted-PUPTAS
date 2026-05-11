<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\ApplicationProcess;
use App\Models\Program;
use App\Models\Grade;
use Illuminate\Support\Facades\DB;
use App\Helpers\FileMapper;
use App\Http\Traits\ManagesApplicationFiles;
use App\Services\ApplicationService;
use App\Services\ApplicationProcessService;
use App\Services\AuditLogService;
use App\Services\DashboardService;
use App\Services\UserService;

class InterviewerDashboardController extends Controller
{
    use ManagesApplicationFiles, AuthorizesRequests;

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

        if (!$this->dashboardService->verifyRoleAccess($user, 4)) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $dashboardData = $this->dashboardService->getInterviewerDashboardData();

        return Inertia::render('Dashboard/Interviewer', [
            'user' => $user,
            'pendingUsers' => $dashboardData['pendingUsers'],
            'summary' => $dashboardData['summary'],
            'chartData' => $dashboardData['chartData'],
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
        // Ensure user has interviewer role
        $this->ensureRole($this->getRoleId());

        // Resolve this interviewer's assigned program IDs from the pivot table
        $programIds = Auth::user()
            ->programs()
            ->pluck('programs.id')
            ->toArray();

        \Log::info('InterviewerDashboard::getUsers', [
            'user_id'     => Auth::user()->id,
            'program_ids' => $programIds,
        ]);

        // If the interviewer has no assigned programs, return an empty list.
        // Interviewers must be explicitly assigned to programs to see applicants.
        if (empty($programIds)) {
            return response()->json([]);
        }

        $results = $this->userService->getAllApplicantsByStage('interviewer', $programIds);

        \Log::info('InterviewerDashboard::getUsers results', ['count' => count($results)]);

        // Return applicants at interviewer stage, scoped to assigned courses only
        return response()->json($results);
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

                // Keep application status as 'submitted' - it will be 'accepted' only after all stages complete
                // $application->status remains 'submitted' or 'transferred' as it was

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

            $this->auditLogService->logActivity('UPDATE', 'Applications', "Interviewer accepted application for applicant ID {$userId}.", null, 'ADMISSION_DATA');

            return response()->json(['message' => 'Application accepted.']);
        } catch (\Throwable $e) {
            \Log::error("❌ Accept failed: " . $e->getMessage());
            return response()->json(['message' => 'An error occurred while accepting the application.'], 400);
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
        try {
            \Log::info("🚀 Transfer START for user {$userId}");
            
            $validated = $request->validate([
                'program_id' => 'required|exists:programs,id',
            ]);
            
            \Log::info("✅ Validation passed", ['program_id' => $validated['program_id']]);

            $this->ensureRole(4);
            \Log::info("✅ Role check passed");

            $application = $this->applicationService->getApplicationByUserId($userId);
            \Log::info("✅ Application found", [
                'application_id' => $application->id,
                'status' => $application->status,
                'enrollment_status' => $application->enrollment_status,
            ]);

            // Check authorization using ApplicationPolicy
            $this->authorize('changeCourse', $application);
            \Log::info("✅ Authorization passed");

            // Check if evaluator stage is completed
            $this->applicationService->ensureStageCompleted(
                $application,
                'evaluator',
                "Cannot transfer - evaluator stage not completed."
            );
            \Log::info("✅ Evaluator stage check passed");

            $grades = Grade::where('user_id', $userId)->first();

            if (!$grades) {
                \Log::warning("⚠️ No grades found for user {$userId}");
                return response()->json(['message' => 'User has no grades recorded.'], 400);
            }
            
            \Log::info("✅ Grades found");

            DB::transaction(function () use ($application, $validated, $grades, $userId) {
                \Log::info("🔄 Starting transaction");
                
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

                $oldProgramId = $application->program_id;
                $application->program_id = $program->id;
                $application->status = 'transferred';
                $application->save();
                \Log::info("✅ Application updated with program_id {$program->id}");

                // Decrement new program slots
                $program->slots -= 1;
                $program->save();

                // Increment old program slots
                if ($oldProgramId) {
                    $oldProgram = Program::find($oldProgramId);
                    if ($oldProgram) {
                        $oldProgram->slots += 1;
                        $oldProgram->save();
                    }
                }
                \Log::info("📉 Program slots updated. New slots: {$program->slots}");

                // Handle interviewer process - could be in_progress or already completed
                $interviewerProcess = $application->processes()
                    ->where('stage', 'interviewer')
                    ->whereIn('status', ['in_progress', 'completed'])
                    ->latest()
                    ->first();

                \Log::info("🔍 Interviewer process search", [
                    'found' => $interviewerProcess ? 'yes' : 'no',
                    'process_id' => $interviewerProcess?->id,
                ]);

                if ($interviewerProcess) {
                    // Update existing process to reflect the transfer
                    $interviewerProcess->update([
                        'status' => 'completed',
                        'action' => 'transferred',
                        'reviewer_notes' => 'Transferred to program ID ' . $program->id,
                        'performed_by' => auth()->id(),
                    ]);
                    \Log::info("✅ Updated existing interviewer process");
                } else {
                    // No interviewer process exists - create one
                    ApplicationProcess::create([
                        'application_id' => $application->id,
                        'stage' => 'interviewer',
                        'status' => 'completed',
                        'action' => 'transferred',
                        'reviewer_notes' => 'Transferred to program ID ' . $program->id,
                        'performed_by' => auth()->id(),
                    ]);
                    \Log::info("✅ Created new interviewer process");
                }

                // Ensure medical stage exists if not already present
                $medicalProcess = $application->processes()
                    ->where('stage', 'medical')
                    ->latest()
                    ->first();

                \Log::info("🔍 Medical process search", [
                    'found' => $medicalProcess ? 'yes' : 'no',
                    'process_id' => $medicalProcess?->id,
                ]);

                if (!$medicalProcess) {
                    ApplicationProcess::create([
                        'application_id' => $application->id,
                        'stage' => 'medical',
                        'status' => 'in_progress',
                        'performed_by' => null,
                    ]);
                    \Log::info("✅ Created medical process");
                }

                \Log::info("📝 ApplicationProcess logged for application {$application->id}");
            });

            \Log::info("🎉 Transfer completed for user {$userId}");

            $this->auditLogService->logActivity('UPDATE', 'Applications', "Interviewer transferred applicant ID {$userId} to program ID {$validated['program_id']}.", null, 'ADMISSION_DATA');

            return response()->json(['message' => 'Transferred successfully.']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            \Log::warning("🚫 Authorization failed for user {$userId}: " . $e->getMessage());
            return response()->json(['message' => 'You do not have permission to transfer this applicant.'], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning("⚠️ Validation failed for user {$userId}: " . $e->getMessage());
            throw $e; // Re-throw validation exceptions
        } catch (\Throwable $e) {
            \Log::error("❌ Transfer failed for user {$userId}");
            \Log::error("Error message: " . $e->getMessage());
            \Log::error("Error file: " . $e->getFile() . " line " . $e->getLine());
            \Log::error("Stack trace: " . $e->getTraceAsString());
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
