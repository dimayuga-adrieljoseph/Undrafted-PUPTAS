<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class AuditLog extends Model
{
    // Action type constants
    const ACTION_LOGIN  = 'LOGIN';
    const ACTION_LOGOUT = 'LOGOUT';
    const ACTION_CREATE = 'CREATE';
    const ACTION_UPDATE = 'UPDATE';
    const ACTION_DELETE = 'DELETE';

    // High-level log type constants (Mock 1 categories)
    const TYPE_SYSTEM = 'SYSTEM';
    const TYPE_AUDIT = 'AUDIT';
    const TYPE_SECURITY = 'SECURITY';

    // Log category constants
    const CATEGORY_AUTHENTICATION   = 'AUTHENTICATION';
    const CATEGORY_USER_MANAGEMENT  = 'USER_MANAGEMENT';
    const CATEGORY_ADMISSION_DATA   = 'ADMISSION_DATA';
    const CATEGORY_AUDIT_ACCESS     = 'AUDIT_ACCESS';
    const CATEGORY_SYSTEM_OPERATION = 'SYSTEM_OPERATION';

    protected $fillable = [
        'user_id',
        'username',
        'user_role',
        'log_type',
        'log_category',
        'action_type',
        'module_name',
        'description',
        'login_time',
        'logout_time',
        'ip_address',
        'user_agent',
        'request_url',
        'session_id',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'login_time'  => 'datetime',
        'logout_time' => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
        'old_values'  => 'array',
        'new_values'  => 'array',
    ];

    // ─── Relationships ──────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────────

    /**
     * Filter logs by user ID.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Filter logs by calendar date (created_at).
     */
    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('created_at', $date);
    }

    /**
     * Order newest first.
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('id')->orderByDesc('created_at');
    }

    /**
     * Filter logs by high-level type.
     */
    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('log_type', strtoupper($type));
    }
}
