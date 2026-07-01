<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Models\Program;
use App\Models\Grade;
use App\Models\Application;
use App\Models\SystemSetting;

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

        // Determine whether the "Download Grade Verification Slip" button should appear.
        // Conditions:
        //   1. Applicant has submitted their application (status !== 'draft')
        //   2. Grades exist (qualification results have been computed)
        $application = $user->currentApplication;
        $hasSubmittedApplication = $application && $application->status !== 'draft';
        $hasGrades = Grade::where('user_id', (string) $user->id)->exists();
        $canDownloadSlip = $hasSubmittedApplication && $hasGrades;

        // Determine whether the "Download F137 Request Letter" button should appear.
        // Conditions:
        //   - Former School Name has been provided in the applicant's profile
        //   - Former School Address has been provided in the applicant's profile
        $canDownloadF137 = !empty(trim($profile?->school               ?? ''))
                        && !empty(trim($profile?->former_school_address ?? ''));

        return Inertia::render('Dashboard/Applicant', [
            'user'           => $user ? $user->only(['id', 'firstname', 'lastname', 'email', 'role_id']) : null,
            'gradeUrl'       => $strand ? ($gradeRouteMap[$strand] ?? null) : null,
            'canDownloadSlip' => $canDownloadSlip,
            'canDownloadF137' => $canDownloadF137,
            'showQualifiedProgramsNav' => $hasSubmittedApplication,
        ]);
    }

    /**
     * Render the Qualified Programs page
     */
    public function qualifiedProgramsPage()
    {
        $user = Auth::user();

        if ($user->role_id !== 1) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $isEnabled = Cache::remember('setting_qualified_programs_view', 300, function () {
            return SystemSetting::where('key', 'enable_qualified_programs_view')->value('value') !== '0';
        });

        $application = $user->currentApplication;
        $hasSubmittedApplication = $application && $application->status !== 'draft';

        return Inertia::render('Programs/Qualified', [
            'user' => $user ? $user->only(['id', 'firstname', 'lastname', 'email', 'role_id']) : null,
            'showQualifiedProgramsNav' => $hasSubmittedApplication,
        ]);
    }

    /**
     * Render the Applicant's own Profile page (view-only)
     */
    public function profile()
    {
        $user = Auth::user();

        if ($user->role_id !== 1) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $user->load(['applicantProfile', 'testPasser']);

        $application = $user->currentApplication;
        if ($application) {
            $application->load(['program', 'processes', 'secondChoice', 'thirdChoice']);
        }

        $grades = Grade::where('user_id', (string) $user->id)->first();
        $files = $user->files ?? collect();
        $profile = $user->applicantProfile;

        return Inertia::render('Profile/Applicant', [
            'user'             => $user ? $user->only(['id', 'firstname', 'lastname', 'email', 'role_id']) : null,
            'applicantProfile' => $profile,
            'grades'           => $grades,
            'files'            => $files,
            'application'      => $application,
            'showQualifiedProgramsNav' => $application && $application->status !== 'draft',
            'formerSchool' => [
                'school'                  => $profile?->school                  ?? '',
                'former_school_address'   => $profile?->former_school_address   ?? '',
                'former_school_principal' => $profile?->former_school_principal ?? '',
            ],
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

        $isEnabled = Cache::remember('setting_qualified_programs_view', 300, function () {
            return SystemSetting::where('key', 'enable_qualified_programs_view')->value('value') !== '0';
        });

        if (!$isEnabled) {
            return response()->json(['message' => 'Feature disabled.'], 403);
        }

        $grades = Grade::where('user_id', (string) $user->id)->first();
        $profile = $user->applicantProfile;

        if (!$grades) {
            return response()->json([
                'qualified' => [],
                'disqualified' => [],
                'message' => 'No grades found. Please complete your grades first.'
            ]);
        }

        // Calculate GWA
        $gwa = ($grades->g12_first_sem + $grades->g12_second_sem) / 2;
        $userStrand = strtoupper($profile?->strand ?? '');

        // Get all programs (including those with 0 slots for tracking purposes)
        $programs = Program::with('strands')->get();

        $qualified = [];
        $disqualified = [];

        foreach ($programs as $program) {
            // Check grade requirements
            $meetsGrades = $grades->mathematics >= ($program->math ?? 0) &&
                          $grades->science >= ($program->science ?? 0) &&
                          $grades->english >= ($program->english ?? 0) &&
                          $gwa >= ($program->gwa ?? 0);

            // Check strand requirements
            $meetsStrand = $this->checkStrandRequirement($program, $userStrand);

            $isQualified = $meetsGrades && $meetsStrand;

            $programData = [
                'id' => $program->id,
                'code' => $program->code,
                'name' => $program->name,
                'slots' => $program->slots,
                'strand_names' => $program->strand_names,
                'requirements' => [
                    'math' => $program->math,
                    'science' => $program->science,
                    'english' => $program->english,
                    'gwa' => $program->gwa,
                ],
                'your_grades' => [
                    'math' => $grades->mathematics,
                    'science' => $grades->science,
                    'english' => $grades->english,
                    'gwa' => round($gwa, 2),
                ],
                'meets_grades' => $meetsGrades,
                'meets_strand' => $meetsStrand,
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

    /**
     * Check if user's strand meets program requirements
     *
     * @param Program $program
     * @param string $userStrand
     * @return bool
     */
    private function checkStrandRequirement($program, $userStrand)
    {
        if (!$userStrand) {
            return true; // If no strand info, allow all
        }

        $strandNames = strtoupper($program->strand_names ?? '');
        
        // If no strand requirement, allow all
        if (empty($strandNames)) {
            return true;
        }
        
        // If explicitly open to all strands
        if (str_contains($strandNames, 'OPEN TO ALL')) {
            return true;
        }

        // Check if user's strand is in the allowed list
        $allowedStrands = array_map('trim', preg_split('/[,\/]/', $strandNames));
        
        foreach ($allowedStrands as $allowed) {
            // Normalize strand names
            if (str_contains($allowed, 'TECH-VOC') || str_contains($allowed, 'TVL')) {
                $allowed = 'TVL';
            }
            
            if ($allowed === $userStrand) {
                return true;
            }
        }
        
        // Check if "other with bridging" is mentioned
        if (str_contains($strandNames, 'OTHER') && str_contains($strandNames, 'BRIDGING')) {
            return true;
        }
        
        return false;
    }
}
