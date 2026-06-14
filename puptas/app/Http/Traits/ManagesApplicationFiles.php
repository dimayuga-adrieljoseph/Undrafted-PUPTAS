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
     * OPTIMIZED: Loads only essential data first, files are loaded separately
     */
    public function getUserFiles($id)
    {
        try {
            // Ensure user has the correct role (admin bypass allowed in stage check)
            if (auth()->user()->role_id !== 2) {
                $this->ensureRole($this->getRoleId());
            }

            // Load user with ONLY essential data (no files relationship)
            $user = User::with([
                'currentApplication' => function ($query) {
                    $query->select('applications.id', 'applications.user_id', 'applications.status', 'applications.created_at', 'applications.program_id', 'applications.second_choice_id', 'applications.third_choice_id', 'applications.enrollment_status', 'applications.enrollment_position', 'applications.submitted_at', 'applications.requires_promissory_note');
                },
                'currentApplication.program:id,code,name,slots',
                'currentApplication.secondChoice:id,code,name,slots',
                'currentApplication.thirdChoice:id,code,name,slots',
                'currentApplication.processes' => function ($query) {
                    $query->select('id', 'application_id', 'stage', 'status', 'action', 'started_at', 'reviewed_by', 'created_at', 'performed_by', 'reviewer_notes')
                        ->orderBy('created_at', 'desc')
                        ->limit(10)
                        ->with('performedBy:id,firstname,lastname');
                },
                'grades', // Include grades
                'applicantProfile:user_id,firstname,middlename,lastname,extension_name,salutation,sex,date_graduated,school,strand,track',
                'applicantProfile.graduateTypes:id,label',
                'applicantProfile.testPasser:user_id,reference_number',
            ])
            ->where(function($q) use ($id) {
                $q->where('idp_user_id', $id);
                if (is_numeric($id)) {
                    $q->orWhere('id', $id);
                }
            })
            ->firstOrFail();

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

            // Load files separately (not through relationship) for better performance
            $files = UserFile::where('user_id', (string) $id)->get()->keyBy('type');

            // Transform the response to map currentApplication to application for frontend compatibility
            $userData = [
                'id' => $user->id,
                'student_number' => $user->applicantProfile?->student_number,
                'firstname' => $user->applicantProfile?->firstname ?? $user->firstname,
                'middlename' => $user->applicantProfile?->middlename,
                'lastname' => $user->applicantProfile?->lastname ?? $user->lastname,
                'extension_name' => $user->applicantProfile?->extension_name,
                'salutation' => $user->applicantProfile?->salutation,
                'email' => $user->email,
                'sex' => $user->applicantProfile?->sex ?? $user->sex,
                'date_graduated' => $user->applicantProfile?->date_graduated,
                'school' => $user->applicantProfile?->school,
                'strand' => $user->applicantProfile?->strand,
                'track' => $user->applicantProfile?->track,
                'reference_number' => $user->applicantProfile?->testPasser?->reference_number,
                'created_at' => $user->created_at,
                'grades' => $user->grades, // Include grades
                // Map currentApplication to application for frontend compatibility
                'application' => $user->currentApplication ? [
                    'id' => $user->currentApplication->id,
                    'status' => $user->currentApplication->status,
                    'created_at' => $user->currentApplication->created_at,
                    'program' => $user->currentApplication->program,
                    'second_choice' => $user->currentApplication->secondChoice,
                    'third_choice' => $user->currentApplication->thirdChoice,
                    'requires_promissory_note' => $user->currentApplication->requires_promissory_note,
                    'processes' => $user->currentApplication->processes,
                ] : null,
            ];

            $graduateType = $user->applicantProfile?->graduateTypes->first()?->label ?? null;

            // Format files for graduate type
            $fileList = FileMapper::formatFilesForGraduateType($files, $graduateType, false);

            // Debug logging
            \Log::info('Staff getUserFiles response', [
                'userId' => $id,
                'role' => auth()->user()->role_id,
                'graduateType' => $graduateType,
                'hasGrades' => $user->grades !== null,
                'gradesData' => $user->grades,
                'rawFileCount' => $files->count(),
                'formattedFileCount' => count($fileList),
                'fileKeys' => array_keys($fileList),
            ]);

            // Return full file data with URLs (not lazy loading)
            return response()->json([
                'user' => $userData,
                'uploadedFiles' => $fileList,
                'lazyLoad' => false, // Disabled until frontend is updated
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('User not found in getUserFiles', [
                'userId' => $id,
                'role' => auth()->user()->role_id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Applicant not found'], 404);
        } catch (\Throwable $e) {
            \Log::error('Failed to load user files', [
                'userId' => $id,
                'role' => auth()->user()->role_id ?? 'unknown',
                'errorClass' => get_class($e),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
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

            $user = User::with('grades')->select('id', 'idp_user_id')
                ->where(function($q) use ($id) {
                    $q->where('idp_user_id', $id);
                    if (is_numeric($id)) {
                        $q->orWhere('id', $id);
                    }
                })
                ->firstOrFail();

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
            'files' => 'array',
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
                // Only update files if specific files were selected
                if (!empty($fileTypes)) {
                    UserFile::where('user_id', (string) $userId)
                        ->whereIn('type', $fileTypes)
                        ->update([
                            'status' => 'returned',
                            'comment' => $note,
                        ]);
                }

                $application->update([
                    'status' => 'returned',
                ]);

                $inProgress->update([
                    'status' => 'returned',
                    'action' => 'returned',
                    'reviewer_notes' => $note,
                    'files_affected' => !empty($fileTypes) ? $fileTypes : null,
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
            'files' => 'nullable|array',
            'files.*' => 'string|in:' . \App\Helpers\FileMapper::getValidFileFields(),
            'note' => 'nullable|string|max:1000',
            'requires_promissory_note' => 'nullable|boolean',
        ]);

        $this->ensureRole($this->getRoleId());

        $user = User::with('currentApplication')->findOrFail($userId);
        $application = $user->currentApplication;

        if (!$application) {
            return response()->json(['message' => 'Application not found'], 404);
        }

        $assignedProgramIds = Auth::user()->programs()->pluck('programs.id')->toArray();
        if (!in_array($application->program_id, $assignedProgramIds)) {
            return response()->json([
                'message' => 'You are not authorized to return applicants for this program.',
            ], 403);
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
                if ($request->has('requires_promissory_note')) {
                    $application->requires_promissory_note = (bool) $request->requires_promissory_note;
                }
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

                    $file = UserFile::where('user_id', (string) $userId)
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
    abstract protected function getRoleId(): int|array;
}
