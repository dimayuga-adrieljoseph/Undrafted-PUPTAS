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

    /**
     * List active programs
     *
     * Returns the list of academic programs (ID, code, and name) offered by PUPTAS.
     *
     * Requires the `program-read` OAuth scope.
     *
     * @group Program Catalog
     * @authenticated
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 7,
     *       "code": "BSIT",
     *       "name": "Bachelor of Science in Information Technology"
     *     },
     *     {
     *       "id": 8,
     *       "code": "BSCS",
     *       "name": "Bachelor of Science in Computer Science"
     *     }
     *   ]
     * }
     */
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
