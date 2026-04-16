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

        // Guard against null user and verify admin or superadmin role
        if (!$user || !in_array($user->role_id, [2, 7])) {
            return redirect()->back()->withInput()->with('error', 'Unauthorized access.');
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
            $accepted[]  = $applications->where('date', $date)->where('status', 'accepted')->sum('count');
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
            ApplicantProfile::with('currentApplication.program')
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

    public function getUserFiles($id)
    {
        // Defense in depth: Verify authentication and admin role
        $authUser = Auth::user();
        if (!$authUser || !in_array($authUser->role_id, [2, 7])) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $applicant = ApplicantProfile::with(['currentApplication.program', 'currentApplication.processes', 'grades', 'graduateTypes'])
            ->where('user_id', $id)
            ->firstOrFail();

        $files = UserFile::where('user_id', $id)->get()->keyBy('type');

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
            'files' => $files->values(),
            'grades' => $applicant->grades,
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

        return response()->json([
            'user' => $userData,
            'uploadedFiles' => FileMapper::formatFilesForGraduateType($files, $graduateType, false),
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
