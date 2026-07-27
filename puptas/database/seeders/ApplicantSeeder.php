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
     * Creates 11 applicants — one per pipeline stage × state pair.
     * Each seed has ONLY the processes up to its target stage (no "next stage" leaks),
     * so `derivePipelineStatus()` returns the correct unique status for each.
     *
     *   1.  Fresh registrant (no application)
     *   2.  document_evaluator — in_progress
     *   3.  document_evaluator — completed
     *   4.  grade_evaluator     — in_progress
     *   5.  grade_evaluator     — completed
     *   6.  interviewer         — in_progress
     *   7.  interviewer         — completed
     *   8.  medical             — in_progress
     *   9.  medical             — completed
     *  10.  records             — in_progress
     *  11.  records             — completed (officially enrolled)
     *
     * All passwords: Password.1234
     */
    public function run(): void
    {
        $password = Hash::make('Password.1234');

        $applicants = [
            // ─────────────────────────────────────────────────
            // 1. Fresh registrant — no application yet
            //    pipeline_status: unknown (no application)
            // ─────────────────────────────────────────────────
            [
                'email_suffix' => 1,
                'user' => [
                    'idp_user_id' => Str::uuid()->toString(),
                    'salutation' => 'Mr.',
                    'firstname' => 'Juan',
                    'middlename' => 'D',
                    'lastname' => 'Dela Cruz (Fresh)',
                    'birthday' => '2006-03-15',
                    'sex' => 'Male',
                    'street_address' => '123 Mabini St.',
                    'barangay' => 'Sta. Ana',
                    'city' => 'Taguig City',
                    'province' => 'Metro Manila',
                    'postal_code' => '1630',
                    'password' => $password,
                    'role_id' => 1,
                    'privacy_consent' => true,
                    'privacy_consent_at' => now(),
                ],
                'test_passer' => [
                    'surname' => 'Dela Cruz (Fresh)',
                    'first_name' => 'Juan',
                    'middle_name' => 'D',
                    'reference_number' => 'REF-2026-0001',
                    'batch_number' => 'BATCH-2026-01',
                    'school_year' => '2025-2026',
                    'strand' => 'STEM',
                    'shs_school' => 'PUP Taguig SHS',
                    'year_graduated' => 2026,
                    'date_of_birth' => '2006-03-15',
                    'status' => 'registered',
                    'passer_status_id' => 1,
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

            // ─────────────────────────────────────────────────
            // 2. document_evaluator — in_progress
            //    pipeline_status: for_evaluation
            //    Processes: document_evaluator in_progress (only)
            // ─────────────────────────────────────────────────
            [
                'email_suffix' => 2,
                'user' => [
                    'idp_user_id' => Str::uuid()->toString(),
                    'salutation' => 'Ms.',
                    'firstname' => 'Maria',
                    'middlename' => 'C',
                    'lastname' => 'Santos (DocEval-InProg)',
                    'birthday' => '2005-11-20',
                    'sex' => 'Female',
                    'street_address' => '456 Rizal Ave.',
                    'barangay' => 'Bagumbayan',
                    'city' => 'Taguig City',
                    'province' => 'Metro Manila',
                    'postal_code' => '1630',
                    'password' => $password,
                    'role_id' => 1,
                    'privacy_consent' => true,
                    'privacy_consent_at' => now(),
                ],
                'test_passer' => [
                    'surname' => 'Santos (DocEval-InProg)',
                    'first_name' => 'Maria',
                    'middle_name' => 'C',
                    'reference_number' => 'REF-2026-0002',
                    'batch_number' => 'BATCH-2026-01',
                    'school_year' => '2025-2026',
                    'strand' => 'ABM',
                    'shs_school' => 'PUP Taguig SHS',
                    'year_graduated' => 2026,
                    'date_of_birth' => '2005-11-20',
                    'status' => 'application_completed',
                    'passer_status_id' => 1,
                ],
                'profile' => [
                    'school' => 'PUP Taguig SHS',
                    'strand' => 'ABM',
                    'track' => 'Academic',
                    'date_graduated' => '2026-04-01',
                ],
                'graduate_type' => 'Senior High School of A.Y. 2025-2026',
                'grades' => ['english' => 88.50, 'mathematics' => 90.00, 'science' => 87.25, 'g12_first_sem' => 89.00, 'g12_second_sem' => 91.50],
                'application' => [
                    'status' => 'submitted',
                    'enrollment_status' => 'pending',
                    'program_code' => 'BSBA-HRM',
                    'processes' => [
                        ['stage' => 'document_evaluator', 'status' => 'in_progress', 'action' => null],
                    ],
                ],
            ],

            // ─────────────────────────────────────────────────
            // 3. document_evaluator — completed
            //    pipeline_status: evaluation_passed
            //    Processes: document_evaluator completed only
            //    No grade_evaluator — this is the snapshot right after doc eval passes
            // ─────────────────────────────────────────────────
            [
                'email_suffix' => 3,
                'user' => [
                    'idp_user_id' => Str::uuid()->toString(),
                    'salutation' => 'Mr.',
                    'firstname' => 'Pedro',
                    'middlename' => 'R',
                    'lastname' => 'Reyes (DocEval-Done)',
                    'birthday' => '2005-07-08',
                    'sex' => 'Male',
                    'street_address' => '789 Bonifacio Blvd.',
                    'barangay' => 'Central Signal',
                    'city' => 'Taguig City',
                    'province' => 'Metro Manila',
                    'postal_code' => '1630',
                    'password' => $password,
                    'role_id' => 1,
                    'privacy_consent' => true,
                    'privacy_consent_at' => now(),
                ],
                'test_passer' => [
                    'surname' => 'Reyes (DocEval-Done)',
                    'first_name' => 'Pedro',
                    'middle_name' => 'R',
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
                'grades' => ['english' => 92.00, 'mathematics' => 94.50, 'science' => 91.00, 'g12_first_sem' => 93.00, 'g12_second_sem' => 95.00],
                'application' => [
                    'status' => 'submitted',
                    'enrollment_status' => 'pending',
                    'program_code' => 'BSIT',
                    'processes' => [
                        ['stage' => 'document_evaluator', 'status' => 'completed', 'action' => 'passed'],
                    ],
                ],
            ],

            // ─────────────────────────────────────────────────
            // 4. grade_evaluator — in_progress
            //    pipeline_status: for_evaluation
            //    Processes: doc_eval done + grade_eval in_progress
            // ─────────────────────────────────────────────────
            [
                'email_suffix' => 4,
                'user' => [
                    'idp_user_id' => Str::uuid()->toString(),
                    'salutation' => 'Ms.',
                    'firstname' => 'Ana',
                    'middlename' => 'L',
                    'lastname' => 'Garcia (GradeEval-InProg)',
                    'birthday' => '2006-01-25',
                    'sex' => 'Female',
                    'street_address' => '321 Katipunan St.',
                    'barangay' => 'Lower Bicutan',
                    'city' => 'Taguig City',
                    'province' => 'Metro Manila',
                    'postal_code' => '1630',
                    'password' => $password,
                    'role_id' => 1,
                    'privacy_consent' => true,
                    'privacy_consent_at' => now(),
                ],
                'test_passer' => [
                    'surname' => 'Garcia (GradeEval-InProg)',
                    'first_name' => 'Ana',
                    'middle_name' => 'L',
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
                'grades' => ['english' => 89.00, 'mathematics' => 86.50, 'science' => 88.00, 'g12_first_sem' => 87.00, 'g12_second_sem' => 90.00],
                'application' => [
                    'status' => 'submitted',
                    'enrollment_status' => 'pending',
                    'program_code' => 'BSECE',
                    'processes' => [
                        ['stage' => 'document_evaluator', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'grade_evaluator', 'status' => 'in_progress', 'action' => null],
                    ],
                ],
            ],

            // ─────────────────────────────────────────────────
            // 5. grade_evaluator — completed
            //    pipeline_status: evaluation_passed
            //    Processes: doc_eval done + grade_eval done
            //    No interviewer — snapshot right after grade eval passes
            // ─────────────────────────────────────────────────
            [
                'email_suffix' => 5,
                'user' => [
                    'idp_user_id' => Str::uuid()->toString(),
                    'salutation' => 'Mr.',
                    'firstname' => 'Jose',
                    'middlename' => 'P',
                    'lastname' => 'Bautista (GradeEval-Done)',
                    'birthday' => '2005-05-10',
                    'sex' => 'Male',
                    'street_address' => '555 Sampaguita St.',
                    'barangay' => 'Pinagsama',
                    'city' => 'Taguig City',
                    'province' => 'Metro Manila',
                    'postal_code' => '1630',
                    'password' => $password,
                    'role_id' => 1,
                    'privacy_consent' => true,
                    'privacy_consent_at' => now(),
                ],
                'test_passer' => [
                    'surname' => 'Bautista (GradeEval-Done)',
                    'first_name' => 'Jose',
                    'middle_name' => 'P',
                    'reference_number' => 'REF-2026-0005',
                    'batch_number' => 'BATCH-2026-01',
                    'school_year' => '2025-2026',
                    'strand' => 'STEM',
                    'shs_school' => 'PUP Taguig SHS',
                    'year_graduated' => 2026,
                    'date_of_birth' => '2005-05-10',
                    'status' => 'application_completed',
                    'passer_status_id' => 1,
                ],
                'profile' => [
                    'school' => 'PUP Taguig SHS',
                    'strand' => 'STEM',
                    'track' => 'Academic',
                    'date_graduated' => '2026-04-01',
                ],
                'graduate_type' => 'Senior High School of A.Y. 2025-2026',
                'grades' => ['english' => 91.00, 'mathematics' => 93.00, 'science' => 90.50, 'g12_first_sem' => 92.00, 'g12_second_sem' => 94.00],
                'application' => [
                    'status' => 'submitted',
                    'enrollment_status' => 'pending',
                    'program_code' => 'BSIT',
                    'processes' => [
                        ['stage' => 'document_evaluator', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'grade_evaluator', 'status' => 'completed', 'action' => 'passed'],
                    ],
                ],
            ],

            // ─────────────────────────────────────────────────
            // 6. interviewer — in_progress
            //    pipeline_status: for_interview
            //    Processes: doc_eval done + grade_eval done + interviewer in_progress
            // ─────────────────────────────────────────────────
            [
                'email_suffix' => 6,
                'user' => [
                    'idp_user_id' => Str::uuid()->toString(),
                    'salutation' => 'Ms.',
                    'firstname' => 'Sofia',
                    'middlename' => 'M',
                    'lastname' => 'Villanueva (Int-InProg)',
                    'birthday' => '2006-04-18',
                    'sex' => 'Female',
                    'street_address' => '888 Rosal St.',
                    'barangay' => 'Western Bicutan',
                    'city' => 'Taguig City',
                    'province' => 'Metro Manila',
                    'postal_code' => '1630',
                    'password' => $password,
                    'role_id' => 1,
                    'privacy_consent' => true,
                    'privacy_consent_at' => now(),
                ],
                'test_passer' => [
                    'surname' => 'Villanueva (Int-InProg)',
                    'first_name' => 'Sofia',
                    'middle_name' => 'M',
                    'reference_number' => 'REF-2026-0006',
                    'batch_number' => 'BATCH-2026-01',
                    'school_year' => '2025-2026',
                    'strand' => 'HUMSS',
                    'shs_school' => 'Taguig Science High School',
                    'year_graduated' => 2026,
                    'date_of_birth' => '2006-04-18',
                    'status' => 'application_completed',
                    'passer_status_id' => 1,
                ],
                'profile' => [
                    'school' => 'Taguig Science High School',
                    'strand' => 'HUMSS',
                    'track' => 'Academic',
                    'date_graduated' => '2026-04-01',
                ],
                'graduate_type' => 'Senior High School of A.Y. 2025-2026',
                'grades' => ['english' => 94.00, 'mathematics' => 85.00, 'science' => 83.00, 'g12_first_sem' => 90.00, 'g12_second_sem' => 91.50],
                'application' => [
                    'status' => 'submitted',
                    'enrollment_status' => 'pending',
                    'program_code' => 'BSPSYCH',
                    'processes' => [
                        ['stage' => 'document_evaluator', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'grade_evaluator', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'interviewer', 'status' => 'in_progress', 'action' => null],
                    ],
                ],
            ],

            // ─────────────────────────────────────────────────
            // 7. interviewer — completed
            //    pipeline_status: interview_passed
            //    Processes: doc_eval done + grade_eval done + interviewer done
            //    No medical — snapshot right after interviewer passes, before medical is created
            // ─────────────────────────────────────────────────
            [
                'email_suffix' => 7,
                'user' => [
                    'idp_user_id' => Str::uuid()->toString(),
                    'salutation' => 'Mr.',
                    'firstname' => 'Rafael',
                    'middlename' => 'T',
                    'lastname' => 'Cruz (Int-Done)',
                    'birthday' => '2005-09-12',
                    'sex' => 'Male',
                    'street_address' => '77 Ilang-Ilang St.',
                    'barangay' => 'Fort Bonifacio',
                    'city' => 'Taguig City',
                    'province' => 'Metro Manila',
                    'postal_code' => '1630',
                    'password' => $password,
                    'role_id' => 1,
                    'privacy_consent' => true,
                    'privacy_consent_at' => now(),
                ],
                'test_passer' => [
                    'surname' => 'Cruz (Int-Done)',
                    'first_name' => 'Rafael',
                    'middle_name' => 'T',
                    'reference_number' => 'REF-2026-0007',
                    'batch_number' => 'BATCH-2026-01',
                    'school_year' => '2025-2026',
                    'strand' => 'STEM',
                    'shs_school' => 'Makati Science High School',
                    'year_graduated' => 2026,
                    'date_of_birth' => '2005-09-12',
                    'status' => 'application_completed',
                    'passer_status_id' => 1,
                ],
                'profile' => [
                    'school' => 'Makati Science High School',
                    'strand' => 'STEM',
                    'track' => 'Academic',
                    'date_graduated' => '2026-04-01',
                ],
                'graduate_type' => 'Senior High School of A.Y. 2025-2026',
                'grades' => ['english' => 90.00, 'mathematics' => 95.00, 'science' => 92.00, 'g12_first_sem' => 93.50, 'g12_second_sem' => 94.00],
                'application' => [
                    'status' => 'submitted',
                    'enrollment_status' => 'pending',
                    'program_code' => 'BSIT',
                    'processes' => [
                        ['stage' => 'document_evaluator', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'grade_evaluator', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'interviewer', 'status' => 'completed', 'action' => 'passed'],
                    ],
                ],
            ],

            // ─────────────────────────────────────────────────
            // 8. medical — in_progress
            //    pipeline_status: for_medical
            //    Processes: all evals + interviewer done + medical in_progress
            // ─────────────────────────────────────────────────
            [
                'email_suffix' => 8,
                'user' => [
                    'idp_user_id' => Str::uuid()->toString(),
                    'salutation' => 'Ms.',
                    'firstname' => 'Elena',
                    'middlename' => 'R',
                    'lastname' => 'Mendoza (Med-InProg)',
                    'birthday' => '2005-12-03',
                    'sex' => 'Female',
                    'street_address' => '42 Dahlia St.',
                    'barangay' => 'Ususan',
                    'city' => 'Taguig City',
                    'province' => 'Metro Manila',
                    'postal_code' => '1630',
                    'password' => $password,
                    'role_id' => 1,
                    'privacy_consent' => true,
                    'privacy_consent_at' => now(),
                ],
                'test_passer' => [
                    'surname' => 'Mendoza (Med-InProg)',
                    'first_name' => 'Elena',
                    'middle_name' => 'R',
                    'reference_number' => 'REF-2026-0008',
                    'batch_number' => 'BATCH-2026-01',
                    'school_year' => '2025-2026',
                    'strand' => 'ABM',
                    'shs_school' => 'PUP Taguig SHS',
                    'year_graduated' => 2026,
                    'date_of_birth' => '2005-12-03',
                    'status' => 'application_completed',
                    'passer_status_id' => 1,
                ],
                'profile' => [
                    'school' => 'PUP Taguig SHS',
                    'strand' => 'ABM',
                    'track' => 'Academic',
                    'date_graduated' => '2026-04-01',
                ],
                'graduate_type' => 'Senior High School of A.Y. 2025-2026',
                'grades' => ['english' => 91.00, 'mathematics' => 89.00, 'science' => 86.00, 'g12_first_sem' => 90.00, 'g12_second_sem' => 92.00],
                'application' => [
                    'status' => 'submitted',
                    'enrollment_status' => 'pending',
                    'program_code' => 'BSBA-HRM',
                    'processes' => [
                        ['stage' => 'document_evaluator', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'grade_evaluator', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'interviewer', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'medical', 'status' => 'in_progress', 'action' => null],
                    ],
                ],
            ],

            // ─────────────────────────────────────────────────
            // 9. medical — completed
            //    pipeline_status: medical_cleared
            //    Application status: cleared_for_enrollment
            //    Processes: all evals + interviewer + medical done
            //    No records process — snapshot right after medical clears
            // ─────────────────────────────────────────────────
            [
                'email_suffix' => 9,
                'user' => [
                    'idp_user_id' => Str::uuid()->toString(),
                    'salutation' => 'Mr.',
                    'firstname' => 'Miguel',
                    'middlename' => 'S',
                    'lastname' => 'Torres (Med-Done)',
                    'birthday' => '2005-08-22',
                    'sex' => 'Male',
                    'street_address' => '99 Camia St.',
                    'barangay' => 'Hagonoy',
                    'city' => 'Taguig City',
                    'province' => 'Metro Manila',
                    'postal_code' => '1630',
                    'password' => $password,
                    'role_id' => 1,
                    'privacy_consent' => true,
                    'privacy_consent_at' => now(),
                ],
                'test_passer' => [
                    'surname' => 'Torres (Med-Done)',
                    'first_name' => 'Miguel',
                    'middle_name' => 'S',
                    'reference_number' => 'REF-2026-0009',
                    'batch_number' => 'BATCH-2026-01',
                    'school_year' => '2025-2026',
                    'strand' => 'STEM',
                    'shs_school' => 'Taguig Science High School',
                    'year_graduated' => 2026,
                    'date_of_birth' => '2005-08-22',
                    'status' => 'application_completed',
                    'passer_status_id' => 1,
                ],
                'profile' => [
                    'school' => 'Taguig Science High School',
                    'strand' => 'STEM',
                    'track' => 'Academic',
                    'date_graduated' => '2026-04-01',
                ],
                'graduate_type' => 'Senior High School of A.Y. 2025-2026',
                'grades' => ['english' => 87.00, 'mathematics' => 91.00, 'science' => 89.50, 'g12_first_sem' => 88.00, 'g12_second_sem' => 90.00],
                'application' => [
                    'status' => 'cleared_for_enrollment',
                    'enrollment_status' => 'pending',
                    'program_code' => 'BSIT',
                    'processes' => [
                        ['stage' => 'document_evaluator', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'grade_evaluator', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'interviewer', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'medical', 'status' => 'completed', 'action' => 'passed'],
                    ],
                ],
            ],

            // ─────────────────────────────────────────────────
            // 10. records — in_progress
            //     pipeline_status: for_records
            //     Application status: cleared_for_enrollment
            //     Processes: all stages done through medical + records in_progress
            // ─────────────────────────────────────────────────
            [
                'email_suffix' => 10,
                'user' => [
                    'idp_user_id' => Str::uuid()->toString(),
                    'salutation' => 'Ms.',
                    'firstname' => 'Angela',
                    'middlename' => 'T',
                    'lastname' => 'Flores (Rec-InProg)',
                    'birthday' => '2006-06-14',
                    'sex' => 'Female',
                    'street_address' => '56 Orchid St.',
                    'barangay' => 'Bambang',
                    'city' => 'Taguig City',
                    'province' => 'Metro Manila',
                    'postal_code' => '1630',
                    'password' => $password,
                    'role_id' => 1,
                    'privacy_consent' => true,
                    'privacy_consent_at' => now(),
                ],
                'test_passer' => [
                    'surname' => 'Flores (Rec-InProg)',
                    'first_name' => 'Angela',
                    'middle_name' => 'T',
                    'reference_number' => 'REF-2026-0010',
                    'batch_number' => 'BATCH-2026-01',
                    'school_year' => '2025-2026',
                    'strand' => 'HUMSS',
                    'shs_school' => 'PUP Taguig SHS',
                    'year_graduated' => 2026,
                    'date_of_birth' => '2006-06-14',
                    'status' => 'application_completed',
                    'passer_status_id' => 1,
                ],
                'profile' => [
                    'school' => 'PUP Taguig SHS',
                    'strand' => 'HUMSS',
                    'track' => 'Academic',
                    'date_graduated' => '2026-04-01',
                ],
                'graduate_type' => 'Senior High School of A.Y. 2025-2026',
                'grades' => ['english' => 93.00, 'mathematics' => 84.00, 'science' => 82.00, 'g12_first_sem' => 89.00, 'g12_second_sem' => 91.00],
                'application' => [
                    'status' => 'cleared_for_enrollment',
                    'enrollment_status' => 'pending',
                    'program_code' => 'BSBA-HRM',
                    'processes' => [
                        ['stage' => 'document_evaluator', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'grade_evaluator', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'interviewer', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'medical', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'records', 'status' => 'in_progress', 'action' => null],
                    ],
                ],
            ],

            // ─────────────────────────────────────────────────
            // 11. records — completed (officially enrolled)
            //     pipeline_status: officially_enrolled
            //     All processes done + enrollment_status = officially_enrolled
            // ─────────────────────────────────────────────────
            [
                'email_suffix' => 11,
                'user' => [
                    'idp_user_id' => Str::uuid()->toString(),
                    'salutation' => 'Mr.',
                    'firstname' => 'Gabriel',
                    'middlename' => 'V',
                    'lastname' => 'Santos (Rec-Enrolled)',
                    'birthday' => '2005-04-30',
                    'sex' => 'Male',
                    'street_address' => '12 Sunflower St.',
                    'barangay' => 'Wawa',
                    'city' => 'Taguig City',
                    'province' => 'Metro Manila',
                    'postal_code' => '1630',
                    'password' => $password,
                    'role_id' => 1,
                    'privacy_consent' => true,
                    'privacy_consent_at' => now(),
                ],
                'test_passer' => [
                    'surname' => 'Santos (Rec-Enrolled)',
                    'first_name' => 'Gabriel',
                    'middle_name' => 'V',
                    'reference_number' => 'REF-2026-0011',
                    'batch_number' => 'BATCH-2026-01',
                    'school_year' => '2025-2026',
                    'strand' => 'STEM',
                    'shs_school' => 'Makati Science High School',
                    'year_graduated' => 2026,
                    'date_of_birth' => '2005-04-30',
                    'status' => 'application_completed',
                    'passer_status_id' => 1,
                ],
                'profile' => [
                    'school' => 'Makati Science High School',
                    'strand' => 'STEM',
                    'track' => 'Academic',
                    'date_graduated' => '2026-04-01',
                ],
                'graduate_type' => 'Senior High School of A.Y. 2025-2026',
                'grades' => ['english' => 90.00, 'mathematics' => 94.00, 'science' => 91.00, 'g12_first_sem' => 93.00, 'g12_second_sem' => 95.00],
                'application' => [
                    'status' => 'cleared_for_enrollment',
                    'enrollment_status' => 'officially_enrolled',
                    'program_code' => 'BSIT',
                    'processes' => [
                        ['stage' => 'document_evaluator', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'grade_evaluator', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'interviewer', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'medical', 'status' => 'completed', 'action' => 'passed'],
                        ['stage' => 'records', 'status' => 'completed', 'action' => 'passed'],
                    ],
                ],
            ],
        ];

        foreach ($applicants as $data) {
            $this->seedApplicant($data);
        }

        $this->command->info('✓ Seeded 11 applicant accounts (each stage × in_progress + completed):');
        $this->command->info('  applicant1@test.com  / Password.1234 — Fresh registrant');
        $this->command->info('  applicant2@test.com  / Password.1234 — document_evaluator in_progress');
        $this->command->info('  applicant3@test.com  / Password.1234 — document_evaluator completed');
        $this->command->info('  applicant4@test.com  / Password.1234 — grade_evaluator in_progress');
        $this->command->info('  applicant5@test.com  / Password.1234 — grade_evaluator completed');
        $this->command->info('  applicant6@test.com  / Password.1234 — interviewer in_progress');
        $this->command->info('  applicant7@test.com  / Password.1234 — interviewer completed');
        $this->command->info('  applicant8@test.com  / Password.1234 — medical in_progress');
        $this->command->info('  applicant9@test.com  / Password.1234 — medical completed');
        $this->command->info('  applicant10@test.com / Password.1234 — records in_progress');
        $this->command->info('  applicant11@test.com / Password.1234 — records completed (enrolled)');
    }

    private function seedApplicant(array $data): void
    {
        $suffix = $data['email_suffix'];
        $email = "applicant{$suffix}@test.com";

        // 1. Create or update User
        $userData = array_merge($data['user'], ['email' => $email]);
        $user = User::updateOrCreate(['email' => $email], $userData);

        // 2. Create TestPasser record
        $tpData = $data['test_passer'];
        $tpData['email'] = $email;
        $testPasserData = array_merge($tpData, ['user_id' => $user->id]);
        TestPasser::updateOrCreate(
            ['reference_number' => $testPasserData['reference_number']],
            $testPasserData
        );

        // 3. Create ApplicantProfile
        $profileData = array_merge($data['profile'], [
            'user_id' => $user->id,
            'email' => $email,
            'firstname' => $data['user']['firstname'],
            'middlename' => $data['user']['middlename'],
            'lastname' => $data['user']['lastname'],
            'sex' => $data['user']['sex'],
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
                $application = Application::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'program_id' => $program->id,
                        'status' => $data['application']['status'],
                        'enrollment_status' => $data['application']['enrollment_status'],
                        'submitted_at' => now(),
                    ]
                );

                // 7. Create ApplicationProcess records if provided
                if (!empty($data['application']['processes'])) {
                    foreach ($data['application']['processes'] as $process) {
                        \App\Models\ApplicationProcess::updateOrCreate(
                            [
                                'application_id' => $application->id,
                                'stage' => $process['stage'],
                            ],
                            [
                                'status' => $process['status'],
                                'action' => $process['action'] ?? null,
                                'performed_by' => null,
                            ]
                        );
                    }
                }
            }
        }
    }
}