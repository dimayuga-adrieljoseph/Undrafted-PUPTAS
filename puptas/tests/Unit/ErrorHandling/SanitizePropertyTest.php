<?php

// Feature: error-handling — Task 4.2
// Property 4: Sensitive field sanitization
// Validates: Requirements 2.7, 2.8

uses(Tests\TestCase::class);

/**
 * Call the private sanitize() method via reflection.
 */
function callSanitize(array $data): array
{
    $handler = app(\App\Exceptions\Handler::class);
    $ref = new \ReflectionMethod(\App\Exceptions\Handler::class, 'sanitize');
    $ref->setAccessible(true);
    return $ref->invoke($handler, $data);
}

// ---------------------------------------------------------------------------
// Generator helpers
// ---------------------------------------------------------------------------

$sensitiveFields = [
    'password',
    'password_confirmation',
    'token',
    'secret',
    'api_key',
    'authorization',
];

$nonSensitiveFields = [
    'name', 'email', 'username', 'age', 'address', 'phone',
    'city', 'country', 'zip', 'role', 'status', 'type',
    'description', 'title', 'notes', 'comment', 'data',
    'value', 'key', 'id', 'uuid', 'code', 'label',
];

/**
 * Build 100+ test cases: each is an array of [inputData, sensitiveKeysPresent, nonSensitiveKeysPresent].
 */
function generateSanitizeCases(array $sensitiveFields, array $nonSensitiveFields): array
{
    $cases = [];
    $seed = 42;

    $rand = function (int $min, int $max) use (&$seed): int {
        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        return $min + ($seed % ($max - $min + 1));
    };

    while (count($cases) < 120) {
        $inputData = [];
        $presentSensitive = [];
        $presentNonSensitive = [];

        // Pick a random subset of sensitive fields (0–6)
        $numSensitive = $rand(0, count($sensitiveFields));
        $shuffledSensitive = $sensitiveFields;
        for ($i = count($shuffledSensitive) - 1; $i > 0; $i--) {
            $j = $rand(0, $i);
            [$shuffledSensitive[$i], $shuffledSensitive[$j]] = [$shuffledSensitive[$j], $shuffledSensitive[$i]];
        }
        $chosenSensitive = array_slice($shuffledSensitive, 0, $numSensitive);

        // Pick a random subset of non-sensitive fields (1–5)
        $numNonSensitive = $rand(1, min(5, count($nonSensitiveFields)));
        $shuffledNonSensitive = $nonSensitiveFields;
        for ($i = count($shuffledNonSensitive) - 1; $i > 0; $i--) {
            $j = $rand(0, $i);
            [$shuffledNonSensitive[$i], $shuffledNonSensitive[$j]] = [$shuffledNonSensitive[$j], $shuffledNonSensitive[$i]];
        }
        $chosenNonSensitive = array_slice($shuffledNonSensitive, 0, $numNonSensitive);

        // Add sensitive fields with a non-empty value
        foreach ($chosenSensitive as $field) {
            $inputData[$field] = 'secret-value-' . $rand(1000, 9999);
            $presentSensitive[] = $field;
        }

        // Add non-sensitive fields with a value
        foreach ($chosenNonSensitive as $field) {
            $value = 'value-' . $rand(1000, 9999);
            $inputData[$field] = $value;
            $presentNonSensitive[$field] = $value;
        }

        // Occasionally nest some sensitive fields inside a sub-array
        if ($rand(0, 3) === 0 && count($chosenSensitive) > 0) {
            $nestedSensitive = array_splice($chosenSensitive, 0, 1)[0];
            $inputData['nested'] = [$nestedSensitive => 'nested-secret-' . $rand(1000, 9999)];
            $presentSensitive[] = 'nested.' . $nestedSensitive;
        }

        $cases[] = [$inputData, $presentSensitive, $presentNonSensitive];
    }

    return $cases;
}

$sanitizeCases = generateSanitizeCases($sensitiveFields, $nonSensitiveFields);

// ---------------------------------------------------------------------------
// Property 4: Sensitive field sanitization
// Validates: Requirements 2.7, 2.8
// ---------------------------------------------------------------------------

/**
 * For any request data containing any subset of the SensitiveFields, the
 * sanitize() method SHALL replace every sensitive key with "[REDACTED]"
 * while all non-sensitive keys retain their original values.
 */
it(
    'Property 4: sanitize() redacts all sensitive fields and preserves non-sensitive fields',
    function (array $inputData, array $presentSensitive, array $presentNonSensitive) {
        $result = callSanitize($inputData);

        // Every top-level sensitive key must be "[REDACTED]"
        foreach ($presentSensitive as $field) {
            if (str_contains($field, '.')) {
                [$parent, $child] = explode('.', $field, 2);
                if (isset($result[$parent][$child])) {
                    expect($result[$parent][$child])->toBe('[REDACTED]');
                }
            } elseif (array_key_exists($field, $inputData)) {
                expect($result[$field])->toBe('[REDACTED]');
            }
        }

        // Every non-sensitive key must retain its original value
        foreach ($presentNonSensitive as $field => $originalValue) {
            expect($result[$field])->toBe($originalValue);
        }
    }
)->with($sanitizeCases);

// ---------------------------------------------------------------------------
// Additional explicit edge-case tests
// ---------------------------------------------------------------------------

it('sanitize() redacts all six sensitive fields when all are present', function () {
    $input = [
        'password'              => 'hunter2',
        'password_confirmation' => 'hunter2',
        'token'                 => 'abc123',
        'secret'                => 'mysecret',
        'api_key'               => 'key-xyz',
        'authorization'         => 'Bearer token',
        'username'              => 'alice',
        'email'                 => 'alice@example.com',
    ];

    $result = callSanitize($input);

    expect($result['password'])->toBe('[REDACTED]');
    expect($result['password_confirmation'])->toBe('[REDACTED]');
    expect($result['token'])->toBe('[REDACTED]');
    expect($result['secret'])->toBe('[REDACTED]');
    expect($result['api_key'])->toBe('[REDACTED]');
    expect($result['authorization'])->toBe('[REDACTED]');
    expect($result['username'])->toBe('alice');
    expect($result['email'])->toBe('alice@example.com');
});

it('sanitize() redacts sensitive fields nested inside sub-arrays', function () {
    $input = [
        'user' => [
            'name'     => 'Bob',
            'password' => 'secret123',
            'token'    => 'tok-abc',
        ],
        'meta' => ['source' => 'web'],
    ];

    $result = callSanitize($input);

    expect($result['user']['password'])->toBe('[REDACTED]');
    expect($result['user']['token'])->toBe('[REDACTED]');
    expect($result['user']['name'])->toBe('Bob');
    expect($result['meta']['source'])->toBe('web');
});

it('sanitize() returns empty array unchanged', function () {
    expect(callSanitize([]))->toBe([]);
});

it('sanitize() preserves non-sensitive-only data unchanged', function () {
    $input = ['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 30];
    expect(callSanitize($input))->toBe($input);
});
