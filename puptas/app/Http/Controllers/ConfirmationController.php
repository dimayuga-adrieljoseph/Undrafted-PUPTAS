<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use App\Models\UserFile;
use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\Program;
use App\Models\Grade;
use App\Helpers\FileMapper;

class ConfirmationController extends Controller
{
    public function show()
    {

        $user = Auth::user();

        $files = $user->files()->get()->keyBy('type');
        $application = $user->application;
        $profile = $user->applicantProfile;

        // Debug: Uncomment to see what's fetched
        // dd($files);
        $status = $application?->status ?? null;
        //dd($application);

        return response()->json([
            'firstname' => $user->firstname,
            'middlename' => $user->middlename,
            'lastname' => $user->lastname,
            'birthday' => $user->birthday,
            'sex' => $user->sex,
            'contactnumber' => $user->contactnumber,
            'address' => $user->address,
            'email' => $user->email,
            // Include school data from applicant profile
            'school' => $profile->school ?? null,
            'schoolAdd' => $profile->school_address ?? null,
            'schoolyear' => $profile->school_year ?? null,
            'dateGrad' => $profile->date_graduated ? $profile->date_graduated->format('Y-m-d') : null,
            'strand' => $profile->strand ?? null,
            'track' => $profile->track ?? null,

            'status' => $status,

            'uploadedFiles' => FileMapper::formatFiles($files),

            // inside your show() response()->json([...])
            'processes' => $application
                ? $application
                ->processes()
                ->with('performedBy:id,firstname,lastname')   // ← eager-load user
                ->orderBy('created_at')
                ->get(['stage', 'status', 'action', 'decision_reason', 'reviewer_notes', 'performed_by', 'created_at'])
                : [],
            'enrollment_status' => $application?->enrollment_status ?? null,
            // FIX: Return program choices from applications table, fallback to applicant_profiles
            'program_id' => $application?->program_id ?? $profile?->first_choice_program,
            'second_choice_id' => $application?->second_choice_id ?? $profile?->second_choice_program,



        ]);
    }

    public function submit(Request $request)
    {
        $user = Auth::user();

        \Log::info('📥 Incoming submit data', $request->all());

        // FIX: Auto-populate from applicant_profile if not provided
        $profile = $user->applicantProfile;
        $defaultProgramId = $profile?->first_choice_program;
        $defaultSecondChoice = $profile?->second_choice_program;

        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'second_choice_id' => [
                'nullable',
                'exists:programs,id',
                // Allow null or must be different from program_id
                function ($attribute, $value, $fail) use ($request) {
                    if ($value && $value == $request->program_id) {
                        $fail('Second choice must be different from the first choice.');
                    }
                },
            ],
        ]);

        $application = Application::firstOrCreate(
            ['user_id' => $user->id],
            [
                'status' => 'draft',
                // Auto-populate with saved program choices from applicant_profiles
                'program_id' => $defaultProgramId,
                'second_choice_id' => $defaultSecondChoice,
            ],

        );

        // Block if there are still rejected files
        $rejected = $application->files()->where('status', 'rejected')->exists();
        if ($rejected) {
            return response()->json(['message' => 'Fix rejected files first'], 422);
        }

        // Block re-submissions
        if ($application->status === 'submitted') {
            return response()->json(['message' => 'Already submitted'], 409);
        }

        \DB::transaction(function () use ($application, $user, $validated) {
            // 1) Update application details
            $application->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'program_id' => $validated['program_id'],
                'second_choice_id' => $validated['second_choice_id'] ?? null,
            ]);

            // Create the next in-flight process (evaluator)
            $application->processes()->create([
                'stage' => 'evaluator',
                'status' => 'in_progress',
                'performed_by' => null,
            ]);
        });

        return response()->json([
            'message' => 'Application submitted.',
            'status' => $application->status,
            'submitted_at' => $application->submitted_at,
        ]);
    }



    public function reupload(Request $request)
    {
        $request->validate([
            'field' => 'required|string|in:' . FileMapper::getValidFileFields(),
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $user = auth()->user(); // Assumes the user is logged in

        $inputName = $request->input('field');
        $type = FileMapper::MAPPING[$inputName] ?? null;

        if (!$type) {
            return response()->json(['message' => 'Invalid field name'], 400);
        }

        $uploadedFile = $request->file('file');
        $path = $uploadedFile->store('uploads/files', 'public');

        // Optional: delete old file
        $existingFile = UserFile::where('user_id', $user->id)->where('type', $type)->first();
        if ($existingFile && Storage::disk('public')->exists($existingFile->file_path)) {
            Storage::disk('public')->delete($existingFile->file_path);
        }

        // Save new file
        UserFile::updateOrCreate(
            [
                'user_id' => $user->id,
                'type' => $type,
            ],
            [
                'file_path' => $path,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'status'        => 'pending',
            ]
        );

        return response()->json([
            'message' => 'File reuploaded successfully',
            'url' => Storage::url($path),
            'status' => 'pending',
        ]);
    }

    public function getEligiblePrograms(Request $request)
    {
        $user = Auth::user();
        $grades = $user->grades; // related grade record

        if (
            !$grades ||
            is_null($grades->english) ||
            is_null($grades->mathematics) ||
            is_null($grades->science)
        ) {
            return response()->json([
                'programs' => [],
                'message' => 'Applicant grades are incomplete.'
            ]);
        }

        $english = $grades->english;
        $math = $grades->mathematics;
        $science = $grades->science;

        $programs = Program::where(function ($query) use ($english, $math, $science) {
            $query->where(function ($q) use ($english) {
                $q->whereNull('english')->orWhereRaw('? >= english', [$english]);
            })
                ->where(function ($q) use ($math) {
                    $q->whereNull('math')->orWhereRaw('? >= math', [$math]);
                })
                ->where(function ($q) use ($science) {
                    $q->whereNull('science')->orWhereRaw('? >= science', [$science]);
                });
        })->get();

        return response()->json([
            'programs' => $programs
        ]);
    }
}
