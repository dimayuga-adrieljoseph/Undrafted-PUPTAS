<?php

/**
 * Preservation Property Tests — IDP Login Non-Buggy Behaviors
 *
 * Validates: Requirements 3.1, 3.2, 3.3, 3.4
 *
 * These tests capture behaviors that MUST NOT change after the fix.
 * All tests should PASS on unfixed code — except the /login render test,
 * which shares the same route-conflict bug from task 1 and will pass after the fix.
 *
 * Observed on unfixed code:
 *   - GET /auth/idp/redirect → 302 redirect to IDP authorization URL ✓
 *   - GET /auth/idp/callback (no code) → 302 redirect to /login with idp error ✓
 *   - GET /login → FAILS on unfixed code (custom route wins, redirects to IDP instead of rendering Auth/Login)
 */

test('GET /auth/idp/redirect as guest redirects to the IDP authorization URL', function () {
    // Validates: Requirement 3.2 — GET /auth/idp/redirect SHALL CONTINUE TO redirect to the IDP authorization endpoint
    // This route is correctly wired on both fixed and unfixed code.

    $response = $this->get('/auth/idp/redirect');

    $response->assertRedirect();

    $location = $response->headers->get('Location');
    expect($location)->toContain('https://identity-provider.isaxbsit2027.com');
});

test('GET /auth/idp/callback without a code param as guest redirects to /login with an idp error', function () {
    // Validates: Requirement 3.3 — GET /auth/idp/callback SHALL CONTINUE TO handle missing code gracefully
    // The callback controller checks for a missing code and redirects back to /login with an error.

    $response = $this->get('/auth/idp/callback');

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors(['idp']);
});

test('GET /login as guest renders the Auth/Login Inertia page', function () {
    // Validates: Requirement 3.4 — GET /login SHALL CONTINUE TO render the login page
    //
    // NOTE: This test MAY FAIL on unfixed code — it encodes the CORRECT expected behavior.
    // The same route-conflict bug from task 1 causes the custom Route::get('/login', IdpAuthController@login)
    // to win over Fortify's login view route, returning a redirect instead of rendering Auth/Login.
    // This test will PASS after the fix in task 3.

    $response = $this->withoutVite()->get('/login');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
});
