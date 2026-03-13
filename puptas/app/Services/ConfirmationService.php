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
use Illuminate\Support\Facades\Storage;

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
     * Get confirmation data for a user
     *
     * @param User $user
     * @return array
     */
    public function getConfirmationData(User $user): array
    {
        $files = $user->files()->get()->keyBy('type');
        $application = $user->currentApplication;
        $profile = $user->applicantProfile;

        return [
            'firstname' => $user->firstname,
            'middlename' => $user->middlename,
            'lastname' => $user->lastname,
            'birthday' => $user->birthday,
            'sex' => $user->sex,
            'contactnumber' => $user->contactnumber,
            'street_address' => $user->street_address,
            'barangay' => $user->barangay,
            'city' => $user->city,
            'province' => $user->province,
            'postal_code' => $user->postal_code,
            'email' => $user->email,
            'school' => $profile->school ?? null,
            'schoolAdd' => $profile->school_address ?? null,
            'schoolyear' => $profile->school_year ?? null,
            'dateGrad' => $profile?->date_graduated
                ? date('Y-m-d', strtotime((string) $profile->date_graduated))
                : null,
            'strand' => $profile->strand ?? null,
            'track' => $profile->track ?? null,
            'status' => $application?->status ?? null,
            'uploadedFiles' => FileMapper::formatFiles($files),
            'processes' => $this->getApplicationProcesses($application),
            'enrollment_status' => $application?->enrollment_status ?? null,
            'program_id' => $application?->program_id ?? $profile?->first_choice_program,
            'second_choice_id' => $application?->second_choice_id ?? $profile?->second_choice_program,
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
            $profile = $user->applicantProfile;

            $application = Application::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'status' => 'draft',
                    'program_id' => $profile?->first_choice_program,
                    'second_choice_id' => $profile?->second_choice_program,
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
            ]);

            // Create the next in-flight process (evaluator)
            $application->processes()->create([
                'stage' => 'evaluator',
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

        $path = $uploadedFile->store('uploads/files', 'public');

        // Delete existing file
        $this->deleteExistingFile($user, $type);

        // Save new file record
        UserFile::updateOrCreate(
            [
                'user_id' => $user->id,
                'type' => $type,
            ],
            [
                'file_path' => $path,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'status' => 'pending',
            ]
        );

        $savedFile = UserFile::where('user_id', $user->id)
            ->where('type', $type)
            ->first();

        Log::info('File reuploaded', [
            'user_id' => $user->id,
            'field' => $field,
            'type' => $type,
        ]);

        return [
            'message' => 'File reuploaded successfully',
            'file' => $savedFile ? FileMapper::buildFilePayload($savedFile, true) : null,
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
        $existingFile = UserFile::where('user_id', $user->id)
            ->where('type', $type)
            ->first();

        if ($existingFile && Storage::disk('public')->exists($existingFile->file_path)) {
            Storage::disk('public')->delete($existingFile->file_path);
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

        if (!$this->hasCompleteGrades($grades)) {
            return [
                'programs' => [],
                'message' => 'Applicant grades are incomplete.'
            ];
        }

        $english = $grades->english;
        $math = $grades->mathematics;
        $science = $grades->science;

        $programs = Program::where(function ($query) use ($english, $math, $science) {
            $query->where(function ($q) use ($english) {
                $q->whereNull('english')->orWhereRaw('? >= english', [$english]);
            })
                ->where(function ($q) use ($math) {
                    $q->whereNull('math')->orWhereRaw('? >= math', [$math]);
                })
                ->where(function ($q) use ($science) {
                    $q->whereNull('science')->orWhereRaw('? >= science', [$science]);
                });
        })->get();

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
