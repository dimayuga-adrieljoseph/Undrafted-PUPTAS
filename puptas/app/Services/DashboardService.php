<?php

namespace App\Services;

use App\Models\User;
use App\Models\Program;
use App\Models\Application;
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
     * @return array
     */
    public function getCommonDashboardData(): array
    {
        return [
            'allUsers' => User::with('currentApplication.program')->whereHas('currentApplication')->get(),
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
     * Get application chart data grouped by year
     *
     * @return array
     */
    public function getApplicationChartData(): array
    {
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

        return [
            'years' => $years,
            'labels' => $years,
            'submitted' => $submitted,
            'accepted' => $accepted,
            'returned' => $returned,
        ];
    }

    /**
     * Get application chart data grouped by date (last 30 days)
     *
     * @return array
     */
    public function getDailyApplicationChartData(): array
    {
        $now = \Carbon\Carbon::now();
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

        return [
            'labels' => $dateLabels,
            'years' => $dateLabels,  // For backward compatibility
            'submitted' => $submitted,
            'accepted' => $accepted,
            'returned' => $returned,
        ];
    }

    /**
     * Verify user role access
     *
     * @param User $user
     * @param int $requiredRoleId
     * @return bool
     */
    public function verifyRoleAccess(User $user, int $requiredRoleId): bool
    {
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
                      ->whereIn('status', ['in_progress', 'returned']);
                });
            })
            ->get();
    }

    /**
     * Get dashboard data for evaluator with pending applications
     *
     * @return array
     */
    public function getEvaluatorDashboardData(): array
    {
        return [
            'allUsers' => $this->getApplicantsPendingForStage('evaluator'),
            'summary' => $this->applicationService->getApplicationSummary(),
            'chartData' => $this->getApplicationChartData(),
        ];
    }

    /**
     * Get dashboard data for interviewer with pending applications
     *
     * @return array
     */
    public function getInterviewerDashboardData(): array
    {
        return [
            'allUsers' => $this->getApplicantsPendingForStage('interviewer'),
            'summary' => $this->applicationService->getApplicationSummary(),
            'chartData' => $this->getDailyApplicationChartData(),
        ];
    }

    /**
     * Get dashboard data for medical with pending applications
     *
     * @return array
     */
    public function getMedicalDashboardData(): array
    {
        return [
            'allUsers' => $this->getApplicantsPendingForStage('medical'),
            'summary' => $this->applicationService->getApplicationSummary(),
            'chartData' => $this->getApplicationChartData(),
        ];
    }

    /**
     * Get dashboard data for records staff with pending applications
     *
     * @return array
     */
    public function getRecordsDashboardData(): array
    {
        return [
            'allUsers' => $this->userService->getApplicantsForRecordStaff(),
            'programs' => Program::withCount('applications')->get(),
            'summary' => $this->applicationService->getApplicationSummary(),
        ];
    }
}
