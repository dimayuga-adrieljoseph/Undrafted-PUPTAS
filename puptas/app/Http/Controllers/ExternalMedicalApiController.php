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
            'user',
            'currentApplication' => function ($query) {
                $query->select('applications.id', 'applications.user_id', 'applications.status', 'applications.created_at', 'applications.program_id');
            },
            'currentApplication.program' => function ($query) {
                $query->select('id', 'code', 'name');
            },
            'currentApplication.processes' => function ($query) {
                $query->whereIn('stage', ['evaluator', 'interviewer', 'medical'])
                    ->orderBy('created_at', 'desc')
                    ->select('id', 'application_id', 'stage', 'status', 'action', 'created_at');
            },
        ])
        ->whereHas('currentApplication', function ($query) {
            // Must have evaluator stage completed (passed or transferred)
            $query->whereHas('processes', function ($q) {
                $q->where('stage', 'evaluator')
                    ->where('status', 'completed')
                    ->whereIn('action', ['passed', 'transferred']);
            })
            // Must have interviewer stage completed (passed or transferred)
            ->whereHas('processes', function ($q) {
                $q->where('stage', 'interviewer')
                    ->where('status', 'completed')
                    ->whereIn('action', ['passed', 'transferred']);
            })
            // Must currently be at the medical stage (in_progress or returned)
            ->whereHas('processes', function ($q) {
                $q->where('stage', 'medical')
                    ->whereIn('status', ['in_progress', 'returned']);
            })
            // Exclude already-passed medical (already forwarded to registrar)
            ->whereDoesntHave('processes', function ($q) {
                $q->where('stage', 'medical')
                    ->where('status', 'completed')
                    ->whereIn('action', ['passed', 'transferred']);
            })
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
        $medicalProcess = $processes->firstWhere('stage', 'medical');

        $payload = [
            'id'                     => $profile->user_id,
            'idp_user_id'            => $profile->user?->idp_user_id,
            'student_number'         => $profile->user?->student_number,
            'firstname'              => $profile->firstname,
            'middlename'             => $profile->middlename,
            'lastname'               => $profile->lastname,
            'email'                  => $profile->email,
            'contact_number'         => $profile->contactnumber,
            'program'                => $application?->program ? [
                'id'   => $application->program->id,
                'code' => $application->program->code,
                'name' => $application->program->name,
            ] : null,
            'application'            => $application ? [
                'id'         => $application->id,
                'status'     => $application->status,
                'created_at' => $application->created_at,
            ] : null,
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
        $profile = $this->getEligibleApplicantQuery()
            ->whereHas('user', function ($q) use ($idpUserId) {
                $q->where('idp_user_id', $idpUserId);
            })->first();
        
        return $this->formatResponse($profile, "IDP User ID: $idpUserId", $request);
    }

    /**
     * Look up applicant by Student Number.
     */
    public function showByStudentNumber(Request $request, string $studentNumber): JsonResponse
    {
        $profile = $this->getEligibleApplicantQuery()
            ->whereHas('user', function ($q) use ($studentNumber) {
                $q->where('student_number', $studentNumber);
            })->first();
        
        return $this->formatResponse($profile, "Student Number: $studentNumber", $request);
    }

    /**
     * Process medical webhook.
     */
    public function webhookResult(Request $request): JsonResponse
    {
        $request->validate([
            'student_number' => 'required|string',
            'medical_status' => 'required|string|in:cleared,failed',
        ]);

        $studentNumber = $request->input('student_number');
        $status = $request->input('medical_status');

        $profile = $this->getEligibleApplicantQuery()
            ->whereHas('user', function ($q) use ($studentNumber) {
                $q->where('student_number', $studentNumber);
            })->first();

        if (!$profile) {
            $this->auditLogService->logActivity(
                'WEBHOOK_MISS',
                'External Medical API',
                sprintf('Webhook received for ineligible or unknown student: %s from IP %s.', $studentNumber, $request->ip() ?? 'unknown'),
                null,
                AuditLog::CATEGORY_ADMISSION_DATA
            );
            return response()->json(['message' => 'Applicant not found or not eligible for medical stage'], 404);
        }

        $application = $profile->currentApplication;

        if ($status === 'cleared') {
            $application->update(['status' => 'cleared_for_enrollment']);
            $application->processes()->create([
                'stage' => 'medical',
                'status' => 'completed',
                'action' => 'passed',
                'performed_by' => null,
            ]);
            $actionStr = 'passed';
        } else {
            $application->update(['status' => 'rejected']);
            $application->processes()->create([
                'stage' => 'medical',
                'status' => 'completed',
                'action' => 'failed',
                'performed_by' => null,
            ]);
            $actionStr = 'failed';
        }

        $this->auditLogService->logActivity(
            'UPDATE',
            'External Medical API',
            sprintf('Medical webhook processed: student %s marked as %s from IP %s.', $studentNumber, $actionStr, $request->ip() ?? 'unknown'),
            null,
            AuditLog::CATEGORY_ADMISSION_DATA
        );

        return response()->json(['message' => 'Medical result recorded successfully']);
    }
}
