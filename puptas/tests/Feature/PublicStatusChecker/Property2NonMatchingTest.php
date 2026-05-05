<?php

/**
 * Property 2: Non-matching inputs always return qualified: false
 *
 * For any (referenceNumber, email) pair that does not correspond to an existing
 * TestPasser record, the endpoint SHALL return qualified: false with the exact
 * neutral message "No matching record found. Please verify your details."
 *
 * Validates: Requirements 1.3, 3.1
 */

// ---------------------------------------------------------------------------
// Generate random (referenceNumber, email) pairs for property-based testing.
// Because RefreshDatabase is active, the test_passers table is empty for every
// test run, so every generated pair is guaranteed to be non-matching.
// ---------------------------------------------------------------------------

/**
 * Generate a deterministic but varied set of (referenceNumber, email) pairs.
 * None of these are inserted into the database, so all lookups will miss.
 */
function generateNonMatchingCases(): array
{
    $cases = [];
    $domains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'example.com', 'test.edu.ph', 'protonmail.com'];
    $prefixes = ['applicant', 'student', 'user', 'test', 'random', 'nobody', 'ghost', 'unknown', 'visitor', 'guest'];
    $years = ['2024', '2025', '2026', '2027'];

    $seed = 99;
    $iterations = propertyTestIterations();

    for ($i = 0; $i < $iterations; $i++) {
        // Deterministic pseudo-random generation
        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $prefix = $prefixes[$seed % count($prefixes)];

        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $domain = $domains[$seed % count($domains)];

        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $year = $years[$seed % count($years)];

        // Unique identifiers per iteration — offset by 9000 to avoid any
        // accidental collision with Property 1's 2026-000001..N range
        $uniqueId = str_pad((string)(9000 + $i + 1), 6, '0', STR_PAD_LEFT);
        $referenceNumber = $year . '-' . $uniqueId;
        $email = $prefix . $uniqueId . '@' . $domain;

        $cases[] = [$referenceNumber, $email];
    }

    return $cases;
}

$nonMatchingCases = generateNonMatchingCases();

// ===========================================================================
// Property 2 — Non-matching inputs always return qualified: false
// Submitting a (referenceNumber, email) pair absent from the DB returns
// qualified: false with the exact neutral message.
// ===========================================================================

/**
 * **Validates: Requirements 1.3, 3.1**
 */
it(
    'returns qualified: false and the neutral message for any non-matching (referenceNumber, email) pair',
    function (string $referenceNumber, string $email) {
        // The database is empty (RefreshDatabase) — no TestPasser records exist,
        // so every submitted pair is guaranteed to be non-matching.

        $response = $this->postJson('/api/public/check-status', [
            'referenceNumber' => $referenceNumber,
            'email'           => $email,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'qualified' => false,
                'message'   => 'No matching record found. Please verify your details.',
            ]);

        // Ensure batch_number is NOT present in a non-matching response
        $response->assertJsonMissingPath('batch_number');
    }
)->with($nonMatchingCases);
