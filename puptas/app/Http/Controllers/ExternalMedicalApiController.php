<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ApplicantProfile;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;

class ExternalMedicalApiController extends Controller
{
    public function __construct(private AuditLogService $auditLogService)
    {
    }

    /**
     * List medical applicants (deprecated)
     *
     * This endpoint has been deprecated and always returns `410 Gone`.
     * Use a specific lookup endpoint instead:
     * `GET /api/v1/medical/applicants/{referenceNumber}` or
     * `GET /api/v1/medical/applicants/idp/{idpUserId}`.
     *
     * Requires the `medical-read` OAuth scope.
     *
     * @group Medical Integration
     * @authenticated
     *
     * @response 410 scenario="Deprecated" {
     *   "message": "This endpoint is deprecated. Use a specific lookup endpoint."
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $this->auditLogService->logActivity(
            'DEPRECATED_ENDPOINT',
            'External API',
            sprintf(
                'Deprecated list endpoint /api/v1/medical/applicants called from IP %s.',
                $request->ip() ?? 'unknown'
            ),
            null,
            AuditLog::CATEGORY_ADMISSION_DATA
        );

        return response()->json([
            'message' => 'This endpoint is deprecated. Use a specific lookup endpoint.',
        ])->withHeaders([
            'Deprecation' => 'true',
            'Sunset' => 'Tue, 30 Jun 2026 23:59:59 GMT',
            'Link' => '</api/v1/medical/applicants/{id}>; rel="successor-version"',
        ])->setStatusCode(410);
    }

    /**
     * Base query for fetching an eligible medical applicant.
     */
    private function getEligibleApplicantQuery()
    {
        return ApplicantProfile::with([
            'user' => function ($query) {
                $query->select('id', 'idp_user_id');
            },
            'testPasser',
            'currentApplication' => function ($query) {
                $query->select('applications.id', 'applications.user_id', 'applications.status', 'applications.created_at', 'applications.program_id');
            },
            'currentApplication.program' => function ($query) {
                $query->select('id', 'code', 'name');
            },
            'currentApplication.processes' => function ($query) {
                $query->where('stage', 'medical')
                    ->orderBy('created_at', 'desc')
                    ->select('id', 'application_id', 'stage', 'status', 'action', 'created_at');
            },
        ])
        ->whereHas('currentApplication', function ($query) {
            $query->join('application_processes as eval_proc', function ($join) {
                $join->on('eval_proc.application_id', '=', 'applications.id')
                    ->where('eval_proc.stage', '=', 'grade_evaluator')
                    ->where('eval_proc.status', '=', 'completed')
                    ->whereIn('eval_proc.action', ['passed', 'transferred']);
            })
            ->join('application_processes as int_proc', function ($join) {
                $join->on('int_proc.application_id', '=', 'applications.id')
                    ->where('int_proc.stage', '=', 'interviewer')
                    ->where('int_proc.status', '=', 'completed')
                    ->whereIn('int_proc.action', ['passed', 'transferred']);
            })
            ->join('application_processes as med_proc_active', function ($join) {
                $join->on('med_proc_active.application_id', '=', 'applications.id')
                    ->where('med_proc_active.stage', '=', 'medical')
                    ->whereIn('med_proc_active.status', ['in_progress', 'returned']);
            })
            ->leftJoin('application_processes as med_proc_completed', function ($join) {
                $join->on('med_proc_completed.application_id', '=', 'applications.id')
                    ->where('med_proc_completed.stage', '=', 'medical')
                    ->where('med_proc_completed.status', '=', 'completed')
                    ->whereIn('med_proc_completed.action', ['passed', 'transferred']);
            })
            ->whereNull('med_proc_completed.id');
        });
    }

    /**
     * Format response and perform audit logging.
     */
    private function formatResponse(?ApplicantProfile $profile, string $lookupValue, Request $request): JsonResponse
    {
        if (!$profile) {
            $this->auditLogService->logActivity(
                'READ_MISS',
                'External API',
                sprintf(
                    'External medical applicant lookup miss for lookup_value %s from IP %s.',
                    $lookupValue,
                    $request->ip() ?? 'unknown'
                ),
                null,
                AuditLog::CATEGORY_ADMISSION_DATA
            );

            return response()->json([
                'message' => 'Applicant not found or not eligible for medical yet.',
            ], 404);
        }

        $application    = $profile->currentApplication;
        $processes      = $application?->processes ?? collect();
        $medicalProcess = $processes->first();

        $payload = [
            // Basic Identity
            'id'                     => $profile->user_id,
            'idp_user_id'            => $profile->user?->idp_user_id,
            'reference_number'       => $profile->reference_number,
            
            // Personal Information
            'salutation'             => $profile->salutation,
            'firstname'              => $profile->firstname,
            'middlename'             => $profile->middlename,
            'extension_name'         => $profile->extension_name,
            'lastname'               => $profile->lastname,
            'sex'                    => $profile->sex,
            
            // Contact Information
            'email'                  => $profile->email,
            
            // Educational Background
            'date_graduated'         => $profile->date_graduated,
            'strand'                 => $profile->strand,
            'track'                  => $profile->track,
            
            // Current Application (simplified)
            'application'            => $application ? [
                'id'         => $application->id,
                'status'     => $application->status,
                'created_at' => $application->created_at,
            ] : null,
            
            // Current Program
            'program'                => $application?->program ? [
                'id'   => $application->program->id,
                'code' => $application->program->code,
                'name' => $application->program->name,
            ] : null,
            
            // Medical Process Status
            'medical_process_status' => $medicalProcess?->status ?? 'in_progress',
        ];

        $this->auditLogService->logActivity(
            'READ',
            'External API',
            sprintf(
                'External medical applicant lookup success for %s (System ID: %s) from IP %s.',
                $lookupValue,
                $profile->user_id,
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
     * Look up an eligible medical applicant by IDP User ID
     *
     * Returns identity, contact, education, program, and medical-process status
     * for an applicant who has cleared grade evaluation and interviewing and is
     * currently pending medical.
     *
     * Requires the `medical-read` OAuth scope.
     *
     * @group Medical Integration
     * @authenticated
     *
     * @urlParam idpUserId string required The applicant's IDP user UUID. Example: 8f9c6a9b-1a2b-3c4d-5e6f-7a8b9c0d1e2f
     *
     * @response 200 {
     *   "data": {
     *     "id": 345,
     *     "idp_user_id": "8f9c6a9b-1a2b-3c4d-5e6f-7a8b9c0d1e2f",
     *     "reference_number": "T2026-000123",
     *     "salutation": null,
     *     "firstname": "Juan",
     *     "middlename": "Santos",
     *     "extension_name": null,
     *     "lastname": "Dela Cruz",
     *     "sex": "Male",
     *     "email": "juan.delacruz@example.com",
     *     "date_graduated": "2025-05-30",
     *     "strand": "STEM",
     *     "track": "Academic",
     *     "application": {
     *       "id": 456,
     *       "status": "admitted",
     *       "created_at": "2026-06-01T08:00:00.000000Z"
     *     },
     *     "program": {
     *       "id": 7,
     *       "code": "BSIT",
     *       "name": "Bachelor of Science in Information Technology"
     *     },
     *     "medical_process_status": "in_progress"
     *   }
     * }
     *
     * @response 404 scenario="Applicant not found" {
     *   "message": "Applicant not found or not eligible for medical yet."
     * }
     */
    public function showByIdpUserId(Request $request, string $idpUserId): JsonResponse
    {
        // Log the API call FIRST, before any potential errors
        $this->auditLogService->logActivity(
            'READ',
            'External Medical API',
            sprintf(
                'API call to retrieve applicant by IDP User ID: %s from IP %s.',
                $idpUserId,
                $request->ip() ?? 'unknown'
            ),
            null,
            AuditLog::CATEGORY_SYSTEM_OPERATION
        );

        try {
            // Look up by user's idp_user_id
            $profile = $this->getEligibleApplicantQuery()
                ->whereHas('user', function ($q) use ($idpUserId) {
                    $q->where('idp_user_id', $idpUserId);
                })->first();
            
            return $this->formatResponse($profile, "IDP User ID: $idpUserId", $request);
        } catch (\Throwable $e) {
            // Log the error but still throw it
            $this->auditLogService->logActivity(
                'READ_ERROR',
                'External Medical API',
                sprintf(
                    'API call failed for IDP User ID: %s from IP %s. Error: %s',
                    $idpUserId,
                    $request->ip() ?? 'unknown',
                    $e->getMessage()
                ),
                null,
                AuditLog::CATEGORY_SYSTEM_OPERATION
            );
            throw $e;
        }
    }

    /**
     * Look up an eligible medical applicant by reference number
     *
     * Returns identity, contact, education, program, and medical-process status
     * for an applicant who has cleared grade evaluation and interviewing and is
     * currently pending medical.
     *
     * Requires the `medical-read` OAuth scope.
     *
     * @group Medical Integration
     * @authenticated
     *
     * @urlParam referenceNumber string required The applicant's application reference number. Example: T2026-000123
     *
     * @response 200 {
     *   "data": {
     *     "id": 345,
     *     "idp_user_id": "8f9c6a9b-1a2b-3c4d-5e6f-7a8b9c0d1e2f",
     *     "reference_number": "T2026-000123",
     *     "salutation": null,
     *     "firstname": "Juan",
     *     "middlename": "Santos",
     *     "extension_name": null,
     *     "lastname": "Dela Cruz",
     *     "sex": "Male",
     *     "email": "juan.delacruz@example.com",
     *     "date_graduated": "2025-05-30",
     *     "strand": "STEM",
     *     "track": "Academic",
     *     "application": {
     *       "id": 456,
     *       "status": "admitted",
     *       "created_at": "2026-06-01T08:00:00.000000Z"
     *     },
     *     "program": {
     *       "id": 7,
     *       "code": "BSIT",
     *       "name": "Bachelor of Science in Information Technology"
     *     },
     *     "medical_process_status": "in_progress"
     *   }
     * }
     *
     * @response 404 scenario="Applicant not found" {
     *   "message": "Applicant not found or not eligible for medical yet."
     * }
     */
    public function showByReferenceNumber(Request $request, string $referenceNumber): JsonResponse
    {
        // Log the API call FIRST, before any potential errors
        $this->auditLogService->logActivity(
            'READ',
            'External Medical API',
            sprintf(
                'API call to retrieve applicant by Reference Number: %s from IP %s.',
                $referenceNumber,
                $request->ip() ?? 'unknown'
            ),
            null,
            AuditLog::CATEGORY_SYSTEM_OPERATION
        );

        try {
            // Look up by testPasser's reference_number
            $profile = $this->getEligibleApplicantQuery()
                ->whereHas('testPasser', function ($q) use ($referenceNumber) {
                    $q->where('reference_number', $referenceNumber);
                })->first();
            
            return $this->formatResponse($profile, "Reference Number: $referenceNumber", $request);
        } catch (\Throwable $e) {
            // Log the error but still throw it
            $this->auditLogService->logActivity(
                'READ_ERROR',
                'External Medical API',
                sprintf(
                    'API call failed for Reference Number: %s from IP %s. Error: %s',
                    $referenceNumber,
                    $request->ip() ?? 'unknown',
                    $e->getMessage()
                ),
                null,
                AuditLog::CATEGORY_SYSTEM_OPERATION
            );
            throw $e;
        }
    }

    /**
     * Receive a medical result webhook
     *
     * Secure webhook for external medical partners to push a student's medical
     * clearance result into PUPTAS. The payload is queued and processed
     * asynchronously.
     *
     * ## Security requirements
     * This endpoint enforces **two** layers of security:
     *
     * 1. **OAuth client credentials scope** — `client:medical-write`
     *    The caller must present a Passport client-credentials access token
     *    granted the `medical-write` scope via the `Authorization: Bearer` header.
     *
     * 2. **Custom SHA256 HMAC signature** — `X-Medical-Signature` header
     *    The caller computes `hash_hmac('sha256', <raw JSON body>, <shared secret>)`
     *    using the secret configured at `services.medical_webhook.secret`, and
     *    sends the hex digest in the `X-Medical-Signature` header. This verifies
     *    payload authenticity and integrity.
     *
     * ### Replay protection
     * The JSON body must also include:
     * - `timestamp`: ISO8601 string or UNIX epoch seconds, within 5 minutes of now.
     * - `nonce`: a unique value; repeated nonces are rejected for 10 minutes.
     *
     * @group Medical Webhooks
     * @authenticated
     *
     * @header X-Medical-Signature string required SHA256 HMAC hex digest of the raw JSON body using `services.medical_webhook.secret`. Example: 5f4dcc3b5aa765d61d8327deb882cf99
     *
     * @bodyParam reference_number string The student's application reference number. Required if `idp_user_id` is absent. Example: T2026-000123
     * @bodyParam idp_user_id string The student's IDP user UUID (also accepted as `student_id`). Required if `reference_number` is absent. Example: 8f9c6a9b-1a2b-3c4d-5e6f-7a8b9c0d1e2f
     * @bodyParam is_health_profile_completed integer required Whether the student completed their health profile (1 = cleared). Example: 1
     * @bodyParam timestamp string required ISO8601 timestamp or UNIX epoch seconds within the last 5 minutes. Example: 1785240000
     * @bodyParam nonce string required Unique request nonce for replay protection. Example: 7c9e6679-7425-40de-944b-e07f6e7e1c3b
     *
     * @response 200 {
     *   "message": "Medical webhook received and queued for processing"
     * }
     *
     * @response 422 scenario="Validation failed" {
     *   "message": "Validation failed",
     *   "errors": {
     *     "is_health_profile_completed": ["The is health profile completed field is required."]
     *   }
     * }
     *
     * @response 403 scenario="Invalid signature" {
     *   "message": "Invalid Signature"
     * }
     */
    public function webhookResult(Request $request): JsonResponse
    {
        // Map their field names to ours
        $referenceNumber = $request->input('reference_number');
        
        $idpUserId = $request->input('idp_user_id')
                  ?? $request->input('student_id');  // Their UUID field
        
        // They send is_health_profile_completed: 1 when cleared
        // If they send this webhook, it means the student is cleared
        $isHealthProfileCompleted = $request->input('is_health_profile_completed');
        
        // Validate
        $validator = \Illuminate\Support\Facades\Validator::make([
            'reference_number' => $referenceNumber,
            'idp_user_id' => $idpUserId,
            'is_health_profile_completed' => $isHealthProfileCompleted,
        ], [
            'reference_number' => 'nullable|string',
            'idp_user_id' => 'nullable|string',
            'is_health_profile_completed' => 'required|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Ensure at least one identifier is provided
        if (!$referenceNumber && !$idpUserId) {
            return response()->json([
                'message' => 'Either reference_number or student_id (idp_user_id) must be provided'
            ], 422);
        }

        // Dispatch job to process the medical webhook asynchronously
        \App\Jobs\ProcessMedicalWebhookJob::dispatch($request->all(), $request->ip() ?? 'unknown');

        // Immediately return 200 OK so the external system doesn't timeout
        return response()->json(['message' => 'Medical webhook received and queued for processing']);
    }
}
