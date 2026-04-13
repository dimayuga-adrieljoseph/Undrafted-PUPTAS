<?php

// Feature: error-handling — Task 4.3
// Verifies logStructured() writes the correct keys via Log::fake().
// Validates: Requirements 2.1–2.8, Property 5

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// 4.3.1 — Unauthenticated request: user_id must be null
// ---------------------------------------------------------------------------

it('logStructured() writes method, endpoint, and null user_id for unauthenticated requests', function () {
    $log = Log::spy();

    Route::get('/_test/log-unauth', function () {
        throw new \RuntimeException('test error');
    });

    $this->getJson('/_test/log-unauth');

    $log->shouldHaveReceived('error')->withArgs(function ($message, $context) {
        return $message === 'exception'
            && array_key_exists('method', $context)
            && array_key_exists('endpoint', $context)
            && array_key_exists('user_id', $context)
            && $context['user_id'] === null
            && $context['method'] === 'GET'
            && $context['endpoint'] === '_test/log-unauth';
    });
});

// ---------------------------------------------------------------------------
// 4.3.2 — Authenticated request: user_id must equal the authenticated user's ID
// ---------------------------------------------------------------------------

it('logStructured() writes the authenticated user_id for authenticated requests', function () {
    $log = Log::spy();

    $user = User::factory()->create();

    Route::get('/_test/log-auth', function () {
        throw new \RuntimeException('test error');
    });

    $this->actingAs($user)->getJson('/_test/log-auth');

    $log->shouldHaveReceived('error')->withArgs(function ($message, $context) use ($user) {
        return $message === 'exception'
            && array_key_exists('user_id', $context)
            && $context['user_id'] === $user->id;
    });
});

// ---------------------------------------------------------------------------
// 4.3.3 — All required StructuredLog keys are present
// ---------------------------------------------------------------------------

it('logStructured() writes all required StructuredLog keys', function () {
    $log = Log::spy();

    Route::get('/_test/log-keys', function () {
        throw new \RuntimeException('structured log test');
    });

    $this->getJson('/_test/log-keys');

    $log->shouldHaveReceived('error')->withArgs(function ($message, $context) {
        $requiredKeys = [
            'message',
            'exception',
            'trace',
            'timestamp',
            'method',
            'endpoint',
            'user_id',
            'request_data',
        ];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $context)) {
                return false;
            }
        }

        return $message === 'exception';
    });
});

// ---------------------------------------------------------------------------
// 4.3.4 — Sensitive fields in request data are redacted in the log
// ---------------------------------------------------------------------------

it('logStructured() redacts sensitive fields in request_data', function () {
    $log = Log::spy();

    Route::post('/_test/log-sensitive', function () {
        throw new \RuntimeException('sensitive test');
    });

    $this->postJson('/_test/log-sensitive', [
        'username' => 'alice',
        'password' => 'hunter2',
        'token'    => 'abc123',
    ]);

    $log->shouldHaveReceived('error')->withArgs(function ($message, $context) {
        if ($message !== 'exception') {
            return false;
        }

        $data = $context['request_data'] ?? [];

        return ($data['password'] ?? null) === '[REDACTED]'
            && ($data['token'] ?? null) === '[REDACTED]'
            && ($data['username'] ?? null) === 'alice';
    });
});
