<?php

/**
 * Property 6: Response always returns HTTP 200 for valid (non-rate-limited) requests
 *
 * For any valid request (passes validation, not rate-limited), the HTTP response
 * status SHALL be 200 regardless of whether the lookup matched or not.
 *
 * Validates: Requirements 3.2
 */

use App\Models\TestPasser;

// ---------------------------------------------------------------------------
// Generate a mixed set of cases: some matching (TestPasser record exists),
// some non-matching (no record in DB). Both types must return HTTP 200.
// ---------------------------------------------------------------------------

/**
 * Generate a deterministic but varied set of mixed cases.
 * Each case is either:
 *   - 'matching': a TestPasser record will be created and matching credentials submitted
 *   - 'non_matching': credentials submitted with no corresponding DB record
 *
 * Offset reference numbers by 7000 to avoid collision with other property tests.
 */
function generateHttp200Cases(): array
{
    $cases = [];
    $batches = ['Batch 1', 'Batch 2', 'Batch 3'];
    $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'example.com', 'test.edu.ph'];
    $surnames = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Torres', 'Flores', 'Ramos', 'Dela Cruz'];
    $firstNames = ['Maria', 'Jose', 'Juan', 'Ana', 'Pedro', 'Rosa', 'Carlos', 'Elena', 'Miguel', 'Luz'];

    $seed = 55;
    $iterations = propertyTestIterations();

    for ($i = 0; $i < $iterations; $i++) {
        // Deterministic pseudo-random generation
        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $surname = $surnames[$seed % count($surnames)];

        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $firstName = $firstNames[$seed % count($firstNames)];

        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $domain = $domains[$seed % count($domains)];

        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $batch = $batches[$seed % count($batches)];

        // Alternate between matching and non-matching cases
        $isMatching = ($i % 2 === 0);

        // Unique identifiers per iteration — offset by 7000 to avoid collision
        // with Property 1 (2026-000001..N), Property 2 (YYYY-009001..N),
        // and Property 3 (2026-005001..N)
        $uniqueId = str_pad((string)(7000 + $i + 1), 6, '0', STR_PAD_LEFT);
        $referenceNumber = '2026-' . $uniqueId;

        // Sanitize name parts for valid email local-part
        $emailSurname = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $surname));
        $emailFirstName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName));
        $email = $emailFirstName . '.' . $emailSurname . $uniqueId . '@' . $domain;

        if ($isMatching) {
            $cases[] = [
                'type'  => 'matching',
                'attrs' => [
                    'surname'          => $surname,
                    'first_name'       => $firstName,
                    'email'            => $email,
                    'reference_number' => $referenceNumber,
                    'batch_number'     => $batch,
                ],
                'referenceNumber' => $referenceNumber,
                'email'           => $email,
            ];
        } else {
            // Non-matching: submit credentials that are NOT in the DB
            $cases[] = [
                'type'            => 'non_matching',
                'attrs'           => null,
                'referenceNumber' => $referenceNumber,
                'email'           => $email,
            ];
        }
    }

    return $cases;
}

// Build the dataset once for use with ->with()
$http200Cases = array_map(
    fn (array $case) => [$case],
    generateHttp200Cases()
);

// ===========================================================================
// Property 6 — HTTP 200 for all valid non-rate-limited requests
// Both matching and non-matching lookups SHALL return HTTP 200.
// ===========================================================================

/**
 * **Validates: Requirements 3.2**
 */
it(
    'returns HTTP 200 for any valid non-rate-limited request regardless of match outcome',
    function (array $case) {
        // For matching cases, insert the TestPasser record first
        if ($case['type'] === 'matching') {
            TestPasser::create($case['attrs']);
        }
        // For non-matching cases, no record is inserted — the DB is empty
        // (RefreshDatabase ensures a clean slate per test)

        $response = $this->postJson('/api/public/check-status', [
            'referenceNumber' => $case['referenceNumber'],
            'email'           => $case['email'],
        ]);

        // HTTP 200 MUST be returned regardless of whether the lookup matched
        $response->assertStatus(200);

        // Additionally verify the response has the expected shape
        if ($case['type'] === 'matching') {
            $response->assertJson(['qualified' => true]);
        } else {
            $response->assertJson(['qualified' => false]);
        }
    }
)->with($http200Cases);
