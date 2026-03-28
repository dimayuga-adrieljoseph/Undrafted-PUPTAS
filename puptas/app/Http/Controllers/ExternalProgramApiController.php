<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Program;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalProgramApiController extends Controller
{
    public function __construct(private AuditLogService $auditLogService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $programs = Program::select('id', 'code', 'name')
            ->get()
            ->makeHidden(['strand_names', 'strands']);

        $this->auditLogService->logActivity(
            'READ',
            'External API',
            sprintf(
                'External programs list requested from IP %s.',
                $request->ip() ?? 'unknown'
            ),
            null,
            AuditLog::CATEGORY_ADMISSION_DATA
        );

        return response()->json([
            'data' => $programs,
        ]);
    }
}
