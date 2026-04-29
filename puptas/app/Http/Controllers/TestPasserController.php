<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\TestPassersImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\TestPasser;
use App\Models\SarGeneration;
use Inertia\Inertia;
use Illuminate\Support\Facades\Mail;
use App\Mail\TestPasserEmail;
use App\Mail\SarFormEmail;
use App\Services\SarFormService;
use App\Services\AuditLogService;
use Symfony\Component\Mime\Part\TextPart;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class TestPasserController extends Controller
{
    public function __construct(private AuditLogService $auditLogService) {}
    // Get grouped passers by school year and batch number


    public function index()
    {
        $passers = TestPasser::all()
            ->groupBy(['school_year', 'batch_number'])
            ->map(function ($batches) {
                return $batches->map(function ($passers) {
                    return $passers->values(); // reset keys, convert collection to array-like
                });
            });

        return Inertia::render('TestPassers/Email', [
            'groupedPassers' => $passers,
            'registrationUrl' => 'https://identity-provider.isaxbsit2027.com/register?client_id=037f48dd-245b-450b-9e7a-3348b65b9dad',
        ]);
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

        $passers = TestPasser::whereIn('test_passer_id', $passerIds)->get();

        // Handle SAR Template - Generate PDFs and send with download links
        if ($templateType === 'sar') {
            if (!$enrollmentDate || !$enrollmentTime) {
                return response()->json(['error' => 'Enrollment date and time are required for SAR template'], 422);
            }
            return $this->sendSarEmails($passers, $enrollmentDate, $enrollmentTime);
        }

        // Handle Default and Custom templates
        if (!$messageTemplate) {
            return response()->json(['error' => 'Message template is required'], 422);
        }

        foreach ($passers as $passer) {
            // Replace placeholders in template for personalization
            $personalizedMessage = str_replace(
                ['{{firstname}}', '{{surname}}'],
                [$passer->first_name, $passer->surname],
                $messageTemplate
            );

            Mail::to($passer->email)
                ->send(new TestPasserEmail($passer, $personalizedMessage));
        }

        $this->auditLogService->logActivity('CREATE', 'Test Passers', "Sent emails to " . count($passerIds) . " passer(s) using {$templateType} template.", null, 'ADMISSION_DATA');

        return response()->json(['message' => 'Emails sent successfully!']);
    }

    /**
     * Send SAR form emails with PDF download links
     */
    private function sendSarEmails($passers, $enrollmentDate, $enrollmentTime)
    {
        $sarService = app(SarFormService::class);
        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($passers as $passer) {
            $sarGeneration = null;
            $emailSuccess = false;

            try {
                // Prepare SAR data from test passer
                $sarData = $this->prepareSarDataFromPasser($passer, $enrollmentDate, $enrollmentTime);

                // Generate SAR PDF
                $result = $sarService->generateSarPdf($sarData);

                if ($result['success']) {
                    // Generate download URL (valid for 7 days)
                    $downloadUrl = route('sar.passer-download', [
                        'reference' => $passer->reference_number,
                        'filename' => $result['filename']
                    ]);

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

                    // Send email with download link
                    Mail::to($passer->email)
                        ->send(new SarFormEmail($passer, $downloadUrl));

                    // Mark as sent successfully
                    $sarGeneration->update([
                        'sent_at' => now(),
                        'email_sent_successfully' => true,
                    ]);

                    $emailSuccess = true;
                    $successCount++;
                } else {
                    $failedCount++;
                    $errors[] = [
                        'passer' => $passer->first_name . ' ' . $passer->surname,
                        'email' => $passer->email,
                        'error' => $result['error']
                    ];
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

                // Log detailed error for debugging
                \Log::error('SAR email failed for passer: ' . $passer->test_passer_id, [
                    'email' => $passer->email,
                    'error' => $errorMessage,
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $message = "SAR emails sent: {$successCount} successful, {$failedCount} failed";

        $this->auditLogService->logActivity('CREATE', 'Test Passers', "Sent SAR form emails: {$successCount} successful, {$failedCount} failed.", null, 'ADMISSION_DATA');

        return response()->json([
            'message' => $message,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'errors' => $errors
        ], $failedCount > 0 ? 207 : 200); // 207 Multi-Status if some failed
    }

    /**
     * Prepare SAR form data from TestPasser model
     */
    private function prepareSarDataFromPasser($passer, $enrollmentDate, $enrollmentTime)
    {
        // Build full name in SAR format: "Surname, Firstname Middlename"
        $fullName = trim($passer->surname . ', ' . $passer->first_name . ' ' . ($passer->middle_name ?? ''));

        return [
            'id' => 'tp_' . $passer->test_passer_id,
            'reference_number' => $passer->reference_number ?? 'N/A',
            'full_name' => $fullName,
            'surname' => $passer->surname,
            'firstname_middle' => trim($passer->first_name . ' ' . ($passer->middle_name ?? '')),
            'shs_strand' => $passer->strand ?? 'N/A',
            'graduation_year' => $passer->year_graduated ?? date('Y'),
            'school_attended' => $passer->shs_school ?? 'N/A',
            'enrollment_date' => $enrollmentDate,
            'enrollment_time' => $enrollmentTime,
        ];
    }



    // Helper function to replace placeholders
    private function replacePlaceholders($template, $passer)
    {
        return str_replace(
            ['{{firstname}}', '{{surname}}'],
            [$passer->first_name, $passer->surname],
            $template
        );
    }

    public function upload(Request $request)
    {
        $request->validate([
            'batch_number' => 'required|string',
            'school_year' => 'required|string',
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $batch = $request->input('batch_number');
        $schoolYear = $request->input('school_year');

        Excel::import(new TestPassersImport($batch, $schoolYear), $request->file('file'));

        $this->auditLogService->logActivity('CREATE', 'Test Passers', "Uploaded passers file for batch {$batch}, school year {$schoolYear}.", null, 'ADMISSION_DATA');

        return response()->json(['message' => 'Excel file uploaded and data imported successfully']);
    }

    public function update(Request $request, $id)
    {
        // Find the passer or fail
        $passer = TestPasser::findOrFail($id);

        // Validate input
        $validatedData = $request->validate([
            'surname' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'school_address' => 'nullable|string|max:255',
            'shs_school' => 'nullable|string|max:255',
            'strand' => 'nullable|string|max:255',
            'year_graduated' => 'nullable|digits:4|integer|min:1900|max:' . date('Y'),
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('test_passers')->ignore($passer->test_passer_id, 'test_passer_id'),
            ],
            'reference_number' => 'nullable|string|max:255',
            'batch_number' => 'nullable|string|max:255',
            'school_year' => 'nullable|string|max:255',
        ]);

        // Update passer with validated data
        $passer->update($validatedData);

        $this->auditLogService->logActivity('UPDATE', 'Test Passers', "Updated passer: {$passer->first_name} {$passer->surname} (ID: {$passer->test_passer_id}).", null, 'ADMISSION_DATA');

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
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'school_address' => 'nullable|string|max:255',
            'shs_school' => 'nullable|string|max:255',
            'strand' => 'nullable|string|max:255',
            'year_graduated' => 'nullable|digits:4|integer|min:1900|max:' . (date('Y') + 1),
            'email' => 'required|email|unique:test_passers,email',
            'reference_number' => 'nullable|string|max:255',
            'batch_number' => 'nullable|string|max:255',
            'school_year' => 'nullable|string|max:255',
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
                
                $rowData = [
                    'reference_number' => $passer->reference_number,
                    'full_name' => $passer->full_name,
                    'graduation_year' => $passer->graduation_year ?? date('Y'),
                    'school_attended' => $passer->school_attended ?? 'N/A',
                    'shs_strand' => $passer->shs_strand ?? 'N/A',
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
        return response()->download($disk->path($filename), $filename, [
            'Content-Type' => 'application/pdf',
        ]);
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

        $sarGenerations = $query->paginate(20);

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
                
                $rowData = [
                    'reference_number' => $passer->reference_number,
                    'full_name' => $passer->full_name,
                    'graduation_year' => $passer->graduation_year ?? date('Y'),
                    'school_attended' => $passer->school_attended ?? 'N/A',
                    'shs_strand' => $passer->shs_strand ?? 'N/A',
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
        return response()->download($disk->path($sarGeneration->filename), $sarGeneration->filename, [
            'Content-Type' => 'application/pdf',
        ]);
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

        // Return PDF for inline preview
        return response()->file($disk->path($sarGeneration->filename), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $sarGeneration->filename . '"',
        ]);
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
        $downloadUrl = route('sar.passer-download', [
            'reference' => $passer->reference_number,
            'filename' => 'PREVIEW_SAR_' . $passer->reference_number . '_SAMPLE.pdf'
        ]);

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
}
