<?php

namespace App\Http\Controllers\Admin\Notify;

use App\Http\Controllers\Controller;
use App\Jobs\SendCongratulationsEmail;
use App\Services\AuditLogService;
use App\Services\EmailTrackingService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Inertia\Inertia;

class Notify extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService,
        private EmailTrackingService $emailTrackingService,
    ) {}

    public function showUploadForm()
    {
        return Inertia::render('Uploads/Form'); // Vue component name
    }

    public function handleUpload(Request $request)
    {
        // Validation for Vue FormData
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
            'batch_number' => 'required|string',
            'school_year' => 'required|string',
        ]);

        // Optional: Auth check
        $admin = Auth::user();
        if (!$admin || $admin->role_id !== 2) {
            return response()->json(['error' => 'Unauthorized access.'], 403);
        }

        $file = $request->file('file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('uploads', $fileName, 'public');

        $path = $file->getRealPath();

        Log::info('File uploaded: ' . $fileName);
        Log::info('Batch: ' . $request->batch_number);
        Log::info('School Year: ' . $request->school_year);

        try {
            $spreadsheet = IOFactory::load($path);
            $data = $spreadsheet->getActiveSheet()->toArray();

            $emails = $this->findEmailColumn($data);
            Log::info('Extracted Emails: ', $emails);

            // Validate: reject if 0 valid emails
            if (count($emails) === 0) {
                return response()->json(['error' => 'No valid email addresses found in the uploaded file.'], 422);
            }

            // Validate: reject if more than 2000 valid emails
            $maxRecipients = config('email-tracking.max_recipients_per_operation', 2000);
            if (count($emails) > $maxRecipients) {
                return response()->json(['error' => "The uploaded file contains more than {$maxRecipients} valid email addresses. Please reduce the number of recipients."], 422);
            }

            // Create BulkEmailOperation record
            $operation = $this->emailTrackingService->createBulkOperation(
                'congratulations',
                count($emails),
                $admin->id,
                [
                    'batch_number' => $request->batch_number,
                    'school_year' => $request->school_year,
                ]
            );

            // If creation failed, return error without dispatching jobs
            if (!$operation->exists) {
                return response()->json(['error' => 'Failed to create bulk email operation. Please try again.'], 500);
            }

            // Dispatch queued jobs per recipient with staggered delay
            $delaySeconds = config('email-tracking.delay_between_emails_seconds', 30);
            $jobIndex = 0;

            foreach ($emails as $email) {
                // Create EmailLog record for each recipient
                $emailLog = $this->emailTrackingService->createEmailLog(
                    $operation->id,
                    $email,
                    null, // no recipient name from spreadsheet
                    null, // no recipient_id from spreadsheet
                    'congratulations'
                );

                // Dispatch the queued job with staggered delay
                if ($emailLog->exists) {
                    SendCongratulationsEmail::dispatch($email, $emailLog->id, $operation->id)
                        ->delay(now()->addSeconds($jobIndex * $delaySeconds));
                }

                $jobIndex++;
            }

            $this->auditLogService->logActivity('CREATE', 'Test Passers', "Uploaded passers file \"{$fileName}\" (batch: {$request->batch_number}, SY: {$request->school_year}) and queued " . count($emails) . " congratulations email(s).", null, 'ADMISSION_DATA');

            return response()->json([
                'message' => 'Emails queued successfully.',
                'bulk_operation_id' => $operation->id,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Upload or email error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to process file.'], 500);
        }
    }

    private function findEmailColumn($data)
    {
        $emailColumnIndex = null;
        $emails = [];

        foreach ($data[0] as $index => $heading) {
            if (stripos($heading, 'email') !== false) {
                $emailColumnIndex = $index;
                break;
            }
        }

        if ($emailColumnIndex !== null) {
            foreach ($data as $row) {
                if (isset($row[$emailColumnIndex]) && filter_var($row[$emailColumnIndex], FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $row[$emailColumnIndex];
                }
            }
        }

        return $emails;
    }
}
