<?php

/**
 * Bug Condition Exploration Tests — IDP Login Route Conflict
 *
 * Validates: Requirements 1.1, 1.2
 *
 * These tests encode the EXPECTED (correct) behavior.
 * On UNFIXED code, Test 1 FAILS — proving the route conflict bug exists.
 * On FIXED code, both tests PASS.
 *
 * Counterexample documented below after running on unfixed code.
 */

test('GET /login as guest returns HTTP 200 with Inertia Auth/Login component', function () {
    // Bug condition: Route::get('/login', IdpAuthController@login) in web.php
    // conflicts with Fortify's login view route. On unfixed code the custom
    // route wins and returns a 302 redirect to the IDP instead of rendering
    // the login page.
    //
    // Validates: Requirement 1.2 — GET /login SHALL exclusively render the Fortify login view

    $response = $this->withoutVite()->get('/login');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

test('GET /auth/idp/redirect as guest returns a 3xx redirect to the IDP authorization URL', function () {
    // This route is correctly wired on both fixed and unfixed code.
    // It should always redirect to the external IDP authorization endpoint.
    //
    // Validates: Requirement 1.1 — clicking IDP button SHALL redirect to /auth/idp/redirect
    //            which initiates the OAuth2 flow toward the IDP authorization endpoint

    $response = $this->get('/auth/idp/redirect');

    $response->assertRedirect();

    $location = $response->headers->get('Location');
    expect($location)->toContain('https://identity-provider.isaxbsit2027.com');
});
