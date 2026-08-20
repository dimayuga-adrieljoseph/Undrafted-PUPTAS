<?php

namespace App\Repositories\Contracts;

use App\Models\EmailLog;
use Illuminate\Support\Collection;

interface EmailLogRepositoryInterface
{
    /**
     * Create an email log record.
     */
    public function create(array $data): EmailLog;

    /**
     * Find an email log by ID or fail.
     */
    public function find(int $id): EmailLog;

    /**
     * First email log by resend message ID.
     */
    public function firstByResendMessageId(string $resendMessageId): ?EmailLog;

    /**
     * First email log by recipient email with unset message ID and open status.
     */
    public function firstPendingByRecipientEmail(string $recipientEmail): ?EmailLog;

    /**
     * Count of email logs for a bulk operation with a status.
     */
    public function countByBulkOperationStatus(int $bulkOperationId, string $status): int;

    /**
     * Eligible failed logs for retry.
     */
    public function eligibleForRetry(array $ids, int $maxRetryCount): Collection;

    /**
     * Failed log IDs for a bulk operation.
     *
     * @return array<int, int>
     */
    public function failedIdsByBulkOperation(int $bulkOperationId): array;
}