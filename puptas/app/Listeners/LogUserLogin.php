<?php

namespace App\Listeners;

use App\Services\AuditLogService;
use Illuminate\Auth\Events\Login;

class LogUserLogin
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function handle(Login $event): void
    {
        $this->auditLogService->logLogin($event->user);
    }
}
