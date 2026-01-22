<?php

namespace App\Http\Controllers\Admin\Notify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Mail\CongratulationsMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Inertia\Inertia;

class Notify extends Controller
{

    public function showUploadForm()
{
    // $admin = Auth::user();

    // if (!$admin) {
    //     return redirect()->route('login')->with('error', 'Your session has expired. Please log in again.');
    // }

    // if (is_null($admin->role_id) || $admin->role_id != 2) {
    //     return redirect()->back()->with('error', 'Unauthorized access.');
    // }

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

            foreach ($emails as $email) {
                Mail::to($email)->send(new CongratulationsMail());
                Log::info("Email sent to: $email");
            }

            return response()->json(['message' => 'Emails sent successfully.'], 200);
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
