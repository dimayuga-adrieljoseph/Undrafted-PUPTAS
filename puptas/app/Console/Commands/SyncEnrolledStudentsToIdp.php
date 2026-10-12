<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Application;
use App\Models\User;
use App\Services\IdpService;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;

class SyncEnrolledStudentsToIdp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'idp:sync-enrolled-students 
                            {--dry-run : Run without actually updating IDP}
                            {--force : Skip confirmation prompt}
                            {--mfa-code= : MFA code if required}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync previously enrolled students to IDP (backfill for students enrolled before IDP integration)';

    protected IdpService $idpService;
    protected AuditLogService $auditLogService;

    public function __construct(IdpService $idpService, AuditLogService $auditLogService)
    {
        parent::__construct();
        $this->idpService = $idpService;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $mfaCode = $this->option('mfa-code');
        
        $this->info('🔍 IDP Enrollment Sync Tool');
        $this->info('================================');
        $this->newLine();

        // Get admin access token
        $accessToken = $this->idpService->getAdminAccessToken();
        
        if (empty($accessToken)) {
            $this->error('❌ Admin access token not found!');
            $this->error('');
            $this->error('Please ensure:');
            $this->error('1. An admin is logged in via IDP (token in session), OR');
            $this->error('2. IDP_ADMIN_ACCESS_TOKEN is set in .env file');
            $this->newLine();
            return Command::FAILURE;
        }

        $this->info('✅ Admin access token found');
        $this->newLine();

        // Find all officially enrolled students
        $applications = Application::where('enrollment_status', 'officially_enrolled')
            ->whereNull('deleted_at')
            ->with('userAccount')
            ->get();

        $total = $applications->count();
        
        if ($total === 0) {
            $this->info('ℹ️  No officially enrolled students found.');
            return Command::SUCCESS;
        }

        $this->info("📊 Found {$total} officially enrolled student(s)");
        $this->newLine();

        // Count students with and without IDP IDs (check userAccount on User model)
        $withIdpId = $applications->filter(fn($app) => !empty($app->userAccount?->idp_user_id))->count();
        $withoutIdpId = $total - $withIdpId;

        $this->table(
            ['Status', 'Count'],
            [
                ['With IDP ID', $withIdpId],
                ['Without IDP ID (will be skipped)', $withoutIdpId],
                ['Total', $total],
            ]
        );
        $this->newLine();

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual IDP updates will be made');
            $this->newLine();
        } else {
            // Log command execution start
            try {
                $this->auditLogService->logActivity(
                    'COMMAND',
                    'IDP Sync Command',
                    "Started IDP enrollment sync command for {$withIdpId} students",
                    null,
                    \App\Models\AuditLog::CATEGORY_SYSTEM_CHANGE
                );
            } catch (\Exception $e) {
                Log::warning('Failed to create audit log for command start', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Confirmation prompt (unless --force)
        if (!$this->option('force') && !$dryRun) {
            if (!$this->confirm("Do you want to proceed with updating {$withIdpId} student(s) in IDP?", true)) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
            $this->newLine();
        }

        // Process each application
        $this->info('🚀 Starting sync process...');
        $this->newLine();

        $progressBar = $this->output->createProgressBar($withIdpId);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $progressBar->setMessage('Initializing...');
        $progressBar->start();

        $results = [
            'success' => 0,
            'queued' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($applications as $application) {
            $user = $application->userAccount;

            // Skip users without IDP ID
            if (!$user || empty($user->idp_user_id)) {
                $results['skipped']++;
                continue;
            }

            $userId = (int) $user->id;
            $idpUserId = $user->idp_user_id;
            $userEmail = $user->email ?? 'N/A';

            $progressBar->setMessage("Processing: {$userEmail}");

            if ($dryRun) {
                $this->newLine();
                $this->info("  [DRY RUN] Would queue job for user: {$userEmail} (IDP ID: {$idpUserId})");
                $results['success']++;
                $progressBar->advance();
                continue;
            }

            try {
                // Dispatch the job to queue
                \App\Jobs\UpdateIdpAccountType::dispatch(
                    $userId,
                    $idpUserId,
                    $userEmail,
                    $accessToken,
                    $mfaCode,
                    null, // No performed_by for console commands
                    true // isFromSync = true
                );

                $results['queued']++;
                $results['success']++;

                Log::info('Queued IDP sync job for enrolled student', [
                    'user_id' => $userId,
                    'idp_user_id' => $idpUserId,
                    'email' => $userEmail,
                ]);
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'user_id' => $userId,
                    'email' => $userEmail,
                    'idp_user_id' => $idpUserId,
                    'error' => 'QUEUE_FAILED',
                    'message' => $e->getMessage(),
                ];

                Log::error('Failed to queue IDP sync job', [
                    'user_id' => $userId,
                    'idp_user_id' => $idpUserId,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();

            // Small delay to avoid overwhelming the queue
            usleep(50000); // 0.05 second
        }

        $progressBar->setMessage('Complete!');
        $progressBar->finish();
        $this->newLine(2);

        // Log command completion summary (if not dry run)
        if (!$dryRun) {
            try {
                $this->auditLogService->logActivity(
                    'COMMAND',
                    'IDP Sync Command',
                    "Queued {$results['queued']} IDP sync jobs for enrolled students ({$results['failed']} queue failures, {$results['skipped']} skipped)",
                    null,
                    \App\Models\AuditLog::CATEGORY_SYSTEM_CHANGE
                );
            } catch (\Exception $e) {
                Log::warning('Failed to create audit log for command completion', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Display results summary
        $this->displayResults($results, $dryRun);

        // Return appropriate exit code
        if ($results['failed'] > 0) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Display the results summary
     */
    protected function displayResults(array $results, bool $dryRun): void
    {
        $this->info('📊 Sync Results');
        $this->info('================');
        $this->newLine();

        $this->table(
            ['Result', 'Count'],
            [
                ['✅ Jobs queued successfully', $results['queued']],
                ['⏭️  Skipped (no IDP ID)', $results['skipped']],
                ['❌ Failed to queue', $results['failed']],
            ]
        );

        $this->newLine();

        // Show errors if any
        if (!empty($results['errors'])) {
            $this->error('❌ Errors encountered while queueing jobs:');
            $this->newLine();

            $errorTable = [];
            foreach ($results['errors'] as $error) {
                $errorTable[] = [
                    $error['email'],
                    $error['idp_user_id'],
                    $error['error'],
                    substr($error['message'], 0, 50),
                ];
            }

            $this->table(
                ['Email', 'IDP User ID', 'Error', 'Message'],
                $errorTable
            );

            $this->newLine();

            $this->warn('💡 Troubleshooting Tips:');
            $this->info('- Check if Redis queue is running');
            $this->info('- Check if queue worker is running');
            $this->info('- Check storage/logs/laravel.log for detailed errors');
            $this->newLine();
        } else {
            $this->info('ℹ️  Jobs have been queued successfully!');
            $this->info('');
            $this->info('📋 Next Steps:');
            $this->info('1. Jobs are now processing in the background via queue workers');
            $this->info('2. Monitor progress: php artisan queue:listen --verbose');
            $this->info('3. Check audit logs for results:');
            $this->info('   SELECT * FROM audit_logs WHERE module_name = "IDP Account Type" ORDER BY created_at DESC;');
            $this->info('4. Failed jobs will be retried automatically (3 attempts with 60s backoff)');
            $this->newLine();
        }

        if ($dryRun) {
            $this->info('');
            $this->info('🔍 This was a DRY RUN - no actual changes were made.');
            $this->info('To perform actual sync, run without --dry-run flag:');
            $this->info('');
            $this->info('  php artisan idp:sync-enrolled-students');
            $this->newLine();
        } else {
            $statusEmoji = $results['failed'] > 0 ? '⚠️' : '✅';
            $this->info("{$statusEmoji} Sync complete!");
            $this->newLine();
        }
    }
}
