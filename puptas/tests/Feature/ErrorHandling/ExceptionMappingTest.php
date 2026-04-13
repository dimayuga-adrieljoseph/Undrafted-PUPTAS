<?php

// Feature: error-handling — Task 4.1
// PHPUnit feature tests for each exception-to-status mapping.

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

// Register ephemeral test routes before each test
beforeEach(function () {
    Route::get('/_test/validation', function () {
        $validator = \Illuminate\Support\Facades\Validator::make([], ['name' => 'required']);
        throw new ValidationException($validator);
    });

    Route::get('/_test/authentication', function () {
        throw new AuthenticationException();
    });

    Route::get('/_test/authorization', function () {
        throw new AuthorizationException();
    });

    Route::get('/_test/model-not-found', function () {
        throw (new ModelNotFoundException())->setModel(\App\Models\User::class);
    });

    Route::get('/_test/runtime', function () {
        throw new \RuntimeException('Something broke internally');
    });
});

// ---------------------------------------------------------------------------
// 4.1.1 — ValidationException → 422 VALIDATION_ERROR
// ---------------------------------------------------------------------------

it('returns 422 with VALIDATION_ERROR and errors field for ValidationException', function () {
    $response = $this->getJson('/_test/validation');

    $response->assertStatus(422)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'success'   => false,
            'errorCode' => 'VALIDATION_ERROR',
        ])
        ->assertJsonStructure(['success', 'message', 'errorCode', 'errors']);
});

// ---------------------------------------------------------------------------
// 4.1.2 — AuthenticationException → 401 UNAUTHENTICATED
// ---------------------------------------------------------------------------

it('returns 401 with UNAUTHENTICATED for AuthenticationException', function () {
    $response = $this->getJson('/_test/authentication');

    $response->assertStatus(401)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'success'   => false,
            'errorCode' => 'UNAUTHENTICATED',
            'message'   => 'You are not authenticated. Please log in.',
        ]);
});

// ---------------------------------------------------------------------------
// 4.1.3 — AuthorizationException → 403 FORBIDDEN
// ---------------------------------------------------------------------------

it('returns 403 with FORBIDDEN for AuthorizationException', function () {
    $response = $this->getJson('/_test/authorization');

    $response->assertStatus(403)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'success'   => false,
            'errorCode' => 'FORBIDDEN',
            'message'   => 'You do not have permission to perform this action.',
        ]);
});

// ---------------------------------------------------------------------------
// 4.1.4 — ModelNotFoundException → 404 NOT_FOUND
// ---------------------------------------------------------------------------

it('returns 404 with NOT_FOUND for ModelNotFoundException', function () {
    $response = $this->getJson('/_test/model-not-found');

    $response->assertStatus(404)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'success'   => false,
            'errorCode' => 'NOT_FOUND',
            'message'   => 'The requested resource was not found.',
        ]);
});

// ---------------------------------------------------------------------------
// 4.1.5 — RuntimeException → 500 INTERNAL_ERROR
// ---------------------------------------------------------------------------

it('returns 500 with INTERNAL_ERROR for generic RuntimeException', function () {
    $response = $this->getJson('/_test/runtime');

    $response->assertStatus(500)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'success'   => false,
            'errorCode' => 'INTERNAL_ERROR',
            'message'   => 'Something went wrong. Please try again later.',
        ]);
});
