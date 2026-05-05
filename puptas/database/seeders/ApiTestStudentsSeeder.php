<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ApiTestStudentsSeeder extends Seeder
{
    public function run(): void
    {
        $program = Program::firstOrCreate(
            ['code' => 'API-TST'],
            ['name' => 'API Testing Program']
        );

        for ($i = 1; $i <= 20; $i++) {
            $num = str_pad((string) $i, 3, '0', STR_PAD_LEFT);

            $user = User::updateOrCreate(
                ['email' => "apitest.student{$num}@example.com"],
                [
                    'firstname' => "APITest{$num}",
                    'lastname' => 'Student',
                    'contactnumber' => '09170000000',
                    'password' => Hash::make('password'),
                    'role_id' => 1,
                ]
            );

            \App\Models\ApplicantProfile::updateOrCreate(
                ['user_id' => $user->idp_user_id ?? $user->id], // fallback logic depending on setup
                ['user_id' => $user->id, 'student_number' => "2026-TST-{$num}"]
            );

            Application::updateOrCreate(
                ['user_id' => $user->id], 
                [
                    'program_id' => $program->id,
                    'status' => 'accepted',
                    'enrollment_status' => 'officially_enrolled',
                    'submitted_at' => now(),
                ]
            );
        }
    }
}
