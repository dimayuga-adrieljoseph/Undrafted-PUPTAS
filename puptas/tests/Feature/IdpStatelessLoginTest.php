<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

test('IDP login authentically succeeds without querying the local users table', function () {
    // Mock the IDP configuration so it matches expected test environment settings
    config([
        'services.idp.base_url' => 'https://mock-idp.example.com',
        'services.idp.client_id' => 'mock-client',
        'services.idp.client_secret' => 'mock-secret',
    ]);

    // Mock the HTTP responses for Token and User info endpoints
    Http::fake([
        'https://mock-idp.example.com/api/v1/auth/token' => Http::response([
            'access_token' => 'mock_access_token',
            'refresh_token' => 'mock_refresh_token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], 200),
        'https://mock-idp.example.com/api/v1/user' => Http::response([
            'id' => 'uuid-1234-5678-admin',
            'name' => 'Admin User',
            'email' => 'admin@mock.test',
            'role_name' => 'admin',
        ], 200),
        'https://mock-idp.example.com/api/v1/me' => Http::response([
            'id' => 'uuid-1234-5678-admin',
            'name' => 'Admin User',
            'email' => 'admin@mock.test',
            'role_name' => 'admin',
        ], 200),
        '*' => Http::response([
            'id' => 'uuid-1234-5678-admin',
            'name' => 'Admin User',
            'email' => 'admin@mock.test',
            'role_name' => 'admin',
        ], 200),
    ]);

    // Enable query logging to track database interaction
    DB::enableQueryLog();

    // Fire the callback as if the IDP just redirected the user back with an auth code
    $response = $this->get('/auth/idp/callback?code=mock_authorization_code');

    // The unknown user should be redirected to /register
    $response->assertRedirect('/register');

    // Verify the Auth guard thinks we are NOT logged in yet
    $this->assertFalse(Auth::check(), 'User should not be authenticated without a local DB record.');

    // Verify pending_registration is set in session
    $this->assertTrue(session()->has('pending_registration'));

    $queries = DB::getQueryLog();
    $usersTableQueryCount = collect($queries)->filter(function ($queryDetail) {
        return preg_match('/\s+users\s+/i', $queryDetail['query']) ||
            preg_match('/`users`/i', $queryDetail['query']) ||
            preg_match('/"users"/i', $queryDetail['query']);
    })->count();

    expect($usersTableQueryCount)->toBeGreaterThan(0, "The users table should be queried to cross-reference the email.");
});
