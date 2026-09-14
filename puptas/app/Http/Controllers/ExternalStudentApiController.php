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

    /**
     * List enrolled students
     *
     * Returns a paginated list of students whose enrollment status is
     * `officially_enrolled`. Results can be filtered by program code.
     * Use `GET /api/v1/students/{referenceNumber}` to look up a specific student.
     *
     * Requires the `student-read` OAuth scope.
     *
     * @group Student Admission
     * @authenticated
     *
     * @queryParam per_page integer Number of results per page (1–100). Defaults to 15. Example: 15
     * @queryParam page integer Page number. Defaults to 1. Example: 1
     * @queryParam program string Filter by program code (e.g. BSIT). No-example
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 123,
     *       "reference_number": "T2026-000123",
     *       "firstname": "Juan",
     *       "middlename": "Santos",
     *       "extension_name": null,
     *       "lastname": "Dela Cruz",
     *       "email": "juan.delacruz@example.com",
     *       "sex": "Male",
     *       "g12_gwa": 1.25,
     *       "application": {
     *         "application_id": 456,
     *         "status": "admitted",
     *         "enrollment_status": "officially_enrolled",
     *         "enrollment_position": 10,
     *         "submitted_at": "2026-06-01T08:00:00.000000Z"
     *       },
     *       "program": {
     *         "program_id": 7,
     *         "program_code": "BSIT",
     *         "program_name": "Bachelor of Science in Information Technology"
     *       },
     *       "created_at": "2026-05-01T08:00:00.000000Z",
     *       "updated_at": "2026-06-01T08:00:00.000000Z"
     *     }
     *   ],
     *   "meta": {
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 42,
     *     "last_page": 3
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);
        $program = $request->query('program');

        $query = Application::query()
            ->with(['user.grades', 'program', 'user.testPasser'])
            ->where('enrollment_status', 'officially_enrolled');

        if ($program) {
            $query->whereHas('program', function ($q) use ($program) {
                $q->where('code', $program);
            });
        }

        $paginator = $query->paginate($perPage);

        $data = $paginator->map(function (Application $application) {
            $user    = $application->user;
            $program = $application->program;
            $grades  = $user?->grades;

            $g12_gwa = null;
            if ($grades && $grades->g12_first_sem && $grades->g12_second_sem) {
                $g12_gwa = round(($grades->g12_first_sem + $grades->g12_second_sem) / 2, 2);
            }

            return [
                'id'             => $user?->user_id,
                'reference_number' => $user?->testPasser?->reference_number,
                'firstname'      => $user?->firstname,
                'middlename'     => $user?->middlename,
                'extension_name' => $user?->extension_name,
                'lastname'       => $user?->lastname,
                'email'          => $user?->email,
                'sex'            => $user?->sex,
                'g12_gwa'        => $g12_gwa,
                'application'    => [
                    'application_id'      => $application->id,
                    'status'              => $application->status,
                    'enrollment_status'   => $application->enrollment_status,
                    'enrollment_position' => $application->enrollment_position,
                    'submitted_at'        => $application->submitted_at,
                ],
                'program' => [
                    'program_id'   => $program?->id,
                    'program_code' => $program?->code,
                    'program_name' => $program?->name,
                ],
                'created_at' => $user?->created_at,
                'updated_at' => $user?->updated_at,
            ];
        });

        $this->auditLogService->logActivity(
            'READ',
            'External API',
            sprintf(
                'External student list requested from IP %s. Page %d, %d results.',
                $request->ip() ?? 'unknown',
                $paginator->currentPage(),
                $paginator->total()
            ),
            null,
            AuditLog::CATEGORY_ADMISSION_DATA
        );

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Look up an enrolled student by reference number
     *
     * Returns the applicant profile, computed G12 GWA, program, and admission
     * status for a student whose enrollment status is `officially_enrolled`.
     *
     * Requires the `student-read` OAuth scope.
     *
     * @group Student Admission
     * @authenticated
     *
     * @urlParam referenceNumber string required The student's application reference number. Example: T2026-000123
     *
     * @response 200 {
     *   "data": {
     *     "id": 123,
     *     "reference_number": "T2026-000123",
     *     "firstname": "Juan",
     *     "middlename": "Santos",
     *     "extension_name": null,
     *     "lastname": "Dela Cruz",
     *     "email": "juan.delacruz@example.com",
     *     "sex": "Male",
     *     "g12_gwa": 1.25,
     *     "application": {
     *       "application_id": 456,
     *       "status": "admitted",
     *       "enrollment_status": "officially_enrolled",
     *       "enrollment_position": 10,
     *       "submitted_at": "2026-06-01T08:00:00.000000Z"
     *     },
     *     "program": {
     *       "program_id": 7,
     *       "program_code": "BSIT",
     *       "program_name": "Bachelor of Science in Information Technology"
     *     },
     *     "created_at": "2026-05-01T08:00:00.000000Z",
     *     "updated_at": "2026-06-01T08:00:00.000000Z"
     *   }
     * }
     *
     * @response 404 scenario="Student not found" {
     *   "message": "Student not found"
     * }
     */
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

    /**
     * Look up an enrolled student by email address
     *
     * Returns the applicant profile, computed G12 GWA, program, and admission
     * status for a student whose enrollment status is `officially_enrolled`.
     *
     * Requires the `student-read` OAuth scope.
     *
     * @group Student Admission
     * @authenticated
     *
     * @urlParam email string required The student's registered email address. Example: juan.delacruz@example.com
     *
     * @response 200 {
     *   "data": {
     *     "id": 123,
     *     "idp_user_id": "8f9c6a9b-1a2b-3c4d-5e6f-7a8b9c0d1e2f",
     *     "reference_number": "T2026-000123",
     *     "firstname": "Juan",
     *     "middlename": "Santos",
     *     "extension_name": null,
     *     "lastname": "Dela Cruz",
     *     "email": "juan.delacruz@example.com",
     *     "sex": "Male",
     *     "g12_gwa": 1.25,
     *     "application": {
     *       "application_id": 456,
     *       "status": "admitted",
     *       "enrollment_status": "officially_enrolled",
     *       "enrollment_position": 10,
     *       "submitted_at": "2026-06-01T08:00:00.000000Z"
     *     },
     *     "program": {
     *       "program_id": 7,
     *       "program_code": "BSIT",
     *       "program_name": "Bachelor of Science in Information Technology"
     *     },
     *     "created_at": "2026-05-01T08:00:00.000000Z",
     *     "updated_at": "2026-06-01T08:00:00.000000Z"
     *   }
     * }
     *
     * @response 404 scenario="Student not found" {
     *   "message": "Student not found"
     * }
     */
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
