<?php

namespace Database\Seeders;

use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\Grade;
use App\Models\GraduateType;
use App\Models\Program;
use App\Models\TestPasser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApplicantSeeder extends Seeder
{
    /**
     * Seed applicant users for local testing.
     *
     * Creates multiple applicants at different stages of the application flow:
     * 1. Fresh registrant (just registered, no grades/application yet)
     * 2. Applicant with grades submitted
     * 3. Applicant with a submitted application
     * 4. Applicant with an accepted application
     *
     * Login credentials:
     *   - applicant@test.com / Password.1234
     *   - applicant2@test.com / Password.1234
     *   - applicant3@test.com / Password.1234
     *   - applicant4@test.com / Password.1234
     */
    public function run(): void
    {
        $password = Hash::make('Password.1234');

        $applicants = [
            // 1. Fresh registrant — just completed registration
            [
                'user' => [
                    'idp_user_id' => Str::uuid()->toString(),
                    'salutation' => 'Mr.',
                    'firstname' => 'Juan',
                    'middlename' => 'D',
                    'lastname' => 'Dela Cruz',
                    'birthday' => '2006-03-15',
                    'sex' => 'Male',
                    'contactnumber' => '09171234567',
                    'street_address' => '123 Mabini St.',
                    'barangay' => 'Sta. Ana',
                    'city' => 'Taguig City',
                    'province' => 'Metro Manila',
                    'postal_code' => '1630',
                    'email' => 'applicant@test.com',
                    'password' => $password,
                    'role_id' => 1,
                    'privacy_consent' => true,
                    'privacy_consent_at' => now(),
                ],
                'test_passer' => [
                    'surname' => 'Dela Cruz',
                    'first_name' => 'Juan',
                    'middle_name' => 'D',
                    'email' => 'applicant@test.com',
                    'reference_number' => 'REF-2026-0001',
                    'batch_number' => 'BATCH-2026-01',
                    'school_year' => '2025-2026',
                    'strand' => 'STEM',
                    'shs_school' => 'PUP Taguig SHS',
                    'year_graduated' => 2026,
                    'date_of_birth' => '2006-03-15',
                    'status' => 'registered',
                    'passer_status_id' => 1, // qualified
                ],
                'profile' => [
                    'school' => 'PUP Taguig SHS',
                    'strand' => 'STEM',
                    'track' => 'Academic',
                    'date_graduated' => '2026-04-01',
                ],
                'graduate_type' => 'Senior High School of A.Y. 2025-2026',
                'grades' => null,
                'application' => null,
            ],

            // 2. Applicant with grades submitted
            [
                'user' => [
                    'idp_user_id' => Str::uuid()->toString(),
                    'salutation' => 'Ms.',
                    'firstname' => 'Maria',
                    'middlename' => 'C',
                    'lastname' => 'Santos',
                    'birthday' => '2005-11-20',
                    'sex' => 'Female',
                    'contactnumber' => '09189876543',
                    'street_address' => '456 Rizal Ave.',
                    'barangay' => 'Bagumbayan',
                    'city' => 'Taguig City',
                    'province' => 'Metro Manila',
                    'postal_code' => '1630',
                    'email' => 'applicant2@test.com',
                    'password' => $password,
                    'role_id' => 1,
                    'privacy_consent' => true,
                    'privacy_consent_at' => now(),
                ],
                'test_passer' => [
                    'surname' => 'Santos',
                    'first_name' => 'Maria',
                    'middle_name' => 'C',
                    'email' => 'applicant2@test.com',
                    'reference_number' => 'REF-2026-0002',
                    'batch_number' => 'BATCH-2026-01',
                    'school_year' => '2025-2026',
                    'strand' => 'ABM',
                    'shs_school' => 'PUP Taguig SHS',
                    'year_graduated' => 2026,
                    'date_of_birth' => '2005-11-20',
                    'status' => 'registered',
                    'passer_status_id' => 1,
                ],
                'profile' => [
                    'school' => 'PUP Taguig SHS',
                    'strand' => 'ABM',
                    'track' => 'Academic',
                    'date_graduated' => '2026-04-01',
                ],
                'graduate_type' => 'Senior High School of A.Y. 2025-2026',
                'grades' => [
                    'english' => 88.50,
                    'mathematics' => 90.00,
                    'science' => 87.25,
                    'g12_first_sem' => 89.00,
                    'g12_second_sem' => 91.50,
                ],
                'application' => null,
            ],

            // 3. Applicant with submitted application
            [
                'user' => [
                    'idp_user_id' => Str::uuid()->toString(),
                    'salutation' => 'Mr.',
                    'firstname' => 'Pedro',
                    'middlename' => 'R',
                    'lastname' => 'Reyes',
                    'birthday' => '2005-07-08',
                    'sex' => 'Male',
                    'contactnumber' => '09201112233',
                    'street_address' => '789 Bonifacio Blvd.',
                    'barangay' => 'Central Signal',
                    'city' => 'Taguig City',
                    'province' => 'Metro Manila',
                    'postal_code' => '1630',
                    'email' => 'applicant3@test.com',
                    'password' => $password,
                    'role_id' => 1,
                    'privacy_consent' => true,
                    'privacy_consent_at' => now(),
                ],
                'test_passer' => [
                    'surname' => 'Reyes',
                    'first_name' => 'Pedro',
                    'middle_name' => 'R',
                    'email' => 'applicant3@test.com',
                    'reference_number' => 'REF-2026-0003',
                    'batch_number' => 'BATCH-2026-01',
                    'school_year' => '2025-2026',
                    'strand' => 'STEM',
                    'shs_school' => 'Taguig National High School',
                    'year_graduated' => 2026,
                    'date_of_birth' => '2005-07-08',
                    'status' => 'application_completed',
                    'passer_status_id' => 1,
                ],
                'profile' => [
                    'school' => 'Taguig National High School',
                    'strand' => 'STEM',
                    'track' => 'Academic',
                    'date_graduated' => '2026-04-01',
                ],
                'graduate_type' => 'Senior High School of A.Y. 2025-2026',
                'grades' => [
                    'english' => 92.00,
                    'mathematics' => 94.50,
                    'science' => 91.00,
                    'g12_first_sem' => 93.00,
                    'g12_second_sem' => 95.00,
                ],
                'application' => [
                    'status' => 'submitted',
                    'enrollment_status' => 'pending',
                    'program_code' => 'BSIT',
                ],
            ],

            // 4. Applicant with accepted application
            [
                'user' => [
                    'idp_user_id' => Str::uuid()->toString(),
                    'salutation' => 'Ms.',
                    'firstname' => 'Ana',
                    'middlename' => 'L',
                    'lastname' => 'Garcia',
                    'birthday' => '2006-01-25',
                    'sex' => 'Female',
                    'contactnumber' => '09154445566',
                    'street_address' => '321 Katipunan St.',
                    'barangay' => 'Lower Bicutan',
                    'city' => 'Taguig City',
                    'province' => 'Metro Manila',
                    'postal_code' => '1630',
                    'email' => 'applicant4@test.com',
                    'password' => $password,
                    'role_id' => 1,
                    'privacy_consent' => true,
                    'privacy_consent_at' => now(),
                ],
                'test_passer' => [
                    'surname' => 'Garcia',
                    'first_name' => 'Ana',
                    'middle_name' => 'L',
                    'email' => 'applicant4@test.com',
                    'reference_number' => 'REF-2026-0004',
                    'batch_number' => 'BATCH-2026-01',
                    'school_year' => '2025-2026',
                    'strand' => 'GAS',
                    'shs_school' => 'PUP Taguig SHS',
                    'year_graduated' => 2026,
                    'date_of_birth' => '2006-01-25',
                    'status' => 'application_completed',
                    'passer_status_id' => 1,
                ],
                'profile' => [
                    'school' => 'PUP Taguig SHS',
                    'strand' => 'GAS',
                    'track' => 'Academic',
                    'date_graduated' => '2026-04-01',
                ],
                'graduate_type' => 'Senior High School of A.Y. 2025-2026',
                'grades' => [
                    'english' => 89.00,
                    'mathematics' => 86.50,
                    'science' => 88.00,
                    'g12_first_sem' => 87.00,
                    'g12_second_sem' => 90.00,
                ],
                'application' => [
                    'status' => 'accepted',
                    'enrollment_status' => 'officially_enrolled',
                    'program_code' => 'BSBA-HRM',
                ],
            ],
        ];

        foreach ($applicants as $data) {
            $this->seedApplicant($data);
        }

        $this->command->info('✓ Seeded 4 applicant accounts:');
        $this->command->info('  applicant@test.com  / Password.1234 — Fresh registrant');
        $this->command->info('  applicant2@test.com / Password.1234 — Has grades');
        $this->command->info('  applicant3@test.com / Password.1234 — Application submitted');
        $this->command->info('  applicant4@test.com / Password.1234 — Application accepted');
    }

    private function seedApplicant(array $data): void
    {
        // 1. Create or update User
        $user = User::updateOrCreate(
            ['email' => $data['user']['email']],
            $data['user']
        );

        // 2. Create TestPasser record (required for registration validation)
        $testPasserData = array_merge($data['test_passer'], ['user_id' => $user->id]);
        TestPasser::updateOrCreate(
            ['reference_number' => $data['test_passer']['reference_number']],
            $testPasserData
        );

        // 3. Create ApplicantProfile
        $profileData = array_merge($data['profile'], [
            'user_id' => $user->id,
            'email' => $data['user']['email'],
            'firstname' => $data['user']['firstname'],
            'middlename' => $data['user']['middlename'],
            'lastname' => $data['user']['lastname'],
            'sex' => $data['user']['sex'],
            'contactnumber' => $data['user']['contactnumber'],
            'privacy_consent' => true,
            'privacy_consent_at' => now(),
        ]);

        $profile = ApplicantProfile::updateOrCreate(
            ['user_id' => $user->id],
            $profileData
        );

        // 4. Attach graduate type
        if (!empty($data['graduate_type'])) {
            $graduateType = GraduateType::where('label', $data['graduate_type'])->first();
            if ($graduateType) {
                $profile->graduateTypes()->syncWithoutDetaching([$graduateType->id]);
            }
        }

        // 5. Create grades if provided
        if (!empty($data['grades'])) {
            Grade::updateOrCreate(
                ['user_id' => $user->id],
                $data['grades']
            );
        }

        // 6. Create application if provided
        if (!empty($data['application'])) {
            $program = Program::where('code', $data['application']['program_code'])->first();

            if ($program) {
                Application::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'program_id' => $program->id,
                        'status' => $data['application']['status'],
                        'enrollment_status' => $data['application']['enrollment_status'],
                        'submitted_at' => now(),
                    ]
                );
            }
        }
    }
}
