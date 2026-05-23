<?php

namespace App\Services;

use App\Models\BulkEmailOperation;
use App\Models\EmailLog;
use Illuminate\Support\Facades\DB;

/**
 * EmailTrackingService
 *
 * Centralised service for tracking bulk email operations and individual
 * email delivery statuses. All database operations are wrapped in
 * try/catch to avoid blocking email delivery on tracking failures.
 */
class EmailTrackingService
{
    /**
     * Create a new bulk email operation record.
     *
     * @param string   $emailType   The type of email (pupcet_result, sar_form, waitlisted, congratulations, user_created)
     * @param int      $totalCount  Total number of recipients in this operation
     * @param int|null $initiatedBy The user ID who initiated this operation
     * @param array    $meta        Optional metadata (batch_number, school_year)
     * @return BulkEmailOperation
     */
    public function createBulkOperation(string $emailType, int $totalCount, ?int $initiatedBy, array $meta = []): BulkEmailOperation
    {
        try {
            return BulkEmailOperation::create([
                'email_type'    => $emailType,
                'total_count'   => $totalCount,
                'pending_count' => $totalCount,
                'initiated_by'  => $initiatedBy,
                'batch_number'  => $meta['batch_number'] ?? null,
                'school_year'   => $meta['school_year'] ?? null,
                'started_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            logger()->error('[EmailTrackingService] Failed to create bulk operation', [
                'email_type'   => $emailType,
                'total_count'  => $totalCount,
                'initiated_by' => $initiatedBy,
                'error'        => $e->getMessage(),
            ]);

            // Return an unsaved model so callers can detect failure via $model->exists
            return new BulkEmailOperation();
        }
    }

    /**
     * Create an individual email log record within a bulk operation.
     *
     * @param int         $bulkOperationId The parent bulk operation ID
     * @param string      $email           Recipient email address
     * @param string|null $name            Recipient display name
     * @param int|null    $recipientId     Associated test_passer_id or user_id
     * @param string      $emailType       The type of email being sent
     * @param string|null $content         Stored HTML content for retry capability
     * @return EmailLog The created EmailLog, or an unsaved instance on failure (check $model->exists)
     */
    public function createEmailLog(int $bulkOperationId, string $email, ?string $name, ?int $recipientId, string $emailType, ?string $content = null): EmailLog
    {
        try {
            return EmailLog::create([
                'bulk_operation_id' => $bulkOperationId,
                'recipient_email'   => $email,
                'recipient_name'    => $name,
                'recipient_id'      => $recipientId,
                'email_type'        => $emailType,
                'email_content'     => $content,
                'status'            => 'pending',
            ]);
        } catch (\Throwable $e) {
            logger()->error('[EmailTrackingService] Failed to create email log', [
                'bulk_operation_id' => $bulkOperationId,
                'recipient_email'   => $email,
                'email_type'        => $emailType,
                'error'             => $e->getMessage(),
            ]);

            // Return an unsaved model so callers can detect failure via $model->exists
            return new EmailLog();
        }
    }

    /**
     * Mark an email log as successfully sent.
     *
     * Updates the status to 'sent' and records the sent timestamp.
     * Optionally stores the Resend message ID for webhook correlation.
     * Errors are logged without blocking email delivery.
     *
     * @param int         $emailLogId      The ID of the EmailLog record to update
     * @param string|null $resendMessageId The Resend API message ID for webhook tracking
     * @return void
     */
    public function markSent(int $emailLogId, ?string $resendMessageId = null): void
    {
        try {
            $emailLog = EmailLog::findOrFail($emailLogId);

            $updateData = [
                'status'  => 'sent',
                'sent_at' => now(),
            ];

            if ($resendMessageId) {
                $updateData['resend_message_id'] = $resendMessageId;
            }

            $emailLog->update($updateData);
        } catch (\Throwable $e) {
            logger()->error('[EmailTrackingService] Failed to mark email as sent', [
                'email_log_id' => $emailLogId,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a Resend webhook event and update the corresponding email log.
     *
     * Looks up the email log by resend_message_id and updates its status
     * based on the event type (delivered, bounced, complained, etc.).
     *
     * @param string $resendMessageId The Resend message ID from the webhook payload
     * @param string $eventType       The Resend event type (e.g. 'email.bounced')
     * @param array  $eventData       The full event data payload
     * @return bool Whether the email log was found and updated
     */
    public function handleResendWebhook(string $resendMessageId, string $eventType, array $eventData): bool
    {
        try {
            // Primary lookup: by stored Resend message ID
            $emailLog = EmailLog::where('resend_message_id', $resendMessageId)->first();

            // Fallback: match by recipient email if message ID not stored yet
            if (!$emailLog) {
                $recipientEmail = $eventData['to'][0] ?? ($eventData['email'] ?? null);

                if ($recipientEmail) {
                    $emailLog = EmailLog::where('recipient_email', $recipientEmail)
                        ->where('status', 'sent')
                        ->whereNull('resend_message_id')
                        ->orderBy('sent_at', 'desc')
                        ->first();

                    // Store the message ID for future webhook events on this email
                    if ($emailLog) {
                        $emailLog->update(['resend_message_id' => $resendMessageId]);
                    }
                }
            }

            if (!$emailLog) {
                logger()->warning('[EmailTrackingService] Webhook received for unknown message', [
                    'resend_message_id' => $resendMessageId,
                    'event_type'        => $eventType,
                    'recipient'         => $eventData['to'][0] ?? ($eventData['email'] ?? 'unknown'),
                ]);
                return false;
            }

            switch ($eventType) {
                case 'email.delivered':
                    // Already marked as sent, no status change needed
                    // but confirm delivery timestamp
                    $emailLog->update(['sent_at' => now()]);
                    break;

                case 'email.bounced':
                    $reason = $eventData['bounce']['message'] ?? 'Email bounced';
                    $emailLog->update([
                        'status'        => 'failed',
                        'failed_at'     => now(),
                        'error_message' => 'Bounced: ' . mb_substr($reason, 0, 1000),
                    ]);
                    $this->updateBulkProgressIfNeeded($emailLog);
                    break;

                case 'email.suppressed':
                    $reason = $eventData['reason'] ?? $eventData['message'] ?? 'Email suppressed by Resend';
                    $emailLog->update([
                        'status'        => 'failed',
                        'failed_at'     => now(),
                        'error_message' => 'Suppressed: ' . mb_substr($reason, 0, 1000),
                    ]);
                    $this->updateBulkProgressIfNeeded($emailLog);
                    break;

                case 'email.complained':
                    $emailLog->update([
                        'status'        => 'failed',
                        'failed_at'     => now(),
                        'error_message' => 'Recipient marked email as spam (complained)',
                    ]);
                    $this->updateBulkProgressIfNeeded($emailLog);
                    break;

                case 'email.delivery_delayed':
                    $emailLog->update([
                        'error_message' => 'Delivery delayed: ' . ($eventData['reason'] ?? 'unknown reason'),
                    ]);
                    break;

                default:
                    logger()->info('[EmailTrackingService] Unhandled Resend webhook event', [
                        'event_type'        => $eventType,
                        'resend_message_id' => $resendMessageId,
                    ]);
                    return false;
            }

            return true;
        } catch (\Throwable $e) {
            logger()->error('[EmailTrackingService] Failed to handle Resend webhook', [
                'resend_message_id' => $resendMessageId,
                'event_type'        => $eventType,
                'error'             => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Update bulk progress if the email log belongs to a bulk operation.
     */
    private function updateBulkProgressIfNeeded(EmailLog $emailLog): void
    {
        if ($emailLog->bulk_operation_id) {
            $this->updateBulkProgress($emailLog->bulk_operation_id);
        }
    }

    /**
     * Mark an email log as failed.
     *
     * Updates the status to 'failed', records the failure timestamp,
     * and stores the error message truncated to 1024 characters.
     * Errors are logged without blocking email delivery.
     *
     * @param int    $emailLogId   The ID of the EmailLog record to update
     * @param string $errorMessage The error message describing the failure
     * @return void
     */
    public function markFailed(int $emailLogId, string $errorMessage): void
    {
        try {
            $emailLog = EmailLog::findOrFail($emailLogId);
            $emailLog->update([
                'status'        => 'failed',
                'failed_at'     => now(),
                'error_message' => mb_substr($errorMessage, 0, 1024),
            ]);
        } catch (\Throwable $e) {
            logger()->error('[EmailTrackingService] Failed to mark email as failed', [
                'email_log_id' => $emailLogId,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    /**
     * Atomically recalculate and update bulk operation progress counts.
     *
     * Uses a database transaction to query the actual email_logs table
     * for current counts by status, then updates the BulkEmailOperation
     * record. When pending_count reaches 0, transitions the operation
     * status to 'completed' (all sent) or 'completed_with_failures'
     * (some failed), and sets the completed_at timestamp.
     *
     * Errors are logged without blocking email delivery.
     *
     * @param int $bulkOperationId The ID of the BulkEmailOperation to update
     * @return void
     */
    public function updateBulkProgress(int $bulkOperationId): void
    {
        try {
            DB::transaction(function () use ($bulkOperationId) {
                $sentCount = EmailLog::where('bulk_operation_id', $bulkOperationId)
                    ->where('status', 'sent')
                    ->count();

                $failedCount = EmailLog::where('bulk_operation_id', $bulkOperationId)
                    ->where('status', 'failed')
                    ->count();

                $pendingCount = EmailLog::where('bulk_operation_id', $bulkOperationId)
                    ->where('status', 'pending')
                    ->count();

                $updateData = [
                    'sent_count'   => $sentCount,
                    'failed_count' => $failedCount,
                    'pending_count' => $pendingCount,
                ];

                if ($pendingCount === 0) {
                    $updateData['status'] = $failedCount === 0
                        ? 'completed'
                        : 'completed_with_failures';
                    $updateData['completed_at'] = now();
                }

                BulkEmailOperation::where('id', $bulkOperationId)->update($updateData);
            });
        } catch (\Throwable $e) {
            logger()->error('[EmailTrackingService] Failed to update bulk progress', [
                'bulk_operation_id' => $bulkOperationId,
                'error'             => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the current progress of a bulk email operation.
     *
     * Returns an associative array with the operation's id, status,
     * total_count, sent_count, failed_count, pending_count, and completed_at.
     * Returns an empty array if the operation is not found or on failure.
     *
     * @param int $bulkOperationId The ID of the BulkEmailOperation to query
     * @return array{id: int, status: string, total_count: int, sent_count: int, failed_count: int, pending_count: int, completed_at: string|null}|array{}
     */
    public function getBulkOperationProgress(int $bulkOperationId): array
    {
        try {
            $operation = BulkEmailOperation::findOrFail($bulkOperationId);

            return [
                'id'            => $operation->id,
                'status'        => $operation->status,
                'total_count'   => $operation->total_count,
                'sent_count'    => $operation->sent_count,
                'failed_count'  => $operation->failed_count,
                'pending_count' => $operation->pending_count,
                'completed_at'  => $operation->completed_at?->toISOString(),
            ];
        } catch (\Throwable $e) {
            logger()->error('[EmailTrackingService] Failed to get bulk operation progress', [
                'bulk_operation_id' => $bulkOperationId,
                'error'             => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Retry failed email log records by resetting them to pending status.
     *
     * Queries EmailLog records matching the given IDs that have a status of
     * 'failed' and a retry_count below the configured maximum. For each
     * eligible record, resets status to 'pending', clears error_message and
     * failed_at, and increments retry_count. Also transitions the parent
     * BulkEmailOperation back to 'in_progress' if it was in a terminal state.
     *
     * @param array $emailLogIds Array of EmailLog IDs to retry
     * @return int The number of records successfully reset for retry
     */
    public function retryFailedEmails(array $emailLogIds): int
    {
        try {
            $maxRetryCount = config('email-tracking.max_retry_count', 3);

            $eligibleLogs = EmailLog::whereIn('id', $emailLogIds)
                ->where('status', 'failed')
                ->where('retry_count', '<', $maxRetryCount)
                ->get();

            if ($eligibleLogs->isEmpty()) {
                return 0;
            }

            $retriedCount = 0;

            DB::transaction(function () use ($eligibleLogs, &$retriedCount) {
                $bulkOperationIds = [];

                foreach ($eligibleLogs as $emailLog) {
                    $emailLog->update([
                        'status'        => 'pending',
                        'error_message' => null,
                        'failed_at'     => null,
                        'retry_count'   => $emailLog->retry_count + 1,
                    ]);

                    $retriedCount++;

                    if ($emailLog->bulk_operation_id) {
                        $bulkOperationIds[$emailLog->bulk_operation_id] = true;
                    }
                }

                // Transition parent BulkEmailOperations back to 'in_progress' if in terminal state
                if (!empty($bulkOperationIds)) {
                    BulkEmailOperation::whereIn('id', array_keys($bulkOperationIds))
                        ->whereIn('status', ['completed', 'completed_with_failures'])
                        ->update([
                            'status'       => 'in_progress',
                            'completed_at' => null,
                        ]);
                }
            });

            return $retriedCount;
        } catch (\Throwable $e) {
            logger()->error('[EmailTrackingService] Failed to retry failed emails', [
                'email_log_ids' => $emailLogIds,
                'error'         => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Retry all failed email logs for a given bulk operation.
     *
     * Queries all EmailLog IDs with status 'failed' belonging to the specified
     * bulk operation and delegates to retryFailedEmails() for processing.
     *
     * @param int $bulkOperationId The ID of the BulkEmailOperation whose failed logs should be retried
     * @return int The number of records successfully reset for retry
     */
    public function retryAllFailed(int $bulkOperationId): int
    {
        try {
            $failedLogIds = EmailLog::where('bulk_operation_id', $bulkOperationId)
                ->where('status', 'failed')
                ->pluck('id')
                ->toArray();

            if (empty($failedLogIds)) {
                return 0;
            }

            return $this->retryFailedEmails($failedLogIds);
        } catch (\Throwable $e) {
            logger()->error('[EmailTrackingService] Failed to retry all failed emails for operation', [
                'bulk_operation_id' => $bulkOperationId,
                'error'             => $e->getMessage(),
            ]);

            return 0;
        }
    }
}
