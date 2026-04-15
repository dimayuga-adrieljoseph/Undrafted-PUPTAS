<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AuditLog;
use Inertia\Inertia;

/**
 * Controller for managing Audit Logs.
 * Only accessible by Superadmin users.
 */
class AuditLogController extends Controller
{
    /**
     * Display a listing of the audit logs.
     *
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'user_id' => ['nullable'], // changed from integer to nullable string support
            'date' => ['nullable', 'date'],
            'log_type' => ['nullable', 'in:' . implode(',', [
                AuditLog::TYPE_SYSTEM,
                AuditLog::TYPE_AUDIT,
                AuditLog::TYPE_SECURITY,
            ])],
        ]);

        $logsQuery = AuditLog::query();
        $this->applyFilters($logsQuery, $filters);

        $logs = $logsQuery
            ->latestFirst()
            ->paginate(25)
            ->withQueryString();

        // Extract users from the logs themselves since User model is gone
        $users = AuditLog::query()
            ->select('user_id as id', 'username as email')
            ->whereNotNull('user_id')
            ->groupBy('user_id', 'username')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'firstname' => $log->email, // fallback
                    'lastname' => '',
                    'email' => $log->email,
                ];
            });

        // Add a "System/API" option for logs without a user_id
        $hasSystemLogs = AuditLog::whereNull('user_id')->exists();
        if ($hasSystemLogs) {
            $users->prepend([
                'id' => 'system',
                'firstname' => 'System/API',
                'lastname' => '',
                'email' => 'system',
            ]);
        }

        return Inertia::render('SuperAdmin/Logs', [
            'logs' => $logs,
            'users' => $users,
            'logTypes' => [
                AuditLog::TYPE_SYSTEM,
                AuditLog::TYPE_AUDIT,
                AuditLog::TYPE_SECURITY,
            ],
            'filters' => [
                'user_id' => $filters['user_id'] ?? null,
                'date' => $filters['date'] ?? null,
                'log_type' => $filters['log_type'] ?? null,
            ]
        ]);
    }

    /**
     * Polling endpoint to check for new logs
     */
    public function checkNew(Request $request)
    {
        $sinceId = (int) $request->query('since_id', 0);
        $filters = $request->validate([
            'user_id' => ['nullable'], // changed from integer
            'date' => ['nullable', 'date'],
            'log_type' => ['nullable', 'in:' . implode(',', [
                AuditLog::TYPE_SYSTEM,
                AuditLog::TYPE_AUDIT,
                AuditLog::TYPE_SECURITY,
            ])],
        ]);

        $query = AuditLog::query()->where('id', '>', $sinceId);
        $this->applyFilters($query, $filters);

        $newLogIds = $query->pluck('id')->toArray();
        $total = AuditLog::count();

        return response()->json([
            'has_new' => count($newLogIds) > 0,
            'new_log_ids' => $newLogIds,
            'total' => $total,
        ]);
    }

    /**
     * Apply filters to the query.
     */
    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['user_id'])) {
            // Support filtering by 'system' for API/system logs
            if ($filters['user_id'] === 'system') {
                $query->whereNull('user_id');
            } else {
                $query->where('user_id', $filters['user_id']);
            }
        }

        if (!empty($filters['date'])) {
            $query->forDate($filters['date']);
        }

        if (!empty($filters['log_type'])) {
            $query->forType($filters['log_type']);
        }
    }
}
