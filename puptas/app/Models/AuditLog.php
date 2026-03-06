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
        'log_category',
        'action_type',
        'module_name',
        'description',
        'login_time',
        'logout_time',
    ];

    protected $casts = [
        'login_time'  => 'datetime',
        'logout_time' => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
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
        return $query->orderByDesc('created_at');
    }
}
