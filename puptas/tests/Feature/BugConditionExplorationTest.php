<?php

/**
 * Bug Condition Exploration Tests — Applicant Flow Fix
 *
 * Validates: Requirements 1.1, 1.2, 1.4
 *
 * These tests encode the EXPECTED (correct) behavior.
 * On UNFIXED code, ALL THREE tests FAIL — proving the bugs exist.
 * On FIXED code, all tests PASS.
 *
 * Counterexamples documented at the bottom of this file after running on unfixed code.
 */

use App\Helpers\FileMapper;
use App\Models\ApplicantProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// ---------------------------------------------------------------------------
// Bug 1 — Login redirect: applicant with strand="STEM" and no grades
//          should redirect to /applicant-dashboard, not /grades/stem
// ---------------------------------------------------------------------------

test('Bug 1: applicant with strand STEM and no grades redirects to /applicant-dashboard after login', function () {
    // Bug condition: isBugCondition_1(user) — role_id=1, no grades record, strand="STEM"
    // On unfixed code, AuthenticatedSessionController::toResponse redirects to /grades/stem.
    // This assertion FAILS on unfixed code — proving Bug 1 exists.
    //
    // Validates: Requirement 1.1

    $user = User::factory()->create([
        'role_id'  => 1,
        'password' => Hash::make('password'),
    ]);

    ApplicantProfile::create([
        'user_id' => $user->id,
        'strand'  => 'STEM',
    ]);

    // No Grade record created — user has no grades on file.

    $response = $this->post('/login', [
        'email'    => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/applicant-dashboard');
});

// ---------------------------------------------------------------------------
// Bug 2 — Unknown graduate type: formatFilesForGraduateType should return []
//          for null or unrecognized graduate types, not a 13-key null array
// ---------------------------------------------------------------------------

test('Bug 2a: formatFilesForGraduateType with null graduate type returns empty array', function () {
    // Bug condition: isBugCondition_2(null) — null is not a recognized graduate type
    // On unfixed code, returns array_fill_keys(array_keys(MAPPING), null) — 13 null slots.
    // This assertion FAILS on unfixed code — proving Bug 2 exists.
    //
    // Validates: Requirement 1.2

    $result = FileMapper::formatFilesForGraduateType(collect([]), null);

    expect($result)->toBe([]);
});

test('Bug 2b: formatFilesForGraduateType with unknown graduate type returns empty array', function () {
    // Bug condition: isBugCondition_2('Unknown Type') — not a recognized graduate type
    // On unfixed code, returns array_fill_keys(array_keys(MAPPING), null) — 13 null slots.
    // This assertion FAILS on unfixed code — proving Bug 2 variant exists.
    //
    // Validates: Requirement 1.2

    $result = FileMapper::formatFilesForGraduateType(collect([]), 'Unknown Type');

    expect($result)->toBe([]);
});

// ---------------------------------------------------------------------------
// Bug 3 — Missing extract route: POST /api/grades/extract should return HTTP 200
//          (or at minimum not 404 — route must be registered and reachable)
// ---------------------------------------------------------------------------

test('Bug 3: POST /api/grades/extract as authenticated applicant is reachable (not 404)', function () {
    // Bug condition: isBugCondition_3 — POST /api/grades/extract is not registered in api.php
    // On unfixed code, the route is missing and returns 404.
    // After the fix, the route is registered — any non-404 response confirms the route exists.
    // A 422 is acceptable here (no files uploaded → InvalidArgumentException → 422).
    //
    // Validates: Requirement 1.4

    $user = User::factory()->create(['role_id' => 1]);

    $response = $this->actingAs($user)->postJson('/api/grades/extract');

    $response->assertStatus(422); // Route is registered; 422 = no files, not 404 = missing route
});

/*
 * ---------------------------------------------------------------------------
 * Counterexamples documented after running on UNFIXED code
 * ---------------------------------------------------------------------------
 *
 * Bug 1 — FAILED: Login for applicant with strand="STEM" and no grades redirects to
 *          /grades/stem instead of /applicant-dashboard.
 *          Counterexample: assertRedirect('/applicant-dashboard') failed —
 *          actual redirect was to /grades/stem.
 *
 * Bug 2a — FAILED: formatFilesForGraduateType(collect([]), null) returned a 13-key
 *           array with all null values instead of [].
 *           Counterexample: expect($result)->toBe([]) failed —
 *           actual result had 13 keys: file10Front, file10, file11Front, file11,
 *           file12Front, file12, nof137a, schoolId, nonEnrollCert, psa, goodMoral,
 *           underOath, photo2x2 — all null.
 *
 * Bug 2b — FAILED: formatFilesForGraduateType(collect([]), 'Unknown Type') returned
 *           a 13-key array with all null values instead of [].
 *           Counterexample: same 13-key null array as Bug 2a.
 *
 * Bug 3 — FAILED: POST /api/grades/extract returned 404 (route not registered in api.php).
 *          Counterexample: assertStatus(422) failed — actual status was 404.
 *          After fix: route is registered, returns 422 (no files uploaded) — not 404.
 * ---------------------------------------------------------------------------
 */
