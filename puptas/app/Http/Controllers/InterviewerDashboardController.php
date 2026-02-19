<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\UserFile;
use Illuminate\Support\Facades\Storage;
use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\Program;
use App\Models\Grade;
use Illuminate\Support\Facades\DB;
use App\Helpers\FileMapper;

class InterviewerDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role_id !== 4) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $summary = [
            'total' => Application::count(),
            'accepted' => Application::where('status', 'accepted')->count(),
            'pending' => Application::where('status', 'submitted')->count(),
            'returned' => Application::where('status', 'returned')->count(),
        ];


        return Inertia::render('Dashboard/Interviewer', [
            'user' => $user,
            'allUsers' => User::all(),
            'summary' => $summary,
        ]);
    }

    public function getUserFiles($id)
    {
        $user = User::with(['application.program', 'application.processes', 'files', 'grades'])->findOrFail($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $files = $user->files->keyBy('type');

        return response()->json([
            'user' => $user,
            'uploadedFiles' => FileMapper::formatFilesUrls($files),
        ]);
    }

    public function getUsers()
    {
        return response()->json(
            User::with('application.program')
                ->where('role_id', 1)
                ->whereHas('application')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'firstname' => $user->firstname,
                        'lastname' => $user->lastname,
                        'course' => $user->course,
                        'status' => $user->application->status ?? null,
                        'email' => $user->email,
                        'username' => $user->username,
                        'phone' => $user->phone,
                        'company' => $user->company,
                        'program' => $user->application->program ?? null,
                    ];
                })
        );
    }

    public function accept($userId)
    {
        $this->ensureRole(4);

        $application = Application::where('user_id', $userId)->firstOrFail();

        // Check if evaluator stage is completed
        $evaluatorCompleted = $application->processes()
            ->where('stage', 'evaluator')
            ->where('status', 'completed')
            ->exists();

        if (!$evaluatorCompleted) {
            abort(409, "Cannot accept - evaluator stage not completed.");
        }

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

        $application = Application::where('user_id', $userId)->firstOrFail();

        // Check if evaluator stage is completed
        $evaluatorCompleted = $application->processes()
            ->where('stage', 'evaluator')
            ->where('status', 'completed')
            ->exists();

        if (!$evaluatorCompleted) {
            abort(409, "Cannot transfer - evaluator stage not completed.");
        }

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
