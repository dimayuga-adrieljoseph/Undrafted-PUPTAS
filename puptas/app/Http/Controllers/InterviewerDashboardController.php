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
use App\Models\Grade;
use Illuminate\Support\Facades\DB;

class InterviewerDashboardController extends Controller
{
     public function index()
{
    $user = Auth::user();

    if ($user->role_id !== 4) {
        return redirect()->back()->with('error', 'Unauthorized access.');
    }

    $summary = [
        'total' => Application::count(),
        'accepted' => Application::where('status', 'accepted')->count(),
        'pending' => Application::where('status', 'submitted')->count(),
        'returned' => Application::where('status', 'returned')->count(),
    ];


    return Inertia::render('Dashboards/InterviewerDashboard', [
        'user' => $user,
        'allUsers' => User::all(),
        'summary' => $summary,
    ]);
    
}

public function getUserFiles($id)
{
    $user = User::with(['application.program', 'application.processes', 'files', 'grades'])->findOrFail($id);

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

public function getUsers()
{
    return response()->json(
        User::with('application.program')
            ->where('role_id', 1)
            ->whereHas('application')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'course' => $user->course,
                    'status' => $user->application->status ?? null,
                    'email' => $user->email,
                    'username' => $user->username,
                    'phone' => $user->phone,
                    'company' => $user->company,
                    'program' => $user->application->program ?? null,
                ];
            })
    );
}

public function accept($userId)
{
    $application = Application::where('user_id', $userId)->firstOrFail();
    $grades = Grade::where('user_id', $userId)->first();

    if (!$grades) {
        return response()->json(['message' => 'User has no grades recorded.'], 400);
    }

    try {
        DB::transaction(function () use ($application, $grades, $userId) {
            $program = Program::lockForUpdate()->findOrFail($application->program_id);

            if ($program->slots <= 0) {
                \Log::warning("❌ No slots left in program {$program->id}");
                // ✅ Instead of throwing, use Laravel-style abort or exception:
                abort(400, 'No available slots in the selected program.');
            }

            if (
                $grades->mathematics < $program->math ||
                $grades->science < $program->science ||
                $grades->english < $program->english
            ) {
                \Log::warning("📉 User {$userId} does not meet grade requirements for program {$program->id}");
                abort(400, 'User does not meet the grade requirements for this program.');
            }

            $application->status = 'accepted';
            $application->save();

            $program->slots -= 1;
            $program->save();

            ApplicationProcess::create([
                'application_id' => $application->id,
                'stage' => 'interview',
                'status' => 'accepted',
                'notes' => 'Accepted by interviewer',
                'performed_by' => auth()->id(),
            ]);
        });

        return response()->json(['message' => 'Application accepted.']);
    } catch (\Throwable $e) {
        \Log::error("❌ Accept failed: " . $e->getMessage());
        return response()->json(['message' => $e->getMessage()], 400);
    }
}


public function getPrograms()
{
    $programs = Program::where('slots', '>', 0)->get();

    return response()->json([
        'programs' => $programs
    ]);
}


public function transferToProgram(Request $request, $userId)
{
    $validated = $request->validate([
        'program_id' => 'required|exists:programs,id',
    ]);

    \Log::info("🚀 Transfer requested for user {$userId} to program {$validated['program_id']}");

    $application = Application::where('user_id', $userId)->firstOrFail();
    $grades = Grade::where('user_id', $userId)->first();

    if (!$grades) {
        \Log::warning("⚠️ No grades found for user {$userId}");
        return response()->json(['message' => 'User has no grades recorded.'], 400);
    }

    DB::transaction(function () use ($application, $validated, $grades, $userId) {
        $program = Program::lockForUpdate()->findOrFail($validated['program_id']);

        \Log::info("📦 Fetched program: {$program->id}, current slots: {$program->slots}");

        if ($program->slots <= 0) {
            \Log::warning("❌ No slots left in program {$program->id}");
            throw new \Exception("No available slots in the selected program.");
        }

        // Check if applicant's grades meet the requirements
        if (
            $grades->mathematics < $program->math ||
            $grades->science < $program->science ||
            $grades->english < $program->english
        ) {
            \Log::warning("📉 User {$userId} does not meet grade requirements for program {$program->id}");
            throw new \Exception("User does not meet the grade requirements for this program.");
        }

        // Update application
        $application->program_id = $program->id;
        $application->status = 'transferred';
        $application->save();
        \Log::info("✅ Application updated with program_id {$program->id}");

        // Decrease slot
        $program->slots -= 1;
        $program->save();
        \Log::info("📉 Program slots updated. New slots: {$program->slots}");

        // Log process
        ApplicationProcess::create([
            'application_id' => $application->id,
            'stage' => 'interviewer',
            'status' => 'transferred',
            'notes' => 'Transferred to program ID ' . $program->id,
            'performed_by' => auth()->id(),
        ]);

        \Log::info("📝 ApplicationProcess logged for application {$application->id}");
    });

    \Log::info("🎉 Transfer completed for user {$userId}");

    return response()->json(['message' => 'Transferred successfully.']);
}



}
