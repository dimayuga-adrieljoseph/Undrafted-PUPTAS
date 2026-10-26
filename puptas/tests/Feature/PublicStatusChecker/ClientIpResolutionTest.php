<?php

use App\Models\AuditLog;
use App\Models\TestPasser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('status_checker:fail:ip:203.0.113.7');
    RateLimiter::clear('status_checker:fail:ip:198.51.100.50');
    RateLimiter::clear('status_checker:fail:ip:192.168.1.50');
    RateLimiter::clear('ip:203.0.113.7');
    RateLimiter::clear('ip:198.51.100.50');
});

test('resolves client IP from leftmost X-Forwarded-For when peer is in 100.0.0.0/8', function () {
    TestPasser::create([
        'reference_number' => '2026-900101',
        'first_name' => 'Maria',
        'surname' => 'Santos',
        'email' => 'maria.santos@example.com',
        'passer_status_id' => 1,
    ]);

    $response = $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-900101',
        'firstName' => 'Maria',
        'lastName' => 'Santos',
    ], [
        'REMOTE_ADDR' => '100.64.0.1',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.7, 198.51.100.2',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]);

    $response->assertStatus(200);

    // Verify audit log row records the resolved client IP (203.0.113.7)
    $log = AuditLog::where('module_name', 'Public Status Checker')->latest()->first();
    expect($log)->not->toBeNull();
    expect($log->ip_address)->toBe('203.0.113.7');
    expect($log->description)->toContain('203.0.113.7');
    expect($log->description)->not->toContain('100.64.0.1');
    expect($log->description)->not->toContain('198.51.100.2');
});

test('ignores forged X-Forwarded-For from non-Railway peer (198.51.100.50)', function () {
    TestPasser::create([
        'reference_number' => '2026-900102',
        'first_name' => 'Juan',
        'surname' => 'Dela Cruz',
        'email' => 'juan.delacruz@example.com',
        'passer_status_id' => 1,
    ]);

    $response = $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-900102',
        'firstName' => 'Juan',
        'lastName' => 'Dela Cruz',
    ], [
        'REMOTE_ADDR' => '198.51.100.50',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.7, 198.51.100.2',
    ]);

    $response->assertStatus(200);

    // Verify audit log row records the peer address (198.51.100.50) and ignores forged header
    $log = AuditLog::where('module_name', 'Public Status Checker')->latest()->first();
    expect($log)->not->toBeNull();
    expect($log->ip_address)->toBe('198.51.100.50');
    expect($log->description)->toContain('198.51.100.50');
    expect($log->description)->not->toContain('203.0.113.7');
});

test('status-checker limiter key uses the resolved IP', function () {
    // Send a non-matching query to trigger the failed lookup limiter
    $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-000000',
        'firstName' => 'Unknown',
        'lastName' => 'Person',
    ], [
        'REMOTE_ADDR' => '100.64.0.1',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.7, 198.51.100.2',
    ]);

    // The fail limiter key in PublicStatusCheckerController should have been hit for 203.0.113.7
    expect(RateLimiter::attempts('status_checker:fail:ip:203.0.113.7'))->toBe(1);
    expect(RateLimiter::attempts('status_checker:fail:ip:100.64.0.1'))->toBe(0);
    expect(RateLimiter::attempts('status_checker:fail:ip:198.51.100.2'))->toBe(0);

    // Now test a non-Railway peer: 198.51.100.50 with forged header
    $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-000000',
        'firstName' => 'Unknown',
        'lastName' => 'Person',
    ], [
        'REMOTE_ADDR' => '198.51.100.50',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.7, 198.51.100.2',
    ]);

    // The fail limiter key should be hit for the peer IP (198.51.100.50), NOT the forged IP
    expect(RateLimiter::attempts('status_checker:fail:ip:198.51.100.50'))->toBe(1);
    expect(RateLimiter::attempts('status_checker:fail:ip:203.0.113.7'))->toBe(1); // unchanged
});

test('logs diagnostic with masked last octet when LOG_CLIENT_IP_DIAG is true', function () {
    Config::set('services.status_checker.log_client_ip_diag', true);

    $loggedContext = null;
    Log::shouldReceive('info')
        ->withArgs(function ($message, $context = []) use (&$loggedContext) {
            if ($message === 'status_checker_client_ip_diag') {
                $loggedContext = $context;
                return true;
            }
            return true;
        })
        ->atLeast()->once();

    $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-900999',
        'firstName' => 'Sample',
        'lastName' => 'User',
    ], [
        'REMOTE_ADDR' => '100.64.0.1',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.7, 198.51.100.2',
        'HTTP_X_REAL_IP' => '198.51.100.2',
        'HTTP_FASTLY_CLIENT_IP' => '203.0.113.7',
        'HTTP_CF_CONNECTING_IP' => '203.0.113.7',
    ]);

    expect($loggedContext)->not->toBeNull();
    expect($loggedContext['remote_addr'])->toBe('100.64.0.xxx');
    expect($loggedContext['x_forwarded_for'])->toBe('203.0.113.xxx, 198.51.100.xxx');
    expect($loggedContext['x_real_ip'])->toBe('198.51.100.xxx');
    expect($loggedContext['fastly_client_ip'])->toBe('203.0.113.xxx');
    expect($loggedContext['cf_connecting_ip'])->toBe('203.0.113.xxx');
    expect($loggedContext['resolved_ip'])->toBe('203.0.113.xxx');
});

test('does not log diagnostic when LOG_CLIENT_IP_DIAG is false', function () {
    Config::set('services.status_checker.log_client_ip_diag', false);

    $diagLogged = false;
    Log::shouldReceive('info')
        ->withArgs(function ($message) use (&$diagLogged) {
            if ($message === 'status_checker_client_ip_diag') {
                $diagLogged = true;
            }
            return true;
        })
        ->zeroOrMoreTimes();

    $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-900999',
        'firstName' => 'Sample',
        'lastName' => 'User',
    ], [
        'REMOTE_ADDR' => '100.64.0.1',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.7, 198.51.100.2',
    ]);

    expect($diagLogged)->toBeFalse();
});
