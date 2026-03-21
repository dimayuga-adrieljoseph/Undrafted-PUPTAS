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

test('external students endpoint returns officially enrolled students with student number', function () {
    config()->set('services.external_api.token', 'test-shared-token');

    $program = Program::create([
        'code' => 'BSCS',
        'name' => 'BS Computer Science',
    ]);

    $officiallyEnrolledUser = \App\Models\ApplicantProfile::create([
        'user_id' => 'u1',
        'student_number' => '2026-00001',
        'firstname' => 'Alice',
        'lastname' => 'Reyes',
        'contactnumber' => '09170000001',
        'email' => 'alice@example.com',
    ]);

    $pendingUser = \App\Models\ApplicantProfile::create([
        'user_id' => 'u2',
        'student_number' => '2026-00002',
        'firstname' => 'Bob',
        'lastname' => 'Santos',
        'contactnumber' => '09170000002',
        'email' => 'bob@example.com',
    ]);

    Application::create([
        'user_id' => $officiallyEnrolledUser->user_id,
        'program_id' => $program->id,
        'status' => 'accepted',
        'enrollment_status' => 'officially_enrolled',
    ]);

    Application::create([
        'user_id' => $pendingUser->user_id,
        'program_id' => $program->id,
        'status' => 'submitted',
        'enrollment_status' => 'pending',
    ]);

    $response = $this->withToken('test-shared-token')->getJson('/api/v1/students');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.student_number', '2026-00001')
        ->assertJsonPath('data.0.application.enrollment_status', 'officially_enrolled')
        ->assertJsonPath('data.0.program.program_code', 'BSCS');

    expect(AuditLog::query()->where('action_type', 'READ')->where('module_name', 'External API')->exists())
        ->toBeTrue();
});
