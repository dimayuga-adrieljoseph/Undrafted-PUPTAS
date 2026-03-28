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

    // The admin user should be redirected to the /dashboard based on the role mapping
    $response->assertRedirect('/dashboard');

    // Verify the Auth guard thinks we are currently logged in
    $this->assertTrue(Auth::check(), 'User should be authenticated after callback.');

    // Get all SQL queries executed during the request
    $queries = DB::getQueryLog();

    // Check that none of the queries touched the local 'users' table
    $usersTableQueryCount = collect($queries)->filter(function ($queryDetail) {
        // Look for the table name strictly (e.g., 'users' or "users")
        // We look for common patterns representing queries against the users table
        return preg_match('/\s+users\s+/i', $queryDetail['query']) ||
            preg_match('/`users`/i', $queryDetail['query']) ||
            preg_match('/"users"/i', $queryDetail['query']);
    })->count();

    // Ensure 0 queries hit the users table
    expect($usersTableQueryCount)->toBe(0, "The users table was queried {$usersTableQueryCount} times during login. It should be 0.");
});
