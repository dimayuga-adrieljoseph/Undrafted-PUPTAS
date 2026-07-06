<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\Program;
use App\Models\User;
use App\Models\UserFile;
use App\Helpers\FileMapper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Services\CutoffSettingsService;

/**
 * Confirmation Service
 * 
 * Handles business logic for application confirmation and submission.
 * Centralizes confirmation-related operations including data aggregation,
 * application submission, file management, and eligibility calculations.
 */
class ConfirmationService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        protected FileService $fileService,
        protected CutoffSettingsService $cutoffSettingsService,
    ) {}


    /**
     * Get confirmation data for a user
     *
     * @param User $user
     * @return array
     */
    public function getConfirmationData(User $user): array
    {
        $files = $user->files()->get();

        // Auto-expire stale 'uploading' statuses (stuck for >10 minutes = failed)
        $staleThreshold = now()->subMinutes(10);
        foreach ($files as $file) {
            if ($file->status === 'uploading' && $file->updated_at < $staleThreshold) {
                $file->update(['status' => 'failed']);
            }
        }

        $files = $files->keyBy('type');
        $application = $user->currentApplication;
        $profile = $user->applicantProfile()->with([
            'graduateTypes',
            'firstChoiceProgram',
            'secondChoiceProgram',
            'thirdChoiceProgram',
        ])->first();

        $graduateType = $profile?->graduateTypes->first()?->label ?? null;

        // Resolve program IDs and names from application (non-draft) or profile (draft/no app)
        $isDraft = !$application || $application->status === 'draft';

        if ($isDraft) {
            $firstId   = $profile?->first_choice_program;
            $secondId  = $profile?->second_choice_program;
            $thirdId   = $profile?->third_choice_program;
            $firstName  = $profile?->firstChoiceProgram?->name;
            $secondName = $profile?->secondChoiceProgram?->name;
            $thirdName  = $profile?->thirdChoiceProgram?->name;
        } else {
            $application->load(['program', 'secondChoice', 'thirdChoice']);
            $firstId   = $application->program_id;
            $secondId  = $application->second_choice_id;
            $thirdId   = $application->third_choice_id;
            $firstName  = $application->program?->name;
            $secondName = $application->secondChoice?->name;
            $thirdName  = $application->thirdChoice?->name;
        }

        return [
            'firstname' => $user->firstname,
            'middlename' => $user->middlename,
            'lastname' => $user->lastname,
            'sex' => $user->sex,
            'email' => $user->email,
            'schoolyear' => $graduateType,
            'dateGrad' => $profile?->date_graduated
                ? date('Y-m-d', strtotime((string) $profile->date_graduated))
                : null,
            'strand' => $profile->strand ?? null,
            'track' => $profile->track ?? null,
            'status' => $application?->status ?? null,
            'uploadedFiles' => FileMapper::formatFilesForGraduateType($files, $graduateType),
            'processes' => $this->getApplicationProcesses($application),
            'enrollment_status' => $application?->enrollment_status ?? null,
            'program_id'       => $firstId,
            'second_choice_id' => $secondId,
            'third_choice_id'  => $thirdId,
            'program_name'        => $firstName,
            'second_choice_name'  => $secondName,
            'third_choice_name'   => $thirdName,
            'requires_guidance_office' => (bool) ($application?->requires_guidance_office ?? false),
            'requires_admission_office' => (bool) ($application?->requires_admission_office ?? false),
            'show_medical_redirect' => $this->shouldShowMedicalRedirect($application),
            'show_f137_button'      => $this->shouldShowF137Button($application),
            'cutoff' => (function () use ($user) {
                $isOverrideAllowed = false;
                $testPasser = \App\Models\TestPasser::where('user_id', $user->id)->first();
                if ($testPasser) {
                    if ($this->cutoffSettingsService->isScoreAllowed((float) $testPasser->pupcet_total_score) || 
                        $this->cutoffSettingsService->isEmailAllowed($user->email)) {
                        $isOverrideAllowed = true;
                    }
                }
                return [
                    'is_passed' => $isOverrideAllowed ? false : $this->cutoffSettingsService->isCutoffPassed(),
                    'display'   => $isOverrideAllowed ? null : $this->cutoffSettingsService->formatForDisplay(),
                ];
            })(),
        ];
    }

    /**
     * Get application processes with performer information
     *
     * @param Application|null $application
     * @return array
     */
    private function getApplicationProcesses(?Application $application): array
    {
        if (!$application) {
            return [];
        }

        return $application
            ->processes()
            ->with('performedBy:id,firstname,lastname')
            ->orderBy('created_at')
            ->get(['stage', 'status', 'action', 'decision_reason', 'reviewer_notes', 'performed_by', 'created_at'])
            ->toArray();
    }

    /**
     * Check if medical redirect should be shown.
     * Returns true when both evaluator and interviewer stages are completed
     * but medical stage has not been completed yet (in_progress only).
     *
     * @param Application|null $application
     * @return bool
     */
    private function shouldShowMedicalRedirect(?Application $application): bool
    {
        if (!$application) {
            return false;
        }

        // Check if evaluator stage is completed
        $evaluatorCompleted = $application->processes()
            ->where('stage', 'grade_evaluator')
            ->where('status', 'completed')
            ->exists();

        // Check if interviewer stage is completed (with a passing action, not rejected)
        $interviewerCompleted = $application->processes()
            ->where('stage', 'interviewer')
            ->where('status', 'completed')
            ->whereIn('action', ['passed', 'transferred'])
            ->exists();

        // Check if medical stage is completed
        $medicalCompleted = $application->processes()
            ->where('stage', 'medical')
            ->where('status', 'completed')
            ->exists();

        // Show redirect only when evaluator and interviewer are done, but medical is not yet completed
        return $evaluatorCompleted && $interviewerCompleted && !$medicalCompleted;
    }

    /**
     * Check if the F137 download button should be shown.
     * Returns true when both evaluator and interviewer stages are completed
     * AND the medical stage exists (in_progress OR completed).
     *
     * @param Application|null $application
     * @return bool
     */
    private function shouldShowF137Button(?Application $application): bool
    {
        if (!$application) {
            return false;
        }

        $evaluatorCompleted = $application->processes()
            ->where('stage', 'grade_evaluator')
            ->where('status', 'completed')
            ->exists();

        $interviewerCompleted = $application->processes()
            ->where('stage', 'interviewer')
            ->where('status', 'completed')
            ->whereIn('action', ['passed', 'transferred'])
            ->exists();

        // Medical stage exists (in_progress or completed)
        $medicalExists = $application->processes()
            ->where('stage', 'medical')
            ->exists();

        return $evaluatorCompleted && $interviewerCompleted && $medicalExists;
    }

    /**
     * Check if application can be submitted
     *
     * @param Application $application
     * @return array
     */
    public function canSubmitApplication(Application $application): array
    {
        // Check for rejected files
        if ($application->files()->where('status', 'rejected')->exists()) {
            return [
                'can_submit' => false,
                'message' => 'Fix rejected files first',
                'status_code' => 422
            ];
        }

        // Check if already submitted
        if ($application->status === 'submitted') {
            return [
                'can_submit' => false,
                'message' => 'Already submitted',
                'status_code' => 409
            ];
        }

        return [
            'can_submit' => true,
            'message' => null,
            'status_code' => null
        ];
    }

    /**
     * Submit an application
     *
     * @param User $user
     * @param array $validated
     * @return Application
     * @throws \Exception
     */
    public function submitApplication(User $user, array $validated): Application
    {
        return DB::transaction(function () use ($user, $validated) {
            $isOverrideAllowed = false;
            $testPasser = $user->testPasser;
            if ($testPasser) {
                if ($this->cutoffSettingsService->isScoreAllowed((float) $testPasser->pupcet_total_score) ||
                    $this->cutoffSettingsService->isEmailAllowed($user->email)) {
                    $isOverrideAllowed = true;
                }
            }

            if (!$isOverrideAllowed && $this->cutoffSettingsService->isCutoffPassed()) {
                abort(422, 'The application submission period has closed.');
            }

            $profile = $user->applicantProfile;

            $application = Application::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'status' => 'draft',
                    'program_id' => $profile?->first_choice_program,
                    'second_choice_id' => $profile?->second_choice_program,
                    'third_choice_id' => $profile?->third_choice_program,
                ]
            );

            // Check if can submit
            $submissionCheck = $this->canSubmitApplication($application);
            if (!$submissionCheck['can_submit']) {
                abort($submissionCheck['status_code'], $submissionCheck['message']);
            }

            // Update application details
            $application->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'program_id' => $validated['program_id'],
                'second_choice_id' => $validated['second_choice_id'] ?? null,
                'third_choice_id' => $validated['third_choice_id'] ?? null,
            ]);

            // Sync program choices back to applicant_profiles so all views
            // that read from profile (e.g. firstChoiceProgram relationship) stay consistent.
            if ($profile) {
                $profile->update([
                    'first_choice_program'  => $validated['program_id'],
                    'second_choice_program' => $validated['second_choice_id'] ?? null,
                    'third_choice_program'  => $validated['third_choice_id'] ?? null,
                ]);
            }

            // Create new process for document_evaluator
            $application->processes()->create([
                'stage' => 'document_evaluator',
                'status' => 'in_progress',
                'performed_by' => null,
            ]);

            Log::info('Application submitted', [
                'user_id' => $user->id,
                'application_id' => $application->id,
                'program_id' => $validated['program_id'],
            ]);

            return $application;
        });
    }

    /**
     * Handle file reupload
     *
     * @param User $user
     * @param string $field
     * @param \Illuminate\Http\UploadedFile $uploadedFile
     * @return array
     * @throws \InvalidArgumentException
     */
    public function reuploadFile(User $user, string $field, $uploadedFile): array
    {
        $type = FileMapper::MAPPING[$field] ?? null;

        if (!$type) {
            throw new \InvalidArgumentException('Invalid field name');
        }

        // Get existing file path before uploading (for cleanup after)
        $existingFile = UserFile::where('user_id', (string) $user->id)
            ->where('type', $type)
            ->first();

        $existingFilePath = $existingFile?->file_path;

        // Mark the file as "uploading" in the DB BEFORE the actual storage operation.
        // This makes the backend authoritative — the frontend can poll this status
        // instead of relying on localStorage to detect in-progress uploads.
        $userFile = UserFile::updateOrCreate(
            [
                'user_id' => $user->id,
                'type' => $type,
            ],
            [
                'status' => 'uploading',
                'original_name' => $uploadedFile->getClientOriginalName(),
            ]
        );

        // Use FileService to store the file (streaming, no memory bloat)
        try {
            $compressed = $this->fileService->storeRaw($uploadedFile, 'uploads/files');
        } catch (\Throwable $e) {
            // Storage failed — mark as failed so frontend shows the correct state
            $userFile->update(['status' => 'failed']);

            Log::error('File storage failed during reupload', [
                'user_id' => $user->id,
                'field' => $field,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        // Prepare update data
        $updateData = [
            'file_path' => $compressed['path'],
            'original_name' => $compressed['original_name'],
            'status' => 'pending',
        ];

        // Finalize the file record with the stored path and 'pending' status
        try {
            $userFile->update($updateData);
        } catch (\Throwable $e) {
            // The Cleanup Safety Net:
            // If DB fails, delete the orphaned file from bucket and mark as failed
            try {
                $this->fileService->delete($compressed['path']);
            } catch (\Throwable $deleteError) {
                // Suppress cleanup error to prioritize reporting the actual DB crash
            }

            // Try to mark as failed (best-effort)
            try {
                $userFile->update(['status' => 'failed']);
            } catch (\Throwable $ignored) {}

            throw $e;
        }

        Log::info('File reuploaded', [
            'user_id' => $user->id,
            'field' => $field,
            'type' => $type,
        ]);

        // Delete old file AFTER success (non-blocking, don't let failure affect response)
        if ($existingFilePath && $existingFilePath !== $compressed['path']) {
            try {
                $this->fileService->delete($existingFilePath);
            } catch (\Throwable $e) {
                Log::warning('Failed to delete old file after reupload', [
                    'path' => $existingFilePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Refresh the model to get the latest state
        $userFile->refresh();

        return [
            'message' => 'File reuploaded successfully',
            'file' => FileMapper::buildFilePayload($userFile, true),
        ];
    }

    /**
     * Confirm a direct-to-S3 upload by recording the file path in the database.
     * Used when the client uploads directly to S3 via presigned URL.
     *
     * @param User $user
     * @param string $field
     * @param string $path
     * @param string $originalName
     * @return array
     * @throws \InvalidArgumentException
     */
    public function confirmDirectUpload(User $user, string $field, string $path, string $originalName): array
    {
        $type = FileMapper::MAPPING[$field] ?? null;

        if (!$type) {
            throw new \InvalidArgumentException('Invalid field name');
        }

        // Delete existing file
        $this->deleteExistingFile($user, $type);

        // Prepare update data
        $updateData = [
            'file_path' => $path,
            'original_name' => $originalName,
            'status' => 'pending',
        ];

        // Save new file record
        $userFile = UserFile::updateOrCreate(
            [
                'user_id' => $user->id,
                'type' => $type,
            ],
            $updateData
        );

        Log::info('Direct upload confirmed', [
            'user_id' => $user->id,
            'field' => $field,
            'type' => $type,
            'path' => $path,
        ]);

        return [
            'message' => 'File uploaded successfully',
            'file' => $userFile ? FileMapper::buildFilePayload($userFile, true) : null,
        ];
    }

    /**
     * Delete existing file for a user
     *
     * @param User $user
     * @param string $type
     * @return void
     */
    private function deleteExistingFile(User $user, string $type): void
    {
        $existingFile = UserFile::where('user_id', (string) $user->id)
            ->where('type', $type)
            ->first();

        if ($existingFile) {
            $this->fileService->delete($existingFile->file_path);
        }
    }

    /**
     * Get eligible programs based on user's grades
     *
     * @param User $user
     * @return array
     */
    public function getEligiblePrograms(User $user): array
    {
        $grades = $user->grades;
        $profile = $user->applicantProfile;

        if (!$this->hasCompleteGrades($grades)) {
            return [
                'programs' => [],
                'message' => 'Applicant grades are incomplete.'
            ];
        }

        $english = $grades->english;
        $math = $grades->mathematics;
        $science = $grades->science;
        $gwa = ($grades->g12_first_sem + $grades->g12_second_sem) / 2;
        $userStrand = strtoupper($profile?->strand ?? '');

        $programs = Program::with('strands')
            ->where(function ($query) use ($english, $math, $science, $gwa) {
                $query->where(function ($q) use ($english) {
                    $q->whereNull('english')->orWhereRaw('? >= english', [$english]);
                })
                    ->where(function ($q) use ($math) {
                        $q->whereNull('math')->orWhereRaw('? >= math', [$math]);
                    })
                    ->where(function ($q) use ($science) {
                        $q->whereNull('science')->orWhereRaw('? >= science', [$science]);
                    })
                    ->where(function ($q) use ($gwa) {
                        $q->whereNull('gwa')->orWhereRaw('? >= gwa', [$gwa]);
                    });
            })
            ->get()
            ->filter(function ($program) use ($userStrand) {
                // Check strand requirements
                if (!$userStrand) {
                    return true; // If no strand info, allow all
                }

                $strandNames = strtoupper($program->strand_names ?? '');
                
                // If no strand requirement, allow all
                if (empty($strandNames)) {
                    return true;
                }
                
                // If explicitly open to all strands
                if (str_contains($strandNames, 'OPEN TO ALL')) {
                    return true;
                }

                // Check if user's strand is in the allowed list
                $allowedStrands = array_map('trim', preg_split('/[,\/]/', $strandNames));
                
                foreach ($allowedStrands as $allowed) {
                    // Normalize strand names
                    if (str_contains($allowed, 'TECH-VOC') || str_contains($allowed, 'TVL')) {
                        $allowed = 'TVL';
                    }
                    
                    if ($allowed === $userStrand) {
                        return true;
                    }
                }
                
                // Check if "other with bridging" is mentioned
                if (str_contains($strandNames, 'OTHER') && str_contains($strandNames, 'BRIDGING')) {
                    return true;
                }
                
                return false;
            })
            ->values();

        return [
            'programs' => $programs
        ];
    }

    /**
     * Check if user has complete grades
     *
     * @param mixed $grades
     * @return bool
     */
    private function hasCompleteGrades($grades): bool
    {
        return $grades &&
            !is_null($grades->english) &&
            !is_null($grades->mathematics) &&
            !is_null($grades->science);
    }

    /**
     * Resubmit a returned application back to the evaluator stage.
     *
     * @param User $user
     * @return Application
     * @throws \Exception
     */
    public function resubmitApplication(User $user): Application
    {
        $isOverrideAllowed = false;
        $testPasser = $user->testPasser;
        if ($testPasser) {
            if ($this->cutoffSettingsService->isScoreAllowed((float) $testPasser->pupcet_total_score) ||
                $this->cutoffSettingsService->isEmailAllowed($user->email)) {
                $isOverrideAllowed = true;
            }
        }

        if (!$isOverrideAllowed && $this->cutoffSettingsService->isCutoffPassed()) {
            abort(422, 'The application submission period has closed.');
        }

        $application = Application::where('user_id', $user->id)
            ->whereIn('status', ['returned', 'rejected'])
            ->first();

        if (!$application) {
            abort(400, 'No returned or rejected application found to resubmit.');
        }

        // Block resubmission if any file is rejected or returned
        if ($application->files()->whereIn('status', ['rejected', 'returned'])->exists()) {
            abort(422, 'Your application has rejected or returned documents that must be fixed before resubmitting.');
        }

        return DB::transaction(function () use ($application) {
            // Reset application status back to submitted
            $application->status = 'submitted';
            $application->save();

            // Find the latest process that is 'returned' or 'rejected'
            $latestProcess = $application->processes()
                ->where(function($q) {
                    $q->where('status', 'returned')
                      ->orWhere(function($subq) {
                          $subq->where('status', 'completed')->where('action', 'rejected');
                      });
                })
                ->latest()
                ->first();
            
            // If it was returned/rejected by grade evaluator, we need to restart from document evaluator
            if ($latestProcess && $latestProcess->stage === 'grade_evaluator') {
                // Delete the grade evaluator process so it doesn't override the document evaluator status
                $application->processes()->where('stage', 'grade_evaluator')->delete();
                
                // Find document_evaluator process and reset it
                $docProcess = $application->processes()->where('stage', 'document_evaluator')->latest()->first();
                if ($docProcess) {
                    $docProcess->update([
                        'status' => 'in_progress',
                        'action' => null,
                        'reviewer_notes' => null,
                        'files_affected' => null,
                        'performed_by' => null,
                    ]);
                } else {
                    $application->processes()->create([
                        'stage' => 'document_evaluator',
                        'status' => 'in_progress',
                        'performed_by' => null,
                    ]);
                }
            } else if ($latestProcess && $latestProcess->stage === 'document_evaluator') {
                $latestProcess->update([
                    'status' => 'in_progress',
                    'action' => null,
                    'reviewer_notes' => null,
                    'files_affected' => null,
                ]);
            }

            // Reset all returned files back to pending
            UserFile::where('user_id', (string) $application->user_id)
                ->where('status', 'returned')
                ->update([
                    'status' => 'pending',
                    'comment' => null,
                ]);

            Log::info('Application resubmitted to evaluator', [
                'user_id' => $application->user_id,
                'application_id' => $application->id,
            ]);

            return $application;
        });
    }

    /**
     * Validate file field
     *
     * @param string $field
     * @return bool
     */
    public function isValidFileField(string $field): bool
    {
        return isset(FileMapper::MAPPING[$field]);
    }

    /**
     * Get valid file fields
     *
     * @return string
     */
    public function getValidFileFields(): string
    {
        return FileMapper::getValidFileFields();
    }
}
