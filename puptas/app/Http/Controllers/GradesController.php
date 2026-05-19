<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Grade;
use App\Models\User;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\Program;
use App\Rules\ValidationRules;
use Illuminate\Support\Facades\Auth;

class GradesController extends Controller
{
    public function showAbmGradeForm()
    {
        $user = Auth::user();
        $profile = ApplicantProfile::where('user_id', $user->id)->first();
        
        // Validate that user's strand matches the route
        if (!$profile || strtoupper($profile->strand) !== 'ABM') {
            abort(403, 'You are not authorized to access this grade input form.');
        }
        
        $grade = Grade::where('user_id', $user->id)->first() ?? new Grade();
        $programs = Program::with('strands')->get()->each(function ($program) {
            $program->append('strand_names');
        });

        // Only pass extraction result if grades haven't been saved yet
        // This prevents overwriting user's manual edits with extraction data
        $extractionResult = $grade->exists ? null : session()->get('extraction_result');

        return inertia('Grades/ABMGradeInput', [
            'grade' => $grade,
            'user' => $user,
            'programs' => $programs,
            'strand' => $profile?->strand,
            'profile' => $profile, // Pass full profile for program choices
            'extractionResult' => $extractionResult,
            'isLocked' => $this->isEvaluatorLocked($user),
        ]);
    }

    public function showIctGradeForm()
    {
        $user = Auth::user();
        $profile = ApplicantProfile::where('user_id', $user->id)->first();
        
        // Validate that user's strand matches the route
        if (!$profile || strtoupper($profile->strand) !== 'ICT') {
            abort(403, 'You are not authorized to access this grade input form.');
        }
        
        $grade = Grade::where('user_id', $user->id)->first() ?? new Grade();
        $programs = Program::with('strands')->get()->each(function ($program) {
            $program->append('strand_names');
        });

        // Only pass extraction result if grades haven't been saved yet
        // This prevents overwriting user's manual edits with extraction data
        $extractionResult = $grade->exists ? null : session()->get('extraction_result');

        return inertia('Grades/ICTGradeInput', [
            'grade' => $grade,
            'user' => $user,
            'programs' => $programs,
            'strand' => $profile?->strand,
            'profile' => $profile,
            'extractionResult' => $extractionResult,
            'isLocked' => $this->isEvaluatorLocked($user),
        ]);
    }

    public function storeIctGrades(Request $request)
    {
        $user = Auth::user();

        if ($this->isEvaluatorLocked($user)) {
            return back()->withErrors(['locked' => 'Grade submission is no longer allowed. Your application has been submitted.']);
        }

        $validated = $request->validate([
            // Computed averages from frontend
            'mathematics' => 'required|numeric|min:0|max:100',
            'english' => 'required|numeric|min:0|max:100',
            'science' => 'required|numeric|min:0|max:100',
            'g12_first_sem' => 'required|numeric|min:0|max:100',
            'g12_second_sem' => 'required|numeric|min:0|max:100',
            // Individual G11 Math grades
            'g11_general_mathematics' => 'required|numeric|min:0|max:100',
            'g11_statistics_probability' => 'required|numeric|min:0|max:100',
            // Individual G11 English grades
            'g11_oral_communication' => 'required|numeric|min:0|max:100',
            'g11_21st_century_lit' => 'required|numeric|min:0|max:100',
            'g11_academic_professional' => 'required|numeric|min:0|max:100',
            'g11_reading_writing' => 'required|numeric|min:0|max:100',
            // Individual G11 Science grades
            'g11_earth_life_science' => 'required|numeric|min:0|max:100',
            'g11_physical_science' => 'required|numeric|min:0|max:100',
            // Program choices
            'first_choice_program' => 'required|exists:programs,id',
            'second_choice_program' => 'nullable|exists:programs,id|different:first_choice_program',
            'third_choice_program' => 'nullable|exists:programs,id|different:first_choice_program,second_choice_program',
            'qualified_programs_count' => 'required|integer|min:0',
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
                // Individual G11 Math grades
                'g11_general_mathematics' => $validated['g11_general_mathematics'],
                'g11_statistics_probability' => $validated['g11_statistics_probability'],
                // Individual G11 English grades
                'g11_oral_communication' => $validated['g11_oral_communication'],
                'g11_21st_century_lit' => $validated['g11_21st_century_lit'],
                'g11_academic_professional' => $validated['g11_academic_professional'],
                'g11_reading_writing' => $validated['g11_reading_writing'],
                // Individual G11 Science grades
                'g11_earth_life_science' => $validated['g11_earth_life_science'],
                'g11_physical_science' => $validated['g11_physical_science'],
            ]
        );

        // Save program choices to applicant profile
        ApplicantProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_choice_program' => $validated['first_choice_program'],
                'second_choice_program' => $validated['second_choice_program'],
                'third_choice_program' => $validated['third_choice_program'],
            ]
        );

        app(\App\Services\AuditLogService::class)->logActivity('CREATE', 'Applications', "Applicant {$user->firstname} {$user->lastname} submitted grades and program choices.", $user, 'ADMISSION_DATA');

        return redirect()->route('applicant.dashboard')->with('success', 'Grades and program choices saved successfully');
    }

    public function showHumssGradeForm()
    {
        $user = Auth::user();
        $profile = ApplicantProfile::where('user_id', $user->id)->first();
        
        // Validate that user's strand matches the route
        if (!$profile || strtoupper($profile->strand) !== 'HUMSS') {
            abort(403, 'You are not authorized to access this grade input form.');
        }
        
        $grade = Grade::where('user_id', $user->id)->first() ?? new Grade();
        $programs = Program::with('strands')->get()->each(function ($program) {
            $program->append('strand_names');
        });

        // Only pass extraction result if grades haven't been saved yet
        // This prevents overwriting user's manual edits with extraction data
        $extractionResult = $grade->exists ? null : session()->get('extraction_result');

        return inertia('Grades/HUMSSGradeInput', [
            'grade' => $grade,
            'user' => $user,
            'programs' => $programs,
            'strand' => $profile?->strand,
            'profile' => $profile,
            'extractionResult' => $extractionResult,
            'isLocked' => $this->isEvaluatorLocked($user),
        ]);
    }

    public function showGasGradeForm()
    {
        $user = Auth::user();
        $profile = ApplicantProfile::where('user_id', $user->id)->first();
        
        // Validate that user's strand matches the route
        if (!$profile || strtoupper($profile->strand) !== 'GAS') {
            abort(403, 'You are not authorized to access this grade input form.');
        }
        
        $grade = Grade::where('user_id', $user->id)->first() ?? new Grade();
        $programs = Program::with('strands')->get()->each(function ($program) {
            $program->append('strand_names');
        });

        // Only pass extraction result if grades haven't been saved yet
        // This prevents overwriting user's manual edits with extraction data
        $extractionResult = $grade->exists ? null : session()->get('extraction_result');

        return inertia('Grades/GASGradeInput', [
            'grade' => $grade,
            'user' => $user,
            'programs' => $programs,
            'strand' => $profile?->strand,
            'profile' => $profile,
            'extractionResult' => $extractionResult,
            'isLocked' => $this->isEvaluatorLocked($user),
        ]);
    }

    public function showStemGradeForm()
    {
        $user = Auth::user();
        $profile = ApplicantProfile::where('user_id', $user->id)->first();
        
        // Validate that user's strand matches the route
        if (!$profile || strtoupper($profile->strand) !== 'STEM') {
            abort(403, 'You are not authorized to access this grade input form.');
        }
        
        $grade = Grade::where('user_id', $user->id)->first() ?? new Grade();
        $programs = Program::with('strands')->get()->each(function ($program) {
            $program->append('strand_names');
        });

        // Only pass extraction result if grades haven't been saved yet
        // This prevents overwriting user's manual edits with extraction data
        $extractionResult = $grade->exists ? null : session()->get('extraction_result');

        return inertia('Grades/STEMGradeInput', [
            'grade' => $grade,
            'user' => $user,
            'programs' => $programs,
            'strand' => $profile?->strand,
            'profile' => $profile,
            'extractionResult' => $extractionResult,
            'isLocked' => $this->isEvaluatorLocked($user),
        ]);
    }

    public function showTvlGradeForm()
    {
        $user = Auth::user();
        $profile = ApplicantProfile::where('user_id', $user->id)->first();
        
        // Validate that user's strand matches the route
        if (!$profile || strtoupper($profile->strand) !== 'TVL') {
            abort(403, 'You are not authorized to access this grade input form.');
        }
        
        $grade = Grade::where('user_id', $user->id)->first() ?? new Grade();
        $programs = Program::with('strands')->get()->each(function ($program) {
            $program->append('strand_names');
        });

        // Only pass extraction result if grades haven't been saved yet
        // This prevents overwriting user's manual edits with extraction data
        $extractionResult = $grade->exists ? null : session()->get('extraction_result');

        return inertia('Grades/TVLGradeInput', [
            'grade' => $grade,
            'user' => $user,
            'programs' => $programs,
            'strand' => $profile?->strand,
            'profile' => $profile,
            'extractionResult' => $extractionResult,
            'isLocked' => $this->isEvaluatorLocked($user),
        ]);
    }

    public function storeAbmGrades(Request $request)
    {
        $user = Auth::user();
        
        if ($this->isEvaluatorLocked($user)) {
            return back()->withErrors(['locked' => 'Grade submission is no longer allowed. Your application has been submitted.']);
        }

        $validated = $request->validate([
            // Computed averages from frontend
            'mathematics' => 'required|numeric|min:0|max:100',
            'english' => 'required|numeric|min:0|max:100',
            'science' => 'required|numeric|min:0|max:100',
            'g12_first_sem' => 'required|numeric|min:0|max:100',
            'g12_second_sem' => 'required|numeric|min:0|max:100',
            // Individual G11 Math grades
            'g11_general_mathematics' => 'required|numeric|min:0|max:100',
            'g11_business_mathematics' => 'required|numeric|min:0|max:100',
            'g11_statistics_probability' => 'required|numeric|min:0|max:100',
            // Individual G11 English grades
            'g11_oral_communication' => 'required|numeric|min:0|max:100',
            'g11_academic_professional' => 'required|numeric|min:0|max:100',
            'g11_reading_writing' => 'required|numeric|min:0|max:100',
            // Individual G12 English grades
            'g12_21st_century_lit' => 'required|numeric|min:0|max:100',
            // Individual G11 Science grades
            'g11_earth_life_science' => 'required|numeric|min:0|max:100',
            'g11_physical_science' => 'required|numeric|min:0|max:100',
            // Program choices - dynamic based on qualified programs count
            'first_choice_program' => 'required|exists:programs,id',
            'second_choice_program' => 'nullable|exists:programs,id|different:first_choice_program',
            'third_choice_program' => 'nullable|exists:programs,id|different:first_choice_program,second_choice_program',
            'qualified_programs_count' => 'required|integer|min:0',
        ]);

        // Calculate GWA from semester grades
        $g12_gwa = ($validated['g12_first_sem'] + $validated['g12_second_sem']) / 2;

        // Perform Qualification Validation
        $profile = ApplicantProfile::where('user_id', $user->id)->first();
        $userStrand = strtoupper($profile?->strand ?? 'ABM');

        $firstProgram = Program::with('strands')->find($validated['first_choice_program']);
        $secondProgram = $validated['second_choice_program'] ? Program::with('strands')->find($validated['second_choice_program']) : null;
        $thirdProgram = $validated['third_choice_program'] ? Program::with('strands')->find($validated['third_choice_program']) : null;

        $errors = [];
        if (!$this->isUserQualified($firstProgram, $userStrand, $validated['mathematics'], $validated['english'], $validated['science'], $g12_gwa)) {
            $errors['first_choice_program'] = "You are not qualified for your first choice program ({$firstProgram->code}) based on the submitted grades.";
        }

        if ($secondProgram && !$this->isUserQualified($secondProgram, $userStrand, $validated['mathematics'], $validated['english'], $validated['science'], $g12_gwa)) {
            $errors['second_choice_program'] = "You are not qualified for your second choice program ({$secondProgram->code}) based on the submitted grades.";
        }

        if ($thirdProgram && !$this->isUserQualified($thirdProgram, $userStrand, $validated['mathematics'], $validated['english'], $validated['science'], $g12_gwa)) {
            $errors['third_choice_program'] = "You are not qualified for your third choice program ({$thirdProgram->code}) based on the submitted grades.";
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
                // Individual G11 Math grades
                'g11_general_mathematics' => $validated['g11_general_mathematics'],
                'g11_business_mathematics' => $validated['g11_business_mathematics'],
                'g11_statistics_probability' => $validated['g11_statistics_probability'],
                // Individual G11 English grades
                'g11_oral_communication' => $validated['g11_oral_communication'],
                'g11_academic_professional' => $validated['g11_academic_professional'],
                'g11_reading_writing' => $validated['g11_reading_writing'],
                // Individual G12 English grades
                'g12_21st_century_lit' => $validated['g12_21st_century_lit'],
                // Individual G11 Science grades
                'g11_earth_life_science' => $validated['g11_earth_life_science'],
                'g11_physical_science' => $validated['g11_physical_science'],
            ]
        );

        // Save program choices to applicant profile
        ApplicantProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_choice_program' => $validated['first_choice_program'],
                'second_choice_program' => $validated['second_choice_program'],
                'third_choice_program' => $validated['third_choice_program'],
            ]
        );

        app(\App\Services\AuditLogService::class)->logActivity('CREATE', 'Applications', "Applicant {$user->firstname} {$user->lastname} submitted grades and program choices.", $user, 'ADMISSION_DATA');

        return redirect()->route('applicant.dashboard')->with('success', 'Grades and program choices saved successfully');
    }

    private function isEvaluatorLocked(User $user): bool
    {
        $application = Application::where('user_id', $user->id)->first();

        if (!$application) {
            return false;
        }

        // Lock grades once application is submitted (status is not 'draft')
        return $application->status !== 'draft';
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

        if ($this->isEvaluatorLocked($user)) {
            return back()->withErrors(['locked' => 'Grade submission is no longer allowed. Your application has been submitted.']);
        }

        $validated = $request->validate([
            // Computed averages from frontend
            'mathematics' => 'required|numeric|min:0|max:100',
            'science' => 'required|numeric|min:0|max:100',
            'english' => 'required|numeric|min:0|max:100',
            'g12_first_sem' => 'required|numeric|min:0|max:100',
            'g12_second_sem' => 'required|numeric|min:0|max:100',
            // Individual G11 Math grades
            'g11_general_mathematics' => 'required|numeric|min:0|max:100',
            'g11_statistics_probability' => 'required|numeric|min:0|max:100',
            // Individual G11 English grades
            'g11_oral_communication' => 'required|numeric|min:0|max:100',
            'g11_21st_century_lit' => 'required|numeric|min:0|max:100',
            'g11_reading_writing' => 'required|numeric|min:0|max:100',
            // Individual G12 Science grades
            'g12_earth_life_science' => 'required|numeric|min:0|max:100',
            'g12_physical_science' => 'required|numeric|min:0|max:100',
            // Program choices
            'first_choice_program' => 'required|exists:programs,id',
            'second_choice_program' => 'nullable|exists:programs,id|different:first_choice_program',
            'third_choice_program' => 'nullable|exists:programs,id|different:first_choice_program,second_choice_program',
            'qualified_programs_count' => 'required|integer|min:0',
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
                // Individual G11 Math grades
                'g11_general_mathematics' => $validated['g11_general_mathematics'],
                'g11_statistics_probability' => $validated['g11_statistics_probability'],
                // Individual G11 English grades
                'g11_oral_communication' => $validated['g11_oral_communication'],
                'g11_21st_century_lit' => $validated['g11_21st_century_lit'],
                'g11_reading_writing' => $validated['g11_reading_writing'],
                // Individual G12 Science grades
                'g12_earth_life_science' => $validated['g12_earth_life_science'],
                'g12_physical_science' => $validated['g12_physical_science'],
            ]
        );

        // Save program choices to applicant profile
        ApplicantProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_choice_program' => $validated['first_choice_program'],
                'second_choice_program' => $validated['second_choice_program'],
                'third_choice_program' => $validated['third_choice_program'],
            ]
        );

        app(\App\Services\AuditLogService::class)->logActivity('CREATE', 'Applications', "Applicant {$user->firstname} {$user->lastname} submitted grades and program choices.", $user, 'ADMISSION_DATA');

        return redirect()->route('applicant.dashboard')->with('success', 'Grades and program choices saved successfully');
    }

    public function storeHumssGrades(Request $request)
    {
        $user = Auth::user();

        if ($this->isEvaluatorLocked($user)) {
            return back()->withErrors(['locked' => 'Grade submission is no longer allowed. Your application has been submitted.']);
        }

        $validated = $request->validate([
            // Computed averages from frontend
            'mathematics' => 'required|numeric|min:0|max:100',
            'english' => 'required|numeric|min:0|max:100',
            'science' => 'required|numeric|min:0|max:100',
            'g12_first_sem' => 'required|numeric|min:0|max:100',
            'g12_second_sem' => 'required|numeric|min:0|max:100',
            // Individual G11 Math grades
            'g11_general_mathematics' => 'required|numeric|min:0|max:100',
            'g11_statistics_probability' => 'required|numeric|min:0|max:100',
            // Individual G11 English grades
            'g11_oral_communication' => 'required|numeric|min:0|max:100',
            'g11_21st_century_lit' => 'required|numeric|min:0|max:100',
            'g11_academic_professional' => 'required|numeric|min:0|max:100',
            'g11_reading_writing' => 'required|numeric|min:0|max:100',
            // Individual G11 Science grade (HUMSS: Earth and Life Science in G11)
            'g11_earth_life_science' => 'required|numeric|min:0|max:100',
            // Individual G12 Science grade (HUMSS: Physical Science in G12)
            'g12_physical_science' => 'required|numeric|min:0|max:100',
            // Program choices
            'first_choice_program' => 'required|exists:programs,id',
            'second_choice_program' => 'nullable|exists:programs,id|different:first_choice_program',
            'third_choice_program' => 'nullable|exists:programs,id|different:first_choice_program,second_choice_program',
            'qualified_programs_count' => 'required|integer|min:0',
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
                // Individual G11 Math grades
                'g11_general_mathematics' => $validated['g11_general_mathematics'],
                'g11_statistics_probability' => $validated['g11_statistics_probability'],
                // Individual G11 English grades
                'g11_oral_communication' => $validated['g11_oral_communication'],
                'g11_21st_century_lit' => $validated['g11_21st_century_lit'],
                'g11_academic_professional' => $validated['g11_academic_professional'],
                'g11_reading_writing' => $validated['g11_reading_writing'],
                // Individual G11 Science grade (HUMSS: Earth and Life Science in G11)
                'g11_earth_life_science' => $validated['g11_earth_life_science'],
                // Individual G12 Science grade (HUMSS: Physical Science in G12)
                'g12_physical_science' => $validated['g12_physical_science'],
            ]
        );

        // Save program choices to applicant profile
        ApplicantProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_choice_program' => $validated['first_choice_program'],
                'second_choice_program' => $validated['second_choice_program'],
                'third_choice_program' => $validated['third_choice_program'],
            ]
        );

        app(\App\Services\AuditLogService::class)->logActivity('CREATE', 'Applications', "Applicant {$user->firstname} {$user->lastname} submitted grades and program choices.", $user, 'ADMISSION_DATA');

        return redirect()->route('applicant.dashboard')->with('success', 'Grades and program choices saved successfully');
    }

    public function storeGasGrades(Request $request)
    {
        $user = Auth::user();

        if ($this->isEvaluatorLocked($user)) {
            return back()->withErrors(['locked' => 'Grade submission is no longer allowed. Your application has been submitted.']);
        }

        $validated = $request->validate([
            // Computed averages from frontend
            'mathematics' => 'required|numeric|min:0|max:100',
            'english' => 'required|numeric|min:0|max:100',
            'science' => 'required|numeric|min:0|max:100',
            'g12_first_sem' => 'required|numeric|min:0|max:100',
            'g12_second_sem' => 'required|numeric|min:0|max:100',
            // Individual G11 Math grades
            'g11_general_mathematics' => 'required|numeric|min:0|max:100',
            'g11_statistics_probability' => 'required|numeric|min:0|max:100',
            // Individual G11 English grades
            'g11_oral_communication' => 'required|numeric|min:0|max:100',
            'g11_21st_century_lit' => 'required|numeric|min:0|max:100',
            'g11_academic_professional' => 'required|numeric|min:0|max:100',
            'g11_reading_writing' => 'required|numeric|min:0|max:100',
            // Individual G11 Science grades
            'g11_earth_life_science' => 'required|numeric|min:0|max:100',
            'g11_physical_science' => 'required|numeric|min:0|max:100',
            // Program choices
            'first_choice_program' => 'required|exists:programs,id',
            'second_choice_program' => 'nullable|exists:programs,id|different:first_choice_program',
            'third_choice_program' => 'nullable|exists:programs,id|different:first_choice_program,second_choice_program',
            'qualified_programs_count' => 'required|integer|min:0',
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
                // Individual G11 Math grades
                'g11_general_mathematics' => $validated['g11_general_mathematics'],
                'g11_statistics_probability' => $validated['g11_statistics_probability'],
                // Individual G11 English grades
                'g11_oral_communication' => $validated['g11_oral_communication'],
                'g11_21st_century_lit' => $validated['g11_21st_century_lit'],
                'g11_academic_professional' => $validated['g11_academic_professional'],
                'g11_reading_writing' => $validated['g11_reading_writing'],
                // Individual G11 Science grades
                'g11_earth_life_science' => $validated['g11_earth_life_science'],
                'g11_physical_science' => $validated['g11_physical_science'],
            ]
        );

        // Save program choices to applicant profile
        ApplicantProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_choice_program' => $validated['first_choice_program'],
                'second_choice_program' => $validated['second_choice_program'],
                'third_choice_program' => $validated['third_choice_program'],
            ]
        );

        app(\App\Services\AuditLogService::class)->logActivity('CREATE', 'Applications', "Applicant {$user->firstname} {$user->lastname} submitted grades and program choices.", $user, 'ADMISSION_DATA');

        return redirect()->route('applicant.dashboard')->with('success', 'Grades and program choices saved successfully');
    }

    public function storeStemGrades(Request $request)
    {
        $user = Auth::user();

        if ($this->isEvaluatorLocked($user)) {
            return back()->withErrors(['locked' => 'Grade submission is no longer allowed. Your application has been submitted.']);
        }

        $validated = $request->validate([
            // Computed averages from frontend
            'mathematics' => 'required|numeric|min:0|max:100',
            'science' => 'required|numeric|min:0|max:100',
            'english' => 'required|numeric|min:0|max:100',
            'g12_first_sem' => 'required|numeric|min:0|max:100',
            'g12_second_sem' => 'required|numeric|min:0|max:100',
            // Individual G11 Math grades
            'g11_general_mathematics' => 'required|numeric|min:0|max:100',
            'g11_statistics_probability' => 'required|numeric|min:0|max:100',
            'g11_pre_calculus' => 'required|numeric|min:0|max:100',
            'g11_basic_calculus' => 'required|numeric|min:0|max:100',
            // Individual G11 Science grades
            'g11_earth_science' => 'required|numeric|min:0|max:100',
            'g11_general_chemistry_1' => 'required|numeric|min:0|max:100',
            // Individual G12 Science grades
            'g12_general_physics_1' => 'required|numeric|min:0|max:100',
            'g12_general_biology_1' => 'required|numeric|min:0|max:100',
            'g12_general_physics_2' => 'required|numeric|min:0|max:100',
            'g12_general_biology_2' => 'required|numeric|min:0|max:100',
            'g12_general_chemistry_2' => 'required|numeric|min:0|max:100',
            // Individual G11 English grades
            'g11_oral_communication' => 'required|numeric|min:0|max:100',
            'g11_reading_writing' => 'required|numeric|min:0|max:100',
            // Individual G12 English grades
            'g12_21st_century_lit' => 'required|numeric|min:0|max:100',
            'g12_academic_professional' => 'required|numeric|min:0|max:100',
            // Program choices
            'first_choice_program' => 'required|exists:programs,id',
            'second_choice_program' => 'nullable|exists:programs,id|different:first_choice_program',
            'third_choice_program' => 'nullable|exists:programs,id|different:first_choice_program,second_choice_program',
            'qualified_programs_count' => 'required|integer|min:0',
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
                // Individual G11 Math grades
                'g11_general_mathematics' => $validated['g11_general_mathematics'],
                'g11_statistics_probability' => $validated['g11_statistics_probability'],
                'g11_pre_calculus' => $validated['g11_pre_calculus'],
                'g11_basic_calculus' => $validated['g11_basic_calculus'],
                // Individual G11 Science grades
                'g11_earth_science' => $validated['g11_earth_science'],
                'g11_general_chemistry_1' => $validated['g11_general_chemistry_1'],
                // Individual G12 Science grades
                'g12_general_physics_1' => $validated['g12_general_physics_1'],
                'g12_general_biology_1' => $validated['g12_general_biology_1'],
                'g12_general_physics_2' => $validated['g12_general_physics_2'],
                'g12_general_biology_2' => $validated['g12_general_biology_2'],
                'g12_general_chemistry_2' => $validated['g12_general_chemistry_2'],
                // Individual G11 English grades
                'g11_oral_communication' => $validated['g11_oral_communication'],
                'g11_reading_writing' => $validated['g11_reading_writing'],
                // Individual G12 English grades
                'g12_21st_century_lit' => $validated['g12_21st_century_lit'],
                'g12_academic_professional' => $validated['g12_academic_professional'],
            ]
        );

        // Save program choices to applicant profile
        ApplicantProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_choice_program' => $validated['first_choice_program'],
                'second_choice_program' => $validated['second_choice_program'],
                'third_choice_program' => $validated['third_choice_program'],
            ]
        );

        app(\App\Services\AuditLogService::class)->logActivity('CREATE', 'Applications', "Applicant {$user->firstname} {$user->lastname} submitted grades and program choices.", $user, 'ADMISSION_DATA');

        return redirect()->route('applicant.dashboard')->with('success', 'Grades and program choices saved successfully');
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if ($this->isEvaluatorLocked($user)) {
            return response()->json(['message' => 'Grade submission is no longer allowed.'], 403);
        }

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
