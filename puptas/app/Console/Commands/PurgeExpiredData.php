<?php

namespace App\Console\Commands;

use App\Services\DataRetentionService;
use Illuminate\Console\Command;

class PurgeExpiredData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data-retention:purge
                            {--type=all : The data category to purge (all, users, logs, email, documents, orphaned-files)}
                            {--dry-run : Inspect and calculate purge statistics without removing records}
                            {--force : Bypass confirmation prompts (recommended for automated schedulers)}
                            {--days= : Override default retention lifespan threshold in days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automated disposal of expired personal data, documents, and system logs under Data Retention Policy';

    /**
     * Execute the console command.
     */
    public function handle(DataRetentionService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');
        $type   = strtolower((string) $this->option('type'));
        $days   = $this->option('days') ? (int) $this->option('days') : null;

        $this->info('===============================================================');
        $this->info(' PUPTAS Data Retention & Automated Disposal Engine (RA 10173) ');
        $this->info('===============================================================');

        if ($dryRun) {
            $this->warn('[DRY-RUN MODE ENABLED] No database rows or storage files will be modified.');
        }

        if (!$dryRun && !$force && !$this->confirm('Proceed with permanent data disposal according to policy?', true)) {
            $this->info('Operation cancelled by user.');
            return self::SUCCESS;
        }

        $results = [];

        switch ($type) {
            case 'users':
                $results[] = $service->purgeSoftDeletedUsers($dryRun, $days);
                break;

            case 'logs':
                $results[] = $service->purgeAuditLogs($dryRun, $days);
                break;

            case 'email':
                $results[] = $service->purgeEmailLogs($dryRun, $days);
                break;

            case 'documents':
                $results[] = $service->purgeGeneratedDocuments($dryRun, $days);
                break;

            case 'orphaned-files':
                $results[] = $service->purgeOrphanedFiles($dryRun, $days);
                break;

            case 'all':
            default:
                $results = array_values($service->purgeAll($dryRun, $days));
                break;
        }

        $tableRows = [];
        $totalPurged = 0;
        $totalBytesFreed = 0;

        foreach ($results as $res) {
            $tableRows[] = [
                $res['category'],
                $res['threshold_days'] . ' days',
                $res['cutoff_date'],
                $res['records_purged'],
                $this->formatBytes($res['bytes_freed']),
            ];
            $totalPurged += $res['records_purged'];
            $totalBytesFreed += $res['bytes_freed'];
        }

        $this->newLine();
        $this->table(
            ['Data Category', 'Retention Threshold', 'Cutoff Timestamp', 'Records Purged', 'Storage Reclaimed'],
            $tableRows
        );

        $this->newLine();
        if ($dryRun) {
            $this->info("Summary (Preview): {$totalPurged} records eligible for purge. Potential storage reclaimed: {$this->formatBytes($totalBytesFreed)}.");
        } else {
            $this->info("Disposal Complete: {$totalPurged} records permanently purged. Storage reclaimed: {$this->formatBytes($totalBytesFreed)}.");
            $this->info("Immutable compliance record created in system audit logs.");
        }

        return self::SUCCESS;
    }

    /**
     * Human-readable byte formatting.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
