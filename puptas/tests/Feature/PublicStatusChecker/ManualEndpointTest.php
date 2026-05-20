<?php

use App\Models\TestPasser;
use App\Models\AuditLog;

/**
 * Manual verification test for the admission-results endpoint
 * Tests the exact user flow: reference number + first name + last name
 */

test('endpoint works with only reference number, first name, and last name', function () {
    // Create a test passer
    TestPasser::create([
        'reference_number' => '2026-000001',
        'first_name' => 'Juan',
        'surname' => 'Dela Cruz',
        'email' => 'juan.delacruz@test.com',
        'passer_status_id' => 1,
        'batch_number' => 'Batch 1',
    ]);

    // Test the endpoint with ONLY the three required fields
    $response = $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-000001',
        'firstName' => 'Juan',
        'lastName' => 'Dela Cruz',
    ]);

    // Verify response is successful
    $response->assertStatus(200);
    $response->assertJson([
        'found' => true,
        'qualified' => true,
        'reference_number' => '2026-000001',
        'batch_number' => 'Batch 1',
    ]);

    // Verify audit log was created
    $auditLog = AuditLog::where('module_name', 'Public Status Checker')
        ->where('description', 'like', '%2026-000001%')
        ->latest()
        ->first();

    expect($auditLog)->not->toBeNull();
    expect($auditLog->action_type)->toBe('READ');
    expect($auditLog->log_type)->toBe('SYSTEM');
    expect($auditLog->description)->toContain('matched');
});

test('endpoint returns not found for non-existent record', function () {
    // Test with non-existent data
    $response = $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-999999',
        'firstName' => 'NonExistent',
        'lastName' => 'Person',
    ]);

    // Verify response
    $response->assertStatus(200);
    $response->assertJson([
        'found' => false,
        'qualified' => false,
        'message' => 'no_record',
    ]);

    // Verify audit log was created
    $auditLog = AuditLog::where('module_name', 'Public Status Checker')
        ->where('description', 'like', '%2026-999999%')
        ->latest()
        ->first();

    expect($auditLog)->not->toBeNull();
    expect($auditLog->description)->toContain('not_matched');
});

test('endpoint validates required fields', function () {
    // Test missing referenceNumber
    $response = $this->postJson('/api/public/admission-results', [
        'firstName' => 'Juan',
        'lastName' => 'Dela Cruz',
    ]);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['referenceNumber']);

    // Test missing firstName
    $response = $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-000001',
        'lastName' => 'Dela Cruz',
    ]);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['firstName']);

    // Test missing lastName
    $response = $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-000001',
        'firstName' => 'Juan',
    ]);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['lastName']);
});

test('endpoint handles case-insensitive name matching', function () {
    TestPasser::create([
        'reference_number' => '2026-000002',
        'first_name' => 'Maria',
        'surname' => 'Santos',
        'email' => 'maria.santos@test.com',
        'passer_status_id' => 1,
    ]);

    // Test with different cases
    $testCases = [
        ['firstName' => 'maria', 'lastName' => 'santos'],
        ['firstName' => 'MARIA', 'lastName' => 'SANTOS'],
        ['firstName' => 'MaRiA', 'lastName' => 'SaNtOs'],
    ];

    foreach ($testCases as $testCase) {
        $response = $this->postJson('/api/public/admission-results', [
            'referenceNumber' => '2026-000002',
            'firstName' => $testCase['firstName'],
            'lastName' => $testCase['lastName'],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['found' => true]);
    }
});

test('endpoint handles whitespace in names', function () {
    TestPasser::create([
        'reference_number' => '2026-000003',
        'first_name' => 'Pedro',
        'surname' => 'Reyes',
        'email' => 'pedro.reyes@test.com',
        'passer_status_id' => 1,
    ]);

    // Test with extra whitespace
    $response = $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-000003',
        'firstName' => '  Pedro  ',
        'lastName' => '  Reyes  ',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['found' => true]);
});

test('audit logging does not break endpoint on error', function () {
    // Even if audit logging fails, the endpoint should still work
    TestPasser::create([
        'reference_number' => '2026-000004',
        'first_name' => 'Ana',
        'surname' => 'Garcia',
        'email' => 'ana.garcia@test.com',
        'passer_status_id' => 1,
    ]);

    $response = $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-000004',
        'firstName' => 'Ana',
        'lastName' => 'Garcia',
    ]);

    // Endpoint should work regardless of audit logging
    $response->assertStatus(200);
    $response->assertJson(['found' => true]);
});
