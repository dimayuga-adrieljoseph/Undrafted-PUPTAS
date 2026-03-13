<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AuditLog;
use App\Models\User;
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
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
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

        $users = User::query()
            ->whereIn('id', AuditLog::query()->select('user_id')->whereNotNull('user_id')->distinct())
            ->orderBy('firstname')
            ->orderBy('lastname')
            ->get(['id', 'firstname', 'lastname', 'email']);

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
            ],
        ]);
    }

    /**
     * Display the details of a specific audit log.
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        // Authorization is handled by EnsureSuperAdmin middleware at route level
        
        $log = AuditLog::findOrFail($id);

        return response()->json([
            'log' => $log,
        ]);
    }

    /**
     * Lightweight endpoint to check for new audit logs.
     * Returns the latest log ID and total count for efficient polling.
     */
    public function checkNew(Request $request)
    {
        $sinceId = (int) $request->query('since_id', 0);
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date' => ['nullable', 'date'],
            'log_type' => ['nullable', 'in:' . implode(',', [
                AuditLog::TYPE_SYSTEM,
                AuditLog::TYPE_AUDIT,
                AuditLog::TYPE_SECURITY,
            ])],
        ]);

        $baseQuery = AuditLog::query();
        $this->applyFilters($baseQuery, $filters);

        $latestId = (clone $baseQuery)->max('id') ?? 0;
        $total = (clone $baseQuery)->count();

        $newLogIds = [];
        if ($sinceId > 0 && $latestId > $sinceId) {
            $newLogIds = (clone $baseQuery)
                ->where('id', '>', $sinceId)
                ->orderBy('id', 'desc')
                ->limit(50)
                ->pluck('id');
        }

        return response()->json([
            'latest_id' => $latestId,
            'total' => $total,
            'new_log_ids' => $newLogIds,
        ]);
    }

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['user_id'])) {
            $query->forUser((int) $filters['user_id']);
        }

        if (!empty($filters['date'])) {
            $query->forDate($filters['date']);
        }

        if (!empty($filters['log_type'])) {
            $query->forType($filters['log_type']);
        }
    }
}
