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
use App\Http\Traits\ManagesApplicationFiles;



class MedicalDashboardController extends Controller
{
    use ManagesApplicationFiles;
    public function index()
    {
        $user = Auth::user();

        if ($user->role_id !== 5) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $summary = [
            'total' => Application::count(),
            'accepted' => Application::where('status', 'accepted')->count(),
            'pending' => Application::where('status', 'submitted')->count(),
            'returned' => Application::where('status', 'returned')->count(),
        ];

        return Inertia::render('Dashboard/Medical', [
            'user' => $user,
            'allUsers' => User::all(),
            'summary' => $summary,
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
        $interviewerCompleted = $application->processes()
            ->where('stage', 'interviewer')
            ->where('status', 'completed')
            ->exists();

        if (!$interviewerCompleted) {
            abort(409, "Cannot proceed - interviewer stage not completed.");
        }
    }

    // getUserFiles() method provided by ManagesApplicationFiles trait

    // returnFiles() method provided by ManagesApplicationFiles trait

    // returnApplication() method provided by ManagesApplicationFiles trait

    public function accept($userId)
    {
        $this->ensureRole(5);

        $application = Application::where('user_id', $userId)->firstOrFail();

        // Check if interviewer stage is completed
        $interviewerCompleted = $application->processes()
            ->where('stage', 'interviewer')
            ->where('status', 'completed')
            ->exists();

        if (!$interviewerCompleted) {
            abort(409, "Cannot clear medically - interviewer stage not completed.");
        }

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
