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
        $programs = Program::all();
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
        $programs = Program::all();
        $profile = ApplicantProfile::where('user_id', $user->id)->first();

        return inertia('Grades/ICTGradeInput', [
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

        return response()->json([
            'message' => 'Grades and program choices saved successfully',
            'grade' => $grade,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(ValidationRules::gradeImport());
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            sleep(2); // Wait 1 second and try again
            $user = User::where('email', $request->email)->first();

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
