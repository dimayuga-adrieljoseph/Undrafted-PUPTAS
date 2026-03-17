<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'salutation' => 'Mr.',
                'firstname' => 'Adriel Joseph',
                'middlename' => 'I',
                'lastname' => 'Dimayuga',
                'birthday' => '2003-09-13',
                'sex' => 'Male',
                'contactnumber' => '09123456789',
                'street_address' => 'Block 123, Sampaguita St.',
                'barangay' => 'Ususan',
                'city' => 'Taguig City',
                'province' => 'Metro Manila',
                'postal_code' => '1630',
                'email' => 'dimayuga.adrieljoseph03@gmail.com',
                'password' => Hash::make('12345678'),
                'role_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'salutation' => 'Ms.',
                'firstname' => 'Jane',
                'middlename' => 'B',
                'lastname' => 'Smith',
                'birthday' => '2001-06-10',
                'sex' => 'Female',
                'contactnumber' => '09129876543',
                'street_address' => 'Street 456, Mabini Ave.',
                'barangay' => 'Bagumbayan',
                'city' => 'Taguig City',
                'province' => 'Metro Manila',
                'postal_code' => '1630',
                'email' => 'evaluator@gmail.com',
                'password' => Hash::make('Evaluator.1234'),
                'role_id' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'salutation' => 'Mr.',
                'firstname' => 'Mark',
                'middlename' => 'C',
                'lastname' => 'Reyes',
                'birthday' => '1999-09-15',
                'sex' => 'Male',
                'contactnumber' => '09125557777',
                'street_address' => 'Street 789, Rizal Blvd.',
                'barangay' => 'Central Bicutan',
                'city' => 'Taguig City',
                'province' => 'Metro Manila',
                'postal_code' => '1630',
                'email' => 'interviewer@gmail.com',
                'password' => Hash::make('Interviewer.1234'),
                'role_id' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'salutation' => 'Ms.',
                'firstname' => 'Maria',
                'middlename' => 'D',
                'lastname' => 'Santos',
                'birthday' => '1998-03-20',
                'sex' => 'Female',
                'contactnumber' => '09126668888',
                'street_address' => 'Street 101, Gen. Santos Ave.',
                'barangay' => 'Upper Bicutan',
                'city' => 'Taguig City',
                'province' => 'Metro Manila',
                'postal_code' => '1630',
                'email' => 'nurse@gmail.com',
                'password' => Hash::make('Nurse.123'),
                'role_id' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'salutation' => 'Mr.',
                'firstname' => 'John',
                'middlename' => 'I',
                'lastname' => 'Doe',
                'birthday' => '2003-09-13',
                'sex' => 'Male',
                'contactnumber' => '09123456789',
                'street_address' => 'Block 123, Lakandula St.',
                'barangay' => 'Western Bicutan',
                'city' => 'Taguig City',
                'province' => 'Metro Manila',
                'postal_code' => '1630',
                'email' => 'registrar@gmail.com',
                'password' => Hash::make('Registrar.1234'),
                'role_id' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'salutation' => 'Mr.',
                'firstname' => 'Super',
                'middlename' => 'Admin',
                'lastname' => 'User',
                'birthday' => '1990-01-01',
                'sex' => 'Male',
                'contactnumber' => '09120000000',
                'street_address' => 'Admin Street',
                'barangay' => 'Poblacion',
                'city' => 'Taguig City',
                'province' => 'Metro Manila',
                'postal_code' => '1630',
                'email' => 'superadmin@puptas.edu',
                'password' => Hash::make('Superadmin.1234'),
                'role_id' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                $user
            );
        }

        // SECURITY: Only seed superadmin in local/dev environments
        // Never seed privileged accounts with default passwords in staging/production
        // Superadmin is now seeded as a regular user in the $users array above
        // $this->seedSuperAdminInLocalEnv();
    }

    /*
    /**
     * Seed superadmin account only in local/development environments.
     * Password is sourced from SEED_SUPERADMIN_PASSWORD env var or randomly generated.
     *
    private function seedSuperAdminInLocalEnv(): void
    {
        $environment = app()->environment();

        // Only seed superadmin in local or development environments
        if (!in_array($environment, ['local', 'development'])) {
            $this->command->warn(
                '⚠️  Superadmin seeding skipped (not in local/development environment).'
            );
            return;
        }

        // Get password from environment variable or generate a secure random one
        $password = env('SEED_SUPERADMIN_PASSWORD');

        if (empty($password)) {
            // Generate a secure random password
            $password = Str::random(16) . '!A1a';
            $this->command->warn(
                "🔐 Generated random superadmin password: {$password}"
            );
            $this->command->warn(
                '   ⚠️  Save this password now! Set SEED_SUPERADMIN_PASSWORD in .env to use a custom password.'
            );
        } else {
            $this->command->info('✓ Using superadmin password from SEED_SUPERADMIN_PASSWORD env variable.');
        }

        $superadmin = [
            'salutation' => 'Mr.',
            'firstname' => 'Super',
            'middlename' => 'Admin',
            'lastname' => 'User',
            'birthday' => '1990-01-01',
            'sex' => 'Male',
            'contactnumber' => '09120000000',
            'street_address' => 'Admin Building, University Ave.',
            'barangay' => 'Poblacion',
            'city' => 'Taguig City',
            'province' => 'Metro Manila',
            'postal_code' => '1630',
            'email' => 'superadmin@puptas.edu',
            'password' => Hash::make($password),
            'role_id' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('users')->updateOrInsert(
            ['email' => $superadmin['email']],
            $superadmin
        );

        $this->command->info('✓ Superadmin account seeded successfully.');
    }
    */
}
