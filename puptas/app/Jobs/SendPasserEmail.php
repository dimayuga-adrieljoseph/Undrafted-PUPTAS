<?php

namespace App\Jobs;

use App\Mail\TestPasserEmail;
use App\Models\TestPasser;
use App\Services\EmailTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\Middleware\RateLimited;

class SendPasserEmail implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 15;
    public $maxExceptions = 3;
    public $backoff = [10, 30, 60];
    public $uniqueFor = 3600;

    public function middleware(): array
    {
        return [new RateLimited('emails')];
    }

    /**
     * One job per passer + template combination.
     * Using a hash of the template prevents locking out legitimate
     * re-sends with a different message (e.g. corrected content).
     */
    public function uniqueId(): string
    {
        return 'passer_email_' . $this->passer->test_passer_id . '_' . md5($this->personalizedMessage);
    }

    public function __construct(
        public readonly TestPasser $passer,
        public readonly string $personalizedMessage,
        public readonly ?int $emailLogId = null,
        public readonly ?int $bulkOperationId = null,
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        try {
            $sentMessage = Mail::to($this->passer->email)
                ->send(new TestPasserEmail($this->passer, $this->personalizedMessage));

            if ($this->emailLogId) {
                $resendMessageId = $this->extractResendMessageId($sentMessage);
                app(EmailTrackingService::class)->markSent($this->emailLogId, $resendMessageId);
            }
        } catch (\Throwable $e) {
            if ($this->emailLogId) {
                app(EmailTrackingService::class)->markFailed($this->emailLogId, $e->getMessage());
            }

            throw $e;
        } finally {
            if ($this->bulkOperationId) {
                app(EmailTrackingService::class)->updateBulkProgress($this->bulkOperationId);
            }
        }
    }

    /**
     * Extract the Resend message ID from the sent message headers.
     */
    private function extractResendMessageId($sentMessage): ?string
    {
        try {
            if ($sentMessage && method_exists($sentMessage, 'getOriginalMessage')) {
                $header = $sentMessage->getOriginalMessage()->getHeaders()->get('X-Resend-Email-ID');
                return $header?->getBodyAsString();
            }
        } catch (\Throwable $e) {
            // Don't block on header extraction failure
        }
        return null;
    }

    /**
     * Handle a job failure after all retries are exhausted.
     * This ensures the email log is marked as failed even when
     * the job fails at the queue level (timeout, max attempts, etc.).
     */
    public function failed(?\Throwable $exception): void
    {
        if ($this->emailLogId) {
            app(EmailTrackingService::class)->markFailed(
                $this->emailLogId,
                $exception?->getMessage() ?? 'Job failed permanently (max attempts exceeded or timeout)'
            );
        }

        if ($this->bulkOperationId) {
            app(EmailTrackingService::class)->updateBulkProgress($this->bulkOperationId);
        }
    }
}
