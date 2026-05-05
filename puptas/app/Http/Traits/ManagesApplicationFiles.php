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
     * Only allows access if the application is at the appropriate stage
     */
    public function getUserFiles($id)
    {
        // Ensure user has the correct role (admin bypass allowed in stage check)
        if (auth()->user()->role_id !== 2) {
            $this->ensureRole($this->getRoleId());
        }

        $user = User::with([
            'currentApplication.program',
            'currentApplication.processes.performedBy:id,firstname,lastname',
            'files',
            'grades',
            'applicantProfile.graduateTypes',
        ])->findOrFail($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Security check: Verify the user's application is at the appropriate stage
        // Admin (role_id 2) can bypass this check
        if (auth()->user()->role_id !== 2) {
            $currentStage = $this->getCurrentStage();
            $application = $user->currentApplication;

            if (!$application) {
                return response()->json(['message' => 'Application not found'], 404);
            }

            // Check if the application has any process at this stage (including completed for read-only access)
            $hasAccess = $application->processes()
                ->where('stage', $currentStage)
                ->whereIn('status', ['in_progress', 'returned', 'completed'])
                ->exists();

            if (!$hasAccess) {
                return response()->json([
                    'message' => 'Unauthorized access. Application is not at the ' . $currentStage . ' stage.'
                ], 403);
            }
        }

        $files = $user->files->keyBy('type');

        // Transform the response to map currentApplication to application for frontend compatibility
        $userData = [
            'id' => $user->id,
            'student_number' => $user->applicantProfile?->student_number,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'contactnumber' => $user->contactnumber,
            'street_address' => $user->street_address,
            'barangay' => $user->barangay,
            'city' => $user->city,
            'province' => $user->province,
            'postal_code' => $user->postal_code,
            'birthday' => $user->birthday,
            'sex' => $user->sex,
            'created_at' => $user->created_at,
            'files' => $user->files,
            'grades' => $user->grades,
            // Map currentApplication to application for frontend compatibility
            'application' => $user->currentApplication ? [
                'id' => $user->currentApplication->id,
                'status' => $user->currentApplication->status,
                'created_at' => $user->currentApplication->created_at,
                'program' => $user->currentApplication->program,
                'processes' => $user->currentApplication->processes,
            ] : null,
        ];

        $graduateType = $user->applicantProfile?->graduateTypes->first()?->label ?? null;

        return response()->json([
            'user' => $userData,
            'uploadedFiles' => FileMapper::formatFilesForGraduateType($files, $graduateType, false),
        ]);
    }

    /**
     * Return specific files to applicant
     */
    public function returnFiles(Request $request, $userId)
    {
        $validated = $request->validate([
            'files' => 'required|array',
            'files.*' => 'string|in:' . \App\Helpers\FileMapper::getValidFileFields(),
            'note' => 'required|string|max:1000',
        ]);

        $this->ensureRole($this->getRoleId());

        $user = User::with('currentApplication')->findOrFail($userId);
        $application = $user->currentApplication;

        if (!$application) {
            return response()->json(['message' => 'Application not found'], 404);
        }

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
        try {
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
                    'files_affected' => $fileTypes,
                    'performed_by' => auth()->id(),
                ]);
            });

            return response()->json([
                'message' => 'Files returned successfully.',
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to return files', ['stage' => $this->getCurrentStage()]);
            return response()->json([
                'message' => 'Failed to return files. Please try again.',
            ], 500);
        }
    }

    /**
     * Return entire application to applicant
     */
    public function returnApplication(Request $request, $userId)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'string|in:' . \App\Helpers\FileMapper::getValidFileFields(),
            'note' => 'required|string|min:3',
        ]);

        $this->ensureRole($this->getRoleId());

        $user = User::with('currentApplication')->findOrFail($userId);
        $application = $user->currentApplication;

        if (!$application) {
            return response()->json(['message' => 'Application not found'], 404);
        }

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
        try {
            DB::transaction(function () use ($application, $inProgress, $request, $files, $userId, $keyMap, &$updatedFiles, &$notFoundFiles) {
                $application->status = 'returned';
                $application->save();

                $inProgress->update([
                    'status' => 'returned',
                    'action' => 'returned',
                    'reviewer_notes' => $request->note,
                    'files_affected' => $files,
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

            app(\App\Services\AuditLogService::class)->logActivity('UPDATE', 'Applications', "Returned application at '{$this->getCurrentStage()}' stage for applicant ID {$userId}.", null, 'ADMISSION_DATA');

            return response()->json([
                'message' => 'Application returned and tracked.',
                'updated_files' => $updatedFiles,
                'not_found_files' => $notFoundFiles,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to return application', ['stage' => $this->getCurrentStage()]);
            return response()->json([
                'message' => 'Failed to return application. Please try again.',
            ], 500);
        }
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
