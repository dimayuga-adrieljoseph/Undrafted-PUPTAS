<?php

namespace App\Jobs;

use App\Mail\WaitlistedEmail;
use App\Models\TestPasser;
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
    public function __construct(TestPasser $passer, $messageTemplate)
    {
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
        Mail::to($this->passer->email)
            ->send(new WaitlistedEmail($this->passer, $this->messageTemplate));
    }
}
