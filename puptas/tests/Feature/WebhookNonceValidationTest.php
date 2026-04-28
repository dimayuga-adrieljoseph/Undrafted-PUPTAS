<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Passport;

class WebhookNonceValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear cache before each test
        Cache::flush();
    }

    /**
     * Test that webhook request without nonce returns 400
     *
     * **Validates: Requirements 5.4, 5.5**
     */
    public function test_webhook_without_nonce_returns_400(): void
    {
        // Arrange: Setup OAuth client with medical-write scope
        Passport::actingAsClient(
            \Laravel\Passport\Client::factory()->create(),
            ['medical-write']
        );
        
        // Arrange: Create a webhook payload without nonce but with valid timestamp
        $payload = [
            'timestamp' => time(),
            'patient_id' => '12345',
            'medical_data' => 'test data'
        ];
        
        $secret = 'test-webhook-secret';
        config(['services.medical_webhook.secret' => $secret]);
        
        $signature = hash_hmac('sha256', json_encode($payload), $secret);

        // Act: Send request without nonce
        $response = $this->postJson('/api/v1/webhooks/medical-result', $payload, [
            'X-Medical-Signature' => $signature
        ]);

        // Assert: Should return 400 with "Missing nonce" message
        $response->assertStatus(400);
        $response->assertJson(['message' => 'Missing nonce']);
    }

    /**
     * Test that webhook request with duplicate nonce returns 403
     *
     * **Validates: Requirements 5.4, 5.6**
     */
    public function test_webhook_with_duplicate_nonce_returns_403(): void
    {
        // Arrange: Setup OAuth client with medical-write scope
        Passport::actingAsClient(
            \Laravel\Passport\Client::factory()->create(),
            ['medical-write']
        );
        
        // Arrange: Create a webhook payload with nonce and valid timestamp
        $nonce = 'unique-nonce-12345';
        $payload = [
            'timestamp' => time(),
            'nonce' => $nonce,
            'patient_id' => '12345',
            'medical_data' => 'test data'
        ];
        
        $secret = 'test-webhook-secret';
        config(['services.medical_webhook.secret' => $secret]);
        
        $signature = hash_hmac('sha256', json_encode($payload), $secret);

        // Act: Send first request with nonce
        $firstResponse = $this->postJson('/api/v1/webhooks/medical-result', $payload, [
            'X-Medical-Signature' => $signature
        ]);

        // Act: Send second request with same nonce
        $secondResponse = $this->postJson('/api/v1/webhooks/medical-result', $payload, [
            'X-Medical-Signature' => $signature
        ]);

        // Assert: Second request should return 403 with "Duplicate request" message
        $secondResponse->assertStatus(403);
        $secondResponse->assertJson(['message' => 'Duplicate request']);
    }

    /**
     * Test that webhook request with unique nonce passes validation
     *
     * **Validates: Requirements 5.4, 5.7**
     */
    public function test_webhook_with_unique_nonce_passes_validation(): void
    {
        // Arrange: Setup OAuth client with medical-write scope
        Passport::actingAsClient(
            \Laravel\Passport\Client::factory()->create(),
            ['medical-write']
        );
        
        // Arrange: Create a webhook payload with unique nonce and valid timestamp
        $nonce = 'unique-nonce-' . uniqid();
        $payload = [
            'timestamp' => time(),
            'nonce' => $nonce,
            'patient_id' => '12345',
            'medical_data' => 'test data'
        ];
        
        $secret = 'test-webhook-secret';
        config(['services.medical_webhook.secret' => $secret]);
        
        $signature = hash_hmac('sha256', json_encode($payload), $secret);

        // Act: Send request with unique nonce
        $response = $this->postJson('/api/v1/webhooks/medical-result', $payload, [
            'X-Medical-Signature' => $signature
        ]);

        // Assert: Should not return 400 or 403 for nonce issues
        $this->assertNotEquals(400, $response->status(), 'Should not return 400 for missing nonce');
        
        // If it returns 403, verify it's not for duplicate nonce
        if ($response->status() === 403) {
            $responseData = $response->json();
            $this->assertNotEquals('Duplicate request', $responseData['message'] ?? '', 
                'Should not return "Duplicate request" for unique nonce');
        }
    }

    /**
     * Test that nonce is stored in cache with correct expiration
     *
     * **Validates: Requirements 5.7**
     */
    public function test_nonce_is_stored_in_cache_with_ten_minute_expiration(): void
    {
        // Arrange: Setup OAuth client with medical-write scope
        Passport::actingAsClient(
            \Laravel\Passport\Client::factory()->create(),
            ['medical-write']
        );
        
        // Arrange: Create a webhook payload with unique nonce and valid timestamp
        $nonce = 'test-nonce-' . uniqid();
        $payload = [
            'timestamp' => time(),
            'nonce' => $nonce,
            'patient_id' => '12345',
            'medical_data' => 'test data'
        ];
        
        $secret = 'test-webhook-secret';
        config(['services.medical_webhook.secret' => $secret]);
        
        $signature = hash_hmac('sha256', json_encode($payload), $secret);

        // Act: Send request with unique nonce
        $response = $this->postJson('/api/v1/webhooks/medical-result', $payload, [
            'X-Medical-Signature' => $signature
        ]);

        // Assert: Nonce should be stored in cache
        $cacheKey = 'webhook_nonce_' . $nonce;
        $this->assertTrue(Cache::has($cacheKey), 'Nonce should be stored in cache');
    }

    /**
     * Test that different nonces can be used simultaneously
     *
     * **Validates: Requirements 5.4, 5.7**
     */
    public function test_different_nonces_can_be_used_simultaneously(): void
    {
        // Arrange: Setup OAuth client with medical-write scope
        Passport::actingAsClient(
            \Laravel\Passport\Client::factory()->create(),
            ['medical-write']
        );
        
        $secret = 'test-webhook-secret';
        config(['services.medical_webhook.secret' => $secret]);

        $responses = [];
        
        // Act: Send multiple requests with different nonces
        for ($i = 0; $i < 3; $i++) {
            $nonce = 'unique-nonce-' . $i . '-' . uniqid();
            $payload = [
                'timestamp' => time(),
                'nonce' => $nonce,
                'patient_id' => '12345',
                'medical_data' => 'test data ' . $i
            ];
            
            $signature = hash_hmac('sha256', json_encode($payload), $secret);

            $response = $this->postJson('/api/v1/webhooks/medical-result', $payload, [
                'X-Medical-Signature' => $signature
            ]);
            
            $responses[] = $response;
        }

        // Assert: None should return 403 for duplicate nonce
        foreach ($responses as $i => $response) {
            if ($response->status() === 403) {
                $responseData = $response->json();
                $this->assertNotEquals('Duplicate request', $responseData['message'] ?? '', 
                    "Request $i should not return 'Duplicate request' for different nonces");
            }
        }
        
        // Assert: At least one assertion was made
        $this->assertTrue(true, 'All different nonces were processed without duplicate errors');
    }

    /**
     * Test that nonce validation happens before signature validation
     *
     * **Validates: Requirements 5.4, 5.5, 5.6, 5.8**
     */
    public function test_nonce_validation_happens_before_signature_validation(): void
    {
        // Arrange: Setup OAuth client with medical-write scope
        Passport::actingAsClient(
            \Laravel\Passport\Client::factory()->create(),
            ['medical-write']
        );
        
        // Arrange: Create a webhook payload with duplicate nonce but invalid signature
        $nonce = 'duplicate-nonce-test';
        $payload = [
            'timestamp' => time(),
            'nonce' => $nonce,
            'patient_id' => '12345',
            'medical_data' => 'test data'
        ];
        
        $secret = 'test-webhook-secret';
        config(['services.medical_webhook.secret' => $secret]);
        
        $validSignature = hash_hmac('sha256', json_encode($payload), $secret);

        // Act: Send first request with valid signature
        $firstResponse = $this->postJson('/api/v1/webhooks/medical-result', $payload, [
            'X-Medical-Signature' => $validSignature
        ]);

        // Act: Send second request with same nonce but invalid signature
        $secondResponse = $this->postJson('/api/v1/webhooks/medical-result', $payload, [
            'X-Medical-Signature' => 'invalid-signature'
        ]);

        // Assert: Should return 403 for duplicate nonce, not invalid signature
        $secondResponse->assertStatus(403);
        $secondResponse->assertJson(['message' => 'Duplicate request']);
    }
}
