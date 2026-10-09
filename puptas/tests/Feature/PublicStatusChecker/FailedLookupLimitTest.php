<?php

namespace Tests\Feature\PublicStatusChecker;

use App\Models\AuditLog;
use App\Models\TestPasser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class FailedLookupLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('status_checker:fail:ip:127.0.0.1');
    }

    /**
     * 1. N+1 failed lookups for the same name with different reference numbers:
     * the first N return found:false and the next returns 429 with Retry-After.
     */
    public function test_n_plus_one_failed_lookups_return_429_with_retry_after(): void
    {
        config([
            'services.status_checker.name_fail_limit' => 3,
            'services.status_checker.disable_throttling' => false,
            'services.status_checker.ref_minute_limit' => 100,
            'services.status_checker.ip_minute_limit' => 100,
        ]);

        RateLimiter::clear('status_checker:fail:name:' . hash('sha256', 'failed target'));

        // First N = 3 failed lookups return 200 with found: false
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->postJson('/api/public/admission-results', [
                'referenceNumber' => "2026-100-00{$i}",
                'firstName' => 'Failed',
                'lastName' => 'Target',
            ]);

            $response->assertStatus(200);
            $response->assertJson([
                'found' => false,
                'message' => 'no_record',
            ]);
        }

        // The N+1 = 4th attempt must be rejected with 429
        $blockedResponse = $this->postJson('/api/public/admission-results', [
            'referenceNumber' => '2026-100-004',
            'firstName' => 'Failed',
            'lastName' => 'Target',
        ]);

        $blockedResponse->assertStatus(429);
        $blockedResponse->assertHeader('Retry-After');
        $blockedResponse->assertJson([
            'found' => false,
            'message' => 'too_many_attempts',
        ]);
        $this->assertGreaterThan(0, (int) $blockedResponse->json('retry_after'));
    }

    /**
     * 2. Successful lookups for a valid passer, more times than the failure limit,
     * never return 429 from the new limiters (disable the old throttle in this test via config).
     */
    public function test_successful_lookups_do_not_consume_failure_limit(): void
    {
        config([
            'services.status_checker.name_fail_limit' => 3,
            'services.status_checker.disable_throttling' => false,
            'services.status_checker.ref_minute_limit' => 100, // loosen old per-reference throttle
            'services.status_checker.ip_minute_limit' => 100,
        ]);

        TestPasser::create([
            'reference_number' => '2026-111-222',
            'first_name' => 'Valid',
            'surname' => 'Passer',
            'email' => 'valid.passer@example.com',
            'passer_status_id' => 1,
        ]);

        // Send 5 successful lookups (> failure limit 3)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/public/admission-results', [
                'referenceNumber' => '2026-111-222',
                'firstName' => 'Valid',
                'lastName' => 'Passer',
            ]);

            $response->assertStatus(200);
            $response->assertJson(['found' => true]);
        }
    }

    /**
     * 3. Name normalization: "José  Santos" and "jose santos" share one counter.
     */
    public function test_name_normalization_shares_failure_counter(): void
    {
        config([
            'services.status_checker.name_fail_limit' => 2,
            'services.status_checker.disable_throttling' => false,
            'services.status_checker.ref_minute_limit' => 100,
            'services.status_checker.ip_minute_limit' => 100,
        ]);

        RateLimiter::clear('status_checker:fail:name:' . hash('sha256', 'jose santos'));

        // Attempt 1 with "José  " and " Santos"
        $res1 = $this->postJson('/api/public/admission-results', [
            'referenceNumber' => '2026-000-001',
            'firstName' => 'José  ',
            'lastName' => ' Santos',
        ]);
        $res1->assertStatus(200);
        $res1->assertJson(['found' => false]);

        // Attempt 2 with "jose" and "santos"
        $res2 = $this->postJson('/api/public/admission-results', [
            'referenceNumber' => '2026-000-002',
            'firstName' => 'jose',
            'lastName' => 'santos',
        ]);
        $res2->assertStatus(200);
        $res2->assertJson(['found' => false]);

        // Attempt 3 must be blocked because they share the 2-failure limit
        $res3 = $this->postJson('/api/public/admission-results', [
            'referenceNumber' => '2026-000-003',
            'firstName' => 'Jose',
            'lastName' => 'Santos',
        ]);
        $res3->assertStatus(429);
        $res3->assertJson([
            'found' => false,
            'message' => 'too_many_attempts',
        ]);
    }

    /**
     * 4. A blocked name cannot be used to tell valid from invalid reference numbers (same 429 body).
     */
    public function test_blocked_name_returns_identical_429_for_valid_and_invalid_reference(): void
    {
        config([
            'services.status_checker.name_fail_limit' => 2,
            'services.status_checker.disable_throttling' => false,
            'services.status_checker.ref_minute_limit' => 100,
            'services.status_checker.ip_minute_limit' => 100,
        ]);

        TestPasser::create([
            'reference_number' => '2026-777-888',
            'first_name' => 'Target',
            'surname' => 'Person',
            'email' => 'target.person@example.com',
            'passer_status_id' => 1,
        ]);

        RateLimiter::clear('status_checker:fail:name:' . hash('sha256', 'target person'));

        // Exhaust failure limit with 2 bad attempts
        for ($i = 1; $i <= 2; $i++) {
            $this->postJson('/api/public/admission-results', [
                'referenceNumber' => "2026-000-00{$i}",
                'firstName' => 'Target',
                'lastName' => 'Person',
            ])->assertStatus(200);
        }

        // Query with an INVALID reference number
        $invalidQuery = $this->postJson('/api/public/admission-results', [
            'referenceNumber' => '2026-999-999',
            'firstName' => 'Target',
            'lastName' => 'Person',
        ]);

        // Query with the VALID reference number
        $validQuery = $this->postJson('/api/public/admission-results', [
            'referenceNumber' => '2026-777-888',
            'firstName' => 'Target',
            'lastName' => 'Person',
        ]);

        $invalidQuery->assertStatus(429);
        $validQuery->assertStatus(429);

        $this->assertEquals($invalidQuery->json(), $validQuery->json());
        $this->assertFalse($invalidQuery->json('found'));
        $this->assertEquals('too_many_attempts', $invalidQuery->json('message'));
    }

    /**
     * 5. Masking: the application log and the audit log description do not contain the full reference number.
     */
    public function test_application_log_and_audit_description_do_not_contain_full_reference_number(): void
    {
        Log::spy();

        TestPasser::create([
            'reference_number' => '2026-888-999',
            'first_name' => 'Masking',
            'surname' => 'Target',
            'email' => 'masking.target@example.com',
            'passer_status_id' => 1,
        ]);

        $response = $this->postJson('/api/public/admission-results', [
            'referenceNumber' => '2026-888-999',
            'firstName' => 'Masking',
            'lastName' => 'Target',
        ]);

        $response->assertStatus(200);

        // Verify audit log
        $auditLog = AuditLog::where('module_name', 'Public Status Checker')->latest()->first();
        $this->assertNotNull($auditLog);
        $this->assertStringContainsString('2026-***-***', $auditLog->description);
        $this->assertStringNotContainsString('2026-888-999', $auditLog->description);

        // Verify application log
        Log::shouldHaveReceived('info')->with('status_check_attempt', Mockery::on(function ($context) {
            return isset($context['reference_number'])
                && $context['reference_number'] === '2026-***-***';
        }));
    }
}
