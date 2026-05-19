<?php

/**
 * Unit tests for CheckStatusRequest validation.
 *
 * These tests verify that the POST /api/public/admission-results endpoint
 * correctly validates the referenceNumber, firstName, and lastName fields.
 */

test('missing referenceNumber returns 422 with error on referenceNumber', function () {
    $response = $this->postJson('/api/public/admission-results', [
        'firstName' => 'Juan',
        'lastName'  => 'Dela Cruz',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['referenceNumber'])
        ->assertJsonMissingValidationErrors(['firstName', 'lastName']);
});

test('missing firstName returns 422 with error on firstName', function () {
    $response = $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-000123',
        'lastName'        => 'Dela Cruz',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['firstName'])
        ->assertJsonMissingValidationErrors(['referenceNumber', 'lastName']);
});

test('missing lastName returns 422 with error on lastName', function () {
    $response = $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-000123',
        'firstName'       => 'Juan',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['lastName'])
        ->assertJsonMissingValidationErrors(['referenceNumber', 'firstName']);
});

test('invalid referenceNumber format returns 422', function () {
    $response = $this->postJson('/api/public/admission-results', [
        'referenceNumber' => 'invalid#format',
        'firstName'       => 'Juan',
        'lastName'        => 'Dela Cruz',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['referenceNumber']);
});

test('invalid firstName format returns 422', function () {
    $response = $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-000123',
        'firstName'       => 'Juan123',
        'lastName'        => 'Dela Cruz',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['firstName']);
});

test('invalid lastName format returns 422', function () {
    $response = $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-000123',
        'firstName'       => 'Juan',
        'lastName'        => 'Dela Cruz!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['lastName']);
});

test('all fields missing returns 422 with errors on all fields', function () {
    $response = $this->postJson('/api/public/admission-results', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['referenceNumber', 'firstName', 'lastName']);
});

test('all fields valid passes validation and does not return 422', function () {
    $response = $this->postJson('/api/public/admission-results', [
        'referenceNumber' => '2026-000123',
        'firstName'       => 'Juan',
        'lastName'        => 'Dela Cruz',
    ]);

    // May return 200 (since no DB records match)
    $response->assertStatus(200);
});
