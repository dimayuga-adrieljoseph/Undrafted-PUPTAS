<?php

namespace App\Listeners;

use App\Services\AuditLogService;
use Illuminate\Auth\Events\Logout;

class LogUserLogout
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function handle(Logout $event): void
    {
        if ($event->user) {
            $this->auditLogService->logLogout($event->user);
        }
    }
}
