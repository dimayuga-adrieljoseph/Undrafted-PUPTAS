<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
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
    public function logLogin(Authenticatable $user): AuditLog
    {
        $id = $user->id ?? $user->idp_user_id;
        $isNumericId = is_numeric($id);

        $query = AuditLog::where('action_type', AuditLog::ACTION_LOGIN)
            ->where('created_at', '>=', now()->subSeconds(10))
            ->latest();

        if ($isNumericId) {
            $query->where('user_id', $id);
        } else {
            $query->where('username', $user->email ?? 'system');
        }

        $existing = $query->first();

        if ($existing) {
            return $existing;
        }

        return $this->write([
            'user'         => $user,
            'log_type'     => AuditLog::TYPE_SECURITY,
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
    public function logLogout(Authenticatable $user): void
    {
        $id = $user->id ?? $user->idp_user_id;
        $isNumericId = is_numeric($id);

        $logoutQuery = AuditLog::where('action_type', AuditLog::ACTION_LOGOUT)
            ->where('created_at', '>=', now()->subSeconds(10))
            ->latest();

        if ($isNumericId) {
            $logoutQuery->where('user_id', $id);
        } else {
            $logoutQuery->where('username', $user->email ?? 'system');
        }

        if ($logoutQuery->first()) {
            return;
        }

        $openLoginQuery = AuditLog::where('action_type', AuditLog::ACTION_LOGIN)
            ->whereNull('logout_time')
            ->latest();

        if ($isNumericId) {
            $openLoginQuery->where('user_id', $id);
        } else {
            $openLoginQuery->where('username', $user->email ?? 'system');
        }

        $openLogin = $openLoginQuery->first();

        if ($openLogin) {
            $openLogin->update(['logout_time' => now()]);
        }

        // Write a discrete LOGOUT row
        $this->write([
            'user'         => $user,
            'log_type'     => AuditLog::TYPE_SECURITY,
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
     * @param Authenticatable|null   $actor        The acting user (falls back to Auth::user())
     * @param string|null $logCategory  AuditLog::CATEGORY_* constant
     */
    public function logActivity(
        string $actionType,
        string $moduleName,
        string $description,
        ?Authenticatable $actor = null,
        ?string $logCategory = null
    ): AuditLog {
        $actor = $actor ?? Auth::user();
        $actorId = $actor ? ($actor->id ?? $actor->idp_user_id) : 'null';

        logger()->info('[AuditLog] ' . strtoupper($actionType) . ' | ' . $moduleName . ' | user=' . $actorId . ' | ' . $description);

        return $this->write([
            'user'         => $actor,
            'action_type'  => strtoupper($actionType),
            'log_category' => $logCategory ?? AuditLog::CATEGORY_SYSTEM_OPERATION,
            'log_type'     => $this->resolveLogType($actionType, $logCategory, $moduleName),
            'module_name'  => $moduleName,
            'description'  => $description,
        ]);
    }

    // ─── Internal ───────────────────────────────────────────────────

    /**
     * Write a single audit log entry.
     * Swallows exceptions so logging never breaks the main flow.
     *
     * @param array{user: Authenticatable|null, action_type: string, module_name: string, description: string, login_time?: \Carbon\Carbon|null, logout_time?: \Carbon\Carbon|null} $data
     */
    private function write(array $data): AuditLog
    {
        try {
            $user = $data['user'] ?? null;
            $request = request();
            $sessionId = null;

            if ($request && method_exists($request, 'hasSession') && $request->hasSession()) {
                $sessionId = $request->session()->getId();
            }

            $id = $user ? ($user->id ?? $user->idp_user_id) : null;
            $isNumericId = $id !== null && is_numeric($id);

            return AuditLog::create([
                'user_id'      => $isNumericId ? $id : null,
                'username'     => $user?->email ?? 'system',
                'user_role'    => $this->resolveRole($user),
                'log_type'     => $data['log_type'] ?? $this->resolveLogType(
                    (string) ($data['action_type'] ?? ''),
                    $data['log_category'] ?? null,
                    (string) ($data['module_name'] ?? '')
                ),
                'log_category' => $data['log_category'] ?? null,
                'action_type'  => $data['action_type'],
                'module_name'  => $data['module_name'],
                'description'  => $data['description'],
                'login_time'   => $data['login_time'] ?? null,
                'logout_time'  => $data['logout_time'] ?? null,
                'ip_address'   => $request?->ip(),
                'user_agent'   => $this->truncate((string) ($request?->userAgent() ?? ''), 512),
                // Avoid storing sensitive query parameters (tokens/codes) in audit rows.
                'request_url'  => $this->truncate((string) ($request?->url() ?? ''), 512),
                'session_id'   => $sessionId,
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

    private function resolveRole(?Authenticatable $user): string
    {
        if (!$user) {
            return 'System';
        }

        return self::ROLE_NAMES[$user->role_id ?? 1] ?? "Role #" . ($user->role_id ?? 1);
    }

    private function fullName(Authenticatable $user): string
    {
        if (isset($user->name) && current(explode(' ', $user->name))) {
            return $user->name;
        }
        return trim(($user->firstname ?? '') . " " . ($user->lastname ?? '')) ?: ($user->email ?? 'Unknown User');
    }

    private function resolveLogType(string $actionType, ?string $logCategory, string $moduleName): string
    {
        $action = strtoupper($actionType);
        $category = strtoupper((string) $logCategory);
        $module = strtoupper($moduleName);

        if (in_array($action, [AuditLog::ACTION_LOGIN, AuditLog::ACTION_LOGOUT], true)
            || $category === AuditLog::CATEGORY_AUTHENTICATION
            || $module === 'AUTHENTICATION') {
            return AuditLog::TYPE_SECURITY;
        }

        if ($category === AuditLog::CATEGORY_SYSTEM_OPERATION || $category === 'SYSTEM_OPERATION') {
            return AuditLog::TYPE_SYSTEM;
        }

        return AuditLog::TYPE_AUDIT;
    }

    private function truncate(string $value, int $maxLength): string
    {
        if ($value === '' || strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength);
    }
}

