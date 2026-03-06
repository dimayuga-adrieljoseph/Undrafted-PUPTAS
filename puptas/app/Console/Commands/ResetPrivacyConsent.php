<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetPrivacyConsent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'privacy:reset {--all : Reset privacy consent for all users} {--user-id= : Reset privacy consent for a specific user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset privacy consent for users to show terms and conditions modal on next login';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('all')) {
            $this->resetAll();
        } elseif ($this->option('user-id')) {
            $this->resetForUser($this->option('user-id'));
        } else {
            $this->showStatus();
        }

        return 0;
    }

    /**
     * Show current status of privacy consent
     */
    private function showStatus()
    {
        $this->info('Privacy Consent Status:');
        $this->newLine();

        $totalUsers = User::count();
        $withConsent = User::where('privacy_consent', true)->count();
        $withoutConsent = User::where('privacy_consent', false)->orWhereNull('privacy_consent')->count();

        $this->table(
            ['Status', 'Count'],
            [
                ['Total Users', $totalUsers],
                ['With Consent', $withConsent],
                ['Without Consent', $withoutConsent],
            ]
        );

        $this->newLine();
        $this->info('Recent users without consent:');
        
        $usersWithoutConsent = User::where('privacy_consent', false)
            ->orWhereNull('privacy_consent')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['id', 'email', 'firstname', 'lastname', 'privacy_consent', 'created_at']);

        if ($usersWithoutConsent->isEmpty()) {
            $this->warn('No users without consent found.');
        } else {
            $this->table(
                ['ID', 'Email', 'Name', 'Privacy Consent', 'Created At'],
                $usersWithoutConsent->map(function ($user) {
                    return [
                        $user->id,
                        $user->email,
                        "{$user->firstname} {$user->lastname}",
                        $user->privacy_consent ? 'Yes' : 'No',
                        $user->created_at->format('Y-m-d H:i:s'),
                    ];
                })
            );
        }

        $this->newLine();
        $this->info('Options:');
        $this->line('  php artisan privacy:reset --all          Reset for all users');
        $this->line('  php artisan privacy:reset --user-id=1    Reset for specific user');
    }

    /**
     * Reset privacy consent for all users
     */
    private function resetAll()
    {
        if (!$this->confirm('This will reset privacy consent for ALL users. They will need to accept terms on next login. Continue?')) {
            $this->info('Operation cancelled.');
            return;
        }

        $updated = User::query()->update([
            'privacy_consent' => false,
            'privacy_consent_at' => null,
        ]);

        $this->info("Privacy consent reset for {$updated} users.");
        $this->warn('All users will see the terms and conditions modal on their next login.');
    }

    /**
     * Reset privacy consent for a specific user
     */
    private function resetForUser($userId)
    {
        $user = User::find($userId);

        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return;
        }

        $user->update([
            'privacy_consent' => false,
            'privacy_consent_at' => null,
        ]);

        $this->info("Privacy consent reset for user: {$user->email}");
        $this->warn('This user will see the terms and conditions modal on their next login.');
    }
}
