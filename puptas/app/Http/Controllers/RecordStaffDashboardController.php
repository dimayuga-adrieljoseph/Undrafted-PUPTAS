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



class RecordStaffDashboardController extends Controller
{
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



    public function getUserFiles($id)
    {
        $user = User::with(['application.processes', 'files'])->findOrFail($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $files = $user->files->keyBy('type');

        $uploadedFiles = [
            'file11'        => isset($files['file11_back']) ? Storage::url($files['file11_back']->file_path) : null,
            'file12'        => isset($files['file12_back']) ? Storage::url($files['file12_back']->file_path) : null,
            'schoolId'      => isset($files['school_id']) ? Storage::url($files['school_id']->file_path) : null,
            'nonEnrollCert' => isset($files['non_enroll_cert']) ? Storage::url($files['non_enroll_cert']->file_path) : null,
            'psa'           => isset($files['psa']) ? Storage::url($files['psa']->file_path) : null,
            'goodMoral'     => isset($files['good_moral']) ? Storage::url($files['good_moral']->file_path) : null,
            'underOath'     => isset($files['under_oath']) ? Storage::url($files['under_oath']->file_path) : null,
            'photo2x2'      => isset($files['photo_2x2']) ? Storage::url($files['photo_2x2']->file_path) : null,
        ];

        return response()->json([
            'user' => $user,
            'uploadedFiles' => $uploadedFiles,
        ]);
    }


    public function returnFiles(Request $request, $userId)
    {
        $validated = $request->validate([
            'files' => 'required|array',
            'files.*' => 'string',
            'note' => 'required|string|max:1000',
        ]);

        $this->ensureRole(6);

        $application = Application::where('user_id', $userId)->firstOrFail();

        // Check if medical stage is completed
        $medicalCompleted = $application->processes()
            ->where('stage', 'medical')
            ->where('status', 'completed')
            ->exists();

        if (!$medicalCompleted) {
            abort(409, "Cannot return files - medical stage not completed.");
        }

        $fileTypes = $validated['files'];
        $note = $validated['note'];

        // Validate process exists BEFORE making any changes
        $inProgress = $application->processes()
            ->where('stage', 'records')
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        if (!$inProgress) {
            return response()->json([
                'message' => 'This action has already been completed or is not available.',
            ], 409);
        }

        // Wrap all mutations in transaction
        DB::transaction(function () use ($userId, $fileTypes, $note, $application, $inProgress) {
            UserFile::where('user_id', $userId)
                ->whereIn('type', $fileTypes)
                ->update([
                    'status' => 'returned',
                    'comment' => $note,
                ]);

            $application->update([
                'status' => 'returned',
            ]);

            $inProgress->update([
                'status' => 'returned',
                'action' => 'returned',
                'reviewer_notes' => $note,
                'files_affected' => json_encode($fileTypes),
                'performed_by' => auth()->id(),
            ]);
        });

        return response()->json([
            'message' => 'Files returned successfully.',
        ]);
    }

    public function returnApplication(Request $request, $userId)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'string',
            'note' => 'required|string|min:3',
        ]);

        $this->ensureRole(6);

        $application = Application::where('user_id', $userId)->firstOrFail();

        // Check if medical stage is completed
        $medicalCompleted = $application->processes()
            ->where('stage', 'medical')
            ->where('status', 'completed')
            ->exists();

        if (!$medicalCompleted) {
            abort(409, "Cannot return application - medical stage not completed.");
        }

        $files = $request->input('files');

        \Log::info('Files array received:', ['files' => $files]);

        // Validate process exists BEFORE making any changes
        $inProgress = $application->processes()
            ->where('stage', 'records')
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        if (!$inProgress) {
            return response()->json([
                'message' => 'This action has already been completed or is not available.',
            ], 409);
        }

        $keyMap = [
            'file11'        => 'file11_back',
            'file12'        => 'file12',
            'schoolId'      => 'school_id',
            'nonEnrollCert' => 'non_enroll_cert',
            'psa'           => 'psa',
            'goodMoral'     => 'good_moral',
            'underOath'     => 'under_oath',
            'photo2x2'      => 'photo_2x2',
        ];

        $updatedFiles = [];
        $notFoundFiles = [];

        // Wrap all mutations in transaction
        DB::transaction(function () use ($application, $inProgress, $request, $files, $userId, $keyMap, &$updatedFiles, &$notFoundFiles) {
            $application->status = 'returned';
            $application->save();

            $inProgress->update([
                'status' => 'returned',
                'action' => 'returned',
                'reviewer_notes' => $request->note,
                'files_affected' => json_encode($files),
                'performed_by' => auth()->id(),
            ]);

            foreach ($files as $fileKey) {
                $dbKey = $keyMap[$fileKey] ?? $fileKey;

                \Log::info("Processing file key: {$fileKey} mapped to DB key: {$dbKey}");

                $file = \App\Models\UserFile::where('user_id', $userId)
                    ->where('type', $dbKey)
                    ->first();

                if (!$file) {
                    \Log::warning("UserFile not found for user_id={$userId}, type={$dbKey}");
                    $notFoundFiles[] = $dbKey;
                    continue;
                }

                $file->status = 'returned';
                $file->comment = $request->note;

                $saved = $file->save();

                \Log::info("Saved UserFile ID {$file->id} with status 'returned' and comment. Save success: " . ($saved ? 'true' : 'false'));

                $updatedFiles[] = $dbKey;
            }
        });

        return response()->json([
            'message' => 'Application returned and tracked.',
            'updated_files' => $updatedFiles,
            'not_found_files' => $notFoundFiles,
        ]);
    }

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
