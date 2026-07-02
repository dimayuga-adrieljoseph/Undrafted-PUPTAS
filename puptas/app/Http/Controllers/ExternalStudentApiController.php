<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalStudentApiController extends Controller
{
    public function __construct(private AuditLogService $auditLogService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->auditLogService->logActivity(
            'DEPRECATED_ENDPOINT',
            'External API',
            sprintf(
                'Deprecated list endpoint /api/v1/students called from IP %s.',
                $request->ip() ?? 'unknown'
            ),
            null,
            AuditLog::CATEGORY_ADMISSION_DATA
        );

        return response()->json([
            'message' => 'This endpoint is deprecated. Use /api/v1/students/{referenceNumber}.',
        ])->withHeaders([
            'Deprecation' => 'true',
            'Sunset' => 'Tue, 30 Jun 2026 23:59:59 GMT',
            'Link' => '</api/v1/students/{referenceNumber}>; rel="successor-version"',
        ])->setStatusCode(410);
    }

    public function showByReferenceNumber(Request $request, string $referenceNumber): JsonResponse
    {
        $application = Application::query()
            ->with(['user.grades', 'program', 'user.testPasser'])
            ->where('enrollment_status', 'officially_enrolled')
            ->whereHas('user.testPasser', function ($query) use ($referenceNumber) {
                $query->where('reference_number', $referenceNumber);
            })
            ->first();

        if (! $application || ! $application->user) {
            $this->auditLogService->logActivity(
                'READ_MISS',
                'External API',
                sprintf(
                    'External student lookup miss for reference_number %s from IP %s.',
                    $referenceNumber,
                    $request->ip() ?? 'unknown'
                ),
                null,
                AuditLog::CATEGORY_ADMISSION_DATA
            );

            return response()->json([
                'message' => 'Student not found',
            ], 404);
        }

        $user = $application->user;
        $program = $application->program;
        $grades = $user->grades;

        // Calculate GWA (General Weighted Average) from G12 grades
        $g12_gwa = null;
        if ($grades && $grades->g12_first_sem && $grades->g12_second_sem) {
            $g12_gwa = round(($grades->g12_first_sem + $grades->g12_second_sem) / 2, 2);
        }

        $payload = [
            'id' => $user->id,
            'reference_number' => $user->reference_number,
            'firstname' => $user->firstname,
            'middlename' => $user->middlename,
            'extension_name' => $user->extension_name,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'sex' => $user->sex,
            'g12_gwa' => $g12_gwa,
            'application' => [
                'application_id' => $application->id,
                'status' => $application->status,
                'enrollment_status' => $application->enrollment_status,
                'enrollment_position' => $application->enrollment_position,
                'submitted_at' => $application->submitted_at,
            ],
            'program' => [
                'program_id' => $program?->id,
                'program_code' => $program?->code,
                'program_name' => $program?->name,
            ],
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        $this->auditLogService->logActivity(
            'READ',
            'External API',
            sprintf(
                'External student lookup success for reference_number %s from IP %s.',
                $referenceNumber,
                $request->ip() ?? 'unknown'
            ),
            null,
            AuditLog::CATEGORY_ADMISSION_DATA
        );

        return response()->json([
            'data' => $payload,
        ]);
    }

    public function showByEmail(Request $request, string $email): JsonResponse
    {
        $application = Application::query()
            ->with(['user.user', 'user.grades', 'program', 'user.testPasser'])
            ->where('enrollment_status', 'officially_enrolled')
            ->whereHas('user.user', function ($query) use ($email) {
                $query->where('email', $email);
            })
            ->first();

        $profile = $application?->user;
        $account = $profile?->user;

        if (! $application || ! $profile || ! $account) {
            $this->auditLogService->logActivity(
                'READ_MISS',
                'External API',
                sprintf(
                    'External student lookup miss for email %s from IP %s.',
                    $email,
                    $request->ip() ?? 'unknown'
                ),
                null,
                AuditLog::CATEGORY_ADMISSION_DATA
            );

            return response()->json([
                'message' => 'Student not found',
            ], 404);
        }

        $user = $profile;
        $program = $application->program;
        $grades = $user->grades;

        // Calculate GWA (General Weighted Average) from G12 grades
        $g12_gwa = null;
        if ($grades && $grades->g12_first_sem && $grades->g12_second_sem) {
            $g12_gwa = round(($grades->g12_first_sem + $grades->g12_second_sem) / 2, 2);
        }

        $payload = [
            'id' => $account->id,
            'idp_user_id' => $account->idp_user_id,
            'reference_number' => $user->reference_number,
            'firstname' => $user->firstname,
            'middlename' => $user->middlename,
            'extension_name' => $user->extension_name,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'sex' => $user->sex,
            'g12_gwa' => $g12_gwa,
            'application' => [
                'application_id' => $application->id,
                'status' => $application->status,
                'enrollment_status' => $application->enrollment_status,
                'enrollment_position' => $application->enrollment_position,
                'submitted_at' => $application->submitted_at,
            ],
            'program' => [
                'program_id' => $program?->id,
                'program_code' => $program?->code,
                'program_name' => $program?->name,
            ],
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        $this->auditLogService->logActivity(
            'READ',
            'External API',
            sprintf(
                'External student lookup success for email %s from IP %s.',
                $email,
                $request->ip() ?? 'unknown'
            ),
            null,
            AuditLog::CATEGORY_ADMISSION_DATA
        );

        return response()->json([
            'data' => $payload,
        ])->header('Cache-Control', 'no-store, no-cache');
    }
}
