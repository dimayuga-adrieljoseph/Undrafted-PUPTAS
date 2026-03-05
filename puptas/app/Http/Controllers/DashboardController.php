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

        // Guard against null user and verify admin role
        if (!$user || $user->role_id !== 2) {
            return redirect()->back()->withInput()->with('error', 'Unauthorized access.');
        }

        $summary = [
            'total' => Application::count(),
            'accepted' => Application::where('status', 'accepted')->count(),
            'pending' => Application::where('status', 'submitted')->count(),
            'returned' => Application::where('status', 'returned')->count(),
        ];

        // Group applications by year and status
        $applications = DB::table('applications')
            ->select(
                DB::raw('YEAR(created_at) as year'),
                'status',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw('YEAR(created_at)'), 'status')
            ->orderBy('year')
            ->get();


        // Build a list of years dynamically
        $years = $applications->pluck('year')->unique()->sort()->values()->all();

        // Initialize status arrays
        $submitted = [];
        $accepted = [];
        $returned = [];

        foreach ($years as $year) {
            $submitted[] = $applications->where('year', $year)->where('status', 'submitted')->sum('count');
            $accepted[]  = $applications->where('year', $year)->where('status', 'accepted')->sum('count');
            $returned[]  = $applications->where('year', $year)->where('status', 'returned')->sum('count');
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
                'years' => $years,
                'submitted' => $submitted,
                'accepted' => $accepted,
                'returned' => $returned,
            ],
        ]);
    }


    public function getUsers()
    {
        // Defense in depth: Verify authentication and admin role
        $user = Auth::user();
        if (!$user || $user->role_id !== 2) {
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
        if (!$authUser || $authUser->role_id !== 2) {
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
            'address' => $user->address,
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
}
