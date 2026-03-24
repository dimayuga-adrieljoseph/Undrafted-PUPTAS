<?php

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Program;
use App\Models\User;

test('external students endpoint requires valid token', function () {
    config()->set('services.external_api.token', 'test-shared-token');

    $response = $this->getJson('/api/v1/students');

    $response->assertStatus(401)
        ->assertJson([
            'message' => 'Unauthorized',
        ]);

    expect(AuditLog::query()->where('action_type', 'AUTH_FAILED')->where('module_name', 'External API')->exists())
        ->toBeTrue();
});

test('external students list endpoint is gone and points to single-student endpoint', function () {
    config()->set('services.external_api.token', 'test-shared-token');

    $response = $this->withToken('test-shared-token')->getJson('/api/v1/students');

    $response->assertStatus(410)
        ->assertJsonPath('message', 'This endpoint is deprecated. Use /api/v1/students/{studentNumber}.')
        ->assertHeader('Deprecation', 'true');

    expect(AuditLog::query()->where('action_type', 'DEPRECATED_ENDPOINT')->where('module_name', 'External API')->exists())
        ->toBeTrue();
});

test('external student lookup returns one student by student number', function () {
    config()->set('services.external_api.token', 'test-shared-token');

    $program = Program::create([
        'code' => 'BSIT',
        'name' => 'BS Information Technology',
    ]);

    $user = User::create([
        'student_number' => '2026-55555',
        'firstname' => 'Carla',
        'lastname' => 'Lopez',
        'contactnumber' => '09170000003',
        'email' => 'carla@example.com',
        'password' => bcrypt('password'),
    ]);

    Application::create([
        'user_id' => $user->id,
        'program_id' => $program->id,
        'status' => 'accepted',
        'enrollment_status' => 'officially_enrolled',
    ]);

    $response = $this->withToken('test-shared-token')->getJson('/api/v1/students/2026-55555');

    $response->assertOk()
        ->assertJsonPath('data.student_number', '2026-55555')
        ->assertJsonPath('data.application.enrollment_status', 'officially_enrolled')
        ->assertJsonPath('data.program.program_code', 'BSIT');
});

test('external student lookup returns 404 when student is not officially enrolled or missing', function () {
    config()->set('services.external_api.token', 'test-shared-token');

    $response = $this->withToken('test-shared-token')->getJson('/api/v1/students/NO-SUCH-STUDENT');

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'Student not found',
        ]);

    expect(AuditLog::query()->where('action_type', 'READ_MISS')->where('module_name', 'External API')->exists())
        ->toBeTrue();
});

test('external student lookup returns one student by idp user id', function () {
    config()->set('services.external_api.token', 'test-shared-token');

    $program = Program::create([
        'code' => 'BSBA',
        'name' => 'BS Business Administration',
    ]);

    $user = User::create([
        'idp_user_id' => 'idp-abc-123',
        'student_number' => '2026-77777',
        'firstname' => 'Dianne',
        'lastname' => 'Garcia',
        'contactnumber' => '09170000004',
        'email' => 'dianne@example.com',
        'password' => bcrypt('password'),
    ]);

    Application::create([
        'user_id' => $user->id,
        'program_id' => $program->id,
        'status' => 'accepted',
        'enrollment_status' => 'officially_enrolled',
    ]);

    $response = $this->withToken('test-shared-token')->getJson('/api/v1/students/idp/idp-abc-123');

    $response->assertOk()
        ->assertJsonPath('data.idp_user_id', 'idp-abc-123')
        ->assertJsonPath('data.application.enrollment_status', 'officially_enrolled')
        ->assertJsonPath('data.program.program_code', 'BSBA');
});

test('external student lookup by idp user id returns 404 when missing', function () {
    config()->set('services.external_api.token', 'test-shared-token');

    $response = $this->withToken('test-shared-token')->getJson('/api/v1/students/idp/idp-missing-user');

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'Student not found',
        ]);
});
