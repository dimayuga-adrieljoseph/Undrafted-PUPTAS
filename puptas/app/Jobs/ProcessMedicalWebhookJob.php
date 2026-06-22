<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\ApplicantProfile;
use App\Models\AuditLog;
use App\Services\AuditLogService;

class ProcessMedicalWebhookJob implements ShouldQueue
{
    use Queueable;

    public $queue = 'high';

    protected array $payload;
    protected string $ipAddress;

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload, string $ipAddress)
    {
        $this->payload = $payload;
        $this->ipAddress = $ipAddress;
    }

    /**
     * Execute the job.
     */
    public function handle(AuditLogService $auditLogService): void
    {
        try {
            $referenceNumber = $this->payload['reference_number'] ?? null;
            $idpUserId = $this->payload['idp_user_id'] ?? $this->payload['student_id'] ?? null;
            $isHealthProfileCompleted = $this->payload['is_health_profile_completed'] ?? 0;

            $status = $isHealthProfileCompleted == 1 ? 'cleared' : 'failed';
            $lookupIdentifier = $referenceNumber ?: $idpUserId;

            $profile = null;
            
            if ($referenceNumber) {
                $profile = $this->getEligibleApplicantQuery()
                    ->whereHas('testPasser', function ($q) use ($referenceNumber) {
                        $q->where('reference_number', $referenceNumber);
                    })->first();
            }
            
            if (!$profile && $idpUserId) {
                $profile = $this->getEligibleApplicantQuery()
                    ->whereHas('user', function ($q) use ($idpUserId) {
                        $q->where('idp_user_id', $idpUserId);
                    })->first();
            }

            if (!$profile) {
                $auditLogService->logActivity(
                    'WEBHOOK_MISS',
                    'External Medical API Worker',
                    sprintf(
                        'Webhook received for ineligible or unknown student: %s (reference_number: %s, idp_user_id: %s) from IP %s.',
                        $lookupIdentifier,
                        $referenceNumber ?: 'not provided',
                        $idpUserId ?: 'not provided',
                        $this->ipAddress
                    ),
                    null,
                    AuditLog::CATEGORY_ADMISSION_DATA
                );
                return;
            }

            $application = $profile->currentApplication;
            $actionStr = $status === 'cleared' ? 'passed' : 'failed';
            $newAppStatus = $status === 'cleared' ? 'cleared_for_enrollment' : 'rejected';

            DB::transaction(function () use ($application, $newAppStatus, $actionStr) {
                $application->update(['status' => $newAppStatus]);
                
                $medicalProcess = $application->processes()
                    ->where('stage', 'medical')
                    ->whereIn('status', ['in_progress', 'returned'])
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($medicalProcess) {
                    $medicalProcess->update([
                        'status' => 'completed',
                        'action' => $actionStr,
                    ]);
                } else {
                    $application->processes()->create([
                        'stage' => 'medical',
                        'status' => 'completed',
                        'action' => $actionStr,
                        'performed_by' => null,
                    ]);
                }
            });

            $auditLogService->logActivity(
                'UPDATE',
                'External Medical API Worker',
                sprintf('Medical webhook processed: student %s marked as %s from IP %s.', $lookupIdentifier, $actionStr, $this->ipAddress),
                null,
                AuditLog::CATEGORY_ADMISSION_DATA
            );
        } catch (\Exception $e) {
            Log::error('Medical webhook job error: ' . $e->getMessage());
            $this->fail($e);
        }
    }

    private function getEligibleApplicantQuery()
    {
        return ApplicantProfile::with([
            'user' => function ($query) {
                $query->select('id', 'idp_user_id');
            },
            'testPasser',
            'currentApplication' => function ($query) {
                $query->select('applications.id', 'applications.user_id', 'applications.status', 'applications.created_at', 'applications.program_id');
            },
            'currentApplication.program' => function ($query) {
                $query->select('id', 'code', 'name');
            },
            'currentApplication.processes' => function ($query) {
                $query->where('stage', 'medical')
                    ->orderBy('created_at', 'desc')
                    ->select('id', 'application_id', 'stage', 'status', 'action', 'created_at');
            },
        ])
        ->whereHas('currentApplication', function ($query) {
            $query->join('application_processes as eval_proc', function ($join) {
                $join->on('eval_proc.application_id', '=', 'applications.id')
                    ->where('eval_proc.stage', '=', 'grade_evaluator')
                    ->where('eval_proc.status', '=', 'completed')
                    ->whereIn('eval_proc.action', ['passed', 'transferred']);
            })
            ->join('application_processes as int_proc', function ($join) {
                $join->on('int_proc.application_id', '=', 'applications.id')
                    ->where('int_proc.stage', '=', 'interviewer')
                    ->where('int_proc.status', '=', 'completed')
                    ->whereIn('int_proc.action', ['passed', 'transferred']);
            })
            ->join('application_processes as med_proc_active', function ($join) {
                $join->on('med_proc_active.application_id', '=', 'applications.id')
                    ->where('med_proc_active.stage', '=', 'medical')
                    ->whereIn('med_proc_active.status', ['in_progress', 'returned']);
            })
            ->leftJoin('application_processes as med_proc_completed', function ($join) {
                $join->on('med_proc_completed.application_id', '=', 'applications.id')
                    ->where('med_proc_completed.stage', '=', 'medical')
                    ->where('med_proc_completed.status', '=', 'completed')
                    ->whereIn('med_proc_completed.action', ['passed', 'transferred']);
            })
            ->whereNull('med_proc_completed.id');
        });
    }
}
