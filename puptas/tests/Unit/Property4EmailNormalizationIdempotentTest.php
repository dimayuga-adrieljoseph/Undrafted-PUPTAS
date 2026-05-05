<?php

/**
 * Property 4: Email normalization is idempotent
 *
 * For any string s, applying strtolower(trim(...)) twice SHALL produce the
 * same result as applying it once.
 *
 * This is a pure unit test — no HTTP, no database.
 *
 * Validates: Requirements 8.2
 */

uses(Tests\TestCase::class);

// ---------------------------------------------------------------------------
// Helper: generate random strings that cover the normalization edge cases
// ---------------------------------------------------------------------------

/**
 * Generate a varied set of raw strings for idempotency testing.
 *
 * Includes:
 *  - plain lowercase emails
 *  - mixed-case emails
 *  - emails with leading/trailing whitespace
 *  - emails with both mixed case AND whitespace
 *  - arbitrary strings (non-email) to prove the property holds universally
 */
function generateIdempotencyInputs(): array
{
    $inputs = [];

    $localParts  = ['user', 'JOHN.DOE', 'Maria.Santos', 'jose123', 'ANA_REYES', 'pedro'];
    $domains     = ['gmail.com', 'YAHOO.COM', 'Outlook.Com', 'example.EDU.PH', 'test.com'];
    $paddings    = ['', ' ', '  ', "\t", " \t ", "  \t  "];
    $arbitraries = [
        'Hello World',
        'UPPERCASE STRING',
        'MiXeD CaSe',
        '   spaces   ',
        "\ttabs\t",
        '',
        '123',
        'already@lowercase.com',
        'ALREADY@UPPERCASE.COM',
    ];

    $seed       = 99;
    $iterations = propertyTestIterations();

    for ($i = 0; $i < $iterations; $i++) {
        // Deterministic pseudo-random selection
        $seed      = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $local     = $localParts[$seed % count($localParts)];

        $seed      = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $domain    = $domains[$seed % count($domains)];

        $seed      = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $padLeft   = $paddings[$seed % count($paddings)];

        $seed      = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $padRight  = $paddings[$seed % count($paddings)];

        // Compose a padded, mixed-case email string
        $inputs[] = [$padLeft . $local . '@' . $domain . $padRight];
    }

    // Also include the arbitrary strings to prove universality
    foreach ($arbitraries as $s) {
        $inputs[] = [$s];
    }

    return $inputs;
}

$idempotencyInputs = generateIdempotencyInputs();

// ===========================================================================
// Property 4 — Email normalization is idempotent
// strtolower(trim(strtolower(trim(s)))) === strtolower(trim(s)) for all s
// ===========================================================================

/**
 * **Validates: Requirements 8.2**
 */
it(
    'email normalization is idempotent: applying it twice equals applying it once',
    function (string $s) {
        $normalizeOnce = strtolower(trim($s));
        $normalizeTwice = strtolower(trim(strtolower(trim($s))));

        expect($normalizeTwice)->toBe($normalizeOnce);
    }
)->with($idempotencyInputs);
