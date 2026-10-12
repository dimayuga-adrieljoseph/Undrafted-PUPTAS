<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Enums\RoleId;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Database\QueryException;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class IdpSyncMonitorController extends Controller
{
    /**
     * @var AuditLogService
     */
    protected $auditLogService;

    /**
     * Constructor
     */
    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Display the IDP sync monitoring dashboard
     *
     * @return Response
     */
    public function index(): Response
    {
        // Ensure user is authenticated and is super admin
        if (!auth()->check() || auth()->user()->role_id !== RoleId::SuperAdmin->value) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('SuperAdmin/IdpSyncMonitor', [
            'pageTitle' => 'IDP Sync Monitor',
        ]);
    }

    /**
     * Get IDP sync metrics and statistics
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getMetrics(Request $request): JsonResponse
    {
        try {
            // Ensure user is authenticated and is super admin
            if (!auth()->check() || auth()->user()->role_id !== RoleId::SuperAdmin->value) {
                // Log unauthorized access attempt
                Log::warning('Unauthorized IDP sync metrics access attempt', [
                    'ip' => $request->ip(),
                    'user_id' => auth()->id(),
                ]);
                
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $timeRange = $request->input('range', '24h'); // 24h, 7d, 30d, all

            // Calculate date range
            $startDate = $this->getStartDate($timeRange);

            // Get statistics
            $stats = $this->getStatistics($startDate);
            
            // Get recent activity
            $recentActivity = $this->getRecentActivity(20);
            
            // Get failure breakdown
            $failureBreakdown = $this->getFailureBreakdown($startDate);
            
            // Get queue status
            $queueStatus = $this->getQueueStatus();

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'recent_activity' => $recentActivity,
                    'failure_breakdown' => $failureBreakdown,
                    'queue_status' => $queueStatus,
                    'time_range' => $timeRange,
                ],
            ]);
        } catch (QueryException $e) {
            Log::error('Database error fetching IDP sync metrics', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'user_id' => auth()->id(),
            ]);

            // Log database error in audit
            $this->auditLogService->logActivity(
                'FAILED',
                'IDP Sync Monitor',
                'Database error while fetching sync metrics',
                auth()->user(),
                \App\Models\AuditLog::CATEGORY_SYSTEM_CHANGE
            );

            return response()->json([
                'success' => false,
                'message' => 'Database error occurred. Please try again.',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Failed to fetch IDP sync metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            // Log general error in audit
            $this->auditLogService->logActivity(
                'FAILED',
                'IDP Sync Monitor',
                'Error fetching sync metrics',
                auth()->user(),
                \App\Models\AuditLog::CATEGORY_SYSTEM_CHANGE
            );

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch metrics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistics for the specified time range
     *
     * @param Carbon|null $startDate
     * @return array
     */
    protected function getStatistics(?Carbon $startDate): array
    {
        $query = DB::table('audit_logs')
            ->where('module_name', 'IDP Account Type');

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        // Get counts by action
        $actionCounts = $query->select('action_type', DB::raw('count(*) as count'))
            ->groupBy('action_type')
            ->get()
            ->keyBy('action_type');

        $totalSuccessful = $actionCounts->get('UPDATE')?->count ?? 0;
        $totalFailed = $actionCounts->get('FAILED')?->count ?? 0;
        $totalAttempted = $actionCounts->get('ATTEMPTED')?->count ?? 0;
        $total = $totalSuccessful + $totalFailed + $totalAttempted;

        $successRate = $total > 0 ? round(($totalSuccessful / $total) * 100, 2) : 0;

        // Get hourly activity for chart (last 24 hours)
        $hourlyActivity = DB::table('audit_logs')
            ->where('module_name', 'IDP Account Type')
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour"),
                DB::raw('SUM(CASE WHEN action_type = "UPDATE" THEN 1 ELSE 0 END) as successful'),
                DB::raw('SUM(CASE WHEN action_type = "FAILED" THEN 1 ELSE 0 END) as failed'),
                DB::raw('SUM(CASE WHEN action_type = "ATTEMPTED" THEN 1 ELSE 0 END) as attempted')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // Count enrolled students with IDP IDs
        $totalEnrolledWithIdp = DB::table('applications')
            ->join('users', 'applications.user_id', '=', 'users.id')
            ->where('applications.enrollment_status', 'officially_enrolled')
            ->whereNotNull('users.idp_user_id')
            ->whereNull('applications.deleted_at')
            ->count();

        // Get count of students who have successful sync audit logs
        // Get distinct user IDs from successful sync audit logs
        $syncedUserIds = DB::table('audit_logs')
            ->where('module_name', 'IDP Account Type')
            ->where('action_type', 'UPDATE')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->toArray();

        // Count how many of those synced users are in officially enrolled applications
        $syncedCount = 0;
        if (!empty($syncedUserIds)) {
            $syncedCount = DB::table('applications')
                ->join('users', 'applications.user_id', '=', 'users.id')
                ->where('applications.enrollment_status', 'officially_enrolled')
                ->whereNotNull('users.idp_user_id')
                ->whereNull('applications.deleted_at')
                ->whereIn('applications.user_id', $syncedUserIds)
                ->distinct()
                ->count('applications.id');
        }

        return [
            'total_syncs' => $total,
            'successful' => $totalSuccessful,
            'failed' => $totalFailed,
            'mfa_required' => $totalAttempted,
            'success_rate' => $successRate,
            'synced_students' => $syncedCount,
            'unsynced_students' => max(0, $totalEnrolledWithIdp - $syncedCount),
            'hourly_activity' => $hourlyActivity->toArray(),
        ];
    }

    /**
     * Get recent IDP sync activity
     *
     * @param int $limit
     * @return array
     */
    protected function getRecentActivity(int $limit = 20): array
    {
        $activities = DB::table('audit_logs')
            ->where('module_name', 'IDP Account Type')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $activities->map(function ($activity) {
            return [
                'id' => $activity->id,
                'action' => $activity->action_type,
                'details' => $activity->description,
                'created_at' => Carbon::parse($activity->created_at)->format('Y-m-d H:i:s'),
                'created_at_human' => Carbon::parse($activity->created_at)->diffForHumans(),
                'category' => $activity->log_category ?? null,
            ];
        })->toArray();
    }

    /**
     * Get failure breakdown by error type
     *
     * @param Carbon|null $startDate
     * @return array
     */
    protected function getFailureBreakdown(?Carbon $startDate): array
    {
        $query = DB::table('audit_logs')
            ->where('module_name', 'IDP Account Type')
            ->whereIn('action_type', ['FAILED', 'ATTEMPTED']);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        $failures = $query->select('description')
            ->get();

        $breakdown = [
            'MFA_REQUIRED' => 0,
            'UNAUTHORIZED' => 0,
            'USER_NOT_FOUND' => 0,
            'RATE_LIMIT_EXCEEDED' => 0,
            'IDP_ERROR' => 0,
            'EXCEPTION' => 0,
            'OTHER' => 0,
        ];

        foreach ($failures as $failure) {
            $details = $failure->description;
            
            if (str_contains($details, 'MFA')) {
                $breakdown['MFA_REQUIRED']++;
            } elseif (str_contains($details, 'UNAUTHORIZED')) {
                $breakdown['UNAUTHORIZED']++;
            } elseif (str_contains($details, 'USER_NOT_FOUND')) {
                $breakdown['USER_NOT_FOUND']++;
            } elseif (str_contains($details, 'RATE_LIMIT_EXCEEDED')) {
                $breakdown['RATE_LIMIT_EXCEEDED']++;
            } elseif (str_contains($details, 'IDP_ERROR')) {
                $breakdown['IDP_ERROR']++;
            } elseif (str_contains($details, 'EXCEPTION')) {
                $breakdown['EXCEPTION']++;
            } else {
                $breakdown['OTHER']++;
            }
        }

        return $breakdown;
    }

    /**
     * Get queue status
     *
     * @return array
     */
    protected function getQueueStatus(): array
    {
        try {
            $queueDriver = config('queue.default');
            
            // Get failed jobs count (only if failed_jobs table exists)
            $failedJobsCount = 0;
            try {
                if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                    $failedJobsCount = DB::table('failed_jobs')->count();
                }
            } catch (QueryException $e) {
                Log::debug('Could not query failed_jobs table', ['error' => $e->getMessage()]);
            }

            // Get pending jobs count (only works for redis/database drivers)
            $pendingJobs = null;
            if ($queueDriver !== 'sync') {
                try {
                    $pendingJobs = Queue::size('default');
                } catch (\Exception $e) {
                    Log::debug('Could not get queue size', ['error' => $e->getMessage()]);
                }
            }

            return [
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobsCount,
                'queue_connection' => $queueDriver,
                'is_running' => $this->isQueueRunning(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get queue status', ['error' => $e->getMessage()]);
            return [
                'pending_jobs' => null,
                'failed_jobs' => 0,
                'queue_connection' => config('queue.default'),
                'is_running' => false,
                'error' => 'Could not determine queue status',
            ];
        }
    }

    /**
     * Check if queue workers are running
     *
     * @return bool
     */
    protected function isQueueRunning(): bool
    {
        // This is a simple check - you may want to implement a more robust solution
        // For Railway, workers are managed externally
        return config('queue.default') === 'redis';
    }

    /**
     * Get start date based on time range
     *
     * @param string $range
     * @return Carbon|null
     */
    protected function getStartDate(string $range): ?Carbon
    {
        return match($range) {
            '24h' => Carbon::now()->subHours(24),
            '7d' => Carbon::now()->subDays(7),
            '30d' => Carbon::now()->subDays(30),
            'all' => Carbon::now()->subYear(1), // Limit to 1 year to prevent performance issues
            default => Carbon::now()->subHours(24),
        };
    }

    /**
     * Manually trigger sync for enrolled students
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function triggerSync(Request $request): JsonResponse
    {
        try {
            // Ensure user is authenticated and is super admin
            if (!auth()->check() || auth()->user()->role_id !== RoleId::SuperAdmin->value) {
                // Log unauthorized attempt
                Log::warning('Unauthorized IDP sync trigger attempt', [
                    'ip' => $request->ip(),
                    'user_id' => auth()->id(),
                ]);
                
                $this->auditLogService->logActivity(
                    'ATTEMPTED',
                    'IDP Sync Monitor',
                    'Unauthorized attempt to trigger IDP sync',
                    auth()->user(),
                    \App\Models\AuditLog::CATEGORY_AUTHENTICATION
                );
                
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $request->validate([
                'mfa_code' => 'nullable|string|max:8',
            ]);

            $mfaProvided = !empty($request->input('mfa_code'));
            $maskedMfa = $mfaProvided ? '[MFA_PROVIDED]' : null;

            // Log the sync trigger attempt with masked MFA
            $this->auditLogService->logActivity(
                'COMMAND',
                'IDP Sync Monitor',
                "Super admin triggered manual IDP sync" . ($mfaProvided ? ' with MFA code' : ''),
                auth()->user(),
                \App\Models\AuditLog::CATEGORY_SYSTEM_CHANGE
            );

            Log::info('Super admin triggering IDP sync', [
                'user_id' => auth()->id(),
                'username' => auth()->user()->username,
                'mfa_provided' => $mfaProvided,
                'ip' => $request->ip(),
            ]);

            // Call the artisan command programmatically
            $exitCode = \Illuminate\Support\Facades\Artisan::call('idp:sync-enrolled-students', [
                '--force' => true,
                '--mfa-code' => $request->input('mfa_code'),
            ]);

            $output = \Illuminate\Support\Facades\Artisan::output();

            // Check for specific error conditions
            $isTokenMissing = str_contains($output, 'Admin access token not found');
            
            if ($exitCode === 0) {
                Log::info('IDP sync triggered successfully', [
                    'user_id' => auth()->id(),
                ]);
                
                // Log successful trigger
                $this->auditLogService->logActivity(
                    'UPDATE',
                    'IDP Sync Monitor',
                    'Successfully triggered IDP sync for enrolled students',
                    auth()->user(),
                    \App\Models\AuditLog::CATEGORY_SYSTEM_CHANGE
                );
            } else {
                Log::warning('IDP sync completed with errors', [
                    'user_id' => auth()->id(),
                    'exit_code' => $exitCode,
                    'error_type' => $isTokenMissing ? 'TOKEN_MISSING' : 'UNKNOWN',
                ]);
                
                // Log failed trigger
                $failureReason = $isTokenMissing 
                    ? 'No IDP admin access token available' 
                    : 'Sync command returned errors';
                
                $this->auditLogService->logActivity(
                    'FAILED',
                    'IDP Sync Monitor',
                    "Failed to trigger IDP sync: {$failureReason}",
                    auth()->user(),
                    \App\Models\AuditLog::CATEGORY_SYSTEM_CHANGE
                );
            }

            // Provide specific error messages
            $message = match(true) {
                $exitCode === 0 => 'Sync jobs have been queued successfully',
                $isTokenMissing => 'Cannot sync: No IDP admin access token available. Please ensure an admin is logged in via IDP or set IDP_ADMIN_ACCESS_TOKEN in .env',
                default => 'Sync command completed with errors. Check logs for details.'
            };

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $message,
                'output' => $exitCode !== 0 ? $output : null,
            ]);
        } catch (QueryException $e) {
            Log::error('Database error triggering IDP sync', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            // Log database error
            $this->auditLogService->logActivity(
                'FAILED',
                'IDP Sync Monitor',
                'Database error while triggering IDP sync',
                auth()->user(),
                \App\Models\AuditLog::CATEGORY_SYSTEM_CHANGE
            );

            return response()->json([
                'success' => false,
                'message' => 'Database error occurred. Please try again.',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Failed to trigger IDP sync', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            // Log general error
            $this->auditLogService->logActivity(
                'FAILED',
                'IDP Sync Monitor',
                'Exception while triggering IDP sync: ' . $e->getMessage(),
                auth()->user(),
                \App\Models\AuditLog::CATEGORY_SYSTEM_CHANGE
            );

            return response()->json([
                'success' => false,
                'message' => 'Failed to trigger sync. Please contact support.',
            ], 500);
        }
    }

    /**
     * Retry failed jobs
     *
     * @return JsonResponse
     */
    public function retryFailedJobs(): JsonResponse
    {
        try {
            // Ensure user is authenticated and is super admin
            if (!auth()->check() || auth()->user()->role_id !== RoleId::SuperAdmin->value) {
                // Log unauthorized attempt
                Log::warning('Unauthorized retry failed jobs attempt', [
                    'ip' => request()->ip(),
                    'user_id' => auth()->id(),
                ]);
                
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Count failed jobs before retry
            $failedJobsCount = DB::table('failed_jobs')->count();

            Log::info('Super admin retrying failed jobs', [
                'user_id' => auth()->id(),
                'username' => auth()->user()->username,
                'failed_jobs_count' => $failedJobsCount,
            ]);

            if ($failedJobsCount === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No failed jobs found to retry.',
                    'count' => 0,
                ]);
            }

            // Retry all failed jobs
            \Illuminate\Support\Facades\Artisan::call('queue:retry', ['id' => ['all']]);

            $output = \Illuminate\Support\Facades\Artisan::output();

            Log::info('Failed jobs retry completed', [
                'user_id' => auth()->id(),
                'output' => $output,
            ]);

            // Log after operation completes
            try {
                $this->auditLogService->logActivity(
                    'UPDATE',
                    'IDP Sync Monitor',
                    "Successfully queued {$failedJobsCount} failed job(s) for retry",
                    auth()->user(),
                    \App\Models\AuditLog::CATEGORY_SYSTEM_CHANGE
                );
            } catch (\Exception $auditEx) {
                Log::error('Failed to log retry success to audit', [
                    'error' => $auditEx->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Failed jobs have been queued for retry',
                'count' => $failedJobsCount,
            ]);
        } catch (QueryException $e) {
            Log::error('Database error retrying failed jobs', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Database error occurred: ' . $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Failed to retry failed jobs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retry jobs: ' . $e->getMessage(),
            ], 500);
        }
    }
}
