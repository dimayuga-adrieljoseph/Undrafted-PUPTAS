<?php

namespace App\Jobs;

use App\Services\IdpService;
use App\Services\AuditLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateIdpAccountType implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * The user ID to update
     *
     * @var int
     */
    protected $userId;

    /**
     * The IDP user ID
     *
     * @var string
     */
    protected $idpUserId;

    /**
     * The user email (for logging)
     *
     * @var string
     */
    protected $userEmail;

    /**
     * The admin access token
     *
     * @var string
     */
    protected $accessToken;

    /**
     * Optional MFA code
     *
     * @var string|null
     */
    protected $mfaCode;

    /**
     * The admin user ID who initiated this
     *
     * @var int|null
     */
    protected $performedBy;

    /**
     * Whether this is from sync command
     *
     * @var bool
     */
    protected $isFromSync;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $userId,
        string $idpUserId,
        string $userEmail,
        string $accessToken,
        ?string $mfaCode = null,
        ?int $performedBy = null,
        bool $isFromSync = false
    ) {
        $this->userId = $userId;
        $this->idpUserId = $idpUserId;
        $this->userEmail = $userEmail;
        $this->accessToken = $accessToken;
        $this->mfaCode = $mfaCode;
        $this->performedBy = $performedBy;
        $this->isFromSync = $isFromSync;
    }

    /**
     * Execute the job.
     */
    public function handle(IdpService $idpService, AuditLogService $auditLogService): void
    {
        Log::info('Processing IDP account type update job', [
            'user_id' => $this->userId,
            'idp_user_id' => $this->idpUserId,
            'is_from_sync' => $this->isFromSync,
            'attempt' => $this->attempts(),
        ]);

        // Account type ID 3 = student
        $result = $idpService->updateUserAccountType(
            $this->idpUserId,
            3,
            $this->accessToken,
            $this->mfaCode
        );

        if ($result['success']) {
            Log::info('Successfully updated IDP account type via queue', [
                'user_id' => $this->userId,
                'idp_user_id' => $this->idpUserId,
            ]);

            // Log audit trail
            try {
                $message = $this->isFromSync
                    ? "Backfilled IDP account type to student for user {$this->userId} (Email: {$this->userEmail}, IDP ID: {$this->idpUserId}) via queued sync"
                    : "Updated IDP account type to student for user {$this->userId} via queue";

                $targetUser = \App\Models\User::find($this->userId);
                
                $auditLogService->logActivity(
                    'UPDATE',
                    'IDP Account Type',
                    $message,
                    $targetUser, // Log the student being synced, not the admin
                    \App\Models\AuditLog::CATEGORY_SYSTEM_CHANGE
                );
            } catch (\Exception $e) {
                Log::warning('Failed to create audit log for queued IDP update', [
                    'user_id' => $this->userId,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            $error = $result['error'] ?? 'UNKNOWN';
            $message = $result['message'] ?? 'Unknown error';

            Log::warning('Failed to update IDP account type via queue', [
                'user_id' => $this->userId,
                'idp_user_id' => $this->idpUserId,
                'error' => $error,
                'message' => $message,
                'attempt' => $this->attempts(),
            ]);

            // Log failure in audit trail
            try {
                $failureMessage = $this->isFromSync
                    ? "IDP sync failed for user {$this->userId} (Email: {$this->userEmail}): {$error} - {$message}"
                    : "Failed to update IDP account type for user {$this->userId}: {$error} - {$message}";

                $targetUser = \App\Models\User::find($this->userId);

                $auditLogService->logActivity(
                    $error === 'MFA_REQUIRED' ? 'ATTEMPTED' : 'FAILED',
                    'IDP Account Type',
                    $failureMessage,
                    $targetUser, // Log the student that failed, not the admin
                    \App\Models\AuditLog::CATEGORY_SYSTEM_CHANGE
                );
            } catch (\Exception $e) {
                Log::warning('Failed to create audit log for failed queued IDP update', [
                    'user_id' => $this->userId,
                    'error' => $e->getMessage(),
                ]);
            }

            // If MFA required or user not found, don't retry
            if (in_array($error, ['MFA_REQUIRED', 'USER_NOT_FOUND', 'INVALID_ACCOUNT_TYPE'])) {
                Log::info('Not retrying job due to error type', [
                    'error' => $error,
                    'user_id' => $this->userId,
                ]);
                return; // Don't throw, job completes
            }

            // For other errors, throw to trigger retry
            if ($this->attempts() < $this->tries) {
                throw new \Exception("IDP update failed: {$error} - {$message}");
            }

            // Max retries reached
            Log::error('Max retries reached for IDP account type update', [
                'user_id' => $this->userId,
                'idp_user_id' => $this->idpUserId,
                'error' => $error,
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('IDP account type update job failed permanently', [
            'user_id' => $this->userId,
            'idp_user_id' => $this->idpUserId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
