<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
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
            'accepted' => Application::where('status', 'accepted')->count(),
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
            'allUsers' => User::with(['currentApplication.program', 'role'])
                ->where('role_id', 1)
                ->whereHas('currentApplication')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'firstname' => $user->firstname,
                        'lastname' => $user->lastname,
                        'email' => $user->email,
                        'role' => $user->role,
                        'created_at' => $user->created_at,
                        // Map currentApplication to application for frontend compatibility
                        'application' => $user->currentApplication ? [
                            'id' => $user->currentApplication->id,
                            'status' => $user->currentApplication->status,
                            'created_at' => $user->currentApplication->created_at,
                            'program' => $user->currentApplication->program,
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
            User::with('currentApplication.program')
                ->where('role_id', 1)
                ->whereHas('currentApplication')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'firstname' => $user->firstname,
                        'lastname' => $user->lastname,
                        'course' => $user->course,
                        'status' => $user->currentApplication->status ?? null,
                        'email' => $user->email,
                        'username' => $user->username,
                        'phone' => $user->phone,
                        'company' => $user->company,
                        'program' => $user->currentApplication->program ?? null,
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

        $user = User::with(['currentApplication.program', 'currentApplication.processes', 'files', 'grades'])->findOrFail($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $files = $user->files->keyBy('type');

        // Transform the response to use 'application' key for frontend compatibility
        $userData = [
            'id' => $user->id,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'contactnumber' => $user->contactnumber,
            'street_address' => $user->street_address,
            'barangay' => $user->barangay,
            'city' => $user->city,
            'province' => $user->province,
            'postal_code' => $user->postal_code,
            'birthday' => $user->birthday,
            'sex' => $user->sex,
            'created_at' => $user->created_at,
            'files' => $user->files,
            'grades' => $user->grades,
            // Map currentApplication to application for frontend compatibility
            'application' => $user->currentApplication ? [
                'id' => $user->currentApplication->id,
                'status' => $user->currentApplication->status,
                'created_at' => $user->currentApplication->created_at,
                'program' => $user->currentApplication->program,
                'processes' => $user->currentApplication->processes,
            ] : null,
        ];

        return response()->json([
            'user' => $userData,
            'uploadedFiles' => FileMapper::formatFilesUrls($files),
        ]);
    }

    public function getPrograms()
    {
        $programs = Program::where('slots', '>', 0)->get();

        return response()->json([
            'programs' => $programs
        ]);
    }

    /**
     * Get all applicants pending special case review (FOR_SPECIAL_REVIEW).
     */
    public function getSpecialCaseApplicants()
    {
        $authUser = Auth::user();
        if (!$authUser || !in_array($authUser->role_id, [2, 7])) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $profiles = \App\Models\ApplicantProfile::with('user')
            ->where('applicant_status', 'FOR_SPECIAL_REVIEW')
            ->get()
            ->map(function ($profile) {
                return [
                    'profile_id'   => $profile->id,
                    'user_id'      => $profile->user_id,
                    'firstname'    => $profile->user?->firstname,
                    'lastname'     => $profile->user?->lastname,
                    'email'        => $profile->user?->email,
                    'source'       => $profile->source,
                    'status'       => $profile->applicant_status,
                    'decision'     => $profile->admission_decision,
                    'created_at'   => $profile->created_at,
                ];
            });

        return response()->json($profiles);
    }

    /**
     * Admin approves a special case applicant.
     */
    public function approveSpecialCase(Request $request, $profileId)
    {
        $authUser = Auth::user();
        if (!$authUser || !in_array($authUser->role_id, [2, 7])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate(['reason' => 'nullable|string|max:1000']);

        $service = app(\App\Services\SpecialCaseService::class);
        $profile = $service->approveSpecialCase((int) $profileId, $authUser->id, $request->reason);

        return response()->json([
            'message' => 'Applicant approved as special case.',
            'profile' => $profile,
        ]);
    }

    /**
     * Admin rejects a special case applicant.
     */
    public function rejectSpecialCase(Request $request, $profileId)
    {
        $authUser = Auth::user();
        if (!$authUser || !in_array($authUser->role_id, [2, 7])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $profile = \App\Models\ApplicantProfile::findOrFail($profileId);
        $profile->update([
            'admission_decision' => 'REJECTED',
            'applicant_status'   => 'REJECTED',
        ]);

        return response()->json(['message' => 'Applicant rejected.']);
    }
}
