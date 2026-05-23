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

class SendWaitlistedEmail implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
            Mail::to($this->passer->email)
                ->send(new WaitlistedEmail($this->passer, $this->messageTemplate));

            if ($this->emailLogId) {
                app(EmailTrackingService::class)->markSent($this->emailLogId);
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
