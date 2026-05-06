<?php

/**
 * Unit tests for CheckStatusRequest validation.
 *
 * These tests verify that the POST /api/public/check-status endpoint
 * correctly validates the referenceNumber and email fields and returns
 * 422 Unprocessable Entity with appropriate error messages when validation fails.
 *
 * Validates: Requirements 2.1, 2.2, 2.3, 2.4
 */

test('missing referenceNumber returns 422 with error on referenceNumber', function () {
    $response = $this->postJson('/api/public/check-status', [
        'email' => 'applicant@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['referenceNumber'])
        ->assertJsonMissingValidationErrors(['email']);
});

test('missing email returns 422 with error on email', function () {
    $response = $this->postJson('/api/public/check-status', [
        'referenceNumber' => '2026-000123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email'])
        ->assertJsonMissingValidationErrors(['referenceNumber']);
});

test('invalid email format returns 422 with error on email', function () {
    $response = $this->postJson('/api/public/check-status', [
        'referenceNumber' => '2026-000123',
        'email'           => 'not-a-valid-email',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email'])
        ->assertJsonMissingValidationErrors(['referenceNumber']);
});

test('both fields missing returns 422 with errors on both fields', function () {
    $response = $this->postJson('/api/public/check-status', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['referenceNumber', 'email']);
});

test('both fields valid passes validation and does not return 422', function () {
    $response = $this->postJson('/api/public/check-status', [
        'referenceNumber' => '2026-000123',
        'email'           => 'applicant@example.com',
    ]);

    $response->assertStatus(200);
});
