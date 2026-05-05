<?php

use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\Program;
use App\Models\User;
use Laravel\Passport\Passport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createMedicalApplicant(array $processes = [], $isDeleted = false)
{
    $program = Program::create([
        'code' => 'BSCS',
        'name' => 'BS Computer Science',
    ]);

    $user = User::create([
        'student_number' => '2026-' . rand(10000, 99999),
        'firstname' => 'Test',
        'lastname' => 'User',
        'contactnumber' => '09170000001',
        'email' => 'test' . rand(10000, 99999) . '@example.com',
        'password' => bcrypt('password'),
    ]);

    $profile = ApplicantProfile::create([
        'user_id' => $user->id,
        'student_number' => $user->student_number,
        'firstname' => $user->firstname,
        'lastname' => $user->lastname,
        'email' => $user->email,
        'contactnumber' => $user->contactnumber,
    ]);

    $application = Application::create([
        'user_id' => $user->id,
        'program_id' => $program->id,
        'status' => 'medical',
        'deleted_at' => $isDeleted ? now() : null,
    ]);

    foreach ($processes as $process) {
        ApplicationProcess::create(array_merge([
            'application_id' => $application->id,
        ], $process));
    }

    return [
        'user' => $user,
        'profile' => $profile,
        'application' => $application,
    ];
}

test('medical external api returns applicant when strictly eligible', function () {
    Passport::actingAsClient(
        \Laravel\Passport\Client::factory()->create(),
        ['medical-read']
    );

    $applicant = createMedicalApplicant([
        ['stage' => 'evaluator', 'status' => 'completed', 'action' => 'passed'],
        ['stage' => 'interviewer', 'status' => 'completed', 'action' => 'transferred'],
        ['stage' => 'medical', 'status' => 'in_progress', 'action' => null],
    ]);

    $response = $this->getJson('/api/v1/medical/applicants/' . $applicant['user']->student_number);

    $response->assertOk()
        ->assertJsonPath('data.student_number', $applicant['user']->student_number);
});

test('medical external api returns 404 when medical is already completed', function () {
    Passport::actingAsClient(
        \Laravel\Passport\Client::factory()->create(),
        ['medical-read']
    );

    $applicant = createMedicalApplicant([
        ['stage' => 'evaluator', 'status' => 'completed', 'action' => 'passed'],
        ['stage' => 'interviewer', 'status' => 'completed', 'action' => 'passed'],
        ['stage' => 'medical', 'status' => 'completed', 'action' => 'passed'], // Completed medical makes them ineligible for this specific lookup
    ]);

    $response = $this->getJson('/api/v1/medical/applicants/' . $applicant['user']->student_number);

    $response->assertStatus(404);
});

test('medical external api returns 404 when evaluator is not completed', function () {
    Passport::actingAsClient(
        \Laravel\Passport\Client::factory()->create(),
        ['medical-read']
    );

    $applicant = createMedicalApplicant([
        ['stage' => 'evaluator', 'status' => 'in_progress', 'action' => null],
        ['stage' => 'interviewer', 'status' => 'completed', 'action' => 'passed'],
        ['stage' => 'medical', 'status' => 'in_progress', 'action' => null],
    ]);

    $response = $this->getJson('/api/v1/medical/applicants/' . $applicant['user']->student_number);

    $response->assertStatus(404);
});

test('medical external api returns 404 when interviewer is failed', function () {
    Passport::actingAsClient(
        \Laravel\Passport\Client::factory()->create(),
        ['medical-read']
    );

    $applicant = createMedicalApplicant([
        ['stage' => 'evaluator', 'status' => 'completed', 'action' => 'passed'],
        ['stage' => 'interviewer', 'status' => 'completed', 'action' => 'failed'],
        ['stage' => 'medical', 'status' => 'in_progress', 'action' => null],
    ]);

    $response = $this->getJson('/api/v1/medical/applicants/' . $applicant['user']->student_number);

    $response->assertStatus(404);
});

test('medical external api returns 404 when application is soft deleted', function () {
    Passport::actingAsClient(
        \Laravel\Passport\Client::factory()->create(),
        ['medical-read']
    );

    $applicant = createMedicalApplicant([
        ['stage' => 'evaluator', 'status' => 'completed', 'action' => 'passed'],
        ['stage' => 'interviewer', 'status' => 'completed', 'action' => 'passed'],
        ['stage' => 'medical', 'status' => 'in_progress', 'action' => null],
    ], true); // true = soft deleted

    $response = $this->getJson('/api/v1/medical/applicants/' . $applicant['user']->student_number);

    $response->assertStatus(404);
});
