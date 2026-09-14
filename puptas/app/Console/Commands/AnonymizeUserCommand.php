<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class AnonymizeUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:anonymize-user 
                            {user_id : The ID of the user to anonymize} 
                            {--dry-run : Simulate the anonymization without writing to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently scrub personal identifiable information (PII) from a user record in compliance with DPA 2012';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->argument('user_id');
        $dryRun = (bool) $this->option('dry-run');

        $user = User::with('applicantProfile')->find($userId);

        if (!$user) {
            $this->error("User with ID [{$userId}] not found.");
            return Command::FAILURE;
        }

        if ($user->isAnonymized()) {
            $this->warn("User [{$userId}] is ALREADY permanently anonymized (at {$user->anonymized_at}).");
            return Command::SUCCESS;
        }

        $this->info("=================================================");
        $this->info("  PUPTAS Database Anonymization Mechanism       ");
        $this->info("=================================================");
        $this->line("Target User ID : <comment>{$user->id}</comment>");
        $this->line("Original Name  : <comment>{$user->firstname} {$user->lastname}</comment>");
        $this->line("Original Email : <comment>{$user->email}</comment>");
        $this->line("IDP UUID       : <comment>" . ($user->idp_user_id ?? 'None') . "</comment>");
        $this->line("Has Profile    : <comment>" . ($user->applicantProfile ? 'Yes' : 'No') . "</comment>");
        $this->line("Dry Run Mode   : " . ($dryRun ? '<fg=yellow>ENABLED (No writes)</>' : '<fg=green>DISABLED (Live write)</>'));
        $this->newLine();

        if ($dryRun) {
            $this->table(
                ['Field', 'Current Value', 'Simulated Anonymized Value'],
                [
                    ['email', $user->email, "anon_{$user->id}_[uuid]@privacy.local"],
                    ['firstname', $user->firstname, 'ANONYMIZED'],
                    ['lastname', $user->lastname, "USER_{$user->id}"],
                    ['middlename', $user->middlename ?? 'NULL', 'NULL'],
                    ['idp_user_id', $user->idp_user_id ?? 'NULL', 'NULL'],
                    ['is_active', $user->is_active ? '1' : '0', '0'],
                    ['anonymized_at', 'NULL', now()->toIso8601String()],
                ]
            );
            $this->info("[DRY RUN] Simulation complete. No database changes were committed.");
            return Command::SUCCESS;
        }

        if (!$this->confirm("Are you sure you want to permanently scrub all PII for User ID [{$userId}]? This operation cannot be undone.", true)) {
            $this->line("Operation cancelled.");
            return Command::SUCCESS;
        }

        $success = $user->anonymize();

        if ($success) {
            $user->refresh();
            $this->table(
                ['Field', 'Post-Anonymization State'],
                [
                    ['User ID', (string) $user->id],
                    ['Email', (string) $user->email],
                    ['Full Name', "{$user->firstname} {$user->lastname}"],
                    ['IDP UUID', $user->idp_user_id ?? '[DETACHED/NULL]'],
                    ['Active State', $user->is_active ? 'Active' : 'Deactivated'],
                    ['Anonymized At', (string) $user->anonymized_at],
                ]
            );
            $this->info("✓ User [{$userId}] successfully anonymized and audit log recorded.");
            return Command::SUCCESS;
        }

        $this->error("Failed to anonymize User [{$userId}]. Transaction rolled back.");
        return Command::FAILURE;
    }
}
