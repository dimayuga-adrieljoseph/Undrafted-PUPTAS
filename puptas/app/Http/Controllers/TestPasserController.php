<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\TestPassersImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\TestPasser;
use App\Models\PasserStatus;
use App\Models\SarGeneration;
use Inertia\Inertia;
use Illuminate\Support\Facades\Mail;
use App\Mail\TestPasserEmail;
use App\Mail\SarFormEmail;
use App\Mail\WaitlistedEmail;
use App\Jobs\SendPasserEmail;
use App\Jobs\SendSarFormEmail;
use App\Jobs\SendWaitlistedEmail;
use App\Services\SarFormService;
use App\Services\AuditLogService;
use App\Services\EmailTrackingService;
use Symfony\Component\Mime\Part\TextPart;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;

class TestPasserController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService,
        private EmailTrackingService $emailTrackingService,
    ) {}
    // Get grouped passers by school year and batch number


    public function index(Request $request)
    {
        // Per-page clamping BEFORE validation: default 15 for non-numeric, clamp to [1, 100]
        $perPageInput = $request->input('per_page');
        if (!is_numeric($perPageInput)) {
            $perPage = 15;
        } else {
            $perPage = max(1, min(100, (int) $perPageInput));
        }

        // Merge the clamped per_page back into the request so validation passes
        $request->merge(['per_page' => $perPage]);

        // Validate filter and pagination parameters
        $validated = $request->validate([
            'school_year' => 'nullable|string|max:20',
            'batch_number' => 'nullable|string|max:50',
            'strand' => 'nullable|string|max:100',
            'status' => 'nullable|integer|exists:passer_statuses,id',
            'search' => 'nullable|string|max:100',
            'sort_key' => 'nullable|string|in:pupcet_total_score,surname,first_name,email,school_year,batch_number',
            'sort_order' => 'nullable|string|in:asc,desc',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Default school_year: most recent when not provided
        $schoolYear = $request->input('school_year');
        if (!$request->has('school_year')) {
            $schoolYear = TestPasser::max('school_year');
            if ($schoolYear) {
                $request->merge(['school_year' => $schoolYear]);
            }
        }

        // Default batch_number: first available for selected school_year when not provided
        $batchNumber = $request->input('batch_number');
        if (!$request->has('batch_number') && $schoolYear && $schoolYear !== 'all') {
            $batchNumber = TestPasser::where('school_year', $schoolYear)
                ->whereNotNull('batch_number')
                ->orderBy('batch_number')
                ->value('batch_number');
            if ($batchNumber) {
                $request->merge(['batch_number' => $batchNumber]);
            }
        }

        try {
            // Build filtered query and paginate
            $query = $this->buildQuery($request);
            $passers = $query->paginate($perPage);

            // Page clamping: if page > last_page, re-query with last_page
            $lastPage = $passers->lastPage();
            $currentPage = $passers->currentPage();

            if ($currentPage > $lastPage && $lastPage > 0) {
                $request->merge(['page' => $lastPage]);
                $passers = $this->buildQuery($request)->paginate($perPage, ['*'], 'page', $lastPage);
            }
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'timeout') || str_contains($e->getMessage(), 'Lock wait')) {
                return back()->with('error', 'The request timed out. Please try again.');
            }
            throw $e;
        }

        // Get filter options for dropdowns
        $filterOptions = $this->getFilterOptions($schoolYear);

        // Build current filters state
        $filters = [
            'school_year' => $schoolYear,
            'batch_number' => $batchNumber,
            'strand' => $request->input('strand'),
            'status' => $request->input('status') ? (int) $request->input('status') : null,
            'search' => $request->input('search'),
            'sort_key' => $request->input('sort_key', 'pupcet_total_score'),
            'sort_order' => $request->input('sort_order', 'desc'),
        ];

        return Inertia::render('TestPassers/Email', [
            'passers' => $passers,
            'filterOptions' => $filterOptions,
            'filters' => $filters,
            'registrationUrl' => url('/links/register'),
            'admissionCriteriaUrl' => url('/links/admission-criteria'),
            'facebookUrl' => url('/links/facebook'),
        ]);
    }



    /**
     * Build an optimized Eloquent query with conditional filters, search, and ordering.
     *
     * Applies WHERE clauses for school_year, batch_number, strand, and passer_status_id
     * only when the corresponding request parameter is present and non-empty.
     * Supports server-side search across surname, first_name, and email.
     * Supports configurable sort column and direction.
     */
    private function buildQuery(Request $request): Builder
    {
        $query = TestPasser::query()->with('passerStatus');

        if ($request->filled('school_year') && $request->input('school_year') !== 'all') {
            $query->where('school_year', $request->input('school_year'));
        }

        if ($request->filled('batch_number') && $request->input('batch_number') !== 'all') {
            $query->where('batch_number', $request->input('batch_number'));
        }

        if ($request->filled('strand')) {
            $query->where('strand', $request->input('strand'));
        }

        if ($request->filled('status')) {
            $query->where('passer_status_id', $request->input('status'));
        }

        // Server-side search across name and email
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('surname', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Configurable sort (whitelist allowed columns)
        $allowedSortColumns = ['pupcet_total_score', 'surname', 'first_name', 'email', 'school_year', 'batch_number'];
        $sortKey = $request->input('sort_key', 'pupcet_total_score');
        $sortOrder = $request->input('sort_order', 'desc');

        if (!in_array($sortKey, $allowedSortColumns)) {
            $sortKey = 'pupcet_total_score';
        }
        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        $query->orderBy($sortKey, $sortOrder);

        return $query;
    }

    /**
     * Return all test_passer_id values matching the current filters.
     * Used by the frontend "Select All" feature to select across all pages.
     */
    public function selectAllIds(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = TestPasser::query();

        if ($request->filled('school_year') && $request->input('school_year') !== 'all') {
            $query->where('school_year', $request->input('school_year'));
        }

        if ($request->filled('batch_number') && $request->input('batch_number') !== 'all') {
            $query->where('batch_number', $request->input('batch_number'));
        }

        if ($request->filled('strand')) {
            $query->where('strand', $request->input('strand'));
        }

        if ($request->filled('status')) {
            $query->where('passer_status_id', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('surname', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $ids = $query->pluck('test_passer_id')->all();

        return response()->json(['ids' => $ids]);
    }

    public function sendEmails(Request $request)
    {
        $passerIds = $request->input('passer_ids');
        $messageTemplate = $request->input('message_template');
        $templateType = $request->input('template_type', 'default');
        $enrollmentDate = $request->input('enrollment_date');
        $enrollmentTime = $request->input('enrollment_time');

        // Check if inputs are present
        if (!$passerIds) {
            return response()->json(['error' => 'Missing required inputs'], 422);
        }

        // Validate recipient count against max_recipients_per_operation config
        $maxRecipients = config('email-tracking.max_recipients_per_operation', 2000);
        if (count($passerIds) > $maxRecipients) {
            return response()->json([
                'error' => "The maximum recipient limit of {$maxRecipients} has been exceeded.",
            ], 422);
        }

        // Map template_type to email_type for tracking
        $emailTypeMap = [
            'default' => 'pupcet_result',
            'sar'     => 'sar_form',
            'waitlisted' => 'waitlisted',
        ];
        $emailType = $emailTypeMap[$templateType] ?? 'pupcet_result';

        // Create BulkEmailOperation record before dispatching any jobs
        $operation = $this->emailTrackingService->createBulkOperation(
            $emailType,
            count($passerIds),
            auth()->id(),
        );

        // If BulkEmailOperation creation fails, return error without dispatching
        if (!$operation->exists) {
            return response()->json([
                'error' => 'Failed to create bulk email operation. No emails were dispatched.',
            ], 500);
        }

        // Handle SAR Template - Generate PDFs and send with download links
        if ($templateType === 'sar') {
            if (!$enrollmentDate || !$enrollmentTime) {
                return response()->json(['error' => 'Enrollment date and time are required for SAR template'], 422);
            }
            return $this->sendSarEmails($passerIds, $enrollmentDate, $enrollmentTime, $operation);
        }

        // Handle Waitlisted Template
        if ($templateType === 'waitlisted') {
            if (!$messageTemplate) {
                return response()->json(['error' => 'Message template is required for waitlisted emails'], 422);
            }

            $chunkSize = config('email-tracking.chunk_size', 100);
            $delaySeconds = config('email-tracking.delay_between_emails_seconds', 30);
            $globalIndex = 0;

            // Load recipients in chunks
            foreach (array_chunk($passerIds, $chunkSize) as $chunkIds) {
                $passers = TestPasser::whereIn('test_passer_id', $chunkIds)->get();

                foreach ($passers as $passer) {
                    // Replace placeholders in template for personalization
                    $confirmationUrl = url('/links/register');
                    $redName = '<span style="color:#cc0000;">' . $passer->first_name . ' ' . $passer->surname . '</span>';
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
                        '{{ref_no}}',
                        '{{confirmationUrl}}'
                    ];
                    $replaceValues = [
                        $redName,
                        $redName,
                        $redName,
                        $redName,
                        $redName,
                        $passer->first_name,
                        $passer->first_name,
                        $passer->surname,
                        $passer->surname,
                        $passer->surname,
                        $passer->reference_number,
                        $passer->reference_number,
                        $passer->reference_number,
                        $confirmationUrl
                    ];

                    $personalizedMessage = str_ireplace($searchTags, $replaceValues, $messageTemplate);

                    // Create EmailLog record for this recipient
                    $emailLog = $this->emailTrackingService->createEmailLog(
                        $operation->id,
                        $passer->email,
                        $passer->first_name . ' ' . $passer->surname,
                        $passer->test_passer_id,
                        $emailType,
                        $personalizedMessage,
                    );

                    // Dispatch job with emailLogId, bulkOperationId, and staggered delay
                    SendWaitlistedEmail::dispatch(
                        $passer,
                        $personalizedMessage,
                        $emailLog->exists ? $emailLog->id : null,
                        $operation->id,
                    )->delay(now()->addSeconds($delaySeconds * $globalIndex));

                    $globalIndex++;
                }
            }

            $this->auditLogService->logActivity('CREATE', 'Test Passers', "Sent waitlisted emails to " . count($passerIds) . " passer(s).", null, 'ADMISSION_DATA');

            return response()->json([
                'message' => 'Waitlisted emails sent successfully!',
                'bulk_operation_id' => $operation->id,
            ]);
        }

        // Handle Default and Custom templates
        if (!$messageTemplate) {
            return response()->json(['error' => 'Message template is required'], 422);
        }

        $chunkSize = config('email-tracking.chunk_size', 100);
        $delaySeconds = config('email-tracking.delay_between_emails_seconds', 30);
        $globalIndex = 0;

        // Load recipients in chunks
        foreach (array_chunk($passerIds, $chunkSize) as $chunkIds) {
            $passers = TestPasser::whereIn('test_passer_id', $chunkIds)->get();

            foreach ($passers as $passer) {
                // Replace placeholders in template for personalization
                $redName = '<span style="color:#cc0000;">' . $passer->first_name . ' ' . $passer->surname . '</span>';
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
                    '{{last_name}}'
                ];
                $replaceValues = [
                    $redName,
                    $redName,
                    $redName,
                    $redName,
                    $redName,
                    $passer->first_name,
                    $passer->first_name,
                    $passer->surname,
                    $passer->surname,
                    $passer->surname
                ];

                $personalizedMessage = str_ireplace($searchTags, $replaceValues, $messageTemplate);

                // Create EmailLog record for this recipient
                $emailLog = $this->emailTrackingService->createEmailLog(
                    $operation->id,
                    $passer->email,
                    $passer->first_name . ' ' . $passer->surname,
                    $passer->test_passer_id,
                    $emailType,
                    $personalizedMessage,
                );

                // Dispatch job with emailLogId, bulkOperationId, and staggered delay
                SendPasserEmail::dispatch(
                    $passer,
                    $personalizedMessage,
                    $emailLog->exists ? $emailLog->id : null,
                    $operation->id,
                )->delay(now()->addSeconds($delaySeconds * $globalIndex));

                $globalIndex++;
            }
        }

        $this->auditLogService->logActivity('CREATE', 'Test Passers', "Sent emails to " . count($passerIds) . " passer(s) using {$templateType} template.", null, 'ADMISSION_DATA');

        return response()->json([
            'message' => 'Emails sent successfully!',
            'bulk_operation_id' => $operation->id,
        ]);
    }

    /**
     * Send SAR form emails with PDF download links.
     * SAR Forms can only be sent to confirmed applicants (for_evaluation status).
     */
    private function sendSarEmails($passerIds, $enrollmentDate, $enrollmentTime, $operation)
    {
        $sarService = app(SarFormService::class);
        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        // Pre-filter: only passers with a linked confirmed applicant (for_evaluation)
        $confirmedPasserIds = \App\Models\ApplicantProfile::whereHas('currentApplication.processes', function ($q) {
            $q->where('stage', 'evaluator')
                ->whereIn('status', ['in_progress', 'returned']);
        })
            ->whereHas('testPasser')
            ->with('testPasser:test_passer_id,user_id')
            ->get()
            ->pluck('testPasser.test_passer_id')
            ->toArray();

        $chunkSize = config('email-tracking.chunk_size', 100);
        $delaySeconds = config('email-tracking.delay_between_emails_seconds', 30);
        $globalIndex = 0;

        // Load recipients in chunks
        foreach (array_chunk($passerIds, $chunkSize) as $chunkIds) {
            $passers = TestPasser::whereIn('test_passer_id', $chunkIds)->get();

            foreach ($passers as $passer) {
                $sarGeneration = null;
                $emailSuccess = false;

                // Enforce: SAR can only be sent to confirmed applicants (for_evaluation)
                if (!in_array($passer->test_passer_id, $confirmedPasserIds)) {
                    $failedCount++;
                    $errors[] = [
                        'passer' => $passer->first_name . ' ' . $passer->surname,
                        'email'  => $passer->email,
                        'error'  => 'Not a confirmed applicant. SAR Forms can only be sent to applicants with "For Evaluation" status.',
                    ];

                    // Create a failed EmailLog for tracking
                    $emailLog = $this->emailTrackingService->createEmailLog(
                        $operation->id,
                        $passer->email,
                        $passer->first_name . ' ' . $passer->surname,
                        $passer->test_passer_id,
                        'sar_form',
                        null,
                    );
                    if ($emailLog->exists) {
                        $this->emailTrackingService->markFailed($emailLog->id, 'Not a confirmed applicant. SAR Forms can only be sent to applicants with "For Evaluation" status.');
                        $this->emailTrackingService->updateBulkProgress($operation->id);
                    }

                    continue;
                }

                try {
                    // Prepare SAR data from test passer
                    $sarData = $this->prepareSarDataFromPasser($passer, $enrollmentDate, $enrollmentTime);

                    // Generate SAR PDF
                    $result = $sarService->generateSarPdf($sarData);

                    if ($result['success']) {
                        // Generate signed download URL (valid for 30 days)
                        $downloadUrl = \URL::temporarySignedRoute(
                            'sar.passer-download',
                            now()->addDays(30),
                            [
                                'reference' => $passer->reference_number,
                                'filename' => $result['filename']
                            ]
                        );

                        // Create SAR generation record BEFORE sending email (no sent_at yet)
                        $sarGeneration = SarGeneration::create([
                            'test_passer_id' => $passer->test_passer_id,
                            'filename' => $result['filename'],
                            'file_path' => $result['pdf_path'],
                            'enrollment_date' => $enrollmentDate,
                            'enrollment_time' => $enrollmentTime,
                            'sent_to_email' => $passer->email,
                            'created_by_user_id' => auth()->id(),
                            'email_sent_successfully' => false,
                        ]);

                        // Create EmailLog record for this recipient
                        $emailLog = $this->emailTrackingService->createEmailLog(
                            $operation->id,
                            $passer->email,
                            $passer->first_name . ' ' . $passer->surname,
                            $passer->test_passer_id,
                            'sar_form',
                            $downloadUrl,
                        );

                        // Dispatch unique job with emailLogId, bulkOperationId, and staggered delay
                        SendSarFormEmail::dispatch(
                            $passer,
                            $downloadUrl,
                            $sarGeneration->id,
                            $emailLog->exists ? $emailLog->id : null,
                            $operation->id,
                        )->delay(now()->addSeconds($delaySeconds * $globalIndex));

                        $emailSuccess = true;
                        $successCount++;
                        $globalIndex++;
                    } else {
                        $failedCount++;
                        $errors[] = [
                            'passer' => $passer->first_name . ' ' . $passer->surname,
                            'email' => $passer->email,
                            'error' => $result['error']
                        ];

                        // Create a failed EmailLog for tracking
                        $emailLog = $this->emailTrackingService->createEmailLog(
                            $operation->id,
                            $passer->email,
                            $passer->first_name . ' ' . $passer->surname,
                            $passer->test_passer_id,
                            'sar_form',
                            null,
                        );
                        if ($emailLog->exists) {
                            $this->emailTrackingService->markFailed($emailLog->id, $result['error'] ?? 'PDF generation failed');
                            $this->emailTrackingService->updateBulkProgress($operation->id);
                        }
                    }
                } catch (\Exception $e) {
                    $failedCount++;

                    $errorMessage = $e->getMessage() ?: 'Unknown error occurred while sending SAR email';

                    $errors[] = [
                        'passer' => $passer->first_name . ' ' . $passer->surname,
                        'email' => $passer->email,
                        'error' => $errorMessage,
                    ];

                    // If the SAR generation record was created but email failed, mark it with the error
                    if ($sarGeneration) {
                        $sarGeneration->update([
                            'email_sent_successfully' => false,
                            'sent_at' => null,
                        ]);
                    }

                    // Create a failed EmailLog for tracking
                    $emailLog = $this->emailTrackingService->createEmailLog(
                        $operation->id,
                        $passer->email,
                        $passer->first_name . ' ' . $passer->surname,
                        $passer->test_passer_id,
                        'sar_form',
                        null,
                    );
                    if ($emailLog->exists) {
                        $this->emailTrackingService->markFailed($emailLog->id, $errorMessage);
                        $this->emailTrackingService->updateBulkProgress($operation->id);
                    }

                    // Log detailed error for debugging
                    \Log::error('SAR email failed for passer: ' . $passer->test_passer_id, [
                        'email' => $passer->email,
                        'error' => $errorMessage,
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }

        $message = "SAR emails sent: {$successCount} successful, {$failedCount} failed";

        $this->auditLogService->logActivity('CREATE', 'Test Passers', "Sent SAR form emails: {$successCount} successful, {$failedCount} failed.", null, 'ADMISSION_DATA');

        return response()->json([
            'message' => $message,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'errors' => $errors,
            'bulk_operation_id' => $operation->id,
        ], $failedCount > 0 ? 207 : 200); // 207 Multi-Status if some failed
    }

    /**
     * Prepare SAR form data from TestPasser model
     */
    private function prepareSarDataFromPasser($passer, $enrollmentDate, $enrollmentTime)
    {
        // Build full name in SAR format: "Surname, Firstname Middlename"
        $fullName = trim($passer->surname . ', ' . $passer->first_name . ' ' . ($passer->middle_name ?? ''));

        // Natural name format for affidavit: "Firstname Middlename Surname"
        $fullNameNatural = trim($passer->first_name . ' ' . ($passer->middle_name ?? '') . ' ' . $passer->surname);

        // Format enrollment date as "Month Day, Year" (e.g. May 18, 2026)
        $formattedDate = $enrollmentDate
            ? \Carbon\Carbon::parse($enrollmentDate)->format('F j, Y')
            : \Carbon\Carbon::now()->format('F j, Y');

        // Format enrollment time with AM/PM (e.g. 08:30 AM)
        $formattedTime = $enrollmentTime
            ? \Carbon\Carbon::parse($enrollmentTime)->format('h:i A')
            : \Carbon\Carbon::now()->format('h:i A');

        return [
            'id' => 'tp_' . $passer->test_passer_id,
            'reference_number' => $passer->reference_number ?? 'N/A',
            'full_name' => $fullName,
            'full_name_natural' => $fullNameNatural,
            'shs_strand' => $passer->strand ?? 'N/A',
            'graduation_year' => $passer->graduation_year,
            'school_attended' => $passer->shs_school ?? 'N/A',
            'enrollment_date' => $formattedDate,
            'enrollment_time' => $formattedTime,
            'campus' => 'Taguig Campus',
        ];
    }



    // Helper function to replace placeholders
    private function replacePlaceholders($template, $passer)
    {
        $searchTags = [
            '{{firstname}}',
            '{{first_name}}',
            '{{surname}}',
            '{{lastname}}',
            '{{last_name}}'
        ];
        $replaceValues = [
            $passer->first_name,
            $passer->first_name,
            $passer->surname,
            $passer->surname,
            $passer->surname
        ];
        return str_ireplace($searchTags, $replaceValues, $template);
    }

    public function upload(Request $request)
    {
        // Validate payload fields strictly
        $request->validate([
            'batch_number' => 'required|string',
            'school_year' => 'required|string|max:9|regex:/^\d{4}-\d{4}$/',
            'passer_status_id' => 'required|integer|in:1,2,3,4',
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $batch = $request->input('batch_number');
        $schoolYear = $request->input('school_year');
        $passerStatusId = (int) $request->input('passer_status_id');

        $import = new TestPassersImport($batch, $schoolYear, $passerStatusId);
        \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('file'));

        $importedCount = $import->getImportedCount();
        $skippedCount = $import->getSkippedCount();

        $statusNames = [1 => 'Qualified', 2 => 'Waitlisted', 3 => 'Unqualified', 4 => 'Waitlisted Below Cut Off'];
        $statusName = $statusNames[$passerStatusId] ?? 'Unknown';
        $this->auditLogService->logActivity('CREATE', 'Test Passers', "Uploaded passers file for batch {$batch}, school year {$schoolYear}, status: {$statusName}. Imported: {$importedCount}, Skipped: {$skippedCount}.", null, 'ADMISSION_DATA');

        if ($importedCount === 0 && $skippedCount > 0) {
            return response()->json([
                'message' => 'No new records were imported. All entries already exist in the system.',
                'imported_count' => $importedCount,
                'skipped_count' => $skippedCount,
            ], 422);
        }

        return response()->json([
            'message' => 'Excel file uploaded and data imported successfully.',
            'imported_count' => $importedCount,
            'skipped_count' => $skippedCount,
        ]);
    }

    public function update(Request $request, $id)
    {
        // Find the passer or fail
        $passer = TestPasser::findOrFail($id);
        $oldValues = $passer->toArray();

        // Validate input
        $validatedData = $request->validate([
            'surname' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'strand' => 'nullable|string|max:255',
            'shs_school' => 'nullable|string|max:255',
            'year_graduated' => 'nullable|digits:4|integer|min:1900|max:' . date('Y'),
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('test_passers')->ignore($passer->test_passer_id, 'test_passer_id'),
            ],
            'reference_number' => 'nullable|string|max:255',
            'batch_number' => 'nullable|string|max:255',
            'school_year' => 'nullable|string|max:9|regex:/^\d{4}-\d{4}$/',
            'pupcet_total_score' => 'nullable|numeric|min:0|max:999.99',
            'passer_status_id' => 'nullable|exists:passer_statuses,id',
            'graduate_of' => 'nullable|string|max:255',
            'graduation_date' => 'nullable|date',
        ]);

        // Only Superadmin (role_id 7) can update the email
        if (auth()->user()->role_id !== 7) {
            unset($validatedData['email']);
        }

        // Update passer with validated data
        $passer->update($validatedData);
        $newValues = $passer->fresh()->toArray();

        $this->auditLogService->logActivity(
            'UPDATE', 
            'Test Passers', 
            "Updated passer: {$passer->first_name} {$passer->surname} (ID: {$passer->test_passer_id}).", 
            null, 
            'ADMISSION_DATA',
            $oldValues,
            $newValues
        );

        return response()->json([
            'message' => 'Passer updated successfully',
            'passer' => $passer,
        ]);
    }

    public function store(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'surname' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'strand' => 'nullable|string|max:255',
            'shs_school' => 'nullable|string|max:255',
            'year_graduated' => 'nullable|digits:4|integer|min:1900|max:' . (date('Y') + 1),
            'email' => 'required|email|unique:test_passers,email',
            'reference_number' => 'nullable|string|max:255',
            'batch_number' => 'nullable|string|max:255',
            'school_year' => 'nullable|string|max:9|regex:/^\d{4}-\d{4}$/',
            'pupcet_total_score' => 'nullable|numeric|min:0|max:999.99',
            'passer_status_id' => 'nullable|exists:passer_statuses,id',
            'graduate_of' => 'nullable|string|max:255',
            'graduation_date' => 'nullable|date',
        ]);

        // Create new passer record
        $passer = TestPasser::create($validated);

        $this->auditLogService->logActivity('CREATE', 'Test Passers', "Added new passer: {$passer->first_name} {$passer->surname} ({$passer->email}).", null, 'ADMISSION_DATA');

        // Return the new passer data (adjust as needed)
        return response()->json($passer, 201);
    }

    /**
     * Download SAR PDF for a specific test passer
     * Public route with reference number verification
     */
    public function downloadSar($reference, $filename)
    {
        // Validate filename pattern (alphanumeric, dash, underscore, period only)
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
            abort(400, 'Invalid filename pattern');
        }

        // Block any path traversal attempts
        if (strpos($filename, '..') !== false) {
            \Log::warning('SAR download blocked: path traversal attempt detected', [
                'filename' => $filename,
                'reference' => $reference,
            ]);
            abort(400, 'Invalid filename');
        }

        // Validate reference number exists
        $passer = TestPasser::where('reference_number', $reference)->first();

        if (!$passer) {
            abort(404, 'Invalid reference number');
        }

        // Sanitize filename to prevent path traversal attacks
        $filename = basename($filename);

        // Construct the expected filename pattern
        $expectedFilenamePattern = 'SAR_' . $reference . '_';

        // Verify filename matches the reference number
        if (strpos($filename, $expectedFilenamePattern) !== 0) {
            abort(403, 'Unauthorized access');
        }

        // Use sar_tmp disk for consistent file access
        $disk = Storage::disk('sar_tmp');

        // Check if file exists - if not, try to regenerate it
        if (!$disk->exists($filename)) {
            \Log::warning('SAR file not found, attempting to regenerate', [
                'filename' => $filename,
                'reference' => $reference,
            ]);

            // Try to regenerate the SAR file
            try {
                $sarGeneration = SarGeneration::where('filename', $filename)
                    ->where('test_passer_id', $passer->test_passer_id)
                    ->first();

                if (!$sarGeneration) {
                    \Log::error('SAR generation record not found', [
                        'filename' => $filename,
                        'reference' => $reference,
                    ]);
                    abort(404, 'File not found or expired. Please contact the admission office.');
                }

                // Regenerate the SAR file
                $sarService = app(\App\Services\SarFormService::class);

                $fullName = trim("{$passer->surname}, {$passer->first_name} " . ($passer->middle_name ?? ''));

                $rowData = [
                    'reference_number' => $passer->reference_number,
                    'full_name' => $fullName,
                    'graduation_year' => $passer->graduation_year,
                    'school_attended' => $passer->shs_school ?? 'N/A',
                    'shs_strand' => $passer->strand ?? 'N/A',
                    'enrollment_date' => $sarGeneration->enrollment_date ?
                        \Carbon\Carbon::parse($sarGeneration->enrollment_date)->format('F d, Y') :
                        date('F d, Y'),
                    'enrollment_time' => $sarGeneration->enrollment_time ?? date('h:i A'),
                    'student_number' => $passer->student_number ?? '',
                    'admission_status' => 'Admitted',
                ];

                $result = $sarService->generateSarPdf($rowData);

                if (!$result['success']) {
                    \Log::error('SAR regeneration failed', [
                        'filename' => $filename,
                        'error' => $result['error'] ?? 'Unknown error',
                    ]);
                    abort(500, 'Unable to regenerate file. Please contact the admission office.');
                }

                // Update the filename in case it changed
                $filename = $result['filename'];

                \Log::info('SAR file regenerated successfully', [
                    'filename' => $filename,
                    'reference' => $reference,
                ]);
            } catch (\Exception $e) {
                \Log::error('SAR regeneration exception', [
                    'filename' => $filename,
                    'reference' => $reference,
                    'error' => $e->getMessage(),
                ]);
                abort(500, 'Unable to regenerate file. Please contact the admission office.');
            }
        }

        // Return file download response
        $disk = Storage::disk('sar_tmp');

        // Check if using S3 by checking if path() method throws exception
        try {
            $localPath = $disk->path($filename);
            // Local storage - use regular download
            return response()->download($localPath, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\RuntimeException $e) {
            // S3 storage - stream the file
            $stream = $disk->readStream($filename);

            if (! is_resource($stream)) {
                \Log::error('SAR S3 stream could not be opened', [
                    'filename' => $filename,
                    'disk'     => 'sar_tmp',
                ]);
                abort(404, 'SAR file could not be retrieved. Please contact the admission office.');
            }

            return response()->streamDownload(function () use ($stream) {
                try {
                    fpassthru($stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        }
    }

    /**
     * Get all SAR generations for admin viewing
     */
    public function getSarGenerations(Request $request)
    {
        $query = SarGeneration::with('testPasser')
            ->orderBy('sent_at', 'desc');

        // Filter by school year if provided
        if ($request->has('school_year') && $request->school_year) {
            $query->whereHas('testPasser', function ($q) use ($request) {
                $q->where('school_year', $request->school_year);
            });
        }

        // Filter by batch if provided
        if ($request->has('batch_number') && $request->batch_number) {
            $query->whereHas('testPasser', function ($q) use ($request) {
                $q->where('batch_number', $request->batch_number);
            });
        }

        // Search by name or reference number
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('testPasser', function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('surname', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $limit = $request->input('limit', 20);
        $sarGenerations = $query->paginate($limit);

        return response()->json($sarGenerations);
    }

    /**
     * Admin download SAR PDF
     */
    public function adminDownloadSar($id)
    {
        $sarGeneration = SarGeneration::findOrFail($id);

        // Use sar_tmp disk for consistent file access
        $disk = Storage::disk('sar_tmp');

        // Check if file exists - if not, try to regenerate it
        if (!$disk->exists($sarGeneration->filename)) {
            \Log::warning('SAR file not found for admin download, attempting to regenerate', [
                'id' => $id,
                'filename' => $sarGeneration->filename,
            ]);

            // Try to regenerate the SAR file
            try {
                $passer = $sarGeneration->testPasser;

                if (!$passer) {
                    \Log::error('Test passer not found for SAR generation', ['id' => $id]);
                    abort(404, 'Test passer record not found');
                }

                // Regenerate the SAR file
                $sarService = app(\App\Services\SarFormService::class);

                $fullName = trim("{$passer->surname}, {$passer->first_name} " . ($passer->middle_name ?? ''));

                $rowData = [
                    'reference_number' => $passer->reference_number,
                    'full_name' => $fullName,
                    'graduation_year' => $passer->graduation_year,
                    'school_attended' => $passer->shs_school ?? 'N/A',
                    'shs_strand' => $passer->strand ?? 'N/A',
                    'enrollment_date' => $sarGeneration->enrollment_date ?
                        \Carbon\Carbon::parse($sarGeneration->enrollment_date)->format('F d, Y') :
                        date('F d, Y'),
                    'enrollment_time' => $sarGeneration->enrollment_time ?? date('h:i A'),
                    'student_number' => $passer->student_number ?? '',
                    'admission_status' => 'Admitted',
                ];

                $result = $sarService->generateSarPdf($rowData);

                if (!$result['success']) {
                    \Log::error('SAR regeneration failed for admin', [
                        'id' => $id,
                        'error' => $result['error'] ?? 'Unknown error',
                    ]);
                    abort(500, 'Unable to regenerate file. Please try again or contact support.');
                }

                // Update the filename in the database if it changed
                if ($result['filename'] !== $sarGeneration->filename) {
                    $sarGeneration->filename = $result['filename'];
                    $sarGeneration->save();
                }

                \Log::info('SAR file regenerated successfully for admin', [
                    'id' => $id,
                    'filename' => $result['filename'],
                ]);
            } catch (\Exception $e) {
                \Log::error('SAR regeneration exception for admin', [
                    'id' => $id,
                    'error' => $e->getMessage(),
                ]);
                abort(500, 'Unable to regenerate file. Please try again or contact support.');
            }
        }

        // Return file download response
        $disk = Storage::disk('sar_tmp');

        // Check if using S3 by checking if path() method throws exception
        try {
            $localPath = $disk->path($sarGeneration->filename);
            // Local storage - use regular download
            return response()->download($localPath, $sarGeneration->filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\RuntimeException $e) {
            // S3 storage - stream the file
            return response()->stream(function () use ($disk, $sarGeneration) {
                $stream = $disk->readStream($sarGeneration->filename);
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $sarGeneration->filename . '"',
            ]);
        }
    }

    /**
     * Admin preview SAR PDF (inline view)
     */
    public function adminPreviewSar($id)
    {
        $sarGeneration = SarGeneration::findOrFail($id);

        // Use sar_tmp disk for consistent file access
        $disk = Storage::disk('sar_tmp');

        // Check if file exists
        if (!$disk->exists($sarGeneration->filename)) {
            \Log::error('SAR file not found for preview', ['id' => $id]);
            abort(404, 'File not found or expired');
        }

        // Check if using S3 by checking if path() method throws exception
        try {
            $localPath = $disk->path($sarGeneration->filename);
            // Local storage - return PDF for inline preview
            return response()->file($localPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $sarGeneration->filename . '"',
            ]);
        } catch (\RuntimeException $e) {
            // S3 storage - stream the file for inline preview
            return response()->stream(function () use ($disk, $sarGeneration) {
                $stream = $disk->readStream($sarGeneration->filename);
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $sarGeneration->filename . '"',
            ]);
        }
    }

    /**
     * Preview SAR email template
     */
    public function previewSarEmailTemplate(Request $request)
    {
        $request->validate([
            'passer_id' => 'required|exists:test_passers,test_passer_id',
        ]);

        $passer = TestPasser::findOrFail($request->passer_id);

        // Generate a sample download URL (won't actually work, just for preview)
        $downloadUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'sar.passer-download',
            now()->addMinutes(30),
            [
                'reference' => $passer->reference_number,
                'filename' => 'PREVIEW_SAR_' . $passer->reference_number . '_SAMPLE.pdf'
            ]
        );

        // Return the email view directly
        return view('emails.sar-form', [
            'passerName' => trim($passer->surname . ', ' . $passer->first_name . ' ' . ($passer->middle_name ?? '')),
            'referenceNumber' => $passer->reference_number,
            'downloadUrl' => $downloadUrl,
        ]);
    }

    /**
     * Preview SAR PDF Form with sample data
     */
    public function previewSarPdfTemplate(Request $request)
    {
        $request->validate([
            'passer_id' => 'required|exists:test_passers,test_passer_id',
            'enrollment_date' => 'required|string',
            'enrollment_time' => 'required|string',
        ]);

        $passer = TestPasser::findOrFail($request->passer_id);
        $enrollmentDate = $request->enrollment_date;
        $enrollmentTime = $request->enrollment_time;

        try {
            // Prepare SAR data from test passer
            $sarData = $this->prepareSarDataFromPasser($passer, $enrollmentDate, $enrollmentTime);

            // Generate SAR PDF
            $sarService = app(SarFormService::class);
            $result = $sarService->generateSarPdf($sarData);

            if ($result['success']) {
                // Use sar_tmp disk for consistent file access
                $disk = Storage::disk('sar_tmp');

                if ($disk->exists($result['filename'])) {
                    // Read PDF content before deletion
                    $pdfContent = $disk->get($result['filename']);

                    // Delete temporary preview file
                    $disk->delete($result['filename']);

                    // Return PDF for inline preview
                    return response($pdfContent, 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="PREVIEW_' . $result['filename'] . '"',
                    ]);
                }
            }

            return response()->json(['error' => 'Failed to generate preview'], 500);
        } catch (\Exception $e) {
            // Log detailed error for debugging
            \Log::error('SAR PDF preview failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'passer_id' => $passer->test_passer_id
            ]);

            // Return sanitized error message to user
            return response()->json(['error' => 'Failed to generate preview'], 500);
        }
    }

    /**
     * Preview Waitlisted email template
     */
    public function previewWaitlistedEmailTemplate(Request $request)
    {
        $request->validate([
            'passer_id' => 'required|exists:test_passers,test_passer_id',
            'message_template' => 'nullable|string',
        ]);

        $passer = TestPasser::findOrFail($request->passer_id);
        $messageTemplate = $request->message_template ?? '';

        // Replace placeholders in template for preview
        $searchTags = [
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
            $passer->first_name,
            $passer->first_name,
            $passer->surname,
            $passer->surname,
            $passer->surname,
            $passer->reference_number,
            $passer->reference_number,
            $passer->reference_number
        ];
        $personalizedMessage = str_ireplace($searchTags, $replaceValues, $messageTemplate);

        // Return the email view directly
        return view('emails.waitlisted', [
            'passerName' => trim($passer->first_name . ' ' . $passer->surname),
            'firstName' => $passer->first_name,
            'surname' => $passer->surname,
            'referenceNumber' => $passer->reference_number,
            'customMessage' => $personalizedMessage,
        ]);
    }

    /**
     * Bulk-enroll selected TestPassers as officially_enrolled accounts.
     *
     * For each passer:
     *   1. Creates (or updates) a User record keyed by email.
     *   2. Creates (or updates) an ApplicantProfile with a unique student number.
     *   3. Creates a Grade record with passing grades so all grade checks pass.
     *   4. Creates (or updates) an Application set to accepted + officially_enrolled.
     *   5. Inserts completed ApplicationProcess records for every pipeline stage.
     *
     * The operation is fully idempotent — safe to call multiple times.
     *
     * POST /test-passers/bulk-enroll
     * Body: { passer_ids: [1, 2, 3, ...] }
     */
    public function bulkEnroll(
        \Illuminate\Http\Request $request
    ): \Illuminate\Http\JsonResponse {
        $request->validate([
            'passer_ids'   => 'required|array|min:1',
            'passer_ids.*' => 'integer',
        ]);

        $passers = TestPasser::whereIn('test_passer_id', $request->passer_ids)->get();

        if ($passers->isEmpty()) {
            return response()->json(['error' => 'No passers found for the given IDs.'], 422);
        }

        // Pick the first available program — mock accounts just need a valid program_id
        $program = \App\Models\Program::orderBy('id')->first();
        if (! $program) {
            return response()->json(['error' => 'No programs found in the database. Please seed programs first.'], 422);
        }

        $stages  = ['evaluator', 'interviewer', 'medical', 'records'];
        $results = ['enrolled' => [], 'skipped' => [], 'errors' => []];

        foreach ($passers as $passer) {
            try {
                \Illuminate\Support\Facades\DB::transaction(function () use (
                    $passer,
                    $program,
                    $stages,
                    &$results
                ) {
                    // ── 1. User ───────────────────────────────────────────
                    $user = \App\Models\User::updateOrCreate(
                        ['email' => $passer->email],
                        [
                            'firstname'          => $passer->first_name,
                            'middlename'         => $passer->middle_name ?? null,
                            'lastname'           => $passer->surname,
                            'salutation'         => 'Mr.',
                            'sex'                => 'Male',
                            'role_id'            => 1,
                            'password'           => \Illuminate\Support\Facades\Hash::make('Password123'),
                            'privacy_consent'    => true,
                            'privacy_consent_at' => now(),
                        ]
                    );

                    // ── 2. ApplicantProfile ───────────────────────────────
                    // Use the passer's reference_number as the student number.
                    $studentNumber = $passer->reference_number;

                    \App\Models\ApplicantProfile::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'student_number'       => $studentNumber,
                            'email'                => $passer->email,
                            'firstname'            => $passer->first_name,
                            'middlename'           => $passer->middle_name ?? null,
                            'lastname'             => $passer->surname,
                            'salutation'           => 'Mr.',
                            'sex'                  => 'Male',
                            'privacy_consent'      => true,
                            'privacy_consent_at'   => now(),
                            'strand'               => $passer->strand ?? 'STEM',
                            'track'                => 'Academic',
                            'date_graduated'       => $passer->year_graduated
                                ? ($passer->year_graduated . '-04-01')
                                : '2024-04-01',
                            'first_choice_program' => $program->id,
                        ]
                    );

                    // ── 3. Grades ─────────────────────────────────────────
                    \App\Models\Grade::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'english'        => 90.00,
                            'mathematics'    => 90.00,
                            'science'        => 90.00,
                            'g12_first_sem'  => 90.00,
                            'g12_second_sem' => 90.00,
                        ]
                    );

                    // ── 4. Application ────────────────────────────────────
                    $application = \App\Models\Application::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'program_id'        => $program->id,
                            'status'            => 'accepted',
                            'enrollment_status' => 'officially_enrolled',
                            'submitted_at'      => now(),
                        ]
                    );

                    // ── 5. ApplicationProcesses (all stages completed) ────
                    foreach ($stages as $stage) {
                        \App\Models\ApplicationProcess::updateOrCreate(
                            ['application_id' => $application->id, 'stage' => $stage],
                            [
                                'status'         => 'completed',
                                'action'         => 'passed',
                                'reviewer_notes' => '[GUIDANCE-BULK-ENROLL] Auto-enrolled from TestPasser record #' . $passer->test_passer_id,
                                'performed_by'   => null,
                                'ip_address'     => request()->ip(),
                            ]
                        );
                    }

                    $results['enrolled'][] = [
                        'passer_id'      => $passer->test_passer_id,
                        'name'           => $passer->first_name . ' ' . $passer->surname,
                        'email'          => $passer->email,
                        'student_number' => $studentNumber,
                    ];
                });
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('[BulkEnroll] Failed for passer #' . $passer->test_passer_id, [
                    'email' => $passer->email,
                    'error' => $e->getMessage(),
                ]);

                $results['errors'][] = [
                    'passer_id' => $passer->test_passer_id,
                    'name'      => $passer->first_name . ' ' . $passer->surname,
                    'email'     => $passer->email,
                    'reason'    => $e->getMessage(),
                ];
            }
        }

        $this->auditLogService->logActivity(
            'CREATE',
            'Bulk Enrollment',
            sprintf(
                'Bulk-enrolled %d passer(s) as officially_enrolled. %d error(s).',
                count($results['enrolled']),
                count($results['errors'])
            ),
            null,
            'USER_MANAGEMENT'
        );

        return response()->json([
            'message'  => count($results['enrolled']) . ' passer(s) successfully enrolled.',
            'enrolled' => $results['enrolled'],
            'errors'   => $results['errors'],
        ]);
    }

    /**
     * Delete a single test passer.
     *
     * DELETE /test-passers/{test_passer}
     */
    public function destroy(TestPasser $test_passer): \Illuminate\Http\JsonResponse
    {
        $name = "{$test_passer->first_name} {$test_passer->surname}";
        $id   = $test_passer->test_passer_id;

        $test_passer->delete();

        $this->auditLogService->logActivity(
            'DELETE',
            'Test Passers',
            "Deleted passer: {$name} (ID: {$id}).",
            null,
            'ADMISSION_DATA'
        );

        return response()->json(['message' => "Passer \"{$name}\" deleted successfully."]);
    }

    /**
     * Bulk-delete multiple test passers.
     *
     * POST /test-passers/bulk-destroy
     * Body: { passer_ids: [1, 2, 3] }
     */
    public function bulkDestroy(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'passer_ids'   => 'required|array|min:1',
            'passer_ids.*' => 'integer',
        ]);

        $count = TestPasser::whereIn('test_passer_id', $request->passer_ids)->delete();

        $this->auditLogService->logActivity(
            'DELETE',
            'Test Passers',
            "Bulk-deleted {$count} passer(s).",
            null,
            'ADMISSION_DATA'
        );

        return response()->json(['message' => "{$count} passer(s) deleted successfully."]);
    }

    /**
     * Resolve default filter values when not provided in the request.
     *
     * When no school_year is provided, defaults to the maximum (most recent) school_year in the database.
     * When no batch_number is provided, defaults to the first available batch_number for the resolved school_year.
     * Returns null values gracefully when the database is empty.
     *
     * @param Request $request
     * @return array{school_year: string|null, batch_number: string|null}
     */
    private function getDefaultFilters(Request $request): array
    {
        // Resolve school_year: use request value if provided, otherwise query the most recent
        $schoolYear = $request->filled('school_year')
            ? $request->input('school_year')
            : TestPasser::max('school_year');

        // Resolve batch_number: use request value if provided, otherwise query the first available for the school_year
        $batchNumber = $request->filled('batch_number')
            ? $request->input('batch_number')
            : null;

        if ($batchNumber === null && $schoolYear !== null) {
            $batchNumber = TestPasser::where('school_year', $schoolYear)
                ->whereNotNull('batch_number')
                ->orderBy('batch_number')
                ->value('batch_number');
        }

        return [
            'school_year' => $schoolYear,
            'batch_number' => $batchNumber,
        ];
    }

    /**
     * Get available filter options for the test passers listing.
     *
     * Queries distinct values using indexed columns for efficient DISTINCT queries.
     *
     * @param string|null $schoolYear The selected school year to scope batch numbers
     * @return array{schoolYears: array, batchNumbers: array, strands: array, statuses: \Illuminate\Database\Eloquent\Collection}
     */
    private function getFilterOptions(?string $schoolYear): array
    {
        // Distinct school_year values sorted descending (uses idx_test_passers_school_year_batch index)
        $schoolYears = TestPasser::select('school_year')
            ->whereNotNull('school_year')
            ->distinct()
            ->orderBy('school_year', 'desc')
            ->pluck('school_year')
            ->all();

        // Distinct batch_number values for the given school_year (uses idx_test_passers_school_year_batch composite index)
        $batchNumbers = [];
        if ($schoolYear) {
            $batchNumbers = TestPasser::select('batch_number')
                ->where('school_year', $schoolYear)
                ->whereNotNull('batch_number')
                ->distinct()
                ->orderBy('batch_number')
                ->pluck('batch_number')
                ->all();
        }

        // Distinct strand values (uses idx_test_passers_strand index)
        $strands = TestPasser::select('strand')
            ->whereNotNull('strand')
            ->distinct()
            ->orderBy('strand')
            ->pluck('strand')
            ->all();

        // All passer statuses (uses passer_statuses table primary key)
        $statuses = PasserStatus::all();

        return [
            'schoolYears' => $schoolYears,
            'batchNumbers' => $batchNumbers,
            'strands' => $strands,
            'statuses' => $statuses,
        ];
    }
}
