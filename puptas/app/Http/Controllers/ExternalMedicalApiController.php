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
     * Deprecated list endpoint matching the student API's design.
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
                    ->where('eval_proc.stage', '=', 'evaluator')
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
            ->whereNull('med_proc_completed.id')
            ->whereRaw('applications.id = (SELECT MAX(a.id) FROM applications a WHERE a.user_id = applications.user_id AND a.deleted_at IS NULL)');
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
            'student_number'         => $profile->student_number,
            
            // Personal Information
            'salutation'             => $profile->salutation,
            'firstname'              => $profile->firstname,
            'middlename'             => $profile->middlename,
            'extension_name'         => $profile->extension_name,
            'lastname'               => $profile->lastname,
            'birthday'               => $profile->birthday,
            'sex'                    => $profile->sex,
            
            // Contact Information
            'email'                  => $profile->email,
            'contactnumber'          => $profile->contactnumber,
            
            // Address Information
            'street_address'         => $profile->street_address,
            'barangay'               => $profile->barangay,
            'city'                   => $profile->city,
            'province'               => $profile->province,
            'postal_code'            => $profile->postal_code,
            
            // Educational Background
            'school'                 => $profile->school,
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
     * Look up applicant by IDP User ID.
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
     * Look up applicant by Student Number.
     */
    public function showByStudentNumber(Request $request, string $studentNumber): JsonResponse
    {
        // Log the API call FIRST, before any potential errors
        $this->auditLogService->logActivity(
            'READ',
            'External Medical API',
            sprintf(
                'API call to retrieve applicant by Student Number: %s from IP %s.',
                $studentNumber,
                $request->ip() ?? 'unknown'
            ),
            null,
            AuditLog::CATEGORY_SYSTEM_OPERATION
        );

        try {
            // Look up by applicant_profile's student_number
            $profile = $this->getEligibleApplicantQuery()
                ->where('student_number', $studentNumber)
                ->first();
            
            return $this->formatResponse($profile, "Student Number: $studentNumber", $request);
        } catch (\Throwable $e) {
            // Log the error but still throw it
            $this->auditLogService->logActivity(
                'READ_ERROR',
                'External Medical API',
                sprintf(
                    'API call failed for Student Number: %s from IP %s. Error: %s',
                    $studentNumber,
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
     * Process medical webhook.
     */
    public function webhookResult(Request $request): JsonResponse
    {
        // Map their field names to ours
        $studentNumber = $request->input('student_number');
        
        $idpUserId = $request->input('idp_user_id')
                  ?? $request->input('student_id');  // Their UUID field
        
        // They send is_health_profile_completed: 1 when cleared
        // If they send this webhook, it means the student is cleared
        $isHealthProfileCompleted = $request->input('is_health_profile_completed');
        
        // Validate
        $validator = \Illuminate\Support\Facades\Validator::make([
            'student_number' => $studentNumber,
            'idp_user_id' => $idpUserId,
            'is_health_profile_completed' => $isHealthProfileCompleted,
        ], [
            'student_number' => 'nullable|string',
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
        if (!$studentNumber && !$idpUserId) {
            return response()->json([
                'message' => 'Either student_number or student_id (idp_user_id) must be provided'
            ], 422);
        }

        // Map their status to ours
        // 1 = cleared/passed, 0 = failed (though they typically only send when 1)
        $status = $isHealthProfileCompleted == 1 ? 'cleared' : 'failed';
        
        // Determine lookup identifier for logging
        $lookupIdentifier = $studentNumber ?: $idpUserId;

        // Try to find profile - prioritize student_number, fallback to idp_user_id
        $profile = null;
        
        // First try: student_number (if provided and not empty)
        if ($studentNumber) {
            $profile = $this->getEligibleApplicantQuery()
                ->where('student_number', $studentNumber)
                ->first();
        }
        
        // Second try: idp_user_id (if student_number failed or not provided)
        if (!$profile && $idpUserId) {
            $profile = $this->getEligibleApplicantQuery()
                ->whereHas('user', function ($q) use ($idpUserId) {
                    $q->where('idp_user_id', $idpUserId);
                })->first();
        }

        if (!$profile) {
            // Idempotency check: if medical is already completed, return success
            $fallbackProfile = null;
            
            // Try fallback with student_number
            if ($studentNumber) {
                $fallbackProfile = ApplicantProfile::with('currentApplication.processes')
                    ->where('student_number', $studentNumber)
                    ->first();
            }
            
            // Try fallback with idp_user_id
            if (!$fallbackProfile && $idpUserId) {
                $fallbackProfile = ApplicantProfile::with('currentApplication.processes')
                    ->whereHas('user', function ($q) use ($idpUserId) {
                        $q->where('idp_user_id', $idpUserId);
                    })->first();
            }

            if ($fallbackProfile && $fallbackProfile->currentApplication) {
                $latestMedical = $fallbackProfile->currentApplication->processes
                    ->where('stage', 'medical')
                    ->sortByDesc('created_at')
                    ->first();
                if ($latestMedical && $latestMedical->status === 'completed') {
                    return response()->json(['message' => 'Medical result already recorded successfully']);
                }
            }

            $this->auditLogService->logActivity(
                'WEBHOOK_MISS',
                'External Medical API',
                sprintf(
                    'Webhook received for ineligible or unknown student: %s (student_number: %s, idp_user_id: %s) from IP %s.',
                    $lookupIdentifier,
                    $studentNumber ?: 'not provided',
                    $idpUserId ?: 'not provided',
                    $request->ip() ?? 'unknown'
                ),
                null,
                AuditLog::CATEGORY_ADMISSION_DATA
            );
            return response()->json(['message' => 'Applicant not found or not eligible for medical stage'], 404);
        }

        $application = $profile->currentApplication;
        
        $actionStr = $status === 'cleared' ? 'passed' : 'failed';
        $newAppStatus = $status === 'cleared' ? 'cleared_for_enrollment' : 'rejected';

        \Illuminate\Support\Facades\DB::transaction(function () use ($application, $newAppStatus, $actionStr) {
            $application->update(['status' => $newAppStatus]);
            
            $medicalProcess = $application->processes()
                ->where('stage', 'medical')
                ->whereIn('status', ['in_progress', 'returned'])
                ->orderBy('created_at', 'desc')
                ->first();

            if ($medicalProcess) {
                $medicalProcess->update([
                    'status' => 'completed',
                    'action' => $actionStr,
                ]);
            } else {
                $application->processes()->create([
                    'stage' => 'medical',
                    'status' => 'completed',
                    'action' => $actionStr,
                    'performed_by' => null,
                ]);
            }
        });

        $this->auditLogService->logActivity(
            'UPDATE',
            'External Medical API',
            sprintf('Medical webhook processed: student %s marked as %s from IP %s.', $lookupIdentifier, $actionStr, $request->ip() ?? 'unknown'),
            null,
            AuditLog::CATEGORY_ADMISSION_DATA
        );

        return response()->json(['message' => 'Medical result recorded successfully']);
    }
}
