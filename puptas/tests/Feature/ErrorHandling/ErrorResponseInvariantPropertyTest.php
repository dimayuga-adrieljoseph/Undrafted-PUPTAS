<?php

// Feature: error-handling — Task 4.4
// Property 1: ErrorResponse structural invariant
// Property 2: Content-Type is always application/json
// Validates: Requirements 1.2, 1.3, 1.6, 7.3

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// ---------------------------------------------------------------------------
// Generate 100+ exception instances covering all mapped types plus random
// RuntimeException subclasses with varied messages.
// We avoid using facades at dataset-build time by deferring ValidationException
// creation to the test body.
// ---------------------------------------------------------------------------

function buildExceptionCases(): array
{
    $cases = [];

    // All explicitly mapped exception types (no facades needed at build time)
    $cases[] = [new AuthenticationException(), 'authentication'];
    $cases[] = [new AuthorizationException(), 'authorization'];
    $cases[] = [(new ModelNotFoundException())->setModel('App\\Models\\User'), 'model-not-found'];
    $cases[] = [new NotFoundHttpException(), 'not-found-http'];

    // Generic RuntimeException with varied messages (fill to 100+)
    $messages = [
        'Something broke',
        'Database connection failed',
        'Unexpected null value',
        'Division by zero',
        'File not found: /var/www/app/storage/file.txt',
        'SELECT * FROM users WHERE id = 1',
        'INSERT INTO logs VALUES (1)',
        'Stack trace: #0 /app/Handler.php(42)',
        '',
        'Error in ' . str_repeat('x', 200),
    ];

    $seed = 7;
    while (count($cases) < 110) {
        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $msg = $messages[$seed % count($messages)];
        $cases[] = [new \RuntimeException($msg), 'runtime-' . count($cases)];
    }

    // A few other Throwable subclasses (also catch-all)
    $cases[] = [new \LogicException('Logic error'), 'logic'];
    $cases[] = [new \InvalidArgumentException('Bad arg'), 'invalid-arg'];
    $cases[] = [new \OverflowException('Overflow'), 'overflow'];
    $cases[] = [new \UnderflowException('Underflow'), 'underflow'];

    return $cases;
}

$exceptionCases = buildExceptionCases();

// ---------------------------------------------------------------------------
// Property 1 & 2: ErrorResponse structural invariant + Content-Type
// Validates: Requirements 1.2, 1.3, 1.6, 7.3
// ---------------------------------------------------------------------------

/**
 * For any exception type thrown during an HTTP request, the Handler SHALL
 * return a response whose JSON body satisfies:
 *   - success === false
 *   - message is a non-empty string
 *   - errorCode is a non-empty string
 * And the Content-Type header SHALL be application/json.
 */
it(
    'Property 1 & 2: ErrorResponse structural invariant and Content-Type hold for any exception type',
    function (\Throwable $exception, string $label) {
        Route::get('/_test/invariant/' . $label, function () use ($exception) {
            throw $exception;
        });

        $response = $this->getJson('/_test/invariant/' . $label);

        // Property 2: Content-Type must be application/json
        $response->assertHeader('Content-Type', 'application/json');

        $body = $response->json();

        // Property 1: success must be false
        expect($body['success'])->toBeFalse();

        // Property 1: message must be a non-empty string
        expect($body)->toHaveKey('message');
        expect($body['message'])->toBeString();
        expect(strlen($body['message']))->toBeGreaterThan(0);

        // Property 1: errorCode must be a non-empty string
        expect($body)->toHaveKey('errorCode');
        expect($body['errorCode'])->toBeString();
        expect(strlen($body['errorCode']))->toBeGreaterThan(0);
    }
)->with($exceptionCases);

// ---------------------------------------------------------------------------
// ValidationException is tested separately (requires Validator facade at runtime)
// ---------------------------------------------------------------------------

it('Property 1 & 2: ValidationException also satisfies ErrorResponse structural invariant', function () {
    Route::get('/_test/invariant/validation', function () {
        $validator = \Illuminate\Support\Facades\Validator::make([], ['field' => 'required']);
        throw new \Illuminate\Validation\ValidationException($validator);
    });

    $response = $this->getJson('/_test/invariant/validation');

    $response->assertHeader('Content-Type', 'application/json');

    $body = $response->json();

    expect($body['success'])->toBeFalse();
    expect($body['message'])->toBeString()->not->toBeEmpty();
    expect($body['errorCode'])->toBeString()->not->toBeEmpty();
});

