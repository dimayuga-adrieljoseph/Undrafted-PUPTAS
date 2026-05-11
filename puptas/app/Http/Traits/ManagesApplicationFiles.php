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
     * 
     * OPTIMIZED: Returns minimal data without loading file URLs for faster initial load
     */
    public function getUserFiles($id)
    {
        try {
            // Ensure user has the correct role (admin bypass allowed in stage check)
            if (auth()->user()->role_id !== 2) {
                $this->ensureRole($this->getRoleId());
            }

            // OPTIMIZATION: Load only essential data, exclude heavy file relationships
            $user = User::with([
                'currentApplication' => function ($query) {
                    $query->select('applications.id', 'applications.user_id', 'applications.status', 'applications.created_at', 'applications.program_id', 'applications.second_choice_id');
                },
                'currentApplication.program:id,code,name',
                'currentApplication.secondChoice:id,code,name',
                'currentApplication.processes' => function ($query) {
                    $query->select('id', 'application_id', 'stage', 'status', 'action', 'created_at', 'performed_by')
                        ->orderBy('created_at', 'desc')
                        ->limit(10)
                        ->with('performedBy:id,firstname,lastname');
                },
                'applicantProfile:user_id,student_number',
                'applicantProfile.graduateTypes:id,label',
            ])
            ->select('id', 'firstname', 'lastname', 'email', 'contactnumber', 'street_address', 'barangay', 'city', 'province', 'postal_code', 'birthday', 'sex', 'created_at')
            ->findOrFail($id);

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

            // OPTIMIZATION: Get only file metadata (type, status, comment) without loading file paths
            $fileMetadata = UserFile::where('user_id', $id)
                ->select('type', 'status', 'comment', 'original_name')
                ->get()
                ->keyBy('type');

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
                // Map currentApplication to application for frontend compatibility
                'application' => $user->currentApplication ? [
                    'id' => $user->currentApplication->id,
                    'status' => $user->currentApplication->status,
                    'created_at' => $user->currentApplication->created_at,
                    'program' => $user->currentApplication->program,
                    'second_choice' => $user->currentApplication->secondChoice,
                    'processes' => $user->currentApplication->processes,
                ] : null,
            ];

            $graduateType = $user->applicantProfile?->graduateTypes->first()?->label ?? null;

            // OPTIMIZATION: Return file metadata without URLs - frontend will lazy load them
            // Use method_exists to check if the new method is available
            if (method_exists(FileMapper::class, 'formatFilesForGraduateTypeMinimal')) {
                $fileList = FileMapper::formatFilesForGraduateTypeMinimal($fileMetadata, $graduateType);
            } else {
                // Fallback to old method if new method doesn't exist
                $files = UserFile::where('user_id', $id)->get()->keyBy('type');
                $fileList = FileMapper::formatFilesForGraduateType($files, $graduateType, false);
            }

            return response()->json([
                'user' => $userData,
                'uploadedFiles' => $fileList,
                'graduateType' => $graduateType,
                'lazyLoad' => method_exists(FileMapper::class, 'formatFilesForGraduateTypeMinimal'), // Only enable if method exists
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('User not found in getUserFiles', [
                'userId' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Applicant not found'], 404);
        } catch (\Exception $e) {
            \Log::error('Failed to load user files', [
                'userId' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to load applicant data. Please try again.',
            ], 500);
        }
    }

    /**
     * Get user grades separately for lazy loading
     * 
     * @param int $id User ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserGrades($id)
    {
        try {
            // Ensure user has the correct role (admin bypass allowed in stage check)
            if (auth()->user()->role_id !== 2) {
                $this->ensureRole($this->getRoleId());
            }

            $user = User::with('grades')->select('id')->findOrFail($id);

            return response()->json([
                'grades' => $user->grades,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('User not found in getUserGrades', [
                'userId' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Applicant not found',
                'grades' => null,
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Failed to load user grades', [
                'userId' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to load grades. Please try again.',
                'grades' => null,
            ], 500);
        }
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
