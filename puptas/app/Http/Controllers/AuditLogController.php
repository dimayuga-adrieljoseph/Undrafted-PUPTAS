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
}
