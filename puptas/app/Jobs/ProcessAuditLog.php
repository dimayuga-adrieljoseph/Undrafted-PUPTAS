<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAuditLog implements ShouldQueue
{
    use Queueable;

    /**
     * The log data array.
     *
     * @var array
     */
    protected $data;

    /**
     * Create a new job instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            \App\Models\AuditLog::create($this->data);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[ProcessAuditLog] Failed to write log asynchronously', [
                'action_type' => $this->data['action_type'] ?? null,
                'module_name' => $this->data['module_name'] ?? null,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
