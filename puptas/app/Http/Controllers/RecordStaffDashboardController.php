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
use Illuminate\Support\Facades\DB;
use App\Helpers\FileMapper;
use App\Http\Traits\ManagesApplicationFiles;



class RecordStaffDashboardController extends Controller
{
    use ManagesApplicationFiles;
    public function index()
    {
        $user = Auth::user();

        if ($user->role_id !== 6) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $summary = [
            'total' => Application::count(),
            'accepted' => Application::where('status', 'accepted')->count(),
            'pending' => Application::where('status', 'submitted')->count(),
            'returned' => Application::where('status', 'returned')->count(),
        ];

        $programs = Program::withCount('applications')->get();

        return Inertia::render('Dashboard/Records', [
            'user' => $user,
            'allUsers' => User::all(),
            'programs' => $programs,
            'summary' => $summary,
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

    protected function checkPrerequisiteStage($application)
    {
        // Check if medical stage is completed
        $medicalCompleted = $application->processes()
            ->where('stage', 'medical')
            ->where('status', 'completed')
            ->exists();

        if (!$medicalCompleted) {
            abort(409, "Cannot proceed - prerequisite verification not completed.");
        }
    }

    // getUserFiles() method provided by ManagesApplicationFiles trait

    // returnFiles() method provided by ManagesApplicationFiles trait

    // returnApplication() method provided by ManagesApplicationFiles trait

    public function tag($userId)
    {
        $this->ensureRole(6);

        $application = Application::where('user_id', $userId)->firstOrFail();

        // Check if medical stage is completed
        $medicalCompleted = $application->processes()
            ->where('stage', 'medical')
            ->where('status', 'completed')
            ->exists();

        if (!$medicalCompleted) {
            abort(409, "Cannot tag as enrolled - medical stage not completed.");
        }

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
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function untag($userId)
    {
        $this->ensureRole(6);

        $application = Application::where('user_id', $userId)->firstOrFail();

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
