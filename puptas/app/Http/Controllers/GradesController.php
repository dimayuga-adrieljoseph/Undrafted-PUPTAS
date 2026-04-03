<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Grade;
use App\Models\User;
use App\Models\ApplicantProfile;
use App\Models\Program;
use App\Rules\ValidationRules;
use Illuminate\Support\Facades\Auth;

class GradesController extends Controller
{
    public function showAbmGradeForm()
    {
        $user = Auth::user();
        $grade = Grade::where('user_id', $user->id)->first() ?? new Grade();
        $programs = Program::with('strands')->get();
        $profile = ApplicantProfile::where('user_id', $user->id)->first();

        return inertia('Grades/ABMGradeInput', [
            'grade' => $grade,
            'user' => $user,
            'programs' => $programs,
            'strand' => $profile?->strand,
        ]);
    }

    public function showIctGradeForm()
    {
        $user = Auth::user();
        $grade = Grade::where('user_id', $user->id)->first() ?? new Grade();
        $programs = Program::with('strands')->get();
        $profile = ApplicantProfile::where('user_id', $user->id)->first();

        return inertia('Grades/ICTGradeInput', [
            'grade' => $grade,
            'user' => $user,
            'programs' => $programs,
            'strand' => $profile?->strand,
        ]);
    }

    public function showHumssGradeForm()
    {
        $user = Auth::user();
        $grade = Grade::where('user_id', $user->id)->first() ?? new Grade();
        $programs = Program::with('strands')->get();
        $profile = ApplicantProfile::where('user_id', $user->id)->first();

        return inertia('Grades/HUMSSGradeInput', [
            'grade' => $grade,
            'user' => $user,
            'programs' => $programs,
            'strand' => $profile?->strand,
        ]);
    }

    public function showGasGradeForm()
    {
        $user = Auth::user();
        $grade = Grade::where('user_id', $user->id)->first() ?? new Grade();
        $programs = Program::with('strands')->get();
        $profile = ApplicantProfile::where('user_id', $user->id)->first();

        return inertia('Grades/GASGradeInput', [
            'grade' => $grade,
            'user' => $user,
            'programs' => $programs,
            'strand' => $profile?->strand,
        ]);
    }

    public function showStemGradeForm()
    {
        $user = Auth::user();
        $grade = Grade::where('user_id', $user->id)->first() ?? new Grade();
        $programs = Program::with('strands')->get();
        $profile = ApplicantProfile::where('user_id', $user->id)->first();

        return inertia('Grades/STEMGradeInput', [
            'grade' => $grade,
            'user' => $user,
            'programs' => $programs,
            'strand' => $profile?->strand,
        ]);
    }

    public function showTvlGradeForm()
    {
        $user = Auth::user();
        $grade = Grade::where('user_id', $user->id)->first() ?? new Grade();
        $programs = Program::with('strands')->get();
        $profile = ApplicantProfile::where('user_id', $user->id)->first();

        return inertia('Grades/TVLGradeInput', [
            'grade' => $grade,
            'user' => $user,
            'programs' => $programs,
            'strand' => $profile?->strand,
        ]);
    }

    public function storeAbmGrades(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            // Computed averages from frontend
            'mathematics' => 'required|numeric|min:0|max:100',
            'english' => 'required|numeric|min:0|max:100',
            'science' => 'required|numeric|min:0|max:100',
            'g12_first_sem' => 'required|numeric|min:0|max:100',
            'g12_second_sem' => 'required|numeric|min:0|max:100',
            // Program choices
            'first_choice_program' => 'required|exists:programs,id',
            'second_choice_program' => 'required|exists:programs,id|different:first_choice_program',
        ]);

        // Calculate GWA from semester grades
        $g12_gwa = ($validated['g12_first_sem'] + $validated['g12_second_sem']) / 2;

        // Perform Qualification Validation
        $profile = ApplicantProfile::where('user_id', $user->id)->first();
        $userStrand = strtoupper($profile?->strand ?? 'ABM');

        $firstProgram = Program::with('strands')->find($validated['first_choice_program']);
        $secondProgram = Program::with('strands')->find($validated['second_choice_program']);

        $errors = [];
        if (!$this->isUserQualified($firstProgram, $userStrand, $validated['mathematics'], $validated['english'], $validated['science'], $g12_gwa)) {
            $errors['first_choice_program'] = "You are not qualified for your first choice program ({$firstProgram->code}) based on the submitted grades.";
        }

        if (!$this->isUserQualified($secondProgram, $userStrand, $validated['mathematics'], $validated['english'], $validated['science'], $g12_gwa)) {
            $errors['second_choice_program'] = "You are not qualified for your second choice program ({$secondProgram->code}) based on the submitted grades.";
        }

        if (!empty($errors)) {
            return back()->withErrors($errors)->withInput();
        }

        $grade = Grade::updateOrCreate(
            ['user_id' => $user->id],
            [
                'mathematics' => $validated['mathematics'],
                'english' => $validated['english'],
                'science' => $validated['science'],
                'g12_first_sem' => $validated['g12_first_sem'],
                'g12_second_sem' => $validated['g12_second_sem'],
            ]
        );

        // Save program choices to applicant profile
        ApplicantProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_choice_program' => $validated['first_choice_program'],
                'second_choice_program' => $validated['second_choice_program'],
            ]
        );

        app(\App\Services\AuditLogService::class)->logActivity('CREATE', 'Applications', "Applicant {$user->firstname} {$user->lastname} submitted grades and program choices.", $user, 'ADMISSION_DATA');

        return redirect()->route('applicant.dashboard')->with('success', 'Grades and program choices saved successfully');
    }

    private function isUserQualified($program, $userStrand, $math, $english, $science, $gwa)
    {
        // Check Strand Eligibility
        $allowedStrandCodes = $program->strands->pluck('code')->map(fn($c) => strtoupper($c))->toArray();
        $strandAllowed = empty($allowedStrandCodes) || 
                         in_array('OPEN TO ALL', $allowedStrandCodes) || 
                         in_array('OTHER WITH BRIDGING', $allowedStrandCodes) || 
                         in_array($userStrand, $allowedStrandCodes);

        if (!$strandAllowed) {
            return false;
        }

        // Check Grade Requirements
        if ($program->math && $math < $program->math) return false;
        if ($program->english && $english < $program->english) return false;
        if ($program->science && $science < $program->science) return false;
        if ($program->gwa && $gwa < $program->gwa) return false;

        return true;
    }

    public function storeTvlGrades(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            // Computed averages from frontend
            'mathematics' => 'required|numeric|min:0|max:100',
            'science' => 'required|numeric|min:0|max:100',
            'english' => 'required|numeric|min:0|max:100',
            'g12_first_sem' => 'required|numeric|min:0|max:100',
            'g12_second_sem' => 'required|numeric|min:0|max:100',
            // Program choices
            'first_choice_program' => 'required|exists:programs,id',
            'second_choice_program' => 'required|exists:programs,id|different:first_choice_program',
        ]);

        // Calculate GWA from semester grades
        $g12_gwa = ($validated['g12_first_sem'] + $validated['g12_second_sem']) / 2;

        $grade = Grade::updateOrCreate(
            ['user_id' => $user->id],
            [
                'mathematics' => $validated['mathematics'],
                'science' => $validated['science'],
                'english' => $validated['english'],
                'g12_first_sem' => $validated['g12_first_sem'],
                'g12_second_sem' => $validated['g12_second_sem'],
            ]
        );

        // Save program choices to applicant profile
        ApplicantProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_choice_program' => $validated['first_choice_program'],
                'second_choice_program' => $validated['second_choice_program'],
            ]
        );

        app(\App\Services\AuditLogService::class)->logActivity('CREATE', 'Applications', "Applicant {$user->firstname} {$user->lastname} submitted grades and program choices.", $user, 'ADMISSION_DATA');

        return redirect()->route('applicant.dashboard')->with('success', 'Grades and program choices saved successfully');
    }

    public function storeHumssGrades(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            // Computed averages from frontend
            'mathematics' => 'required|numeric|min:0|max:100',
            'english' => 'required|numeric|min:0|max:100',
            'science' => 'required|numeric|min:0|max:100',
            'g12_first_sem' => 'required|numeric|min:0|max:100',
            'g12_second_sem' => 'required|numeric|min:0|max:100',
            // Program choices
            'first_choice_program' => 'required|exists:programs,id',
            'second_choice_program' => 'required|exists:programs,id|different:first_choice_program',
        ]);

        // Calculate GWA from semester grades
        $g12_gwa = ($validated['g12_first_sem'] + $validated['g12_second_sem']) / 2;

        $grade = Grade::updateOrCreate(
            ['user_id' => $user->id],
            [
                'mathematics' => $validated['mathematics'],
                'english' => $validated['english'],
                'science' => $validated['science'],
                'g12_first_sem' => $validated['g12_first_sem'],
                'g12_second_sem' => $validated['g12_second_sem'],
            ]
        );

        // Save program choices to applicant profile
        ApplicantProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_choice_program' => $validated['first_choice_program'],
                'second_choice_program' => $validated['second_choice_program'],
            ]
        );

        app(\App\Services\AuditLogService::class)->logActivity('CREATE', 'Applications', "Applicant {$user->firstname} {$user->lastname} submitted grades and program choices.", $user, 'ADMISSION_DATA');

        return redirect()->route('applicant.dashboard')->with('success', 'Grades and program choices saved successfully');
    }

    public function storeGasGrades(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            // Computed averages from frontend
            'mathematics' => 'required|numeric|min:0|max:100',
            'english' => 'required|numeric|min:0|max:100',
            'science' => 'required|numeric|min:0|max:100',
            'g12_first_sem' => 'required|numeric|min:0|max:100',
            'g12_second_sem' => 'required|numeric|min:0|max:100',
            // Program choices
            'first_choice_program' => 'required|exists:programs,id',
            'second_choice_program' => 'required|exists:programs,id|different:first_choice_program',
        ]);

        // Calculate GWA from semester grades
        $g12_gwa = ($validated['g12_first_sem'] + $validated['g12_second_sem']) / 2;

        $grade = Grade::updateOrCreate(
            ['user_id' => $user->id],
            [
                'mathematics' => $validated['mathematics'],
                'english' => $validated['english'],
                'science' => $validated['science'],
                'g12_first_sem' => $validated['g12_first_sem'],
                'g12_second_sem' => $validated['g12_second_sem'],
            ]
        );

        // Save program choices to applicant profile
        ApplicantProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_choice_program' => $validated['first_choice_program'],
                'second_choice_program' => $validated['second_choice_program'],
            ]
        );

        app(\App\Services\AuditLogService::class)->logActivity('CREATE', 'Applications', "Applicant {$user->firstname} {$user->lastname} submitted grades and program choices.", $user, 'ADMISSION_DATA');

        return redirect()->route('applicant.dashboard')->with('success', 'Grades and program choices saved successfully');
    }

    public function storeStemGrades(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            // Computed averages from frontend
            'mathematics' => 'required|numeric|min:0|max:100',
            'science' => 'required|numeric|min:0|max:100',
            'english' => 'required|numeric|min:0|max:100',
            'g12_first_sem' => 'required|numeric|min:0|max:100',
            'g12_second_sem' => 'required|numeric|min:0|max:100',
            // Program choices
            'first_choice_program' => 'required|exists:programs,id',
            'second_choice_program' => 'required|exists:programs,id|different:first_choice_program',
        ]);

        // Calculate GWA from semester grades
        $g12_gwa = ($validated['g12_first_sem'] + $validated['g12_second_sem']) / 2;

        $grade = Grade::updateOrCreate(
            ['user_id' => $user->id],
            [
                'mathematics' => $validated['mathematics'],
                'science' => $validated['science'],
                'english' => $validated['english'],
                'g12_first_sem' => $validated['g12_first_sem'],
                'g12_second_sem' => $validated['g12_second_sem'],
            ]
        );

        // Save program choices to applicant profile
        ApplicantProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_choice_program' => $validated['first_choice_program'],
                'second_choice_program' => $validated['second_choice_program'],
            ]
        );

        app(\App\Services\AuditLogService::class)->logActivity('CREATE', 'Applications', "Applicant {$user->firstname} {$user->lastname} submitted grades and program choices.", $user, 'ADMISSION_DATA');

        return redirect()->route('applicant.dashboard')->with('success', 'Grades and program choices saved successfully');
    }

    public function store(Request $request)
    {
        $request->validate(ValidationRules::gradeImport());
        $user = ApplicantProfile::where('email', $request->email)->first();

        if (!$user) {
            sleep(2); // Wait 1 second and try again
            $user = ApplicantProfile::where('email', $request->email)->first();

            if (!$user) {
                return response()->json(['message' => 'User not found. Please try again.'], 404);
            }
        }

        Grade::updateOrCreate(
            ['user_id' => $user->id],
            [
                'english' => $request->english,
                'mathematics' => $request->mathematics,
                'science' => $request->science,
                'g12_first_sem' => $request->g12_first_sem,
                'g12_second_sem' => $request->g12_second_sem,
            ]
        );

        return response()->json(['message' => 'Grades saved successfully']);
    }
}
