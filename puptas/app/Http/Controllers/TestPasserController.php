<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\TestPassersImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\TestPasser;
use Inertia\Inertia;
use Illuminate\Support\Facades\Mail;
use App\Mail\TestPasserEmail;
use App\Mail\SarFormEmail;
use App\Services\SarFormService;
use Symfony\Component\Mime\Part\TextPart;
use Illuminate\Validation\Rule;
use App\Rules\ValidationRules;

class TestPasserController extends Controller
{
    public function index()
    {
        $passers = TestPasser::all();

        // Group passers by school_year then batch_number to match frontend expectations
        $groupedPassers = $passers->groupBy([
            function ($passer) {
                return $passer->school_year ?? 'Unknown';
            },
            function ($passer) {
                return $passer->batch_number ?? 'Unbatched';
            },
        ]);

        return Inertia::render('TestPassers/Email', [
            'groupedPassers' => $groupedPassers,
            'registrationUrl' => url('/register'),
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
            try {
                // Prepare SAR data from test passer
                $sarData = $this->prepareSarDataFromPasser($passer, $enrollmentDate, $enrollmentTime);

                // Generate SAR PDF
                $result = $sarService->generateSarPdf($sarData);

                if ($result['success']) {
                    // Generate download URL (valid for 7 days)
                    $downloadUrl = route('sar.passer-download', [
                        'filename' => $result['filename'],
                        'reference' => $passer->reference_number
                    ]);

                    // Send email with download link
                    Mail::to($passer->email)
                        ->send(new SarFormEmail($passer, $downloadUrl));

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
                $errors[] = [
                    'passer' => $passer->first_name . ' ' . $passer->surname,
                    'email' => $passer->email,
                    'error' => $e->getMessage()
                ];
                \Log::error('SAR email failed for passer: ' . $passer->test_passer_id, [
                    'error' => $e->getMessage()
                ]);
            }
        }

        $message = "SAR emails sent: {$successCount} successful, {$failedCount} failed";

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

        return response()->json(['message' => 'Excel file uploaded and data imported successfully']);
    }

    public function update(Request $request, $id)
    {
        // Find the passer or fail
        $passer = TestPasser::findOrFail($id);

        // Validate input
        $validatedData = $request->validate(ValidationRules::testPasserUpdate($id));

        // Update passer with validated data
        $passer->update($validatedData);

        return response()->json([
            'message' => 'Passer updated successfully',
            'passer' => $passer,
        ]);
    }

    public function store(Request $request)
    {
        // Validate input
        $validated = $request->validate(ValidationRules::testPasserStore());

        // Set default status if not provided
        $validated['status'] = $validated['status'] ?? 'pending';

        // Create new passer record
        $passer = TestPasser::create($validated);

        // Return the new passer data (adjust as needed)
        return response()->json($passer, 201);
    }

    /**
     * Download SAR PDF for a specific test passer
     * Public route with reference number verification
     */
    public function downloadSar($filename, $reference)
    {
        // Validate reference number exists
        $passer = TestPasser::where('reference_number', $reference)->first();

        if (!$passer) {
            abort(404, 'Invalid reference number');
        }

        // Construct the expected filename pattern
        $expectedFilenamePattern = 'SAR_' . $reference . '_';

        // Verify filename matches the reference number
        if (strpos($filename, $expectedFilenamePattern) !== 0) {
            abort(403, 'Unauthorized access');
        }

        // Check if file exists
        $filePath = 'tmp/' . $filename;

        if (!\Storage::exists($filePath)) {
            abort(404, 'File not found');
        }

        // Return file download response
        return \Storage::download($filePath, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
