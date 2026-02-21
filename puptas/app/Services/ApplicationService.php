<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Application Service
 * 
 * Handles business logic for application management.
 * Centralizes application-related queries and operations.
 */
class ApplicationService
{
    /**
     * Get application summary statistics
     *
     * @return array
     */
    public function getApplicationSummary(): array
    {
        return [
            'total' => Application::count(),
            'accepted' => Application::where('status', 'accepted')->count(),
            'pending' => Application::where('status', 'submitted')->count(),
            'returned' => Application::where('status', 'returned')->count(),
        ];
    }

    /**
     * Get application by user ID
     *
     * @param int $userId
     * @return Application
     */
    public function getApplicationByUserId(int $userId): Application
    {
        return Application::where('user_id', $userId)->firstOrFail();
    }

    /**
     * Update application status
     *
     * @param int $applicationId
     * @param string $status
     * @param array $additionalData
     * @return Application
     */
    public function updateApplicationStatus(int $applicationId, string $status, array $additionalData = []): Application
    {
        $application = Application::findOrFail($applicationId);
        
        $updateData = array_merge(['status' => $status], $additionalData);
        $application->update($updateData);
        
        return $application->fresh();
    }

    /**
     * Tag application as officially enrolled
     *
     * @param int $userId
     * @return Application
     */
    public function tagAsOfficiallyEnrolled(int $userId): Application
    {
        $application = $this->getApplicationByUserId($userId);

        $application->update([
            'status' => 'accepted',
            'enrollment_status' => 'officially_enrolled',
        ]);

        return $application->fresh();
    }

    /**
     * Check if a prerequisite stage is completed
     *
     * @param Application $application
     * @param string $stage
     * @return bool
     */
    public function isStageCompleted(Application $application, string $stage): bool
    {
        return $application->processes()
            ->where('stage', $stage)
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * Check if prerequisite stage is completed, abort if not
     *
     * @param Application $application
     * @param string $stage
     * @param string $errorMessage
     * @return void
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function ensureStageCompleted(Application $application, string $stage, ?string $errorMessage = null): void
    {
        if (!$this->isStageCompleted($application, $stage)) {
            $message = $errorMessage ?? "Cannot proceed - prerequisite {$stage} stage not completed.";
            abort(409, $message);
        }
    }

    /**
     * Complete application stage workflow
     *
     * @param int $applicationId
     * @param string $stage
     * @param int $processedBy
     * @param string|null $note
     * @return ApplicationProcess
     */
    public function completeStage(int $applicationId, string $stage, int $processedBy, ?string $note = null): ApplicationProcess
    {
        return DB::transaction(function () use ($applicationId, $stage, $processedBy, $note) {
            $process = ApplicationProcess::where('application_id', $applicationId)
                ->where('stage', $stage)
                ->whereIn('status', ['in_progress', 'returned'])
                ->firstOrFail();

            $process->update([
                'status' => 'completed',
                'processed_by' => $processedBy,
                'note' => $note,
                'completed_at' => now(),
            ]);

            return $process->fresh();
        });
    }

    /**
     * Create or update application process stage
     *
     * @param int $applicationId
     * @param string $stage
     * @param string $status
     * @param int|null $processedBy
     * @param string|null $note
     * @return ApplicationProcess
     */
    public function updateOrCreateProcess(
        int $applicationId, 
        string $stage, 
        string $status,
        ?int $processedBy = null,
        ?string $note = null
    ): ApplicationProcess {
        return DB::transaction(function () use ($applicationId, $stage, $status, $processedBy, $note) {
            $data = [
                'status' => $status,
            ];

            if ($processedBy !== null) {
                $data['processed_by'] = $processedBy;
            }

            if ($note !== null) {
                $data['note'] = $note;
            }

            if ($status === 'completed') {
                $data['completed_at'] = now();
            }

            return ApplicationProcess::updateOrCreate(
                [
                    'application_id' => $applicationId,
                    'stage' => $stage,
                ],
                $data
            );
        });
    }
}
