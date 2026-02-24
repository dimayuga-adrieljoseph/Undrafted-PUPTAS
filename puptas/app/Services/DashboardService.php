<?php

namespace App\Services;

use App\Models\User;
use App\Models\Program;
use App\Models\Application;
use Illuminate\Support\Facades\DB;

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
            'allUsers' => User::all(),
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
}
