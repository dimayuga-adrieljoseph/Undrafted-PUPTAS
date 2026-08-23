<?php

namespace App\Services;

use App\Models\User;
use App\Models\Program;
use App\Models\Application;
use App\Repositories\Contracts\ApplicationRepositoryInterface;
use App\Repositories\Contracts\ApplicationProcessRepositoryInterface;
use App\Repositories\Contracts\ProgramRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    public function __construct(
        ApplicationService $applicationService,
        UserService $userService,
        protected ApplicationRepositoryInterface $applicationRepository,
        protected ApplicationProcessRepositoryInterface $applicationProcessRepository,
        protected ProgramRepositoryInterface $programRepository,
    ) {
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
     * Get per-stage summary counts for the admin dashboard pipeline cards.
     *
     * @return array
     */
    public function getStageSummary(): array
    {
        return $this->applicationService->getStageSummary();
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
            'programs' => $this->programRepository->allWithApplicationsCount(),
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

        $cacheKey = 'dashboard_chart_data_v3_' . ($startDateParam ?: 'default') . '_' . ($endDateParam ?: 'default');
        $lockKey = $cacheKey . '_lock';
        
        try {
            $cacheInstance = \Illuminate\Support\Facades\Cache::tags(['dashboard', 'applications']);
        } catch (\BadMethodCallException $e) {
            $cacheInstance = \Illuminate\Support\Facades\Cache::store();
        } catch (\Throwable $e) {
            $cacheInstance = null;
        }

        $generateData = function () use ($now, $startDateParam, $endDateParam) {
            Log::info('Dashboard dates:', ['start' => $startDateParam, 'end' => $endDateParam]);

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

            $applications = $this->applicationRepository->chartDataRaw($startDate, $endDate);

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
                $accepted[]  = $applications->where('date', $date)->where('status', 'accepted')->sum('count');
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
        };

        try {
            if ($cacheInstance) {
                if ($cachedData = $cacheInstance->get($cacheKey)) {
                    return $cachedData;
                }

                return \Illuminate\Support\Facades\Cache::lock($lockKey, 10)->block(5, function () use ($cacheInstance, $cacheKey, $generateData) {
                    return $cacheInstance->remember($cacheKey, 600, $generateData);
                });
            }
        } catch (\Throwable $e) {
            Log::warning('Dashboard cache failed, bypassing: ' . $e->getMessage());
        }

        // Fallback: Generate without cache if Redis is down or lock timed out
        return $generateData();
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
        return $this->userService->getApplicantsByStage($stage);
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
        $user = Auth::user();
        $programIds = $user->programs()->pluck('programs.id')->toArray();

        // Admin bypass: Admins and SuperAdmins can evaluate all programs
        if ($user->role_id == 2 || $user->role_id == 7 || $user->role_id == 8) {
            $programIds = $this->programRepository->allIds();
        }

        // If the evaluator has no assigned programs, pendingUsers is empty.
        // Evaluators must be explicitly assigned to programs to see applicants.
        $pendingUsers = empty($programIds)
            ? collect()
            : $this->userService->getApplicantsByStage($stage, $programIds);

        // Count applicants currently in queue for this stage (in_progress)
        $inProgress = $this->applicationProcessRepository->countDistinctApplications($stage, 'in_progress');

        // Count applicants already processed (completed) at this stage
        $processed = $this->applicationProcessRepository->countDistinctApplications($stage, 'completed');

        return [
            'pendingUsers' => $pendingUsers,
            'summary'      => [
                'in_progress' => $inProgress,
                'processed'   => $processed,
            ],
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

        // Count applicants currently in queue for interview stage
        $inProgress = $this->applicationProcessRepository->countDistinctApplications('interviewer', 'in_progress');

        // Count applicants already processed (completed) at interview stage
        $processed = $this->applicationProcessRepository->countDistinctApplications('interviewer', 'completed');

        return [
            'pendingUsers' => $pendingUsers,
            'summary'      => [
                'in_progress' => $inProgress,
                'processed'   => $processed,
            ],
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
        $programs = $this->programRepository->allWithApplicationsCount()
            ->map(function ($program) {
                return [
                    'id' => $program->id,
                    'code' => $program->code,
                    'name' => $program->name,
                    'slots' => $program->slots,
                    'applications_count' => $program->applications_count,
                ];
            });

        // Applicants currently cleared for enrollment (in queue for records)
        $inProgress = $this->applicationRepository->countClearedForEnrollment();

        // Applicants already officially enrolled (processed by records)
        $processed = $this->applicationRepository->countOfficiallyEnrolled();

        return [
            'allUsers' => $this->userService->getApplicantsForRecordStaff(),
            'programs' => $programs,
            'summary'  => [
                'in_progress' => $inProgress,
                'processed'   => $processed,
            ],
        ];
    }
}
