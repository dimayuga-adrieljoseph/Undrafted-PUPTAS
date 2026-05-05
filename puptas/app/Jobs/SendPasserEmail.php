<?php

namespace App\Jobs;

use App\Mail\TestPasserEmail;
use App\Models\TestPasser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPasserEmail implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the unique lock should be maintained.
     * If the worker is down longer than this, the lock will expire
     * and the job can be dispatched again.
     */
    public $uniqueFor = 3600;

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
    ) {}

    public function handle(): void
    {
        Mail::to($this->passer->email)
            ->send(new TestPasserEmail($this->passer, $this->personalizedMessage));
    }
}
