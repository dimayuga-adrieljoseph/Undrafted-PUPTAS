<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\ApplicantProfile;
use App\Models\Grade;
use App\Models\Program;
use App\Models\User;
use App\Services\StudentNumberService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Bulk fast-tracks mock IDP test accounts to officially_enrolled status.
 *
 * This command is intended for testing integration with the Guidance System.
 * It creates or updates the 50 mock accounts (student1@gmail.com – student50@gmail.com)
 * that were registered on the IDP, bypassing all manual admission stages
 * (evaluator → interviewer → medical → records) and setting them directly to
 * officially_enrolled so the Guidance System can query them via the External API.
 *
 * Safe to run multiple times — all operations are idempotent (updateOrCreate / upsert).
 *
 * Usage:
 *   php artisan mock:enroll-guidance-accounts
 *   php artisan mock:enroll-guidance-accounts --dry-run
 *   php artisan mock:enroll-guidance-accounts --rollback
 */
class EnrollMockGuidanceAccounts extends Command
{
    protected $signature = 'mock:enroll-guidance-accounts
                            {--dry-run : Preview what will be created/updated without writing to DB}
                            {--rollback : Remove all mock accounts created by this command}';

    protected $description = 'Fast-tracks the 50 mock IDP guidance test accounts to officially_enrolled status';

    /** Email prefix shared by all mock accounts. */
    private const EMAIL_DOMAIN = 'gmail.com';
    private const EMAIL_PREFIX  = 'student';
    private const TOTAL_ACCOUNTS = 50;

    /** Marker stored in reviewer_notes so rollback can target only these records. */
    private const MOCK_MARKER = '[MOCK-GUIDANCE-TEST]';

    /** Password set on the IDP for all mock accounts. */
    private const MOCK_PASSWORD = 'Password123';

    /**
     * All pipeline stages that a real applicant would pass through.
     * We create completed records for every stage so the system sees
     * the accounts as fully processed.
     */
    private const STAGES = ['evaluator', 'interviewer', 'medical', 'records'];

    public function handle(StudentNumberService $studentNumberService): int
    {
        $isDryRun  = $this->option('dry-run');
        $isRollback = $this->option('rollback');

        if ($isDryRun) {
            $this->warn('⚠  DRY-RUN mode — no database changes will be made.');
        }

        if ($isRollback) {
            return $this->rollback($isDryRun);
        }

        return $this->enroll($studentNumberService, $isDryRun);
    }

    // -------------------------------------------------------------------------
    //  Enroll
    // -------------------------------------------------------------------------

    private function enroll(StudentNumberService $studentNumberService, bool $isDryRun): int
    {
        $program = null;

        if (! $isDryRun) {
            // Pick the first available program so every mock account has a valid program_id.
            $program = Program::orderBy('id')->first();

            if (! $program) {
                $this->error('❌  No programs found in the database. Please seed programs first.');
                return self::FAILURE;
            }

            $this->info("📋  Using program: [{$program->id}] {$program->code} — {$program->name}");
        } else {
            $this->info('📋  [DRY-RUN] Will use the first available program from the database.');
        }

        $this->info('');

        $created  = 0;
        $updated  = 0;
        $skipped  = 0;

        for ($n = 1; $n <= self::TOTAL_ACCOUNTS; $n++) {
            $email = self::EMAIL_PREFIX . $n . '@' . self::EMAIL_DOMAIN;

            $this->line("  → Processing <comment>{$email}</comment>");

            if ($isDryRun) {
                $this->line("     [DRY-RUN] Would upsert user, profile, grades, application & processes.");
                continue;
            }

            try {
                DB::transaction(function () use ($n, $email, $program, $studentNumberService, &$created, &$updated) {
                    // ----------------------------------------------------------
                    // 1. User (auth account)
                    // ----------------------------------------------------------
                    $userAlreadyExisted = User::where('email', $email)->exists();

                    $user = User::updateOrCreate(
                        ['email' => $email],
                        [
                            'firstname'         => 'Student',
                            'middlename'        => 'Mock',
                            'lastname'          => "No{$n}",
                            'salutation'        => 'Mr.',
                            'birthday'          => '2005-01-01',
                            'sex'               => 'Male',
                            'contactnumber'     => '09000000' . str_pad($n, 3, '0', STR_PAD_LEFT),
                            'street_address'    => "Mock Street {$n}",
                            'barangay'          => 'Mock Barangay',
                            'city'              => 'Quezon City',
                            'province'          => 'Metro Manila',
                            'postal_code'       => '1100',
                            'role_id'           => 1, // Applicant
                            'password'          => Hash::make(self::MOCK_PASSWORD),
                            'privacy_consent'   => true,
                            'privacy_consent_at' => now(),
                        ]
                    );

                    // ----------------------------------------------------------
                    // 2. ApplicantProfile — generate student number only once
                    // ----------------------------------------------------------
                    $profileExists = ApplicantProfile::where('user_id', $user->id)->exists();

                    if (! $profileExists) {
                        $studentNumber = $studentNumberService->generate('STU');
                    } else {
                        // Preserve existing student number; only update non-critical fields.
                        $studentNumber = ApplicantProfile::where('user_id', $user->id)
                            ->value('student_number')
                            ?? $studentNumberService->generate('STU');
                    }

                    ApplicantProfile::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'student_number'     => $studentNumber,
                            'email'              => $email,
                            'firstname'          => 'Student',
                            'middlename'         => 'Mock',
                            'lastname'           => "No{$n}",
                            'salutation'         => 'Mr.',
                            'birthday'           => '2005-01-01',
                            'sex'                => 'Male',
                            'contactnumber'      => '09000000' . str_pad($n, 3, '0', STR_PAD_LEFT),
                            'street_address'     => "Mock Street {$n}",
                            'barangay'           => 'Mock Barangay',
                            'city'               => 'Quezon City',
                            'province'           => 'Metro Manila',
                            'postal_code'        => '1100',
                            'privacy_consent'    => true,
                            'privacy_consent_at' => now(),
                            'school'             => 'Mock High School',
                            'school_address'     => 'Mock Address',
                            'strand'             => 'STEM',
                            'track'              => 'Academic',
                            'date_graduated'     => '2024-04-01',
                            'first_choice_program'  => $program->id,
                            'second_choice_program' => null,
                        ]
                    );

                    // ----------------------------------------------------------
                    // 3. Grades — need values so interviewer grade checks pass
                    //    (set values well above any program minimums)
                    // ----------------------------------------------------------
                    Grade::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'english'       => 90.00,
                            'mathematics'   => 90.00,
                            'science'       => 90.00,
                            'g12_first_sem' => 90.00,
                            'g12_second_sem' => 90.00,
                        ]
                    );

                    // ----------------------------------------------------------
                    // 4. Application
                    // ----------------------------------------------------------
                    $application = Application::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'program_id'         => $program->id,
                            'status'             => 'accepted',
                            'enrollment_status'  => 'officially_enrolled',
                            'submitted_at'       => now(),
                        ]
                    );

                    // ----------------------------------------------------------
                    // 5. ApplicationProcess — one completed record per stage
                    // ----------------------------------------------------------
                    foreach (self::STAGES as $stage) {
                        ApplicationProcess::updateOrCreate(
                            [
                                'application_id' => $application->id,
                                'stage'          => $stage,
                            ],
                            [
                                'status'         => 'completed',
                                'action'         => 'passed',
                                'reviewer_notes' => self::MOCK_MARKER . " Mock account fast-tracked for guidance system integration testing.",
                                'performed_by'   => null,
                                'ip_address'     => '127.0.0.1',
                            ]
                        );
                    }

                    $userAlreadyExisted ? $updated++ : $created++;
                });

            } catch (\Throwable $e) {
                $this->error("  ❌  Failed for {$email}: " . $e->getMessage());
            }
        }

        $this->info('');
        $this->info('✅  Done!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Accounts created', $created],
                ['Accounts updated', $updated],
                ['Total processed',  $created + $updated],
            ]
        );

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    //  Rollback
    // -------------------------------------------------------------------------

    private function rollback(bool $isDryRun): int
    {
        $this->warn('⚠  ROLLBACK mode — this will permanently delete all 50 mock accounts and their data.');

        if (! $this->confirm('Are you sure you want to delete all mock guidance accounts?')) {
            $this->info('Rollback cancelled.');
            return self::SUCCESS;
        }

        $emails = [];
        for ($n = 1; $n <= self::TOTAL_ACCOUNTS; $n++) {
            $emails[] = self::EMAIL_PREFIX . $n . '@' . self::EMAIL_DOMAIN;
        }

        $users = User::whereIn('email', $emails)->get();

        if ($users->isEmpty()) {
            $this->info('No mock accounts found — nothing to roll back.');
            return self::SUCCESS;
        }

        $this->line("Found {$users->count()} account(s) to remove.");

        if ($isDryRun) {
            foreach ($users as $user) {
                $this->line("  [DRY-RUN] Would delete: {$user->email}");
            }
            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($users as $user) {
            DB::transaction(function () use ($user) {
                // ApplicationProcesses are cascade-deleted via application
                Application::where('user_id', $user->id)->forceDelete();
                Grade::where('user_id', $user->id)->delete();
                ApplicantProfile::where('user_id', $user->id)->delete();
                $user->forceDelete();
            });
            $this->line("  🗑  Deleted: {$user->email}");
            $deleted++;
        }

        $this->info('');
        $this->info("✅  Rollback complete — {$deleted} account(s) removed.");

        return self::SUCCESS;
    }
}
