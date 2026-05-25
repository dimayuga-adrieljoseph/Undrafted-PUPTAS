<?php

use App\Models\AuditLog;
use App\Models\TestPasser;

/**
 * Tests for audit logging on the public admission status checker endpoint.
 * Ensures all status check attempts are properly logged to the audit trail.
 */

test('successful status check creates audit log entry', function () {
    // Create a test passer
    $passer = TestPasser::create([
        'reference_number' => '2026-900001',
        'first_name' => 'Juan',
        'surname' => 'Dela Cruz',
        'email' => 'juan.delacruz@example.com',
        'passer_status_id' => 1,
    ]);

    // Make a successful status check
    $response = $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-900001',
        'firstName' => 'Juan',
        'lastName' => 'Dela Cruz',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['found' => true]);

    // Verify audit log was created
    $this->assertDatabaseHas('audit_logs', [
        'action_type' => 'READ',
        'module_name' => 'Public Status Checker',
        'log_category' => AuditLog::CATEGORY_ADMISSION_DATA,
        'log_type' => AuditLog::TYPE_SYSTEM,
        'user_id' => null, // Public endpoint, no authenticated user
    ]);

    // Verify the description contains the reference number and outcome
    $log = AuditLog::where('module_name', 'Public Status Checker')->latest()->first();
    expect($log->description)->toContain('2026-900001');
    expect($log->description)->toContain('matched');
});

test('failed status check creates audit log entry', function () {
    // Make a failed status check (no matching record)
    $response = $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-999999',
        'firstName' => 'NonExistent',
        'lastName' => 'Person',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['found' => false]);

    // Verify audit log was created
    $this->assertDatabaseHas('audit_logs', [
        'action_type' => 'READ',
        'module_name' => 'Public Status Checker',
        'log_category' => AuditLog::CATEGORY_ADMISSION_DATA,
        'log_type' => AuditLog::TYPE_SYSTEM,
        'user_id' => null,
    ]);

    // Verify the description contains the reference number and outcome
    $log = AuditLog::where('module_name', 'Public Status Checker')->latest()->first();
    expect($log->description)->toContain('2026-999999');
    expect($log->description)->toContain('not_matched');
});

test('audit log captures IP address', function () {
    TestPasser::create([
        'reference_number' => '2026-900002',
        'first_name' => 'Maria',
        'surname' => 'Santos',
        'email' => 'maria.santos@example.com',
        'passer_status_id' => 1,
    ]);

    // Make request with specific IP
    $response = $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-900002',
        'firstName' => 'Maria',
        'lastName' => 'Santos',
    ], ['REMOTE_ADDR' => '192.168.1.100']);

    $response->assertStatus(200);

    // Verify IP address is captured in audit log
    $log = AuditLog::where('module_name', 'Public Status Checker')->latest()->first();
    expect($log->ip_address)->toBe('192.168.1.100');
    expect($log->description)->toContain('192.168.1.100');
});

test('multiple status checks create separate audit log entries', function () {
    $passer = TestPasser::create([
        'reference_number' => '2026-900003',
        'first_name' => 'Pedro',
        'surname' => 'Reyes',
        'email' => 'pedro.reyes@example.com',
        'passer_status_id' => 1,
    ]);

    $initialCount = AuditLog::where('module_name', 'Public Status Checker')->count();

    // Make three status checks
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/public/admission-results', [
            'referenceNumber' => '2026-900003',
            'firstName' => 'Pedro',
            'lastName' => 'Reyes',
        ]);
    }

    // Verify three new audit logs were created
    $finalCount = AuditLog::where('module_name', 'Public Status Checker')->count();
    expect($finalCount)->toBe($initialCount + 3);
});

test('audit log does not store plaintext names', function () {
    TestPasser::create([
        'reference_number' => '2026-900004',
        'first_name' => 'Sensitive',
        'surname' => 'Name',
        'email' => 'sensitive.name@example.com',
        'passer_status_id' => 1,
    ]);

    $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-900004',
        'firstName' => 'Sensitive',
        'lastName' => 'Name',
    ]);

    // Verify names are not stored in plaintext in the description
    $log = AuditLog::where('module_name', 'Public Status Checker')->latest()->first();
    expect($log->description)->not->toContain('Sensitive');
    expect($log->description)->not->toContain('Name');
    
    // But reference number should be present
    expect($log->description)->toContain('2026-900004');
});
