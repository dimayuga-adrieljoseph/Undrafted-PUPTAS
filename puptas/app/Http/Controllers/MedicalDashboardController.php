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



class MedicalDashboardController extends Controller
{
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



    public function getUserFiles($id)
    {
        $user = User::with(['application.processes.performedBy:id,firstname,lastname', 'files', 'application.program'])->findOrFail($id);

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

        $this->ensureRole(5);

        $application = Application::where('user_id', $userId)->firstOrFail();

        // Check if interviewer stage is completed
        $interviewerCompleted = $application->processes()
            ->where('stage', 'interviewer')
            ->where('status', 'completed')
            ->exists();

        if (!$interviewerCompleted) {
            abort(409, "Cannot return files - interviewer stage not completed.");
        }

        $fileTypes = $validated['files'];
        $note = $validated['note'];

        // Validate process exists BEFORE making any changes
        $inProgress = $application->processes()
            ->where('stage', 'medical')
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

        $this->ensureRole(5);

        $application = Application::where('user_id', $userId)->firstOrFail();

        // Check if interviewer stage is completed
        $interviewerCompleted = $application->processes()
            ->where('stage', 'interviewer')
            ->where('status', 'completed')
            ->exists();

        if (!$interviewerCompleted) {
            abort(409, "Cannot return application - interviewer stage not completed.");
        }

        $files = $request->input('files');

        \Log::info('Files array received:', ['files' => $files]);

        // Validate process exists BEFORE making any changes
        $inProgress = $application->processes()
            ->where('stage', 'medical')
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
