<?php

namespace App\Jobs;

use App\Mail\SarFormEmail;
use App\Models\SarGeneration;
use App\Models\TestPasser;
use App\Services\EmailTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendSarFormEmail implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the unique lock should be maintained.
     */
    public $uniqueFor = 3600;

    /**
     * One SAR email job per passer — prevents duplicate sends
     * even if the evaluator clicks "Send" multiple times while
     * the worker is down.
     */
    public function uniqueId(): string
    {
        return 'sar_email_' . $this->passer->test_passer_id;
    }

    public function __construct(
        public readonly TestPasser $passer,
        public readonly string $downloadUrl,
        public readonly int $sarGenerationId,
        public readonly ?int $emailLogId = null,
        public readonly ?int $bulkOperationId = null,
    ) {}

    public function handle(): void
    {
        try {
            Mail::to($this->passer->email)
                ->send(new SarFormEmail($this->passer, $this->downloadUrl));

            // Mark as sent only after the email is actually delivered
            SarGeneration::where('id', $this->sarGenerationId)->update([
                'sent_at'               => now(),
                'email_sent_successfully' => true,
            ]);

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
