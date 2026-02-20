<?php

namespace App\Http\Traits;

use App\Models\User;
use App\Helpers\FileMapper;
use App\Models\Application;
use App\Models\UserFile;
use App\Models\ApplicationProcess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait ManagesApplicationFiles
{
    /**
     * Get user files with formatted URLs
     */
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

    /**
     * Return specific files to applicant
     */
    public function returnFiles(Request $request, $userId)
    {
        $validated = $request->validate([
            'files' => 'required|array',
            'files.*' => 'string',
            'note' => 'required|string|max:1000',
        ]);

        $this->ensureRole($this->getRoleId());

        $application = Application::where('user_id', $userId)->firstOrFail();

        // Check prerequisite stage if needed
        $this->checkPrerequisiteStage($application);

        $fileTypes = $validated['files'];
        $note = $validated['note'];

        // Validate process exists BEFORE making any changes
        $inProgress = $this->getInProgressProcess($application);

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

    /**
     * Return entire application to applicant
     */
    public function returnApplication(Request $request, $userId)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'string',
            'note' => 'required|string|min:3',
        ]);

        $this->ensureRole($this->getRoleId());

        $application = Application::where('user_id', $userId)->firstOrFail();

        // Check prerequisite stage if needed
        $this->checkPrerequisiteStage($application);

        $files = $request->input('files');

        // Validate process exists BEFORE making any changes
        $inProgress = $this->getInProgressProcess($application);

        if (!$inProgress) {
            return response()->json([
                'message' => 'This action has already been completed or is not available.',
            ], 409);
        }

        // Use canonical file mapping from FileMapper
        $keyMap = \App\Helpers\FileMapper::MAPPING;

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

                $file = UserFile::where('user_id', $userId)
                    ->where('type', $dbKey)
                    ->first();

                if (!$file) {
                    $notFoundFiles[] = $dbKey;
                    continue;
                }

                $file->status = 'returned';
                $file->comment = $request->note;
                $file->save();

                $updatedFiles[] = $dbKey;
            }
        });

        return response()->json([
            'message' => 'Application returned and tracked.',
            'updated_files' => $updatedFiles,
            'not_found_files' => $notFoundFiles,
        ]);
    }

    /**
     * Get the in-progress process for the current stage
     */
    protected function getInProgressProcess($application)
    {
        $stage = $this->getCurrentStage();
        $statuses = ['in_progress', 'returned'];

        return $application->processes()
            ->where('stage', $stage)
            ->whereIn('status', $statuses)
            ->latest()
            ->first();
    }

    /**
     * Check prerequisite stage completion
     * Override in controllers that need this check
     */
    protected function checkPrerequisiteStage($application)
    {
        // Default: no prerequisite check
        // Override in MedicalDashboardController and RecordStaffDashboardController
    }

    /**
     * Get the current stage name
     * Must be implemented by each controller
     */
    abstract protected function getCurrentStage(): string;

    /**
     * Get the role ID for this controller
     * Must be implemented by each controller
     */
    abstract protected function getRoleId(): int;
}
