<?php

namespace App\Repositories\Eloquent;

use App\Models\EmailLog;
use App\Repositories\Contracts\EmailLogRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentEmailLogRepository implements EmailLogRepositoryInterface
{
    public function create(array $data): EmailLog
    {
        return EmailLog::create($data);
    }

    public function find(int $id): EmailLog
    {
        return EmailLog::findOrFail($id);
    }

    public function firstByResendMessageId(string $resendMessageId): ?EmailLog
    {
        return EmailLog::where('resend_message_id', $resendMessageId)->first();
    }

    public function firstPendingByRecipientEmail(string $recipientEmail): ?EmailLog
    {
        return EmailLog::where('recipient_email', $recipientEmail)
            ->whereNull('resend_message_id')
            ->whereIn('status', ['sent', 'pending'])
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function countByBulkOperationStatus(int $bulkOperationId, string $status): int
    {
        return EmailLog::where('bulk_operation_id', $bulkOperationId)
            ->where('status', $status)
            ->count();
    }

    public function eligibleForRetry(array $ids, int $maxRetryCount): Collection
    {
        return EmailLog::whereIn('id', $ids)
            ->where('status', 'failed')
            ->where('retry_count', '<', $maxRetryCount)
            ->get();
    }

    public function failedIdsByBulkOperation(int $bulkOperationId): array
    {
        return EmailLog::where('bulk_operation_id', $bulkOperationId)
            ->where('status', 'failed')
            ->pluck('id')
            ->toArray();
    }
}