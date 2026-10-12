<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurgeOldAuditLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:purge 
                            {--days=365 : Number of days to retain audit logs}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge audit logs older than the specified retention period';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $retentionDays = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info('🗑️  Audit Log Purge Tool');
        $this->info('============================');
        $this->newLine();

        if ($retentionDays < 90) {
            $this->error('⚠️  Retention period must be at least 90 days for compliance reasons.');
            return Command::FAILURE;
        }

        $cutoffDate = now()->subDays($retentionDays);

        $this->info("📅 Retention Period: {$retentionDays} days");
        $this->info("🔪 Cutoff Date: {$cutoffDate->format('Y-m-d H:i:s')}");
        $this->info("🏃 Mode: " . ($dryRun ? 'DRY RUN (no changes will be made)' : 'LIVE'));
        $this->newLine();

        // Count logs to be deleted
        $totalCount = AuditLog::where('created_at', '<', $cutoffDate)->count();

        if ($totalCount === 0) {
            $this->info('✅ No audit logs found older than the retention period.');
            return Command::SUCCESS;
        }

        // Show breakdown by category
        $this->info("📊 Logs to be deleted: {$totalCount}");
        $this->newLine();

        $categoryCounts = DB::table('audit_logs')
            ->select('log_category', DB::raw('count(*) as count'))
            ->where('created_at', '<', $cutoffDate)
            ->groupBy('log_category')
            ->get();

        $this->table(
            ['Category', 'Count'],
            $categoryCounts->map(fn($row) => [
                $row->log_category ?? 'null',
                number_format($row->count),
            ])
        );

        $this->newLine();

        // Confirm deletion in live mode
        if (!$dryRun) {
            if (!$this->confirm("⚠️  Are you sure you want to delete {$totalCount} audit log(s)?", false)) {
                $this->warn('❌ Purge cancelled by user.');
                return Command::SUCCESS;
            }
        }

        if ($dryRun) {
            $this->info('🔍 DRY RUN: Would delete ' . number_format($totalCount) . ' audit log(s).');
            $this->info('💡 Run without --dry-run to actually delete the logs.');
            return Command::SUCCESS;
        }

        // Perform deletion in chunks to avoid memory issues
        $this->info('🗑️  Deleting old audit logs...');
        $progressBar = $this->output->createProgressBar($totalCount);

        $deleted = 0;
        $chunkSize = 1000;

        try {
            DB::transaction(function () use ($cutoffDate, &$deleted, $progressBar, $chunkSize) {
                while (true) {
                    $chunk = AuditLog::where('created_at', '<', $cutoffDate)
                        ->limit($chunkSize)
                        ->delete();

                    if ($chunk === 0) {
                        break;
                    }

                    $deleted += $chunk;
                    $progressBar->advance($chunk);

                    // Small delay to reduce DB load
                    usleep(100000); // 0.1 second
                }
            });

            $progressBar->finish();
            $this->newLine(2);

            Log::info('Audit log purge completed', [
                'deleted_count' => $deleted,
                'retention_days' => $retentionDays,
                'cutoff_date' => $cutoffDate->toDateTimeString(),
            ]);

            $this->info("✅ Successfully deleted " . number_format($deleted) . " audit log(s).");
            
            // Show remaining count
            $remaining = AuditLog::count();
            $this->info("📊 Remaining audit logs: " . number_format($remaining));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error during purge: ' . $e->getMessage());
            
            Log::error('Audit log purge failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
