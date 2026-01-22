<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

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
                'address' => 'Block 123',
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
                'address' => 'Street 456',
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
                'address' => 'Street 789',
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
                'address' => 'Street 101',
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
                'address' => 'Block 123',
                'email' => 'registrar@gmail.com',
                'password' => Hash::make('Registrar.1234'),
                'role_id' => 6,
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
    }
}
