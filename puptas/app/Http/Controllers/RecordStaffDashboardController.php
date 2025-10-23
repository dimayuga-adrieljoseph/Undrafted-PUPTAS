<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\UserFile;
use Illuminate\Support\Facades\Storage;
use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\Program;
use Illuminate\Support\Facades\DB;



class RecordStaffDashboardController extends Controller
{
    public function index()
{
    $user = Auth::user();

    if ($user->role_id !== 6) {
        return redirect()->back()->with('error', 'Unauthorized access.');
    }

    $summary = [
        'total' => Application::count(),
        'accepted' => Application::where('status', 'accepted')->count(),
        'pending' => Application::where('status', 'submitted')->count(),
        'returned' => Application::where('status', 'returned')->count(),
    ];

   $programs = Program::withCount('applications')->get();

    return Inertia::render('Dashboards/RecordStaffDashboard', [
        'user' => $user,
        'allUsers' => User::all(),
        'programs' => $programs,
         'summary' => $summary,
    ]);
    
}



public function getUserFiles($id)
{
    $user = User::with(['application.processes', 'files'])->findOrFail($id);

    if (!$user) {
        return response()->json(['message' => 'User not found'], 404);
    }

    $files = $user->files->keyBy('type');

    $uploadedFiles = [
        'file10Front'   => isset($files['file10_front']) ? Storage::url($files['file10_front']->file_path) : null,
        'file11'        => isset($files['file10_front']) ? Storage::url($files['file10_front']->file_path) : null,
        'file12'        => isset($files['file10_front']) ? Storage::url($files['file10_front']->file_path) : null,
        'schoolId'      => isset($files['school_id']) ? Storage::url($files['school_id']->file_path) : null,
        'nonEnrollCert' => isset($files['non_enroll_cert']) ? Storage::url($files['non_enroll_cert']->file_path) : null,
        'psa'           => isset($files['psa']) ? Storage::url($files['psa']->file_path) : null,
        'goodMoral'     => isset($files['good_moral']) ? Storage::url($files['good_moral']->file_path) : null,
        'underOath'     => isset($files['under_oath']) ? Storage::url($files['under_oath']->file_path) : null,
        'photo2x2'      => isset($files['photo_2x2']) ? Storage::url($files['photo_2x2']->file_path) : null,
    ];

    return response()->json([
        'user' => $user,
        'uploadedFiles' => $uploadedFiles,
    ]);
}


public function returnFiles(Request $request, $userId)
    {
        $validated = $request->validate([
            'files' => 'required|array',
            'files.*' => 'string', // file type keys like 'file11'
            'note' => 'required|string|max:1000',
        ]);

        $fileTypes = $validated['files'];
        $note = $validated['note'];

        // Update the file records
        $updated = UserFile::where('user_id', $userId)
            ->whereIn('type', $fileTypes)
            ->update([
                'status' => 'returned',
                'comment' => $note,
            ]);

        // update application status
        $application = Application::where('user_id', $userId)->first();
        if ($application) {
            $application->update([
                'status' => 'returned',
            ]);

            // Log the action in application_processes
            ApplicationProcess::create([
                'application_id' => $application->id,
                'stage' => 'evaluator',
                'status' => 'returned',
                'notes' => $note,
            ]);
        }

        return response()->json([
            'message' => 'Files returned successfully.',
            'updated' => $updated,
        ]);
    }

   public function returnApplication(Request $request, $userId)
{
    $request->validate([
        'files' => 'required|array',
        'files.*' => 'string',
        'note' => 'required|string|min:3',
    ]);

    $files = $request->input('files'); // Correctly get the files array from JSON payload

    \Log::info('Files array received:', ['files' => $files]);

    // Map frontend keys (camelCase) to DB keys (snake_case)
    $keyMap = [
        'file10Front'   => 'file10_front',
        'file11'        => 'file11',
        'file12'        => 'file12',
        'schoolId'      => 'school_id',
        'nonEnrollCert' => 'non_enroll_cert',
        'psa'           => 'psa',
        'goodMoral'     => 'good_moral',
        'underOath'     => 'under_oath',
        'photo2x2'      => 'photo_2x2',
    ];

    $application = Application::where('user_id', $userId)->firstOrFail();

    // Update overall application status
    $application->status = 'returned';
    $application->save();

    // Log new process for evaluator stage
    ApplicationProcess::create([
        'application_id' => $application->id,
        'stage' => 'evaluator',
        'status' => 'returned',
        'notes' => $request->note,
        'performed_by' => auth()->id(),
    ]);

    $updatedFiles = [];
    $notFoundFiles = [];

    foreach ($files as $fileKey) {
        $dbKey = $keyMap[$fileKey] ?? $fileKey;

        \Log::info("Processing file key: {$fileKey} mapped to DB key: {$dbKey}");

        $file = \App\Models\UserFile::where('user_id', $userId)
            ->where('type', $dbKey)
            ->first();

        if (!$file) {
            \Log::warning("UserFile not found for user_id={$userId}, type={$dbKey}");
            $notFoundFiles[] = $dbKey;
            continue;
        }

        $file->status = 'returned';
        $file->comment = $request->note;

        $saved = $file->save();

        \Log::info("Saved UserFile ID {$file->id} with status 'returned' and comment. Save success: " . ($saved ? 'true' : 'false'));

        $updatedFiles[] = $dbKey;
    }

    return response()->json([
        'message' => 'Application returned and tracked.',
        'updated_files' => $updatedFiles,
        'not_found_files' => $notFoundFiles,
    ]);
}

public function tag($userId)
{
    $application = Application::where('user_id', $userId)->firstOrFail();

    try {
        DB::transaction(function () use ($application, $userId) {

            $application->status = 'officially_enrolled';
            $application->save();


            ApplicationProcess::create([
                'application_id' => $application->id,
                'stage' => 'record',
                'status' => 'Enrolled',
                'notes' => 'tagged by record staff',
                'performed_by' => auth()->id(),
            ]);
        });

        return response()->json(['message' => 'Tagged as enrolled.']);
    } catch (\Throwable $e) {
        \Log::error("❌ Accept failed: " . $e->getMessage());
        return response()->json(['message' => $e->getMessage()], 400);
    }
}

public function untag($userId)
{
    $application = Application::where('user_id', $userId)->firstOrFail();

    try {
        DB::transaction(function () use ($application, $userId) {

            $application->status = 'temporary enrolled';
            $application->save();


            ApplicationProcess::create([
                'application_id' => $application->id,
                'stage' => 'record',
                'status' => 'Temporary Enrolled',
                'notes' => 'Reverted to temporary enrolled',
                'performed_by' => auth()->id(),
            ]);
        });

        return response()->json(['message' => 'Reverted to Temporary Enrolled.']);
    } catch (\Throwable $e) {
        \Log::error("❌ Accept failed: " . $e->getMessage());
        return response()->json(['message' => $e->getMessage()], 400);
    }
}




}
