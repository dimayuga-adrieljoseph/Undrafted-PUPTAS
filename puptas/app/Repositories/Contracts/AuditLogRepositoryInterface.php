<?php

namespace App\Repositories\Contracts;

use App\Models\AuditLog;

interface AuditLogRepositoryInterface
{
    /**
     * Find the most recent security event (login/logout) for the user,
     * within the last N seconds, for dedup purposes.
     *
     * Filters by user_id when provided, otherwise by username.
     */
    public function findRecentSecurityEvent(
        string $actionType,
        ?string $userId,
        ?string $username,
        int $withinSeconds = 10,
    ): ?AuditLog;

    /**
     * Find the most recent open login (logout_time null) for the user.
     */
    public function findOpenLogin(?string $userId, ?string $username): ?AuditLog;

    /**
     * Create an audit log entry.
     */
    public function create(array $data): AuditLog;
}