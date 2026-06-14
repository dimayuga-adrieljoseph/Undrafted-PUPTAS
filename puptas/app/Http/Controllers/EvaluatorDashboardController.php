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
use App\Services\AuditLogService;
use App\Services\DashboardService;
use App\Services\UserService;

class EvaluatorDashboardController extends Controller
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

        if (!$this->dashboardService->verifyRoleAccess($user, [2, 3, 7, 8])) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $dashboardData = $this->dashboardService->getEvaluatorDashboardData($this->getCurrentStage());

        return Inertia::render('Dashboard/Evaluator', [
            'user' => $user,
            'pendingUsers' => $dashboardData['pendingUsers'],
            'summary' => $dashboardData['summary'],
            'chartData' => $dashboardData['chartData'],
        ]);
    }

    protected function getCurrentStage(): string
    {
        return Auth::user()->role_id == 3 ? 'document_evaluator' : 'grade_evaluator';
    }

    protected function getRoleId(): array
    {
        return [2, 3, 7, 8];
    }

    // returnFiles() method provided by ManagesApplicationFiles trait

    // returnApplication() method provided by ManagesApplicationFiles trait

    public function getUsers()
    {
        // Ensure user has evaluator role
        $this->ensureRole($this->getRoleId());

        $user = Auth::user();
        
        // Resolve this evaluator's assigned program IDs from the pivot table
        $programIds = $user->programs()->pluck('programs.id')->toArray();

        // Admin bypass: Admins can evaluate all programs
        if ($user->role_id == 2 || $user->role_id == 7 || $user->role_id == 8) {
            $programIds = \App\Models\Program::pluck('id')->toArray();
        }

        \Log::info('EvaluatorDashboard::getUsers', [
            'user_id'     => $user->id,
            'program_ids' => $programIds,
        ]);

        // If the evaluator has no assigned programs, return an empty list.
        if (empty($programIds)) {
            return response()->json([]);
        }

        $results = $this->userService->getAllApplicantsByStage($this->getCurrentStage(), $programIds);

        \Log::info('EvaluatorDashboard::getUsers results', ['count' => count($results)]);

        // Return applicants at evaluator stage, scoped to assigned courses only
        return response()->json($results);
    }

    public function passApplication(Request $request, $userId)
    {
        $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        $this->ensureRole([2, 3, 8]);

        $application = $this->applicationService->getApplicationByUserId($userId);
        $user = Auth::user();

        $assignedProgramIds = $user->programs()->pluck('programs.id')->toArray();
        if ($user->role_id == 2 || $user->role_id == 7 || $user->role_id == 8) {
            $assignedProgramIds = \App\Models\Program::pluck('id')->toArray();
        }
        
        if (!in_array($application->program_id, $assignedProgramIds)) {
            return response()->json([
                'message' => 'You are not authorized to evaluate applicants for this program.',
            ], 403);
        }

        DB::transaction(function () use ($application, $userId, $request) {
            // Update existing evaluator process (can be in_progress or returned status)
            $evaluatorProcess = ApplicationProcess::where('application_id', $application->id)
                ->where('stage', $this->getCurrentStage())
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
            $updatedCount = UserFile::where('user_id', (string) $userId)
                ->where('status', 'returned')
                ->update(['status' => 'approved', 'comment' => null]);

            \Log::info("Updated {$updatedCount} files from 'returned' to 'approved' for user {$userId}");

            // Clear office flags if passing
            $application->requires_guidance_office = false;
            $application->requires_admission_office = false;
            $application->save();

            // Update application status back to submitted
            $application->status = 'submitted';
            $application->save();

            \Log::info("Updated application status to 'submitted' for application {$application->id}");

            // Create next stage process
            $nextStage = $this->getCurrentStage() === 'document_evaluator' ? 'grade_evaluator' : 'interviewer';
            ApplicationProcess::create([
                'application_id' => $application->id,
                'stage' => $nextStage,
                'status' => 'in_progress',
                'performed_by' => null,
            ]);
        });

        $this->auditLogService->logActivity('UPDATE', 'Applications', "Evaluator passed application for applicant ID {$userId} to interviewer stage.", null, 'ADMISSION_DATA');

        return response()->json([
            'message' => 'Application successfully passed to the next step.',
        ]);
    }

    public function rejectApplication(Request $request, $userId)
    {
        $this->ensureRole([2, 3, 7, 8]);

        $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        $application = $this->applicationService->getApplicationByUserId($userId);

        if (!$application) {
            return response()->json(['message' => 'Application not found'], 404);
        }

        $user = Auth::user();
        $assignedProgramIds = $user->programs()->pluck('programs.id')->toArray();
        if ($user->role_id == 2 || $user->role_id == 7 || $user->role_id == 8) {
            $assignedProgramIds = \App\Models\Program::pluck('id')->toArray();
        }

        if (!in_array($application->program_id, $assignedProgramIds)) {
            return response()->json([
                'message' => 'You are not authorized to evaluate applicants for this program.',
            ], 403);
        }

        $inProgress = ApplicationProcess::where('application_id', $application->id)
            ->where('stage', $this->getCurrentStage())
            ->whereIn('status', ['in_progress', 'returned'])
            ->first();

        if (!$inProgress) {
            return response()->json([
                'message' => 'This action has already been completed or is not available.',
            ], 409);
        }

        try {
            DB::transaction(function () use ($application, $inProgress, $request, $userId) {
                $application->status = 'rejected';
                $application->save();

                $inProgress->update([
                    'status' => 'completed',
                    'action' => 'rejected',
                    'reviewer_notes' => $request->note,
                    'performed_by' => auth()->id(),
                ]);
            });

            $this->auditLogService->logActivity('UPDATE', 'Applications', "Application rejected for applicant ID {$userId}.", null, 'ADMISSION_DATA');

            return response()->json(['message' => 'Application successfully rejected.']);
        } catch (\Throwable $e) {
            \Log::error("❌ Reject failed: " . $e->getMessage());
            return response()->json(['message' => 'An error occurred while rejecting the application.'], 500);
        }
    }

    public function flagApplication(Request $request, $userId)
    {
        $this->ensureRole([2, 3, 7, 8]);

        $request->validate([
            'note' => 'nullable|string|max:1000',
            'requires_guidance_office' => 'nullable|boolean',
            'requires_admission_office' => 'nullable|boolean',
        ]);

        $application = $this->applicationService->getApplicationByUserId($userId);

        if (!$application) {
            return response()->json(['message' => 'Application not found'], 404);
        }

        $user = Auth::user();
        $assignedProgramIds = $user->programs()->pluck('programs.id')->toArray();
        if ($user->role_id == 2 || $user->role_id == 7 || $user->role_id == 8) {
            $assignedProgramIds = \App\Models\Program::pluck('id')->toArray();
        }

        if (!in_array($application->program_id, $assignedProgramIds)) {
            return response()->json([
                'message' => 'You are not authorized to evaluate applicants for this program.',
            ], 403);
        }

        $inProgress = ApplicationProcess::where('application_id', $application->id)
            ->where('stage', $this->getCurrentStage())
            ->whereIn('status', ['in_progress', 'returned'])
            ->first();

        if (!$inProgress) {
            return response()->json([
                'message' => 'This action has already been completed or is not available.',
            ], 409);
        }

        try {
            DB::transaction(function () use ($application, $inProgress, $request, $userId) {
                if ($request->has('requires_guidance_office')) {
                    $application->requires_guidance_office = (bool) $request->requires_guidance_office;
                }
                if ($request->has('requires_admission_office')) {
                    $application->requires_admission_office = (bool) $request->requires_admission_office;
                }
                $application->save();

                // Keep status in_progress but log reviewer notes
                $inProgress->update([
                    'reviewer_notes' => $request->note,
                    'performed_by' => auth()->id(),
                ]);
            });

            $this->auditLogService->logActivity('UPDATE', 'Applications', "Application flagged for applicant ID {$userId}.", null, 'ADMISSION_DATA');

            return response()->json(['message' => 'Application successfully flagged.']);
        } catch (\Throwable $e) {
            \Log::error("❌ Flagging failed: " . $e->getMessage());
            return response()->json(['message' => 'An error occurred while flagging the application.'], 500);
        }
    }

    public function startReview(ApplicationProcess $applicationProcess)
    {
        $this->ensureRole([2, 3, 7, 8]);

        if ($applicationProcess->started_at !== null) {
            return response()->json(['message' => 'Review already started.'], 409);
        }

        $applicationProcess->update([
            'started_at'  => now(),
            'reviewed_by' => auth()->id(),
        ]);

        return response()->json(['started_at' => $applicationProcess->started_at]);
    }

    private function ensureRole(int|array $roleId): void
    {
        $roleIds = is_array($roleId) ? $roleId : [$roleId];
        if (!Auth::user() || !in_array(Auth::user()->role_id, $roleIds)) {
            abort(403, 'Unauthorized access.');
        }
    }
}
