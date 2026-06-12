<?php

namespace App\Services;

use App\Models\User;
use App\Models\Program;
use App\Models\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Dashboard Service
 * 
 * Handles business logic for dashboard data aggregation.
 * Provides common dashboard data across different role dashboards.
 */
class DashboardService
{
    protected ApplicationService $applicationService;
    protected UserService $userService;

    public function __construct(ApplicationService $applicationService, UserService $userService)
    {
        $this->applicationService = $applicationService;
        $this->userService = $userService;
    }

    /**
     * Get common dashboard data
     *
     * Intentional: This role has full visibility across all programs.
     * Do NOT add program ID scoping here — see Requirements 6.1, 6.2, 6.3.
     *
     * @return array
     */
    public function getCommonDashboardData(): array
    {
        return [
            'allUsers' => $this->userService->getApplicantsWithApplications(),
            'summary' => $this->applicationService->getApplicationSummary(),
        ];
    }

    /**
     * Get dashboard data with programs
     *
     * @return array
     */
    public function getDashboardDataWithPrograms(): array
    {
        $commonData = $this->getCommonDashboardData();

        return array_merge($commonData, [
            'programs' => Program::withCount('applications')->get(),
        ]);
    }

    /**
     * Get dashboard data with chart data
     *
     * @return array
     */
    public function getDashboardDataWithCharts(): array
    {
        $commonData = $this->getCommonDashboardData();

        return array_merge($commonData, [
            'chartData' => $this->getApplicationChartData(),
        ]);
    }

    /**
     * Get application chart data grouped by date
     *
     * @return array
     */
    public function getApplicationChartData(): array
    {
        $now = \Carbon\Carbon::now();
        $request = request();
        
        $startDateParam = $request->input('start_date');
        $endDateParam = $request->input('end_date');

        if ($startDateParam && $endDateParam) {
            session(['dashboard_start_date' => $startDateParam, 'dashboard_end_date' => $endDateParam]);
        } else {
            $startDateParam = session('dashboard_start_date');
            $endDateParam = session('dashboard_end_date');
        }

        $cacheKey = 'dashboard_chart_data_' . ($startDateParam ?: 'default') . '_' . ($endDateParam ?: 'default');

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 600, function () use ($now, $startDateParam, $endDateParam) {
        \Log::info('Dashboard dates:', ['start' => $startDateParam, 'end' => $endDateParam]);

        if ($startDateParam && $endDateParam) {
            try {
                $startDate = \Carbon\Carbon::parse($startDateParam)->startOfDay();
                $endDate = \Carbon\Carbon::parse($endDateParam)->endOfDay();
                
                if ($startDate->greaterThan($endDate)) {
                    $temp = $startDate;
                    $startDate = $endDate;
                    $endDate = $temp;
                }
            } catch (\Exception $e) {
                $startDate = $now->copy()->subDays(29)->startOfDay();
                $endDate = $now->copy()->endOfDay();
            }
        } else {
            $startDate = $now->copy()->subDays(29)->startOfDay();
            $endDate = $now->copy()->endOfDay();
        }
        
        $diffInDays = (int) $startDate->diffInDays($endDate->copy()->startOfDay());
        if ($diffInDays > 365) {
            $diffInDays = 365;
            $startDate = $endDate->copy()->subDays(365)->startOfDay();
        }

        $submittedQuery = DB::table('applications')
            ->select(
                DB::raw('DATE(created_at) as date'),
                'status',
                DB::raw('COUNT(*) as count')
            )
            ->where('status', 'submitted')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(created_at)'), 'status');

        $acceptedQuery = DB::table('application_processes')
            ->select(
                DB::raw('DATE(updated_at) as date'),
                DB::raw("'accepted' as status"),
                DB::raw('COUNT(DISTINCT application_id) as count')
            )
            ->where('stage', 'interviewer')
            ->where('status', 'completed')
            ->where('action', 'passed')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(updated_at)'));

        $returnedQuery = DB::table('applications')
            ->select(
                DB::raw('DATE(updated_at) as date'),
                'status',
                DB::raw('COUNT(*) as count')
            )
            ->where('status', 'returned')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(updated_at)'), 'status');

        $applications = $submittedQuery
            ->unionAll($acceptedQuery)
            ->unionAll($returnedQuery)
            ->get();

        // Build a list of dates
        $dates = [];
        $dateLabels = [];
        for ($i = $diffInDays; $i >= 0; $i--) {
            $date = $endDate->copy()->subDays($i);
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

        return [
            'labels' => $dateLabels,
            'years' => $dateLabels,  // For backward compatibility
            'submitted' => $submitted,
            'accepted' => $accepted,
            'returned' => $returned,
            'filters' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
        ];
        });
    }

    /**
     * Get application chart data grouped by date
     *
     * @return array
     */
    public function getDailyApplicationChartData(): array
    {
        return $this->getApplicationChartData();
    }

    /**
     * Verify user role access
     *
     * @param \Illuminate\Contracts\Auth\Authenticatable|mixed $user
     * @param int|array $requiredRoleId
     * @return bool
     */
    public function verifyRoleAccess($user, $requiredRoleId): bool
    {
        if (is_array($requiredRoleId)) {
            return in_array($user->role_id, $requiredRoleId);
        }
        return $user->role_id === $requiredRoleId;
    }

    /**
     * Get applicants for dashboard display
     *
     * @return \Illuminate\Support\Collection
     */
    public function getApplicantsForDashboard()
    {
        return $this->userService->getApplicantsWithApplications();
    }

    /**
     * Get applicants pending for a specific stage
     *
     * @param string $stage
     * @return \Illuminate\Support\Collection
     */
    public function getApplicantsPendingForStage(string $stage)
    {
        return User::with('currentApplication.program')
            ->whereHas('currentApplication', function ($query) use ($stage) {
                $query->whereHas('processes', function ($q) use ($stage) {
                    $q->where('stage', $stage)
                        ->where('status', 'in_progress');
                });
            })
            ->get();
    }

    /**
     * Get dashboard data for evaluator with pending applications.
     * Filters pendingUsers to only those in the evaluator's assigned programs.
     * summary and chartData remain global (not scoped) per Requirement 4.3.
     *
     * @return array
     */
    public function getEvaluatorDashboardData(string $stage = 'document_evaluator'): array
    {
        $programIds = Auth::user()
            ->programs()
            ->pluck('programs.id')
            ->toArray();

        // If the evaluator has no assigned programs, pendingUsers is empty.
        // Evaluators must be explicitly assigned to programs to see applicants.
        $pendingUsers = empty($programIds)
            ? collect()
            : $this->userService->getApplicantsByStage($stage, $programIds);

        return [
            'pendingUsers' => $pendingUsers,
            'summary'      => $this->applicationService->getApplicationSummary(),
            'chartData'    => $this->getApplicationChartData(),
        ];
    }

    /**
     * Get dashboard data for interviewer with pending applications
     * Interviewers have global access to see all applicants.
     *
     * @return array
     */
    public function getInterviewerDashboardData(): array
    {
        // Interviewers see all applicants (global access)
        $pendingUsers = $this->userService->getApplicantsByStage('interviewer');

        return [
            'pendingUsers' => $pendingUsers,
            'summary'      => $this->applicationService->getApplicationSummary(),
            'chartData'    => $this->getDailyApplicationChartData(),
        ];
    }

    /**
     * Get dashboard data for medical with pending applications
     *
     * Intentional: This role has full visibility across all programs.
     * Do NOT add program ID scoping here — see Requirements 6.1, 6.2, 6.3.
     *
     * @return array
     */
    public function getMedicalDashboardData(): array
    {
        return [
            'pendingUsers' => $this->userService->getApplicantsByStage('medical'),
            'summary' => $this->applicationService->getApplicationSummary(),
            'chartData' => $this->getApplicationChartData(),
        ];
    }

    /**
     * Get dashboard data for records staff with pending applications
     *
     * Intentional: This role has full visibility across all programs.
     * Do NOT add program ID scoping here — see Requirements 6.1, 6.2, 6.3.
     *
     * @return array
     */
    public function getRecordsDashboardData(): array
    {
        // Use map to create plain arrays and avoid triggering accessors
        $programs = Program::withCount('applications')
            ->select('id', 'code', 'name', 'slots')
            ->get()
            ->map(function ($program) {
                return [
                    'id' => $program->id,
                    'code' => $program->code,
                    'name' => $program->name,
                    'slots' => $program->slots,
                    'applications_count' => $program->applications_count,
                ];
            });

        return [
            'allUsers' => $this->userService->getApplicantsForRecordStaff(),
            'programs' => $programs,
            'summary' => $this->applicationService->getApplicationSummary(),
        ];
    }
}
