<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\UserFile;
use Illuminate\Support\Facades\Storage;
use App\Models\Application;
use App\Models\ApplicationProcess;



class EvaluatorDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role_id !== 3) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $summary = [
            'total' => Application::count(),
            'accepted' => Application::where('status', 'accepted')->count(),
            'pending' => Application::where('status', 'submitted')->count(),
            'returned' => Application::where('status', 'returned')->count(),
        ];

        return Inertia::render('Dashboard/Evaluator', [
            'user' => $user,
            'allUsers' => User::all(),
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

        return response()->json([
            'user' => $user,
            'uploadedFiles' => FileMapper::formatFilesUrls($files),
        ]);
    }


    public function returnFiles(Request $request, $userId)
    {
        $validated = $request->validate([
            'files' => 'required|array',
            'files.*' => 'string',
            'note' => 'required|string|max:1000',
        ]);

        $this->ensureRole(3);

        $application = Application::where('user_id', $userId)->firstOrFail();

        $fileTypes = $validated['files'];
        $note = $validated['note'];

        $updated = UserFile::where('user_id', $userId)
            ->whereIn('type', $fileTypes)
            ->update([
                'status' => 'returned',
                'comment' => $note,
            ]);

        $application->update([
            'status' => 'returned',
        ]);

        // Update existing in-progress evaluator process
        $evaluatorProcess = ApplicationProcess::where('application_id', $application->id)
            ->where('stage', 'evaluator')
            ->whereIn('status', ['in_progress', 'returned'])
            ->first();

        if (!$evaluatorProcess) {
            return response()->json([
                'message' => 'This action has already been completed or is not available.',
            ], 409);
        }

        $evaluatorProcess->update([
            'status' => 'returned',
            'action' => 'returned',
            'reviewer_notes' => $note,
            'files_affected' => json_encode($fileTypes),
            'performed_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Files returned successfully.',
            'updated' => $updated,
        ]);
    }

    public function returnApplication(Request $request, $userId)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'string',
            'note' => 'required|string|min:3',
        ]);

        $this->ensureRole(3);

        $application = Application::where('user_id', $userId)->firstOrFail();

        $files = $request->input('files');

        \Log::info('Files array received:', ['files' => $files]);

        // Use centralized FileMapper mapping

        $application->status = 'returned';
        $application->save();

        // Update existing in-progress evaluator process
        $evaluatorProcess = ApplicationProcess::where('application_id', $application->id)
            ->where('stage', 'evaluator')
            ->whereIn('status', ['in_progress', 'returned'])
            ->first();

        if (!$evaluatorProcess) {
            return response()->json([
                'message' => 'This action has already been completed or is not available.',
            ], 409);
        }

        $evaluatorProcess->update([
            'status' => 'returned',
            'action' => 'returned',
            'reviewer_notes' => $request->note,
            'files_affected' => json_encode($files),
            'performed_by' => auth()->id(),
        ]);

        $updatedFiles = [];
        $notFoundFiles = [];

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

        return response()->json([
            'message' => 'Application returned and tracked.',
            'updated_files' => $updatedFiles,
            'not_found_files' => $notFoundFiles,
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

    public function passApplication(Request $request, $userId)
    {
        $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        $this->ensureRole(3);

        $application = Application::where('user_id', $userId)->firstOrFail();

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
