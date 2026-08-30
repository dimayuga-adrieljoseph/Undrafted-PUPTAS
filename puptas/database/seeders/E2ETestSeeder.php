<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ApplicantProfile;
use App\Models\Program;
use App\Enums\RoleId;
use Illuminate\Support\Facades\Hash;

/**
 * E2E Test Seeder
 * 
 * Creates test users for E2E testing with Playwright
 * Only run in local/testing environments
 */
class E2ETestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Security check
        if (!app()->environment(['local', 'testing'])) {
            $this->command->error('E2E Test Seeder can only run in local/testing environment!');
            return;
        }

        $this->command->info('Creating E2E test users...');

        // Create test applicant
        $applicant = User::firstOrCreate(
            ['email' => 'e2e.applicant@test.local'],
            [
                'firstname' => 'E2E',
                'lastname' => 'Applicant',
                'sex' => 'Male',
                'password' => Hash::make('password'),
                'role_id' => RoleId::Applicant->value,
                'email_verified_at' => now(),
            ]
        );

        ApplicantProfile::firstOrCreate(
            ['user_id' => $applicant->id],
            [
                'firstname' => 'E2E',
                'lastname' => 'Applicant',
                'email' => 'e2e.applicant@test.local',
                'sex' => 'Male',
                'strand' => 'STEM',
            ]
        );

        $this->command->info('✓ Created E2E Applicant: e2e.applicant@test.local');

        // Create test admin
        $admin = User::firstOrCreate(
            ['email' => 'e2e.admin@test.local'],
            [
                'firstname' => 'E2E',
                'lastname' => 'Admin',
                'sex' => 'Female',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole(RoleId::Admin->value);
        $admin->save();

        $this->command->info('✓ Created E2E Admin: e2e.admin@test.local');

        // Create test evaluator
        $evaluator = User::firstOrCreate(
            ['email' => 'e2e.evaluator@test.local'],
            [
                'firstname' => 'E2E',
                'lastname' => 'Evaluator',
                'sex' => 'Male',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $evaluator->assignRole(RoleId::DocumentEvaluator->value);
        $evaluator->save();

        $this->command->info('✓ Created E2E Evaluator: e2e.evaluator@test.local');

        // Create test interviewer
        $interviewer = User::firstOrCreate(
            ['email' => 'e2e.interviewer@test.local'],
            [
                'firstname' => 'E2E',
                'lastname' => 'Interviewer',
                'sex' => 'Female',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $interviewer->assignRole(RoleId::Interviewer->value);
        $interviewer->save();

        $this->command->info('✓ Created E2E Interviewer: e2e.interviewer@test.local');

        // Assign programs to interviewer if programs exist
        try {
            $programs = Program::limit(2)->get();
            if ($programs->isNotEmpty()) {
                // Attach programs manually to handle pivot table fields
                foreach ($programs as $program) {
                    $interviewer->programs()->syncWithoutDetaching([
                        $program->id => ['role_id' => RoleId::Interviewer->value]
                    ]);
                }
                $this->command->info('  → Assigned ' . $programs->count() . ' programs to interviewer');
            }
        } catch (\Exception $e) {
            $this->command->warn('  ⚠ Could not assign programs: ' . $e->getMessage());
        }

        $this->command->info('');
        $this->command->info('E2E Test users created successfully!');
        $this->command->info('All users have password: password');
    }
}
