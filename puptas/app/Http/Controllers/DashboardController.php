<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ApplicantProfile;
use Inertia\Inertia;
use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\Program;
use App\Models\Grade;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\UserFile;
use Illuminate\Support\Facades\Storage;
use App\Helpers\FileMapper;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Guard: unauthenticated users get sent to login (not redirect()->back(),
        // which throws a 500 when there is no previous URL — e.g. when the IDP
        // redirects the browser directly to /dashboard instead of the callback).
        if (! $user) {
            return redirect()->route('login');
        }

        // Guard: authenticated users with the wrong role get routed correctly
        if (! in_array($user->role_id, [2, 7])) {
            return match ((int) $user->role_id) {
                1 => redirect('/applicant-dashboard'),
                3 => redirect('/evaluator-dashboard'),
                4 => redirect('/interviewer-dashboard'),
                6 => redirect('/record-dashboard'),
                default => redirect()->route('login'),
            };
        }

        $summary = [
            'total' => Application::count(),
            'accepted' => Application::whereIn('status', ['accepted', 'cleared_for_enrollment'])->count(),
            'pending' => Application::where('status', 'submitted')->count(),
            'returned' => Application::where('status', 'returned')->count(),
        ];

        // Group applications by date and status (last 30 days)
        // Use single Carbon::now() reference to prevent midnight misalignment
        $now = Carbon::now();
        $startDate = $now->copy()->subDays(29)->startOfDay();
        $endDate = $now->copy()->endOfDay();

        $applications = DB::table('applications')
            ->select(
                DB::raw('DATE(created_at) as date'),
                'status',
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(created_at)'), 'status')
            ->orderBy('date')
            ->get();

        // Build a list of dates for the last 30 days
        $dates = [];
        $dateLabels = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $dates[] = $date->format('Y-m-d');
            $dateLabels[] = $date->format('M j');
        }

        // Initialize status arrays
        $submitted = [];
        $accepted = [];
        $returned = [];

        foreach ($dates as $date) {
            $submitted[] = $applications->where('date', $date)->where('status', 'submitted')->sum('count');
            $accepted[]  = $applications->where('date', $date)->whereIn('status', ['accepted', 'cleared_for_enrollment'])->sum('count');
            $returned[]  = $applications->where('date', $date)->where('status', 'returned')->sum('count');
        }

        return Inertia::render('Dashboard/Admin', [
            'user' => $user,
            'allUsers' => ApplicantProfile::with(['currentApplication.program'])
                ->whereHas('currentApplication')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($applicant) {
                    return [
                        'id' => $applicant->user_id,
                        'firstname' => $applicant->firstname,
                        'lastname' => $applicant->lastname,
                        'email' => $applicant->email,
                        'role' => ['name' => 'Applicant'], // Mock role as it was removed
                        'created_at' => $applicant->created_at,
                        // Map currentApplication to application for frontend compatibility
                        'application' => $applicant->currentApplication ? [
                            'id' => $applicant->currentApplication->id,
                            'status' => $applicant->currentApplication->status,
                            'created_at' => $applicant->currentApplication->created_at,
                            'program' => $applicant->currentApplication->program,
                        ] : null,
                    ];
                }),
            'summary' => $summary,
            'registrationUrl' => url('/register'),
            'chartData' => [
                'labels' => $dateLabels,
                'submitted' => $submitted,
                'accepted' => $accepted,
                'returned' => $returned,
            ],
        ]);
    }

    public function getUsers()
    {
        // Defense in depth: Verify authentication and authorized role (admin, evaluator, interviewer)
        $user = Auth::user();
        if (!$user || !in_array($user->role_id, [2, 3, 4, 7])) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        return response()->json(
            ApplicantProfile::with(['currentApplication.program', 'currentApplication.processes:id,application_id,stage,status,action,created_at'])
                ->whereHas('currentApplication')
                ->get()
                ->map(function ($applicant) {
                    return [
                        'id' => $applicant->user_id,
                        'firstname' => $applicant->firstname,
                        'lastname' => $applicant->lastname,
                        'course' => $applicant->course, // Note: does ApplicantProfile have this? Make sure.
                        'status' => $applicant->currentApplication->status ?? null,
                        'email' => $applicant->email,
                        'username' => $applicant->email,
                        'phone' => $applicant->contactnumber,
                        'company' => null,
                        'program' => $applicant->currentApplication->program ?? null,
                    ];
                })
        );
    }

    /**
     * Get user files with formatted URLs
     * OPTIMIZED: Returns minimal data without loading file URLs for faster initial load
     */
    public function getUserFiles($id)
    {
        // Defense in depth: Verify authentication and admin role
        $authUser = Auth::user();
        if (!$authUser || !in_array($authUser->role_id, [2, 7])) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        // OPTIMIZATION: Load only essential data, exclude heavy file relationships
        $applicant = ApplicantProfile::with([
            'currentApplication' => function ($query) {
                $query->select('id', 'user_id', 'status', 'created_at', 'program_id');
            },
            'currentApplication.program:id,code,name',
            'currentApplication.processes' => function ($query) {
                $query->select('id', 'application_id', 'stage', 'status', 'action', 'reviewer_notes', 'created_at')
                    ->orderBy('created_at', 'desc')
                    ->limit(10);
            },
            'graduateTypes:id,label',
        ])
        ->select('user_id', 'student_number', 'firstname', 'lastname', 'email', 'contactnumber', 'street_address', 'barangay', 'city', 'province', 'postal_code', 'birthday', 'sex', 'created_at')
        ->where('user_id', $id)
        ->firstOrFail();

        // OPTIMIZATION: Get only file metadata (type, status, comment) without loading file paths
        $fileMetadata = UserFile::where('user_id', $id)
            ->select('type', 'status', 'comment', 'original_name')
            ->get()
            ->keyBy('type');

        // Transform the response to use 'application' key for frontend compatibility
        $userData = [
            'id' => $applicant->user_id,
            'student_number' => $applicant->student_number,
            'firstname' => $applicant->firstname,
            'lastname' => $applicant->lastname,
            'email' => $applicant->email,
            'contactnumber' => $applicant->contactnumber,
            'street_address' => $applicant->street_address,
            'barangay' => $applicant->barangay,
            'city' => $applicant->city,
            'province' => $applicant->province,
            'postal_code' => $applicant->postal_code,
            'birthday' => $applicant->birthday,
            'sex' => $applicant->sex,
            'created_at' => $applicant->created_at,
            // Map currentApplication to application for frontend compatibility 
            'application' => $applicant->currentApplication ? [
                'id' => $applicant->currentApplication->id,
                'status' => $applicant->currentApplication->status,
                'created_at' => $applicant->currentApplication->created_at,
                'program' => $applicant->currentApplication->program,
                'processes' => $applicant->currentApplication->processes,
            ] : null,
        ];

        $graduateType = $applicant->graduateTypes->first()?->label ?? null;

        // OPTIMIZATION: Return file metadata without URLs - frontend will lazy load them
        $fileList = FileMapper::formatFilesForGraduateTypeMinimal($fileMetadata, $graduateType);

        return response()->json([
            'user' => $userData,
            'uploadedFiles' => $fileList,
            'graduateType' => $graduateType,
            'lazyLoad' => true, // Signal to frontend to use lazy loading
        ]);
    }

    /**
     * Get user grades separately for lazy loading
     * 
     * @param int $id User ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserGrades($id)
    {
        // Defense in depth: Verify authentication and admin role
        $authUser = Auth::user();
        if (!$authUser || !in_array($authUser->role_id, [2, 7])) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $applicant = ApplicantProfile::with('grades')
            ->select('user_id')
            ->where('user_id', $id)
            ->firstOrFail();

        return response()->json([
            'grades' => $applicant->grades,
        ]);
    }

    public function getPrograms()
    {
        $programs = Program::where('slots', '>', 0)->get();

        return response()->json([
            'programs' => $programs
        ]);
    }
}
