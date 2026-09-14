<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EmailLog;
use App\Models\BulkEmailOperation;
use App\Models\SarGeneration;
use App\Models\GvsGeneration;
use App\Models\F137Generation;
use App\Models\User;
use App\Models\ApplicantProfile;
use App\Models\UserFile;
use App\Models\Grade;
use App\Models\Application;
use App\Models\ApplicationProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DataRetentionService
{
    /**
     * Get retention summary without modifying any data.
     */
    public function getRetentionSummary(?int $overrideDays = null): array
    {
        return [
            'soft_deleted_users' => $this->purgeSoftDeletedUsers(dryRun: true, days: $overrideDays),
            'audit_logs'         => $this->purgeAuditLogs(dryRun: true, days: $overrideDays),
            'email_logs'         => $this->purgeEmailLogs(dryRun: true, days: $overrideDays),
            'generated_docs'     => $this->purgeGeneratedDocuments(dryRun: true, days: $overrideDays),
            'orphaned_files'     => $this->purgeOrphanedFiles(dryRun: true, days: $overrideDays),
        ];
    }

    /**
     * Purge all expired data categories according to retention policy.
     */
    public function purgeAll(bool $dryRun = false, ?int $overrideDays = null, ?int $performedBy = null): array
    {
        $results = [
            'soft_deleted_users' => $this->purgeSoftDeletedUsers($dryRun, $overrideDays),
            'audit_logs'         => $this->purgeAuditLogs($dryRun, $overrideDays),
            'email_logs'         => $this->purgeEmailLogs($dryRun, $overrideDays),
            'generated_docs'     => $this->purgeGeneratedDocuments($dryRun, $overrideDays),
            'orphaned_files'     => $this->purgeOrphanedFiles($dryRun, $overrideDays),
        ];

        if (!$dryRun) {
            $this->recordDisposalAuditLog($results, $performedBy);
        }

        return $results;
    }

    /**
     * Purge soft-deleted users and associated applicant profiles whose retention hold has expired.
     */
    public function purgeSoftDeletedUsers(bool $dryRun = false, ?int $days = null): array
    {
        $retentionDays = $days ?? config('data_retention.periods.soft_deleted_users_days', 365);
        $cutoffDate = now()->subDays($retentionDays);

        $query = User::onlyTrashed()->where('deleted_at', '<=', $cutoffDate);
        $count = $query->count();
        $freedBytes = 0;

        if (!$dryRun && $count > 0) {
            $users = $query->get();
            foreach ($users as $user) {
                // Delete user files and physical uploads
                $userFiles = UserFile::withTrashed()->where('user_id', (string) $user->id)->get();
                foreach ($userFiles as $file) {
                    if ($file->file_path && Storage::disk('public')->exists($file->file_path)) {
                        $freedBytes += (int) Storage::disk('public')->size($file->file_path);
                        Storage::disk('public')->delete($file->file_path);
                    }
                    $file->forceDelete();
                }

                // Delete applications and processes
                $applications = Application::withTrashed()->where('user_id', (string) $user->id)->get();
                foreach ($applications as $app) {
                    ApplicationProcess::where('application_id', $app->id)->delete();
                    $app->forceDelete();
                }

                // Delete grades and applicant profile
                Grade::where('user_id', (string) $user->id)->delete();
                ApplicantProfile::withTrashed()
                    ->where('user_id', (string) $user->id)
                    ->forceDelete();

                $user->forceDelete();
            }
        }

        return [
            'category'       => 'Soft-Deleted / Deactivated Users (PII)',
            'threshold_days' => $retentionDays,
            'cutoff_date'    => $cutoffDate->toDateTimeString(),
            'records_purged' => $count,
            'bytes_freed'    => $freedBytes,
            'dry_run'        => $dryRun,
        ];
    }

    /**
     * Purge expired system audit logs.
     */
    public function purgeAuditLogs(bool $dryRun = false, ?int $days = null): array
    {
        $retentionDays = $days ?? config('data_retention.periods.audit_logs_days', 180);
        $cutoffDate = now()->subDays($retentionDays);

        $query = AuditLog::where('created_at', '<=', $cutoffDate);
        $count = $query->count();

        if (!$dryRun && $count > 0) {
            $query->delete();
        }

        return [
            'category'       => 'Audit Logs',
            'threshold_days' => $retentionDays,
            'cutoff_date'    => $cutoffDate->toDateTimeString(),
            'records_purged' => $count,
            'bytes_freed'    => 0,
            'dry_run'        => $dryRun,
        ];
    }

    /**
     * Purge expired email delivery logs and bulk operations.
     */
    public function purgeEmailLogs(bool $dryRun = false, ?int $days = null): array
    {
        $retentionDays = $days ?? config('data_retention.periods.email_logs_days', 90);
        $cutoffDate = now()->subDays($retentionDays);

        $logQuery = EmailLog::where('created_at', '<=', $cutoffDate);
        $count = $logQuery->count();

        if (!$dryRun && $count > 0) {
            $logQuery->delete();
            BulkEmailOperation::where('created_at', '<=', $cutoffDate)->delete();
        }

        return [
            'category'       => 'Email Delivery Logs',
            'threshold_days' => $retentionDays,
            'cutoff_date'    => $cutoffDate->toDateTimeString(),
            'records_purged' => $count,
            'bytes_freed'    => 0,
            'dry_run'        => $dryRun,
        ];
    }

    /**
     * Purge generated PDF credential slips (SAR, GVS, F137 slips).
     */
    public function purgeGeneratedDocuments(bool $dryRun = false, ?int $days = null): array
    {
        $retentionDays = $days ?? config('data_retention.periods.generated_documents_days', 180);
        $cutoffDate = now()->subDays($retentionDays);
        $totalPurged = 0;
        $freedBytes = 0;

        // SAR generations
        $sarQuery = SarGeneration::where('created_at', '<=', $cutoffDate);
        $totalPurged += $sarQuery->count();
        if (!$dryRun) {
            foreach ($sarQuery->get() as $sar) {
                if ($sar->file_path && Storage::disk('local')->exists($sar->file_path)) {
                    $freedBytes += (int) Storage::disk('local')->size($sar->file_path);
                    Storage::disk('local')->delete($sar->file_path);
                }
                $sar->delete();
            }
        }

        // GVS generations
        $gvsQuery = GvsGeneration::where('created_at', '<=', $cutoffDate);
        $totalPurged += $gvsQuery->count();
        if (!$dryRun) {
            foreach ($gvsQuery->get() as $gvs) {
                if ($gvs->file_path && Storage::disk('local')->exists($gvs->file_path)) {
                    $freedBytes += (int) Storage::disk('local')->size($gvs->file_path);
                    Storage::disk('local')->delete($gvs->file_path);
                }
                $gvs->delete();
            }
        }

        // F137 generations
        $f137Query = F137Generation::where('created_at', '<=', $cutoffDate);
        $totalPurged += $f137Query->count();
        if (!$dryRun) {
            foreach ($f137Query->get() as $f137) {
                if ($f137->file_path && Storage::disk('local')->exists($f137->file_path)) {
                    $freedBytes += (int) Storage::disk('local')->size($f137->file_path);
                    Storage::disk('local')->delete($f137->file_path);
                }
                $f137->delete();
            }
        }

        return [
            'category'       => 'Generated Credential Documents (PDFs)',
            'threshold_days' => $retentionDays,
            'cutoff_date'    => $cutoffDate->toDateTimeString(),
            'records_purged' => $totalPurged,
            'bytes_freed'    => $freedBytes,
            'dry_run'        => $dryRun,
        ];
    }

    /**
     * Purge orphaned or unlinked file uploads older than threshold.
     */
    public function purgeOrphanedFiles(bool $dryRun = false, ?int $days = null): array
    {
        $retentionDays = $days ?? config('data_retention.periods.orphaned_files_days', 30);
        $cutoffDate = now()->subDays($retentionDays);

        $query = UserFile::whereNull('application_id')
            ->whereNull('user_id')
            ->where('created_at', '<=', $cutoffDate);

        $count = $query->count();
        $freedBytes = 0;

        if (!$dryRun && $count > 0) {
            foreach ($query->get() as $file) {
                if ($file->file_path && Storage::disk('public')->exists($file->file_path)) {
                    $freedBytes += (int) Storage::disk('public')->size($file->file_path);
                    Storage::disk('public')->delete($file->file_path);
                }
                $file->forceDelete();
            }
        }

        return [
            'category'       => 'Orphaned / Unlinked Files',
            'threshold_days' => $retentionDays,
            'cutoff_date'    => $cutoffDate->toDateTimeString(),
            'records_purged' => $count,
            'bytes_freed'    => $freedBytes,
            'dry_run'        => $dryRun,
        ];
    }

    /**
     * Record an immutable audit log entry documenting data disposal execution.
     */
    protected function recordDisposalAuditLog(array $results, ?int $performedBy = null): void
    {
        $totalRecords = array_sum(array_column($results, 'records_purged'));
        $totalBytes = array_sum(array_column($results, 'bytes_freed'));

        AuditLog::create([
            'user_id'       => $performedBy,
            'username'      => auth()->user()?->email ?? 'SYSTEM_SCHEDULER',
            'user_role'     => auth()->user()?->role?->name ?? 'SYSTEM',
            'log_type'      => AuditLog::TYPE_SYSTEM,
            'log_category'  => AuditLog::CATEGORY_SYSTEM_OPERATION,
            'action_type'   => AuditLog::ACTION_DELETE,
            'module_name'   => 'Data Retention & Disposal',
            'description'   => "Executed automated data retention purge. Total records removed: {$totalRecords}. Storage freed: {$totalBytes} bytes.",
            'new_values'    => $results,
        ]);
    }
}
