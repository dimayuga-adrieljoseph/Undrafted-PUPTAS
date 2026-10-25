<?php

use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\Program;
use App\Models\TestPasser;
use App\Models\User;
use Laravel\Passport\Passport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createMedicalApplicant(array $processes = [], $isDeleted = false): array
{
    $program = Program::firstOrCreate([
        'code' => 'BSCS',
    ], [
        'name' => 'BS Computer Science',
    ]);

    $refNumber = 'REF-' . rand(10000, 99999);

    $user = User::create([
        'firstname' => 'Test',
        'lastname' => 'User',
        'email' => 'test' . rand(10000, 99999) . '@example.com',
        'password' => bcrypt('password'),
    ]);

    $passer = TestPasser::create([
        'user_id' => $user->id,
        'reference_number' => $refNumber,
        'first_name' => $user->firstname,
        'surname' => $user->lastname,
        'email' => $user->email,
        'status' => 'passed',
    ]);

    $profile = ApplicantProfile::create([
        'user_id' => $user->id,
        'student_number' => $refNumber,
        'firstname' => $user->firstname,
        'lastname' => $user->lastname,
        'email' => $user->email,
        'contactnumber' => '09170000001',
    ]);

    $application = Application::create([
        'user_id' => $user->id,
        'program_id' => $program->id,
        'status' => 'medical',
    ]);

    if ($isDeleted) {
        $application->delete();
    }

    foreach ($processes as $process) {
        ApplicationProcess::create(array_merge([
            'application_id' => $application->id,
        ], $process));
    }

    return [
        'user' => $user,
        'profile' => $profile,
        'application' => $application,
        'passer' => $passer,
        'reference_number' => $refNumber,
    ];
}

test('medical external api returns applicant when strictly eligible without sensitive fields', function () {
    Passport::actingAsClient(
        \Laravel\Passport\Client::factory()->create(),
        ['medical-read']
    );

    $applicant = createMedicalApplicant([
        ['stage' => 'grade_evaluator', 'status' => 'completed', 'action' => 'passed'],
        ['stage' => 'interviewer', 'status' => 'completed', 'action' => 'transferred'],
        ['stage' => 'medical', 'status' => 'in_progress', 'action' => null],
    ]);

    $url = '/api/v1/medical/applicants/' . $applicant['reference_number'];
    expect($url)->toMatch('#^/api/v1/medical/applicants/[A-Za-z0-9_-]+$#');

    $response = $this->getJson($url);

    $response->assertOk()
        ->assertJsonPath('data.reference_number', $applicant['reference_number'])
        ->assertJsonMissingPath('data.birthdate')
        ->assertJsonMissingPath('data.date_of_birth')
        ->assertJsonMissingPath('data.contactnumber');

    $data = $response->json('data');
    expect(array_key_exists('birthdate', $data))->toBeFalse()
        ->and(array_key_exists('date_of_birth', $data))->toBeFalse()
        ->and(array_key_exists('contactnumber', $data))->toBeFalse();
});

test('medical external api returns 404 when medical is already completed', function () {
    Passport::actingAsClient(
        \Laravel\Passport\Client::factory()->create(),
        ['medical-read']
    );

    $applicant = createMedicalApplicant([
        ['stage' => 'grade_evaluator', 'status' => 'completed', 'action' => 'passed'],
        ['stage' => 'interviewer', 'status' => 'completed', 'action' => 'passed'],
        ['stage' => 'medical', 'status' => 'completed', 'action' => 'passed'],
    ]);

    $url = '/api/v1/medical/applicants/' . $applicant['reference_number'];
    expect($url)->toMatch('#^/api/v1/medical/applicants/[A-Za-z0-9_-]+$#');

    $response = $this->getJson($url);

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'Applicant not found or not eligible for medical yet.',
        ]);
});

test('medical external api returns 404 when evaluator is not completed', function () {
    Passport::actingAsClient(
        \Laravel\Passport\Client::factory()->create(),
        ['medical-read']
    );

    $applicant = createMedicalApplicant([
        ['stage' => 'grade_evaluator', 'status' => 'in_progress', 'action' => null],
        ['stage' => 'interviewer', 'status' => 'completed', 'action' => 'passed'],
        ['stage' => 'medical', 'status' => 'in_progress', 'action' => null],
    ]);

    $url = '/api/v1/medical/applicants/' . $applicant['reference_number'];
    expect($url)->toMatch('#^/api/v1/medical/applicants/[A-Za-z0-9_-]+$#');

    $response = $this->getJson($url);

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'Applicant not found or not eligible for medical yet.',
        ]);
});

test('medical external api returns 404 when interviewer is failed', function () {
    Passport::actingAsClient(
        \Laravel\Passport\Client::factory()->create(),
        ['medical-read']
    );

    $applicant = createMedicalApplicant([
        ['stage' => 'grade_evaluator', 'status' => 'completed', 'action' => 'passed'],
        ['stage' => 'interviewer', 'status' => 'completed', 'action' => 'failed'],
        ['stage' => 'medical', 'status' => 'in_progress', 'action' => null],
    ]);

    $url = '/api/v1/medical/applicants/' . $applicant['reference_number'];
    expect($url)->toMatch('#^/api/v1/medical/applicants/[A-Za-z0-9_-]+$#');

    $response = $this->getJson($url);

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'Applicant not found or not eligible for medical yet.',
        ]);
});

test('medical external api returns 404 when application is soft deleted', function () {
    Passport::actingAsClient(
        \Laravel\Passport\Client::factory()->create(),
        ['medical-read']
    );

    $applicant = createMedicalApplicant([
        ['stage' => 'grade_evaluator', 'status' => 'completed', 'action' => 'passed'],
        ['stage' => 'interviewer', 'status' => 'completed', 'action' => 'passed'],
        ['stage' => 'medical', 'status' => 'in_progress', 'action' => null],
    ], true);

    $url = '/api/v1/medical/applicants/' . $applicant['reference_number'];
    expect($url)->toMatch('#^/api/v1/medical/applicants/[A-Za-z0-9_-]+$#');

    $response = $this->getJson($url);

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'Applicant not found or not eligible for medical yet.',
        ]);
});

test('medical external api returns 403 when token lacks medical-read scope', function () {
    Passport::actingAsClient(
        \Laravel\Passport\Client::factory()->create(),
        ['student-read']
    );

    $applicant = createMedicalApplicant([
        ['stage' => 'grade_evaluator', 'status' => 'completed', 'action' => 'passed'],
        ['stage' => 'interviewer', 'status' => 'completed', 'action' => 'passed'],
        ['stage' => 'medical', 'status' => 'in_progress', 'action' => null],
    ]);

    $url = '/api/v1/medical/applicants/' . $applicant['reference_number'];
    expect($url)->toMatch('#^/api/v1/medical/applicants/[A-Za-z0-9_-]+$#');

    $response = $this->getJson($url);

    $response->assertStatus(403);
});

test('medical external api returns 404 when reference number is unknown', function () {
    Passport::actingAsClient(
        \Laravel\Passport\Client::factory()->create(),
        ['medical-read']
    );

    $url = '/api/v1/medical/applicants/UNKNOWN-REF-99999';
    expect($url)->toMatch('#^/api/v1/medical/applicants/[A-Za-z0-9_-]+$#');

    $response = $this->getJson($url);

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'Applicant not found or not eligible for medical yet.',
        ]);
});
