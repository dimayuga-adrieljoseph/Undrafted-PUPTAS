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

        // Get interviewer's assigned programs
        $assignedPrograms = $user->programs()->get(['id', 'code', 'name']);

        return Inertia::render('Dashboard/Interviewer', [
            'user' => $user,
            'pendingUsers' => $dashboardData['pendingUsers'],
            'summary' => $dashboardData['summary'],
            'chartData' => $dashboardData['chartData'],
            'assignedPrograms' => $assignedPrograms,
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

        // Interviewers see ALL applicants at interviewer stage (global access)
        $results = $this->userService->getAllApplicantsByStage('interviewer');

        \Log::info('InterviewerDashboard::getUsers (global)', [
            'user_id' => Auth::user()->id,
            'count' => count($results),
        ]);

        return response()->json($results);
    }

    public function accept(Request $request, $userId)
    {
        $this->ensureRole(4);

        // Validate that program_id is provided and optional requires_promissory_note
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'requires_promissory_note' => 'nullable|boolean',
        ]);

        $programId = $validated['program_id'];
        $requiresPromissoryNote = $validated['requires_promissory_note'] ?? false;

        // Check if interviewer is assigned to this program
        $assignedProgramIds = Auth::user()->programs()->pluck('programs.id')->toArray();
        
        if (!in_array($programId, $assignedProgramIds)) {
            return response()->json([
                'message' => 'You are not authorized to accept applicants for this program.',
            ], 403);
        }

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
            DB::transaction(function () use ($application, $grades, $userId, $interviewerInProgress, $programId, $requiresPromissoryNote) {
                $program = Program::lockForUpdate()->findOrFail($programId);

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

                // Update application to the interviewer's assigned program
                $oldProgramId = $application->program_id;
                $application->program_id = $programId;
                $application->requires_promissory_note = $requiresPromissoryNote;
                $application->save();

                // Decrement new program slots
                $program->slots -= 1;
                $program->save();

                // Increment old program slots if different
                if ($oldProgramId && $oldProgramId != $programId) {
                    $oldProgram = Program::find($oldProgramId);
                    if ($oldProgram) {
                        $oldProgram->slots += 1;
                        $oldProgram->save();
                    }
                }

                // Close current interviewer in-progress process
                $interviewerInProgress->update([
                    'status' => 'completed',
                    'action' => 'passed',
                    'reviewer_notes' => "Accepted by interviewer for program: {$program->code}",
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

            $this->auditLogService->logActivity('UPDATE', 'Applications', "Interviewer accepted application for applicant ID {$userId} into program ID {$programId}.", null, 'ADMISSION_DATA');

            return response()->json(['message' => 'Application accepted.']);
        } catch (\Throwable $e) {
            \Log::error("❌ Accept failed: " . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function reject(Request $request, $userId)
    {
        $this->ensureRole(4);

        // Validate that program_id is provided
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
        ]);

        $programId = $validated['program_id'];

        // Check if interviewer is assigned to this program
        $assignedProgramIds = Auth::user()->programs()->pluck('programs.id')->toArray();
        
        if (!in_array($programId, $assignedProgramIds)) {
            return response()->json([
                'message' => 'You are not authorized to reject applicants for this program.',
            ], 403);
        }

        $application = $this->applicationService->getApplicationByUserId($userId);

        // Check if evaluator stage is completed
        $this->applicationService->ensureStageCompleted(
            $application,
            'evaluator',
            "Cannot reject - evaluator stage not completed."
        );

        // Block repeat actions or already-finalized applications
        if (!in_array($application->status, ['submitted', 'returned', 'transferred'], true)) {
            return response()->json([
                'message' => 'Application is no longer available for interviewer action.',
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

        try {
            DB::transaction(function () use ($application, $interviewerInProgress, $userId, $programId) {
                $program = Program::findOrFail($programId);

                // Update application status to rejected
                $application->status = 'rejected';
                $application->save();

                // Close current interviewer in-progress process
                $interviewerInProgress->update([
                    'status' => 'completed',
                    'action' => 'rejected',
                    'reviewer_notes' => "Rejected by interviewer for program: {$program->code}",
                    'performed_by' => auth()->id(),
                ]);
            });

            $this->auditLogService->logActivity('UPDATE', 'Applications', "Interviewer rejected application for applicant ID {$userId} for program ID {$programId}.", null, 'ADMISSION_DATA');

            return response()->json(['message' => 'Application rejected.']);
        } catch (\Throwable $e) {
            \Log::error("❌ Reject failed: " . $e->getMessage());
            return response()->json(['message' => 'An error occurred while rejecting the application.'], 400);
        }
    }

    private function ensureRole(int $roleId): void
    {
        if (!Auth::user() || Auth::user()->role_id !== $roleId) {
            abort(403, 'Unauthorized access.');
        }
    }
}
