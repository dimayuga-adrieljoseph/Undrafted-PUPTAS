<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * AuditLogService
 *
 * Centralised, reusable service for writing audit trail entries.
 * Use this class from controllers, listeners, or any service layer —
 * never write to audit_logs directly outside this class.
 */
class AuditLogService
{
    // ─── Role name map (mirrors UserService::getRoleDefinitions) ────

    private const ROLE_NAMES = [
        1 => 'Applicant',
        2 => 'Admin',
        3 => 'Evaluator',
        4 => 'Interviewer',
        5 => 'Medical',
        6 => 'Registrar',
        7 => 'Superadmin',
    ];

    // ─── Public helpers ─────────────────────────────────────────────

    /**
     * Record a LOGIN event for the given user.
     * Deduplicates within a 10-second window to handle Fortify firing
     * the Login event twice in a single request.
     */
    public function logLogin(User $user): AuditLog
    {
        // Deduplicate: if a LOGIN was already recorded for this user in the last 10 seconds, skip
        $existing = AuditLog::where('user_id', $user->id)
            ->where('action_type', AuditLog::ACTION_LOGIN)
            ->where('created_at', '>=', now()->subSeconds(10))
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->write([
            'user'         => $user,
            'action_type'  => AuditLog::ACTION_LOGIN,
            'log_category' => AuditLog::CATEGORY_AUTHENTICATION,
            'module_name'  => 'Authentication',
            'description'  => "User {$this->fullName($user)} logged in.",
            'login_time'   => now(),
        ]);
    }

    /**
     * Record a LOGOUT event for the given user.
     * Stamps logout_time on the most-recent open LOGIN session (keeps action_type=LOGIN)
     * and also writes a discrete LOGOUT row for chronological clarity.
     */
    public function logLogout(User $user): void
    {
        // Deduplicate: skip if a LOGOUT was already written for this user in the last 10 seconds
        $recentLogout = AuditLog::where('user_id', $user->id)
            ->where('action_type', AuditLog::ACTION_LOGOUT)
            ->where('created_at', '>=', now()->subSeconds(10))
            ->latest()
            ->first();

        if ($recentLogout) {
            return;
        }

        // Stamp the open login session
        $openLogin = AuditLog::where('user_id', $user->id)
            ->where('action_type', AuditLog::ACTION_LOGIN)
            ->whereNull('logout_time')
            ->latest()
            ->first();

        if ($openLogin) {
            // Only stamp logout_time — do NOT change action_type so the LOGIN row stays as LOGIN
            $openLogin->update(['logout_time' => now()]);
        }

        // Write a discrete LOGOUT row
        $this->write([
            'user'         => $user,
            'action_type'  => AuditLog::ACTION_LOGOUT,
            'log_category' => AuditLog::CATEGORY_AUTHENTICATION,
            'module_name'  => 'Authentication',
            'description'  => "User {$this->fullName($user)} logged out.",
            'logout_time'  => now(),
        ]);
    }

    /**
     * Log a generic CRUD activity.
     *
     * @param string      $actionType   AuditLog::ACTION_CREATE | UPDATE | DELETE
     * @param string      $moduleName   e.g. 'Users', 'Programs'
     * @param string      $description  Human-readable description
     * @param User|null   $actor        The acting user (falls back to Auth::user())
     * @param string|null $logCategory  AuditLog::CATEGORY_* constant
     */
    public function logActivity(
        string $actionType,
        string $moduleName,
        string $description,
        ?User $actor = null,
        ?string $logCategory = null
    ): AuditLog {
        $actor = $actor ?? Auth::user();

        logger()->info('[AuditLog] ' . strtoupper($actionType) . ' | ' . $moduleName . ' | user=' . ($actor?->id ?? 'null') . ' | ' . $description);

        return $this->write([
            'user'         => $actor,
            'action_type'  => strtoupper($actionType),
            'log_category' => $logCategory ?? AuditLog::CATEGORY_SYSTEM_OPERATION,
            'module_name'  => $moduleName,
            'description'  => $description,
        ]);
    }

    // ─── Internal ───────────────────────────────────────────────────

    /**
     * Write a single audit log entry.
     * Swallows exceptions so logging never breaks the main flow.
     *
     * @param array{user: User|null, action_type: string, module_name: string, description: string, login_time?: \Carbon\Carbon|null, logout_time?: \Carbon\Carbon|null} $data
     */
    private function write(array $data): AuditLog
    {
        try {
            $user = $data['user'] ?? null;

            return AuditLog::create([
                'user_id'      => $user?->id,
                'username'     => $user?->email ?? 'system',
                'user_role'    => $this->resolveRole($user),
                'log_category' => $data['log_category'] ?? null,
                'action_type'  => $data['action_type'],
                'module_name'  => $data['module_name'],
                'description'  => $data['description'],
                'login_time'   => $data['login_time'] ?? null,
                'logout_time'  => $data['logout_time'] ?? null,
            ]);
        } catch (\Throwable $e) {
            logger()->error('[AuditLogService] Failed to write log', [
                'action_type' => $data['action_type'] ?? null,
                'module_name' => $data['module_name'] ?? null,
                'error'       => $e->getMessage(),
            ]);

            // Return an unsaved (empty) model so callers never break
            return new AuditLog();
        }
    }

    private function resolveRole(?User $user): string
    {
        if (!$user) {
            return 'System';
        }

        return self::ROLE_NAMES[$user->role_id] ?? "Role #{$user->role_id}";
    }

    private function fullName(User $user): string
    {
        return trim("{$user->firstname} {$user->lastname}") ?: $user->email;
    }
}
