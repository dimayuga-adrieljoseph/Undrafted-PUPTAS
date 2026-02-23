<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\AuditLog;
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

            // Capture old state for audit trail
            $oldState = [
                'stage' => $currentStage,
                'status' => $currentProcess->status,
                'application_status' => $application->status,
            ];

            // Mark current stage as completed
            $currentProcess->update([
                'status' => 'completed',
                'performed_by' => $processedBy,
                'reviewer_notes' => $note,
            ]);

            // Create next stage if provided
            if ($nextStage) {
                ApplicationProcess::create([
                    'application_id' => $application->id,
                    'stage' => $nextStage,
                    'status' => 'in_progress',
                ]);
            }

            // Capture new state for audit trail
            $newState = [
                'stage' => $currentStage,
                'status' => 'completed',
                'next_stage' => $nextStage,
                'reviewer_notes' => $note,
                'performed_by_id' => $processedBy,
            ];

            // Audit log for stage progression
            try {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'model_type' => 'ApplicationProcess',
                    'model_id' => $currentProcess->id,
                    'action' => 'stage_passed',
                    'old_values' => $oldState,
                    'new_values' => $newState,
                    'ip_address' => request()->ip(),
                ]);
            } catch (\Exception $e) {
                logger()->error('Failed to create audit log for application stage progression', [
                    'application_id' => $application->id,
                    'stage' => $currentStage,
                    'error' => $e->getMessage()
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
            // Capture old state for audit trail
            $oldState = [
                'application_status' => $application->status,
                'stage' => $stage,
            ];

            // Update the application process for this stage
            $process = ApplicationProcess::where('application_id', $application->id)
                ->where('stage', $stage)
                ->whereIn('status', ['in_progress', 'completed'])
                ->first();

            if (!$process) {
                throw new \Exception("Cannot return application - no process record found for stage '{$stage}'.");
            }

            $oldState['process_status'] = $process->status;
            
            $process->update([
                'status' => 'returned',
                'performed_by' => $processedBy,
                'reviewer_notes' => $reason,
            ]);

            // Update application status
            $application->update([
                'status' => 'returned',
            ]);

            // Capture new state for audit trail
            $newState = [
                'application_status' => 'returned',
                'stage' => $stage,
                'process_status' => 'returned',
                'reason' => $reason,
                'performed_by_id' => $processedBy,
            ];

            // Audit log for application return
            try {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'model_type' => 'Application',
                    'model_id' => $application->id,
                    'action' => 'application_returned',
                    'old_values' => $oldState,
                    'new_values' => $newState,
                    'ip_address' => request()->ip(),
                ]);
            } catch (\Exception $e) {
                logger()->error('Failed to create audit log for application return', [
                    'application_id' => $application->id,
                    'stage' => $stage,
                    'error' => $e->getMessage()
                ]);
            }

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
