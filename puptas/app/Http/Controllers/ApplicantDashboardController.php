<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Program;
use App\Models\Grade;

class ApplicantDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role_id !== 1) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $profile = $user->applicantProfile;
        $strand = $profile?->strand ? strtolower(trim($profile->strand)) : null;

        $gradeRouteMap = [
            'abm'   => '/grades/abm',
            'ict'   => '/grades/ict',
            'humss' => '/grades/humss',
            'gas'   => '/grades/gas',
            'stem'  => '/grades/stem',
            'tvl'   => '/grades/tvl',
        ];

        return Inertia::render('Dashboard/Applicant', [
            'user' => $user,
            'gradeUrl' => $strand ? ($gradeRouteMap[$strand] ?? null) : null,
        ]);
    }

    /**
     * Get qualified programs for the authenticated applicant
     * Returns programs with real-time slots and eligibility status
     */
    public function getQualifiedPrograms()
    {
        $user = Auth::user();

        if ($user->role_id !== 1) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $grades = Grade::where('user_id', $user->id)->first();

        if (!$grades) {
            return response()->json([
                'qualified' => [],
                'disqualified' => [],
                'message' => 'No grades found. Please complete your grades first.'
            ]);
        }

        // Get all programs with available slots
        $programs = Program::where('slots', '>', 0)->get();

        $qualified = [];
        $disqualified = [];

        foreach ($programs as $program) {
            $isQualified = $grades->mathematics >= $program->math &&
                           $grades->science >= $program->science &&
                           $grades->english >= $program->english;

            $programData = [
                'id' => $program->id,
                'code' => $program->code,
                'name' => $program->name,
                'slots' => $program->slots,
                'requirements' => [
                    'math' => $program->math,
                    'science' => $program->science,
                    'english' => $program->english,
                ],
                'your_grades' => [
                    'math' => $grades->mathematics,
                    'science' => $grades->science,
                    'english' => $grades->english,
                ],
            ];

            if ($isQualified) {
                $qualified[] = $programData;
            } else {
                $disqualified[] = $programData;
            }
        }

        return response()->json([
            'qualified' => $qualified,
            'disqualified' => $disqualified,
        ]);
    }
}
