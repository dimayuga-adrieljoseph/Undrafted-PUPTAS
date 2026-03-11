<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AuditLog;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

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
    public function index()
    {
        // Authorization is handled by EnsureSuperAdmin middleware at route level
        
        // Get paginated audit logs ordered by created_at DESC
        $logs = AuditLog::orderBy('created_at', 'desc')
            ->paginate(25);

        return Inertia::render('SuperAdmin/Logs', [
            'logs' => $logs,
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

        $latestId = AuditLog::max('id') ?? 0;
        $total = AuditLog::count();

        $newLogIds = [];
        if ($sinceId > 0 && $latestId > $sinceId) {
            $newLogIds = AuditLog::where('id', '>', $sinceId)
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
}
