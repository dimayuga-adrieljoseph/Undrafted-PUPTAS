<?php

use App\Models\AuditLog;
use App\Models\Program;

test('external programs endpoint requires valid token', function () {
    config()->set('services.external_program_api.token', 'test-program-token');

    $response = $this->getJson('/api/v1/programs');

    $response->assertStatus(401)
        ->assertJson([
            'message' => 'Unauthorized',
        ]);

    expect(AuditLog::query()->where('action_type', 'AUTH_FAILED')->where('module_name', 'External API')->exists())
        ->toBeTrue();
});

test('external programs list endpoint returns programs successfully', function () {
    config()->set('services.external_program_api.token', 'test-program-token');

    Program::create([
        'code' => 'BSIT',
        'name' => 'BS Information Technology',
        'slots' => 50,
        'math' => 85,
        'science' => 85,
        'english' => 85,
        'gwa' => 85,
    ]);

    Program::create([
        'code' => 'BSCS',
        'name' => 'BS Computer Science',
        'slots' => 40,
        'math' => 88,
        'science' => 88,
        'english' => 85,
        'gwa' => 88,
    ]);

    $response = $this->withToken('test-program-token')->getJson('/api/v1/programs');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.code', 'BSIT')
        ->assertJsonPath('data.1.code', 'BSCS');

    expect(AuditLog::query()->where('action_type', 'READ')->where('module_name', 'External API')->exists())
        ->toBeTrue();
});
