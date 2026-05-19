<?php

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
use App\Jobs\SendSarFormEmail;
use App\Jobs\SendPasserEmail;

class ConfirmedApplicantsController extends Controller
{
    public function __construct(private AuditLogService $auditLogService) {}

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
     * JSON: Get all confirmed applicants (status = for_evaluation).
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
            'grades',
            'testPasser.passerStatus',
        ])
            ->whereHas('currentApplication.processes', function ($q) {
                $q->where('stage', 'evaluator')
                    ->whereIn('status', ['in_progress', 'returned']);
            })
            ->orderBy('lastname')
            ->get()
            ->map(function ($applicant) {
                $testPasser = $applicant->testPasser;

                // Check if this applicant has a linked test passer record
                $hasTestPasser = $testPasser !== null;

                // Check if grades exist and whether they differ from test passer scores
                $gradesExist = $applicant->grades !== null;
                $gradesSynced = false;

                if ($hasTestPasser && $gradesExist) {
                    // We consider grades "synced" if they were imported from the passer list
                    // This is a simple heuristic: if grades exist, they've been synced at least once
                    $gradesSynced = true;
                }

                return [
                    'id'           => $applicant->user_id,
                    'firstname'    => $applicant->firstname,
                    'lastname'     => $applicant->lastname,
                    'email'        => $applicant->email,
                    'student_number' => $applicant->student_number,
                    'status'       => $applicant->currentApplication?->status,
                    'program'      => $applicant->currentApplication?->program,
                    'grades'       => $applicant->grades,
                    'has_test_passer' => $hasTestPasser,
                    'grades_synced'   => $gradesSynced,
                    'test_passer_id'  => $testPasser?->test_passer_id,
                    'reference_number' => $testPasser?->reference_number,
                    'batch_number' => $testPasser?->batch_number,
                    'passer_status_id' => $testPasser?->passer_status_id,
                    'passer_status_name' => $testPasser?->passerStatus?->status,
                    'sar_sent'     => $testPasser
                        ? SarGeneration::where('test_passer_id', $testPasser->test_passer_id)
                        ->where('email_sent_successfully', true)
                        ->exists()
                        : false,
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
            // enrollment_time is determined automatically by score/batch
        ]);

        $applicantIds   = $request->input('applicant_ids');
        $enrollmentDate = $request->input('enrollment_date');

        // Verify all applicants are confirmed (for_evaluation)
        $applicants = ApplicantProfile::with(['currentApplication', 'testPasser'])
            ->whereIn('user_id', $applicantIds)
            ->whereHas('currentApplication.processes', function ($q) {
                $q->where('stage', 'evaluator')
                    ->whereIn('status', ['in_progress', 'returned']);
            })
            ->get()
            ->sortByDesc(function ($applicant) {
                return $applicant->testPasser->pupcet_total_score ?? 0;
            });

        if ($applicants->isEmpty()) {
            return response()->json([
                'message' => 'No confirmed applicants found. SAR Forms can only be sent to applicants with "For Evaluation" status.',
            ], 422);
        }

        // Automate schedule based on Score & Batch, and restrict per batch
        $selectedBatch = null;

        foreach ($applicants as $app) {
            $passer = $app->testPasser;
            $batch_number = $passer->batch_number ?? '?';
            $status_id = $passer->passer_status_id ?? 3;

            // Restrict SAR to Qualifiers only (status 1)
            if ($status_id != 1) {
                return response()->json([
                    'message' => "Cannot send SAR Forms to Non-Qualifiers or Waitlisted applicants.",
                ], 422);
            }

            // Ensure all selected applicants belong to the exact same batch 
            // (e.g. "Batch 1" cannot be mixed with "Batch 2")
            if ($selectedBatch === null) {
                $selectedBatch = $batch_number;
            } elseif ($selectedBatch !== $batch_number) {
                return response()->json([
                    'message' => 'Restriction: You must select applicants from the same batch. Your selection contains both ' . $selectedBatch . ' and ' . $batch_number . '.',
                ], 422);
            }
        }

        $sarService   = app(SarFormService::class);
        $successCount = 0;
        $failedCount  = 0;
        $errors       = [];

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

            // Automate interview time explicitly based on the updated cutoff rules
            $score = $testPasser->pupcet_total_score ?? 0;
            if ($score >= 85) {
                $enrollmentTime = '08:00'; // 8:00 AM
            } elseif ($score >= 79) {
                $enrollmentTime = '10:00'; // 10:00 AM
            } elseif ($score >= 77) {
                $enrollmentTime = '13:00'; // 1:00 PM
            } elseif ($score >= 75) {
                $enrollmentTime = '14:00'; // 2:00 PM
            } else {
                $enrollmentTime = 'TBD';
            }

            try {
                $sarData = [
                    'id'               => 'ap_' . $applicant->user_id,
                    'reference_number' => $testPasser->reference_number ?? 'N/A',
                    'full_name'        => trim($testPasser->surname . ', ' . $testPasser->first_name . ' ' . ($testPasser->middle_name ?? '')),
                    'surname'          => $testPasser->surname,
                    'firstname_middle' => trim($testPasser->first_name . ' ' . ($testPasser->middle_name ?? '')),
                    'shs_strand'       => $testPasser->strand ?? 'N/A',
                    'graduation_year'  => $testPasser->year_graduated ?? date('Y'),
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

                    SendSarFormEmail::dispatch($testPasser, $downloadUrl, $sarGeneration->id);

                    $successCount++;
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
            'message'       => "SAR emails sent: {$successCount} successful, {$failedCount} failed",
            'success_count' => $successCount,
            'failed_count'  => $failedCount,
            'errors'        => $errors,
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

        // Verify all applicants are confirmed
        $applicants = ApplicantProfile::with(['currentApplication', 'testPasser'])
            ->whereIn('user_id', $applicantIds)
            ->whereHas('currentApplication.processes', function ($q) {
                $q->where('stage', 'evaluator')
                    ->whereIn('status', ['in_progress', 'returned']);
            })
            ->get();

        if ($applicants->isEmpty()) {
            return response()->json([
                'message' => 'No confirmed applicants found.',
            ], 422);
        }

        $successCount = 0;

        foreach ($applicants as $applicant) {
            $testPasser = $applicant->testPasser;

            $firstName = $testPasser?->first_name ?? $applicant->firstname;
            $surname   = $testPasser?->surname ?? $applicant->lastname;
            $refNo     = $testPasser?->reference_number ?? 'N/A';

            $redName = '<span style="color:#cc0000;">' . $firstName . ' ' . $surname . '</span>';

            $personalizedMessage = str_replace(
                ['{{firstname}} {{surname}}', '{{firstname}}', '{{surname}}', '{{reference_no}}'],
                [$redName, $firstName, $surname, $refNo],
                $messageTemplate
            );

            // Use a fake TestPasser-like object for the job (compatible with SendPasserEmail)
            // We dispatch with a mock passer to reuse the existing job infrastructure
            if ($testPasser) {
                SendPasserEmail::dispatch($testPasser, $personalizedMessage);
            } else {
                // For applicants without a linked test passer, create a minimal object
                $mockPasser = new TestPasser();
                $mockPasser->email      = $applicant->email;
                $mockPasser->first_name = $applicant->firstname;
                $mockPasser->surname    = $applicant->lastname;
                SendPasserEmail::dispatch($mockPasser, $personalizedMessage);
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
            'message'       => "Custom emails sent to {$successCount} confirmed applicant(s) successfully!",
            'success_count' => $successCount,
        ]);
    }
}
