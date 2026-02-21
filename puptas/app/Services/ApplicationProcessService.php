<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationProcess;
use Illuminate\Support\Facades\DB;

/**
 * Application Process Service
 * 
 * Handles business logic for application process stage management.
 * Manages workflow transitions between different stages.
 */
class ApplicationProcessService
{
    /**
     * Pass application to next stage
     *
     * @param Application $application
     * @param string $currentStage
     * @param string|null $nextStage
     * @param int $processedBy
     * @param string|null $note
     * @return ApplicationProcess
     * @throws \Exception
     */
    public function passApplication(
        Application $application,
        string $currentStage,
        ?string $nextStage,
        int $processedBy,
        ?string $note = null
    ): ApplicationProcess {
        return DB::transaction(function () use ($application, $currentStage, $nextStage, $processedBy, $note) {
            // Get the current stage process
            $currentProcess = ApplicationProcess::where('application_id', $application->id)
                ->where('stage', $currentStage)
                ->whereIn('status', ['in_progress', 'returned'])
                ->first();

            if (!$currentProcess) {
                throw new \Exception('This action has already been completed or is not available.');
            }

            // Mark current stage as completed
            $currentProcess->update([
                'status' => 'completed',
                'processed_by' => $processedBy,
                'note' => $note,
                'completed_at' => now(),
            ]);

            // Create next stage if provided
            if ($nextStage) {
                ApplicationProcess::create([
                    'application_id' => $application->id,
                    'stage' => $nextStage,
                    'status' => 'in_progress',
                ]);
            }

            return $currentProcess->fresh();
        });
    }

    /**
     * Return application for corrections
     *
     * @param Application $application
     * @param string $stage
     * @param int $processedBy
     * @param string $reason
     * @return Application
     */
    public function returnApplication(
        Application $application,
        string $stage,
        int $processedBy,
        string $reason
    ): Application {
        return DB::transaction(function () use ($application, $stage, $processedBy, $reason) {
            // Update the application process for this stage
            $process = ApplicationProcess::where('application_id', $application->id)
                ->where('stage', $stage)
                ->whereIn('status', ['in_progress', 'completed'])
                ->first();

            if ($process) {
                $process->update([
                    'status' => 'returned',
                    'processed_by' => $processedBy,
                    'note' => $reason,
                    'completed_at' => null,
                ]);
            }

            // Update application status
            $application->update([
                'status' => 'returned',
            ]);

            return $application->fresh();
        });
    }

    /**
     * Get process for a specific application and stage
     *
     * @param int $applicationId
     * @param string $stage
     * @return ApplicationProcess|null
     */
    public function getProcess(int $applicationId, string $stage): ?ApplicationProcess
    {
        return ApplicationProcess::where('application_id', $applicationId)
            ->where('stage', $stage)
            ->first();
    }

    /**
     * Check if stage is in progress
     *
     * @param int $applicationId
     * @param string $stage
     * @return bool
     */
    public function isStageInProgress(int $applicationId, string $stage): bool
    {
        return ApplicationProcess::where('application_id', $applicationId)
            ->where('stage', $stage)
            ->where('status', 'in_progress')
            ->exists();
    }

    /**
     * Check if stage is completed
     *
     * @param int $applicationId
     * @param string $stage
     * @return bool
     */
    public function isStageCompleted(int $applicationId, string $stage): bool
    {
        return ApplicationProcess::where('application_id', $applicationId)
            ->where('stage', $stage)
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * Get all processes for an application
     *
     * @param int $applicationId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getApplicationProcesses(int $applicationId)
    {
        return ApplicationProcess::where('application_id', $applicationId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Create initial process stage
     *
     * @param int $applicationId
     * @param string $stage
     * @param string $status
     * @return ApplicationProcess
     */
    public function createInitialStage(int $applicationId, string $stage, string $status = 'in_progress'): ApplicationProcess
    {
        return ApplicationProcess::create([
            'application_id' => $applicationId,
            'stage' => $stage,
            'status' => $status,
        ]);
    }
}
