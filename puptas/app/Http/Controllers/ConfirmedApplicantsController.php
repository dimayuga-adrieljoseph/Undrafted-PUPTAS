<?php

// Trigger deployment redeployment
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\ApplicantProfile;
use App\Models\TestPasser;
use App\Models\Grade;
use App\Models\SarGeneration;
use App\Services\SarFormService;
use App\Services\AuditLogService;
use App\Services\EmailTrackingService;
use App\Jobs\SendSarFormEmail;
use App\Jobs\SendPasserEmail;

class ConfirmedApplicantsController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService,
        private EmailTrackingService $emailTrackingService,
    ) {}

    /**
     * Render the Confirmed Applicants page.
     * Confirmed applicants = applicants with 'for_evaluation' application status.
     */
    public function index()
    {
        $user = Auth::user();

        if (!$user || !in_array($user->role_id, [2, 7])) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Applications/ConfirmedApplicants');
    }

    /**
     * JSON: Get all confirmed applicants across all active evaluation stages.
     * Includes: document_evaluator, grade_evaluator, interviewer, and medical.
     * Joins with test_passers via reference_number to expose grade sync status.
     */
    public function getApplicants()
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role_id, [2, 7])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $applicants = ApplicantProfile::with([
            'currentApplication.program:id,code,name',
            'currentApplication.processes',
            'grades',
            'testPasser.passerStatus',
            'testPasser.previousPasserStatus',
            'testPasser.sarGenerations',
            'graduateTypes',
        ])
            ->whereHas('currentApplication', function ($q) {
                $q->where(function ($sub) {
                    $sub->whereHas('processes', function ($processQ) {
                        $processQ->whereIn('stage', [
                            'document_evaluator',
                            'grade_evaluator',
                            'interviewer',
                            'medical',
                        ])->whereIn('status', ['in_progress', 'returned']);
                    })->orWhere('status', 'cleared_for_enrollment');
                });
            })
            ->orderBy('lastname')
            ->get()
            ->map(function ($applicant) {
                $testPasser = $applicant->testPasser;

                $hasTestPasser = $testPasser !== null;
                $gradesSynced  = $hasTestPasser && $applicant->grades !== null;

                // Determine the applicant's current active stage from their processes.
                // Priority: medical > interviewer > grade_evaluator > document_evaluator
                $processes   = $applicant->currentApplication?->processes ?? collect();
                $currentStage = null;
                foreach (['medical', 'interviewer', 'grade_evaluator', 'document_evaluator'] as $stage) {
                    $process = $processes->where('stage', $stage)
                        ->whereIn('status', ['in_progress', 'returned'])
                        ->first();
                    if ($process) {
                        $currentStage = $stage;
                        break;
                    }
                }

                // Detect pull-out state:
                // Interviewer is in_progress AND no medical/records processes exist.
                // This indicates the applicant was reverted from a post-interview stage.
                $interviewerProcess = $processes->where('stage', 'interviewer')->first();
                $hasMedicalOrRecords = $processes->whereIn('stage', ['medical', 'records'])->isNotEmpty();
                $isPulledOut = $interviewerProcess
                    && $interviewerProcess->status === 'in_progress'
                    && $interviewerProcess->action === null
                    && !$hasMedicalOrRecords
                    && ($interviewerProcess->decision_reason !== null || $interviewerProcess->reviewer_notes !== null); // notes were set during pull-out

                return [
                    'id'               => $applicant->user_id,
                    'firstname'        => $applicant->firstname,
                    'lastname'         => $applicant->lastname,
                    'email'            => $applicant->email,
                    'status'           => $applicant->currentApplication?->status,
                    'program'          => $applicant->currentApplication?->program,
                    'grades'           => $applicant->grades ? collect($applicant->grades->toArray())->except(['id', 'user_id', 'created_at', 'updated_at'])->toArray() : null,
                    'has_test_passer'  => $hasTestPasser,
                    'grades_synced'    => $gradesSynced,
                    'test_passer_id'   => $testPasser?->test_passer_id,
                    'reference_number' => $testPasser?->reference_number,
                    'batch_number'     => $testPasser?->batch_number,
                    'passer_status_id'   => $testPasser?->passer_status_id,
                    'passer_status_name' => $testPasser?->passerStatus?->status,
                    // For waiver (on_probation) students: show their previous status label instead,
                    // but keep is_waiver=true so the badge still shows.
                    'display_passer_status' => ($testPasser?->passerStatus?->status === 'on_probation' && $testPasser?->previousPasserStatus)
                        ? $testPasser->previousPasserStatus->status
                        : $testPasser?->passerStatus?->status,
                    'is_waiver'          => $testPasser?->passerStatus?->status === 'on_probation',
                    'sar_sent'         => $testPasser
                        ? $testPasser->sarGenerations
                            ->where('email_sent_successfully', true)
                            ->isNotEmpty()
                        : false,
                    'graduate_type'    => $applicant->graduateTypes->first()?->label,
                    'current_stage'    => $currentStage,
                    'pulled_out'       => $isPulledOut,
                    'pullout_notes'    => $isPulledOut ? ($interviewerProcess->decision_reason ?? $interviewerProcess->reviewer_notes) : null,
                ];
            });


        return response()->json($applicants);
    }

    /**
     * Send SAR Form email to a confirmed applicant.
     * Restricted to applicants with 'for_evaluation' status.
     */
    public function sendSar(Request $request)
    {
        $authUser = Auth::user();
        if (!$authUser || !in_array($authUser->role_id, [2, 7])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'applicant_ids'   => 'required|array|min:1',
            'applicant_ids.*' => 'integer',
            'enrollment_date' => 'required|string',
            'enrollment_time' => 'required|string',
        ]);

        $applicantIds   = $request->input('applicant_ids');
        $enrollmentDate = $request->input('enrollment_date');
        $enrollmentTime = $request->input('enrollment_time');

        // Verify all applicants are in any active evaluation stage or cleared for enrollment
        $applicants = ApplicantProfile::with(['currentApplication', 'testPasser'])
            ->whereIn('user_id', $applicantIds)
            ->whereHas('currentApplication', function ($q) {
                $q->where(function ($sub) {
                    $sub->whereHas('processes', function ($processQ) {
                        $processQ->whereIn('stage', [
                            'document_evaluator',
                            'grade_evaluator',
                            'interviewer',
                            'medical',
                        ])->whereIn('status', ['in_progress', 'returned']);
                    })->orWhere('status', 'cleared_for_enrollment');
                });
            })
            ->get()
            ->sortByDesc(function ($applicant) {
                return $applicant->testPasser->pupcet_total_score ?? 0;
            });

        if ($applicants->isEmpty()) {
            return response()->json([
                'message' => 'No confirmed applicants found. SAR Forms can only be sent to applicants currently under evaluation.',
            ], 422);
        }

        // Create bulk email operation for tracking
        $bulkOperation = $this->emailTrackingService->createBulkOperation(
            'sar_form',
            $applicants->count(),
            $authUser->id
        );

        if (!$bulkOperation->exists) {
            return response()->json([
                'message' => 'Failed to create bulk email operation. No emails were dispatched.',
            ], 500);
        }

        $sarService   = app(SarFormService::class);
        $successCount = 0;
        $failedCount  = 0;
        $errors       = [];
        $successIds   = [];

        foreach ($applicants as $applicant) {
            $testPasser    = $applicant->testPasser;
            $sarGeneration = null;

            if (!$testPasser) {
                $failedCount++;
                $errors[] = [
                    'applicant' => $applicant->firstname . ' ' . $applicant->lastname,
                    'email'     => $applicant->email,
                    'error'     => 'No test passer record linked. Cannot generate SAR without reference number.',
                ];
                continue;
            }

            try {
                $sarData = [
                    'id'               => 'ap_' . $applicant->user_id,
                    'reference_number' => $testPasser->reference_number ?? 'N/A',
                    'full_name'        => trim($testPasser->surname . ', ' . $testPasser->first_name . ' ' . ($testPasser->middle_name ?? '')),
                    'surname'          => $testPasser->surname,
                    'firstname_middle' => trim($testPasser->first_name . ' ' . ($testPasser->middle_name ?? '')),
                    'shs_strand'       => $testPasser->strand ?? 'N/A',
                    'graduation_year'  => $testPasser->graduation_year,
                    'school_attended'  => $testPasser->shs_school ?? 'N/A',
                    'enrollment_date'  => $enrollmentDate,
                    'enrollment_time'  => $enrollmentTime,
                ];

                $result = $sarService->generateSarPdf($sarData);

                if ($result['success']) {
                    $downloadUrl = \URL::temporarySignedRoute(
                        'sar.passer-download',
                        now()->addDays(30),
                        [
                            'reference' => $testPasser->reference_number,
                            'filename'  => $result['filename'],
                        ]
                    );

                    $sarGeneration = SarGeneration::create([
                        'test_passer_id'         => $testPasser->test_passer_id,
                        'filename'               => $result['filename'],
                        'file_path'              => $result['pdf_path'],
                        'enrollment_date'        => $enrollmentDate,
                        'enrollment_time'        => $enrollmentTime,
                        'sent_to_email'          => $applicant->email,
                        'created_by_user_id'     => $authUser->id,
                        'email_sent_successfully' => false,
                    ]);

                    // Create email log record for tracking
                    $emailLog = $this->emailTrackingService->createEmailLog(
                        $bulkOperation->id,
                        $applicant->email,
                        trim($applicant->firstname . ' ' . $applicant->lastname),
                        $testPasser->test_passer_id,
                        'sar_form',
                        $downloadUrl
                    );

                    SendSarFormEmail::dispatch(
                        $testPasser,
                        $downloadUrl,
                        $sarGeneration->id,
                        $emailLog->exists ? $emailLog->id : null,
                        $bulkOperation->id
                    );

                    $successCount++;
                    $successIds[] = $applicant->user_id;
                } else {
                    $failedCount++;
                    $errors[] = [
                        'applicant' => $applicant->firstname . ' ' . $applicant->lastname,
                        'email'     => $applicant->email,
                        'error'     => $result['error'],
                    ];
                }
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = [
                    'applicant' => $applicant->firstname . ' ' . $applicant->lastname,
                    'email'     => $applicant->email,
                    'error'     => $e->getMessage() ?: 'Unknown error',
                ];

                if ($sarGeneration) {
                    $sarGeneration->update(['email_sent_successfully' => false]);
                }

                \Log::error('SAR email failed for confirmed applicant: ' . $applicant->user_id, [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->auditLogService->logActivity(
            'CREATE',
            'Confirmed Applicants',
            "Sent SAR form emails to confirmed applicants: {$successCount} successful, {$failedCount} failed.",
            null,
            'ADMISSION_DATA'
        );

        return response()->json([
            'message'           => "SAR emails sent: {$successCount} successful, {$failedCount} failed",
            'success_count'     => $successCount,
            'failed_count'      => $failedCount,
            'errors'            => $errors,
            'success_ids'       => $successIds,
            'bulk_operation_id' => $bulkOperation->id,
        ], $failedCount > 0 ? 207 : 200);
    }

    /**
     * Send custom email to confirmed applicants.
     */
    public function sendCustomEmail(Request $request)
    {
        $authUser = Auth::user();
        if (!$authUser || !in_array($authUser->role_id, [2, 7])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'applicant_ids'    => 'required|array|min:1',
            'applicant_ids.*'  => 'integer',
            'message_template' => 'required|string',
        ]);

        $applicantIds    = $request->input('applicant_ids');
        $messageTemplate = $request->input('message_template');

        // Fetch all provided applicants regardless of their current stage
        $applicants = ApplicantProfile::with(['currentApplication', 'testPasser'])
            ->whereIn('user_id', $applicantIds)
            ->get();

        if ($applicants->isEmpty()) {
            return response()->json([
                'message' => 'No confirmed applicants found.',
            ], 422);
        }

        // Create bulk email operation for tracking
        $bulkOperation = $this->emailTrackingService->createBulkOperation(
            'pupcet_result',
            $applicants->count(),
            $authUser->id
        );

        if (!$bulkOperation->exists) {
            return response()->json([
                'message' => 'Failed to create bulk email operation. No emails were dispatched.',
            ], 500);
        }

        $successCount = 0;

        foreach ($applicants as $applicant) {
            $testPasser = $applicant->testPasser;

            $firstName = $testPasser?->first_name ?? $applicant->firstname;
            $surname   = $testPasser?->surname ?? $applicant->lastname;
            $refNo     = $testPasser?->reference_number ?? 'N/A';

            $redName = '<span style="color:#cc0000;">' . $firstName . ' ' . $surname . '</span>';

            // Support highly robust and case-insensitive replacements for all name/ref variations
            $searchTags = [
                '{{firstname}} {{surname}}',
                '{{first_name}} {{surname}}',
                '{{firstname}} {{lastname}}',
                '{{first_name}} {{lastname}}',
                '{{first_name}} {{last_name}}',
                '{{firstname}}',
                '{{first_name}}',
                '{{surname}}',
                '{{lastname}}',
                '{{last_name}}',
                '{{reference_no}}',
                '{{reference_number}}',
                '{{ref_no}}'
            ];
            $replaceValues = [
                $redName,
                $redName,
                $redName,
                $redName,
                $redName,
                $firstName,
                $firstName,
                $surname,
                $surname,
                $surname,
                $refNo,
                $refNo,
                $refNo
            ];

            $personalizedMessage = str_ireplace($searchTags, $replaceValues, $messageTemplate);

            // Create email log record for tracking
            $recipientEmail = $testPasser?->email ?? $applicant->email;
            $emailLog = $this->emailTrackingService->createEmailLog(
                $bulkOperation->id,
                $recipientEmail,
                trim($firstName . ' ' . $surname),
                $testPasser?->test_passer_id,
                'pupcet_result',
                $personalizedMessage
            );

            // Dispatch with staggered delay to avoid triggering receiving server throttling
            $delaySeconds = config('email-tracking.delay_between_emails_seconds', 30);

            // Use a fake TestPasser-like object for the job (compatible with SendPasserEmail)
            // We dispatch with a mock passer to reuse the existing job infrastructure
            $subject = 'PUP Taguig Admission';

            if ($testPasser) {
                SendPasserEmail::dispatch(
                    $testPasser,
                    $personalizedMessage,
                    $emailLog->exists ? $emailLog->id : null,
                    $bulkOperation->id,
                    $subject
                )->delay(now()->addSeconds($successCount * $delaySeconds));
            } else {
                // For applicants without a linked test passer, create a minimal object
                $mockPasser = new TestPasser();
                $mockPasser->email      = $applicant->email;
                $mockPasser->first_name = $applicant->firstname;
                $mockPasser->surname    = $applicant->lastname;
                SendPasserEmail::dispatch(
                    $mockPasser,
                    $personalizedMessage,
                    $emailLog->exists ? $emailLog->id : null,
                    $bulkOperation->id,
                    $subject
                )->delay(now()->addSeconds($successCount * $delaySeconds));
            }

            $successCount++;
        }

        $this->auditLogService->logActivity(
            'CREATE',
            'Confirmed Applicants',
            "Sent custom emails to {$successCount} confirmed applicant(s).",
            null,
            'ADMISSION_DATA'
        );

        return response()->json([
            'message'           => "Custom emails sent to {$successCount} confirmed applicant(s) successfully!",
            'success_count'     => $successCount,
            'bulk_operation_id' => $bulkOperation->id,
        ]);
    }
}
