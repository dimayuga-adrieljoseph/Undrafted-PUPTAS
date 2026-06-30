<?php

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Program;
use App\Models\User;
use Laravel\Passport\Passport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\ApplicantProfile;

uses(RefreshDatabase::class);

test('external students endpoint requires valid token', function () {
    $response = $this->getJson('/api/v1/students');

    $response->assertStatus(401)
        ->assertJson([
            'message' => 'You are not authenticated. Please log in.',
        ]);
});

test('external students list endpoint is gone and points to single-student endpoint', function () {
    Passport::actingAsClient(
        \Laravel\Passport\Client::factory()->create(),
        ['student-read']
    );

    $response = $this->getJson('/api/v1/students');

    $response->assertStatus(410)
        ->assertJsonPath('message', 'This endpoint is deprecated. Use /api/v1/students/{referenceNumber}.')
        ->assertHeader('Deprecation', 'true');

    expect(AuditLog::query()->where('action_type', 'DEPRECATED_ENDPOINT')->where('module_name', 'External API')->exists())
        ->toBeTrue();
});

test('external student lookup returns one student by student number', function () {
    Passport::actingAsClient(
        \Laravel\Passport\Client::factory()->create(),
        ['student-read']
    );

    $program = Program::create([
        'code' => 'BSIT',
        'name' => 'BS Information Technology',
    ]);

    $user = User::create([
        'firstname' => 'Carla',
        'lastname' => 'Lopez',
        'email' => 'carla@example.com',
        'password' => bcrypt('password'),
    ]);

    $profile = ApplicantProfile::create([
        'user_id' => $user->id,
        'email' => $user->email,
    ]);

    \App\Models\TestPasser::create([
        'user_id' => $user->id,
        'reference_number' => '2026-55555',
        'surname' => 'Lopez',
        'first_name' => 'Carla',
    ]);

    Application::create([
        'user_id' => $user->id,
        'program_id' => $program->id,
        'status' => 'accepted',
        'enrollment_status' => 'officially_enrolled',
    ]);

    $response = $this->getJson('/api/v1/students/2026-55555');

    $response->assertOk()
        ->assertJsonPath('data.reference_number', '2026-55555')
        ->assertJsonPath('data.application.enrollment_status', 'officially_enrolled')
        ->assertJsonPath('data.program.program_code', 'BSIT');
});

test('external student lookup returns 404 when student is not officially enrolled or missing', function () {
    Passport::actingAsClient(
        \Laravel\Passport\Client::factory()->create(),
        ['student-read']
    );

    $response = $this->getJson('/api/v1/students/NO-SUCH-STUDENT');

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'Student not found',
        ]);

    expect(AuditLog::query()->where('action_type', 'READ_MISS')->where('module_name', 'External API')->exists())
        ->toBeTrue();
});

test('external student lookup returns one student by email', function () {
    Passport::actingAsClient(
        \Laravel\Passport\Client::factory()->create(),
        ['student-read']
    );

    $program = Program::create([
        'code' => 'BSBA',
        'name' => 'BS Business Administration',
    ]);

    $user = User::create([
        'idp_user_id' => 'idp-abc-123',
        'firstname' => 'Dianne',
        'lastname' => 'Garcia',
        'email' => 'dianne@example.com',
        'password' => bcrypt('password'),
    ]);

    $profile = ApplicantProfile::create([
        'user_id' => $user->id,
        'email' => $user->email,
    ]);

    Application::create([
        'user_id' => $user->id,
        'program_id' => $program->id,
        'status' => 'accepted',
        'enrollment_status' => 'officially_enrolled',
    ]);

    $response = $this->getJson('/api/v1/students/email/dianne@example.com');

    $response->assertOk()
        ->assertJsonPath('data.email', 'dianne@example.com')
        ->assertJsonPath('data.application.enrollment_status', 'officially_enrolled')
        ->assertJsonPath('data.program.program_code', 'BSBA');
});

test('external student lookup by email returns 404 when missing', function () {
    Passport::actingAsClient(
        \Laravel\Passport\Client::factory()->create(),
        ['student-read']
    );

    $response = $this->getJson('/api/v1/students/email/missing@example.com');

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'Student not found',
        ]);
});
