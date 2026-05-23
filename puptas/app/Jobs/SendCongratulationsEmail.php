<?php

namespace App\Jobs;

use App\Mail\CongratulationsMail;
use App\Services\EmailTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCongratulationsEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly string $email,
        public readonly int $emailLogId,
        public readonly int $bulkOperationId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $sentMessage = Mail::to($this->email)->send(new CongratulationsMail($this->email));

            $resendMessageId = $this->extractResendMessageId($sentMessage);
            app(EmailTrackingService::class)->markSent($this->emailLogId, $resendMessageId);
        } catch (\Throwable $e) {
            app(EmailTrackingService::class)->markFailed($this->emailLogId, $e->getMessage());

            throw $e;
        } finally {
            app(EmailTrackingService::class)->updateBulkProgress($this->bulkOperationId);
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
     */
    public function failed(?\Throwable $exception): void
    {
        app(EmailTrackingService::class)->markFailed(
            $this->emailLogId,
            $exception?->getMessage() ?? 'Job failed permanently (max attempts exceeded or timeout)'
        );

        app(EmailTrackingService::class)->updateBulkProgress($this->bulkOperationId);
    }
}
