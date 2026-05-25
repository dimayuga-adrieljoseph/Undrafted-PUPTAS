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
// Web Route — GET /admission-results
// ===========================================================================

it('GET /admission-results returns HTTP 200 without authentication', function () {
    $response = $this->get('/admission-results');

    $response->assertStatus(200);
});

it('GET /admission-results is an Inertia response', function () {
    $response = $this->get('/admission-results');

    // Route is accessible without auth and returns a successful response
    $response->assertStatus(200);
    // Inertia responses include the page data in the HTML
    $response->assertSee('data-page', false);
});

// ===========================================================================
// API Route — POST /api/public/admission-results
// ===========================================================================

it('POST /api/public/admission-results with valid payload returns 200 without auth', function () {
    // Insert a matching record so the endpoint can return a result
    TestPasser::create([
        'surname'          => 'Dela Cruz',
        'first_name'       => 'Juan',
        'email'            => 'juan.delacruz@example.com',
        'reference_number' => '2026-900001',
        'batch_number'     => 'Batch 1',
    ]);

    $response = $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-900001',
        'firstName'       => 'Juan',
        'lastName'  => 'Dela Cruz',
    ]);

    $response->assertStatus(200);
});

it('POST /api/public/admission-results with invalid payload returns 422 without auth', function () {
    $response = $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '',
        'firstName'       => '',
        'lastName'        => '',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['referenceNumber', 'firstName', 'lastName']);
});

it('POST /api/public/admission-results with missing fields returns 422 without auth', function () {
    $response = $this->postJson('/api/public/admission-results', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['referenceNumber', 'firstName', 'lastName']);
});
