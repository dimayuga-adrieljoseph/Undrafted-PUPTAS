<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AuditLog;
use App\Models\Application;
use App\Models\User;
use Carbon\Carbon;

class MonitorIdpSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'idp:monitor 
                            {--period=24 : Period in hours to monitor (default: 24)}
                            {--alert : Show only alerts/issues}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor IDP enrollment sync health and display metrics';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $period = (int) $this->option('period');
        $alertOnly = $this->option('alert');
        $since = Carbon::now()->subHours($period);

        $this->info("📊 IDP Enrollment Sync Monitor");
        $this->info("================================");
        $this->info("Period: Last {$period} hours");
        $this->info("Since: {$since->format('Y-m-d H:i:s')}");
        $this->newLine();

        // Get metrics
        $metrics = $this->getMetrics($since);
        $alerts = $this->checkAlerts($metrics, $period);

        if ($alertOnly) {
            if (empty($alerts)) {
                $this->info('✅ No alerts - System healthy!');
                return Command::SUCCESS;
            }
            
            $this->displayAlerts($alerts);
            return Command::FAILURE;
        }

        // Display full report
        $this->displayOverview($metrics);
        $this->newLine();
        $this->displaySuccessRate($metrics);
        $this->newLine();
        $this->displayRecentActivity($since);
        $this->newLine();
        
        if (!empty($alerts)) {
            $this->displayAlerts($alerts);
            return Command::FAILURE;
        }

        $this->info('✅ All systems healthy!');
        return Command::SUCCESS;
    }

    /**
     * Get IDP sync metrics
     */
    protected function getMetrics(Carbon $since): array
    {
        $logs = AuditLog::where('subject', 'IDP Account Type')
            ->where('created_at', '>=', $since)
            ->get();

        return [
            'total' => $logs->count(),
            'successful' => $logs->where('action', 'UPDATE')->count(),
            'failed' => $logs->where('action', 'FAILED')->count(),
            'mfa_required' => $logs->where('action', 'ATTEMPTED')->count(),
            'last_operation' => $logs->max('created_at'),
            'first_operation' => $logs->min('created_at'),
        ];
    }

    /**
     * Check for alerts
     */
    protected function checkAlerts(array $metrics, int $period): array
    {
        $alerts = [];

        // Alert 1: High failure rate
        if ($metrics['total'] > 0) {
            $failureRate = ($metrics['failed'] / $metrics['total']) * 100;
            if ($failureRate > 10) {
                $alerts[] = [
                    'level' => 'HIGH',
                    'type' => 'High Failure Rate',
                    'message' => sprintf('%.1f%% of operations failed (%d out of %d)', $failureRate, $metrics['failed'], $metrics['total']),
                    'action' => 'Check IDP service status and admin token validity',
                ];
            }
        }

        // Alert 2: No activity during business hours
        $now = Carbon::now();
        if ($now->isWeekday() && $now->hour >= 8 && $now->hour <= 17 && $period >= 2) {
            if ($metrics['total'] === 0) {
                $alerts[] = [
                    'level' => 'MEDIUM',
                    'type' => 'No Activity',
                    'message' => 'No IDP sync operations in last ' . $period . ' hours during business hours',
                    'action' => 'Verify if enrollments are happening. May be normal if no students enrolled.',
                ];
            }
        }

        // Alert 3: Many MFA required
        if ($metrics['mfa_required'] > 5 && $period <= 24) {
            $alerts[] = [
                'level' => 'LOW',
                'type' => 'MFA Required',
                'message' => $metrics['mfa_required'] . ' operations require MFA verification',
                'action' => 'Run sync command with MFA code',
            ];
        }

        // Alert 4: Check for unsynced students
        $unsynced = $this->getUnsyncedStudentsCount();
        if ($unsynced > 10) {
            $alerts[] = [
                'level' => 'MEDIUM',
                'type' => 'Unsynced Students',
                'message' => $unsynced . ' enrolled students have not been synced to IDP',
                'action' => 'Run: php artisan idp:sync-enrolled-students',
            ];
        }

        return $alerts;
    }

    /**
     * Display overview metrics
     */
    protected function displayOverview(array $metrics): void
    {
        $this->info('📈 Overview');
        $this->info('----------');

        $rows = [
            ['Total Operations', $metrics['total']],
            ['Successful', '✅ ' . $metrics['successful']],
            ['Failed', $metrics['failed'] > 0 ? '❌ ' . $metrics['failed'] : '✅ 0'],
            ['MFA Required', $metrics['mfa_required'] > 0 ? '🔐 ' . $metrics['mfa_required'] : '✅ 0'],
        ];

        if ($metrics['last_operation']) {
            $rows[] = ['Last Operation', Carbon::parse($metrics['last_operation'])->diffForHumans()];
        }

        $this->table(['Metric', 'Value'], $rows);
    }

    /**
     * Display success rate
     */
    protected function displaySuccessRate(array $metrics): void
    {
        if ($metrics['total'] === 0) {
            return;
        }

        $successRate = ($metrics['successful'] / $metrics['total']) * 100;
        $failureRate = ($metrics['failed'] / $metrics['total']) * 100;

        $this->info('📊 Success Rate');
        $this->info('---------------');

        $emoji = $successRate >= 95 ? '✅' : ($successRate >= 85 ? '⚠️' : '❌');
        
        $this->line(sprintf(
            '%s Success Rate: %.1f%% (%d/%d operations)',
            $emoji,
            $successRate,
            $metrics['successful'],
            $metrics['total']
        ));

        if ($failureRate > 0) {
            $this->line(sprintf(
                '❌ Failure Rate: %.1f%% (%d/%d operations)',
                $failureRate,
                $metrics['failed'],
                $metrics['total']
            ));
        }
    }

    /**
     * Display recent activity
     */
    protected function displayRecentActivity(Carbon $since): void
    {
        $recent = AuditLog::where('subject', 'IDP Account Type')
            ->where('created_at', '>=', $since)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($recent->isEmpty()) {
            return;
        }

        $this->info('🕒 Recent Activity (Last 10)');
        $this->info('---------------------------');

        $rows = $recent->map(function ($log) {
            $emoji = match($log->action) {
                'UPDATE' => '✅',
                'FAILED' => '❌',
                'ATTEMPTED' => '🔐',
                'COMMAND' => '⚙️',
                default => '📝',
            };

            return [
                $emoji . ' ' . $log->action,
                Carbon::parse($log->created_at)->format('Y-m-d H:i:s'),
                substr($log->details, 0, 60) . (strlen($log->details) > 60 ? '...' : ''),
            ];
        });

        $this->table(['Action', 'Time', 'Details'], $rows->toArray());
    }

    /**
     * Display alerts
     */
    protected function displayAlerts(array $alerts): void
    {
        $this->newLine();
        $this->error('⚠️  ALERTS DETECTED');
        $this->error('==================');
        $this->newLine();

        foreach ($alerts as $alert) {
            $levelEmoji = match($alert['level']) {
                'HIGH' => '🔴',
                'MEDIUM' => '🟡',
                'LOW' => '🟢',
                default => '⚪',
            };

            $this->line("{$levelEmoji} {$alert['level']}: {$alert['type']}");
            $this->line("   Message: {$alert['message']}");
            $this->line("   Action:  {$alert['action']}");
            $this->newLine();
        }
    }

    /**
     * Get count of unsynced students
     */
    protected function getUnsyncedStudentsCount(): int
    {
        return Application::where('enrollment_status', 'officially_enrolled')
            ->whereNull('deleted_at')
            ->whereHas('user', function ($q) {
                $q->whereNotNull('idp_user_id');
            })
            ->whereDoesntHave('user', function ($q) {
                $q->whereExists(function ($query) {
                    $query->select(\DB::raw(1))
                        ->from('audit_logs')
                        ->whereColumn('audit_logs.details', 'like', \DB::raw("CONCAT('%user ', users.id, '%')"))
                        ->where('audit_logs.subject', 'IDP Account Type')
                        ->where('audit_logs.action', 'UPDATE');
                });
            })
            ->count();
    }
}
