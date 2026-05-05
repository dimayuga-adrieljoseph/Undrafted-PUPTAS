<?php

/**
 * Route Registration Integration Tests
 *
 * Verifies that the public admission status checker routes are correctly
 * registered and accessible without authentication.
 *
 * Validates: Requirements 1.1, 7.8
 */

use App\Models\TestPasser;

// ===========================================================================
// Web Route — GET /check-status
// ===========================================================================

it('GET /check-status returns HTTP 200 without authentication', function () {
    $response = $this->get('/check-status');

    $response->assertStatus(200);
});

it('GET /check-status is an Inertia response', function () {
    $response = $this->get('/check-status');

    // Route is accessible without auth and returns a successful response
    $response->assertStatus(200);
    // Inertia responses include the page data in the HTML
    $response->assertSee('data-page', false);
});

// ===========================================================================
// API Route — POST /api/public/check-status
// ===========================================================================

it('POST /api/public/check-status with valid payload returns 200 without auth', function () {
    // Insert a matching record so the endpoint can return a result
    TestPasser::create([
        'surname'          => 'Dela Cruz',
        'first_name'       => 'Juan',
        'email'            => 'juan.delacruz@example.com',
        'reference_number' => 'ROUTE-TEST-001',
        'batch_number'     => 'Batch 1',
    ]);

    $response = $this->postJson('/api/public/check-status', [
        'referenceNumber' => 'ROUTE-TEST-001',
        'email'           => 'juan.delacruz@example.com',
    ]);

    $response->assertStatus(200);
});

it('POST /api/public/check-status with invalid payload returns 422 without auth', function () {
    $response = $this->postJson('/api/public/check-status', [
        'referenceNumber' => '',
        'email'           => 'not-a-valid-email',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['referenceNumber', 'email']);
});

it('POST /api/public/check-status with missing fields returns 422 without auth', function () {
    $response = $this->postJson('/api/public/check-status', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['referenceNumber', 'email']);
});
