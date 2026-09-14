<?php

namespace App\Repositories\Eloquent;

use App\Models\AuditLog;
use App\Repositories\Contracts\AuditLogRepositoryInterface;

class EloquentAuditLogRepository implements AuditLogRepositoryInterface
{
    public function findRecentSecurityEvent(
        string $actionType,
        ?string $userId,
        ?string $username,
        int $withinSeconds = 10,
    ): ?AuditLog {
        $query = AuditLog::where('action_type', $actionType)
            ->where('created_at', '>=', now()->subSeconds($withinSeconds))
            ->latest();

        $this->applyUserFilter($query, $userId, $username);

        return $query->first();
    }

    public function findOpenLogin(?string $userId, ?string $username): ?AuditLog
    {
        $query = AuditLog::where('action_type', AuditLog::ACTION_LOGIN)
            ->whereNull('logout_time')
            ->latest();

        $this->applyUserFilter($query, $userId, $username);

        return $query->first();
    }

    public function create(array $data): AuditLog
    {
        return AuditLog::create($data);
    }

    private function applyUserFilter($query, ?string $userId, ?string $username): void
    {
        if ($userId !== null) {
            $query->where('user_id', $userId);
        } elseif ($username !== null && $username !== '') {
            $query->where('username', $username);
        }
    }
}