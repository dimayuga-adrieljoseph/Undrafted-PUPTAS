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
            'message' => 'This endpoint is deprecated. Use /api/v1/students/{studentNumber}.',
        ])->withHeaders([
            'Deprecation' => 'true',
            'Sunset' => 'Tue, 30 Jun 2026 23:59:59 GMT',
            'Link' => '</api/v1/students/{studentNumber}>; rel="successor-version"',
        ])->setStatusCode(410);
    }

    public function showByStudentNumber(Request $request, string $studentNumber): JsonResponse
    {
        $application = Application::query()
            ->with(['user', 'program'])
            ->where('enrollment_status', 'officially_enrolled')
            ->whereHas('user', function ($query) use ($studentNumber) {
                $query->where('student_number', $studentNumber);
            })
            ->first();

        if (! $application || ! $application->user) {
            $this->auditLogService->logActivity(
                'READ_MISS',
                'External API',
                sprintf(
                    'External student lookup miss for student_number %s from IP %s.',
                    $studentNumber,
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

        $payload = [
            'id' => $user->id,
            'student_number' => $user->student_number,
            'firstname' => $user->firstname,
            'middlename' => $user->middlename,
            'extension_name' => $user->extension_name,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'contactnumber' => $user->contactnumber,
            'birthday' => $user->birthday,
            'sex' => $user->sex,
            'street_address' => $user->street_address,
            'barangay' => $user->barangay,
            'city' => $user->city,
            'province' => $user->province,
            'postal_code' => $user->postal_code,
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
                'External student lookup success for student_number %s from IP %s.',
                $studentNumber,
                $request->ip() ?? 'unknown'
            ),
            null,
            AuditLog::CATEGORY_ADMISSION_DATA
        );

        return response()->json([
            'data' => $payload,
        ]);
    }

    public function showByIdpUserId(Request $request, string $idpUserId): JsonResponse
    {
        $application = Application::query()
            ->with(['user.user', 'program'])
            ->where('enrollment_status', 'officially_enrolled')
            ->whereHas('user.user', function ($query) use ($idpUserId) {
                $query->where('idp_user_id', $idpUserId);
            })
            ->first();

        $profile = $application?->user;
        $account = $profile?->user;

        if (! $application || ! $profile || ! $account) {
            $this->auditLogService->logActivity(
                'READ_MISS',
                'External API',
                sprintf(
                    'External student lookup miss for idp_user_id %s from IP %s.',
                    $idpUserId,
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

        $payload = [
            'id' => $account->id,
            'idp_user_id' => $account->idp_user_id,
            'student_number' => $user->student_number ?? $account->student_number,
            'firstname' => $user->firstname,
            'middlename' => $user->middlename,
            'extension_name' => $user->extension_name,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'contactnumber' => $user->contactnumber,
            'birthday' => $user->birthday,
            'sex' => $user->sex,
            'street_address' => $user->street_address,
            'barangay' => $user->barangay,
            'city' => $user->city,
            'province' => $user->province,
            'postal_code' => $user->postal_code,
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
                'External student lookup success for idp_user_id %s from IP %s.',
                $idpUserId,
                $request->ip() ?? 'unknown'
            ),
            null,
            AuditLog::CATEGORY_ADMISSION_DATA
        );

        return response()->json([
            'data' => $payload,
        ]);
    }
}
