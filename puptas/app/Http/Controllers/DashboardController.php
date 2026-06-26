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
use App\Services\DashboardService;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

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
                3, 8 => redirect('/evaluator-dashboard'),
                4 => redirect('/interviewer-dashboard'),
                6 => redirect('/record-dashboard'),
                default => redirect()->route('login'),
            };
        }

        $commonData = $this->dashboardService->getCommonDashboardData();
        $summary = $commonData['summary'];

        $chartData = $this->dashboardService->getApplicationChartData();

        return Inertia::render('Dashboard/Admin', [
            'user' => $user ? $user->only(['id', 'firstname', 'lastname', 'email', 'role_id']) : null,
            // SECURITY/PERFORMANCE: Limit to 10 records intentionally.
            // Loading the full table here causes massive HTML payload bloat and leaks PII into DevTools.
            // The full list is fetched asynchronously via /dashboard/users.
            'allUsers' => ApplicantProfile::select('user_id', 'firstname', 'lastname', 'email')
                ->with(['currentApplication.program'])
                ->whereHas('currentApplication')
                ->orderBy('created_at', 'desc')
                ->limit(10)
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
                'labels' => $chartData['labels'],
                'submitted' => $chartData['submitted'],
                'accepted' => $chartData['accepted'],
                'returned' => $chartData['returned'],
            ],
            'filters' => $chartData['filters']
        ]);
    }

    public function getUsers()
    {
        // Defense in depth: Verify authentication and authorized role (admin, evaluator, interviewer)
        $user = Auth::user();
        if (!$user || !in_array($user->role_id, [2, 3, 4, 7, 8])) {
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
                        'company' => null,
                        'program' => $applicant->currentApplication->program ?? null,
                    ];
                })
        );
    }

    /**
     * Get user files with formatted URLs
     * TEMPORARY: Returns full data with URLs until frontend is updated for lazy loading
     */
    public function getUserFiles($id)
    {
        try {
            // Defense in depth: Verify authentication and admin role
            $authUser = Auth::user();
            if (!$authUser || !in_array($authUser->role_id, [2, 7])) {
                return response()->json(['message' => 'Unauthorized access'], 403);
            }

            // Load applicant with all necessary data
            $applicant = ApplicantProfile::with([
                'currentApplication' => function ($query) {
                    $query->select('applications.id', 'applications.user_id', 'applications.status', 'applications.created_at', 'applications.program_id');
                },
                'currentApplication.program:id,code,name,slots',
                'currentApplication.processes' => function ($query) {
                    $query->select('id', 'application_id', 'stage', 'status', 'action', 'reviewer_notes', 'created_at')
                        ->orderBy('created_at', 'desc')
                        ->limit(10);
                },
                'graduateTypes:id,label',
                'grades', // Include grades
                'testPasser', // Include testPasser to get reference_number
            ])
            ->where('user_id', (string) $id)
            ->firstOrFail();

            // Get files with full data
            $files = UserFile::where('user_id', (string) $id)->get()->keyBy('type');

            // Transform the response to use 'application' key for frontend compatibility
            $userData = [
                'id' => $applicant->user_id,
                'reference_number' => $applicant->testPasser->reference_number ?? 'N/A',
                'firstname' => $applicant->firstname,
                'lastname' => $applicant->lastname,
                'email' => $applicant->email,
                'sex' => $applicant->sex,
                'created_at' => $applicant->created_at,
                'grades' => $applicant->grades, // Include grades in response
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

            // Return full file data with URLs (not lazy loading)
            $fileList = FileMapper::formatFilesForGraduateType($files, $graduateType, false);

            // Debug logging
            \Log::info('Admin getUserFiles response', [
                'userId' => $id,
                'graduateType' => $graduateType,
                'hasGrades' => $applicant->grades !== null,
                'gradesData' => $applicant->grades,
                'rawFileCount' => $files->count(),
                'formattedFileCount' => count($fileList),
                'fileKeys' => array_keys($fileList),
            ]);

            return response()->json([
                'user' => $userData,
                'uploadedFiles' => $fileList,
                'graduateType' => $graduateType,
                'lazyLoad' => false, // Disabled until frontend is updated
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Applicant not found in admin getUserFiles', [
                'userId' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Applicant not found'], 404);
        } catch (\Exception $e) {
            \Log::error('Failed to load applicant data in admin dashboard', [
                'userId' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to load applicant data. Please try again.',
            ], 500);
        }
    }

    /**
     * Get user grades separately for lazy loading
     * 
     * @param int $id User ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserGrades($id)
    {
        try {
            // Defense in depth: Verify authentication and admin role
            $authUser = Auth::user();
            if (!$authUser || !in_array($authUser->role_id, [2, 7])) {
                return response()->json(['message' => 'Unauthorized access'], 403);
            }

            $applicant = ApplicantProfile::with('grades')
                ->select('user_id')
                ->where('user_id', (string) $id)
                ->firstOrFail();

            return response()->json([
                'grades' => $applicant->grades,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Applicant not found in admin getUserGrades', [
                'userId' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Applicant not found',
                'grades' => null,
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Failed to load grades in admin dashboard', [
                'userId' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to load grades. Please try again.',
                'grades' => null,
            ], 500);
        }
    }

    public function getPrograms()
    {
        $programs = Program::where('slots', '>', 0)->get();

        return response()->json([
            'programs' => $programs
        ]);
    }
}
