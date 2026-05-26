<?php

namespace App\Jobs;

use App\Mail\WaitlistedEmail;
use App\Models\TestPasser;
use App\Services\EmailTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\Middleware\RateLimited;

class SendWaitlistedEmail implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function middleware(): array
    {
        return [new RateLimited('emails')];
    }

    public $passer;
    public $messageTemplate;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        TestPasser $passer,
        $messageTemplate,
        public readonly ?int $emailLogId = null,
        public readonly ?int $bulkOperationId = null,
    ) {
        $this->passer = $passer;
        $this->messageTemplate = $messageTemplate;
        $this->onQueue('emails');
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'waitlisted-email-' . $this->passer->test_passer_id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $sentMessage = Mail::to($this->passer->email)
                ->send(new WaitlistedEmail($this->passer, $this->messageTemplate));

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
