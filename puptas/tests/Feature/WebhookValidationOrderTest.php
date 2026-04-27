<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Passport;

class WebhookValidationOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear cache before each test
        Cache::flush();
    }

    /**
     * Test that timestamp validation happens before nonce validation
     *
     * **Validates: Requirements 5.8**
     */
    public function test_timestamp_validation_happens_before_nonce_validation(): void
    {
        // Arrange: Setup OAuth client with medical-write scope
        Passport::actingAsClient(
            \Laravel\Passport\Client::factory()->create(),
            ['medical-write']
        );
        
        // Arrange: Create a webhook payload with expired timestamp but missing nonce
        $expiredTimestamp = time() - (6 * 60); // 6 minutes ago
        $payload = [
            'timestamp' => $expiredTimestamp,
            // Intentionally missing nonce
            'patient_id' => '12345',
            'medical_data' => 'test data'
        ];
        
        $secret = 'test-webhook-secret';
        config(['services.medical_webhook.secret' => $secret]);
        
        $signature = hash_hmac('sha256', json_encode($payload), $secret);

        // Act: Send request with expired timestamp and missing nonce
        $response = $this->postJson('/api/v1/webhooks/medical-result', $payload, [
            'X-Medical-Signature' => $signature
        ]);

        // Assert: Should return 403 for expired timestamp, not 400 for missing nonce
        // This proves timestamp validation happens first
        $response->assertStatus(403);
        $response->assertJson(['message' => 'Request expired']);
    }

    /**
     * Test that nonce validation happens before HMAC signature validation
     *
     * **Validates: Requirements 5.8**
     */
    public function test_nonce_validation_happens_before_hmac_validation(): void
    {
        // Arrange: Setup OAuth client with medical-write scope
        Passport::actingAsClient(
            \Laravel\Passport\Client::factory()->create(),
            ['medical-write']
        );
        
        // Arrange: Create a webhook payload with duplicate nonce but invalid signature
        $nonce = 'duplicate-nonce-test-' . uniqid();
        $payload = [
            'timestamp' => time(),
            'nonce' => $nonce,
            'patient_id' => '12345',
            'medical_data' => 'test data'
        ];
        
        $secret = 'test-webhook-secret';
        config(['services.medical_webhook.secret' => $secret]);
        
        $validSignature = hash_hmac('sha256', json_encode($payload), $secret);

        // Act: Send first request with valid signature to register the nonce
        $firstResponse = $this->postJson('/api/v1/webhooks/medical-result', $payload, [
            'X-Medical-Signature' => $validSignature
        ]);

        // Act: Send second request with same nonce but invalid signature
        $secondResponse = $this->postJson('/api/v1/webhooks/medical-result', $payload, [
            'X-Medical-Signature' => 'invalid-signature-12345'
        ]);

        // Assert: Should return 403 for duplicate nonce, not invalid signature
        // This proves nonce validation happens before HMAC validation
        $secondResponse->assertStatus(403);
        $secondResponse->assertJson(['message' => 'Duplicate request']);
    }

    /**
     * Test complete validation order: timestamp → nonce → HMAC
     *
     * **Validates: Requirements 5.8**
     */
    public function test_complete_validation_order_timestamp_nonce_hmac(): void
    {
        // Arrange: Setup OAuth client with medical-write scope
        Passport::actingAsClient(
            \Laravel\Passport\Client::factory()->create(),
            ['medical-write']
        );
        
        $secret = 'test-webhook-secret';
        config(['services.medical_webhook.secret' => $secret]);

        // Test 1: Expired timestamp with missing nonce and invalid signature
        // Should fail on timestamp (first check)
        $payload1 = [
            'timestamp' => time() - (6 * 60), // Expired
            // Missing nonce
            'patient_id' => '12345',
            'medical_data' => 'test data'
        ];
        
        $response1 = $this->postJson('/api/v1/webhooks/medical-result', $payload1, [
            'X-Medical-Signature' => 'invalid-signature'
        ]);
        
        $response1->assertStatus(403);
        $response1->assertJson(['message' => 'Request expired']);

        // Test 2: Valid timestamp with missing nonce and invalid signature
        // Should fail on nonce (second check)
        $payload2 = [
            'timestamp' => time(), // Valid
            // Missing nonce
            'patient_id' => '12345',
            'medical_data' => 'test data'
        ];
        
        $response2 = $this->postJson('/api/v1/webhooks/medical-result', $payload2, [
            'X-Medical-Signature' => 'invalid-signature'
        ]);
        
        $response2->assertStatus(400);
        $response2->assertJson(['message' => 'Missing nonce']);

        // Test 3: Valid timestamp with valid nonce but invalid signature
        // Should fail on HMAC (third check)
        $payload3 = [
            'timestamp' => time(), // Valid
            'nonce' => 'unique-nonce-' . uniqid(), // Valid and unique
            'patient_id' => '12345',
            'medical_data' => 'test data'
        ];
        
        $response3 = $this->postJson('/api/v1/webhooks/medical-result', $payload3, [
            'X-Medical-Signature' => 'invalid-signature'
        ]);
        
        $response3->assertStatus(403);
        $response3->assertJson(['message' => 'Invalid Signature']);

        // Assert: All three validation stages were tested in order
        $this->assertTrue(true, 'Complete validation order verified: timestamp → nonce → HMAC');
    }

    /**
     * Test that validation order ensures fail-fast behavior
     *
     * **Validates: Requirements 5.8**
     */
    public function test_validation_order_ensures_fail_fast_on_cheaper_checks(): void
    {
        // Arrange: Setup OAuth client with medical-write scope
        Passport::actingAsClient(
            \Laravel\Passport\Client::factory()->create(),
            ['medical-write']
        );
        
        $secret = 'test-webhook-secret';
        config(['services.medical_webhook.secret' => $secret]);

        // Arrange: Create a payload that would fail all three checks
        $expiredTimestamp = time() - (6 * 60); // Expired timestamp
        $payload = [
            'timestamp' => $expiredTimestamp,
            // Missing nonce (would fail nonce check)
            'patient_id' => '12345',
            'medical_data' => 'test data'
        ];
        
        // Invalid signature (would fail HMAC check)
        $invalidSignature = 'completely-invalid-signature';

        // Act: Send request that would fail all checks
        $response = $this->postJson('/api/v1/webhooks/medical-result', $payload, [
            'X-Medical-Signature' => $invalidSignature
        ]);

        // Assert: Should fail on the FIRST check (timestamp), not later checks
        // This demonstrates fail-fast behavior - we don't waste time on expensive
        // HMAC validation when cheaper checks already failed
        $response->assertStatus(403);
        $response->assertJson(['message' => 'Request expired']);
        
        // The response should NOT be about missing nonce or invalid signature
        $responseData = $response->json();
        $this->assertNotEquals('Missing nonce', $responseData['message'] ?? '');
        $this->assertNotEquals('Invalid Signature', $responseData['message'] ?? '');
    }
}
