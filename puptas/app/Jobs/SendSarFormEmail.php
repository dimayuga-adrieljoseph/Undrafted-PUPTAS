<?php

namespace App\Jobs;

use App\Mail\SarFormEmail;
use App\Models\SarGeneration;
use App\Models\TestPasser;
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
    ) {}

    public function handle(): void
    {
        Mail::to($this->passer->email)
            ->send(new SarFormEmail($this->passer, $this->downloadUrl));

        // Mark as sent only after the email is actually delivered
        SarGeneration::where('id', $this->sarGenerationId)->update([
            'sent_at'               => now(),
            'email_sent_successfully' => true,
        ]);
    }
}
