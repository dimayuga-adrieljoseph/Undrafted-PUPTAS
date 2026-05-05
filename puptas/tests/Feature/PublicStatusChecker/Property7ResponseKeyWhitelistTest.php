<?php

/**
 * Property 7: Response body never exposes fields beyond the allowed set
 *
 * For any matching lookup, the JSON response body SHALL contain only the keys
 * `qualified`, `batch_number`, and `message` — no other TestPasser model
 * attributes (e.g. id, surname, first_name, email, reference_number,
 * school_year, etc.) SHALL appear.
 *
 * Validates: Requirements 3.3
 */

use App\Models\TestPasser;

// ---------------------------------------------------------------------------
// Generate random TestPasser data for property-based testing
// ---------------------------------------------------------------------------

/**
 * Generate a varied set of TestPasser attribute arrays covering many
 * combinations of names, domains, and batches.
 */
function generateTestPasserCasesForProperty7(): array
{
    $cases = [];
    $batches    = ['Batch 1', 'Batch 2', 'Batch 3'];
    $domains    = ['gmail.com', 'yahoo.com', 'outlook.com', 'example.com', 'test.edu.ph'];
    $surnames   = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Torres', 'Flores', 'Ramos', 'Dela Cruz'];
    $firstNames = ['Maria', 'Jose', 'Juan', 'Ana', 'Pedro', 'Rosa', 'Carlos', 'Elena', 'Miguel', 'Luz'];

    $seed       = 77;
    $iterations = 20;

    for ($i = 0; $i < $iterations; $i++) {
        // Deterministic pseudo-random generation
        $seed    = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $surname = $surnames[$seed % count($surnames)];

        $seed      = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $firstName = $firstNames[$seed % count($firstNames)];

        $seed   = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $domain = $domains[$seed % count($domains)];

        $seed  = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $batch = $batches[$seed % count($batches)];

        // Unique identifiers per iteration (prefix with 'p7' to avoid collisions
        // with records created by other property test files)
        $uniqueId        = 'p7-' . str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT);
        $referenceNumber = '2026-' . $uniqueId;

        // Sanitize name parts for a valid email local-part
        $emailSurname   = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $surname));
        $emailFirstName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName));
        $email          = $emailFirstName . '.' . $emailSurname . $uniqueId . '@' . $domain;

        $cases[] = [
            'surname'          => $surname,
            'first_name'       => $firstName,
            'email'            => $email,
            'reference_number' => $referenceNumber,
            'batch_number'     => $batch,
        ];
    }

    return $cases;
}

// Build the dataset once for use with ->with()
$testPasserCasesP7 = array_map(
    fn (array $attrs) => [$attrs],
    generateTestPasserCasesForProperty7()
);

// ===========================================================================
// Property 7 — Response body never exposes fields beyond the allowed set
//
// The allowed key set for a matching response is exactly:
//   { qualified, batch_number, message }
//
// No other TestPasser attributes (test_passer_id, surname, first_name,
// middle_name, date_of_birth, address, school_address, shs_school, strand,
// year_graduated, email, reference_number, school_year, user_id, status,
// created_at, updated_at, …) may appear.
// ===========================================================================

/**
 * **Validates: Requirements 3.3**
 */
it(
    'returns exactly the allowed keys {qualified, batch_number, message} for any matching lookup',
    function (array $attrs) {
        // Insert the TestPasser record directly
        $passer = TestPasser::create($attrs);

        // POST to the endpoint with the exact stored credentials
        $response = $this->postJson('/api/public/check-status', [
            'referenceNumber' => $passer->reference_number,
            'email'           => $passer->email,
        ]);

        $response->assertStatus(200)
            ->assertJson(['qualified' => true]);

        // Decode the response body and inspect its keys
        $body = $response->json();

        $allowedKeys = ['qualified', 'batch_number', 'message'];

        // Assert no extra keys are present
        $extraKeys = array_diff(array_keys($body), $allowedKeys);
        expect($extraKeys)
            ->toBeEmpty(
                'Response contained unexpected keys: ' . implode(', ', $extraKeys)
            );

        // Assert all allowed keys are present (complete shape check)
        $missingKeys = array_diff($allowedKeys, array_keys($body));
        expect($missingKeys)
            ->toBeEmpty(
                'Response was missing expected keys: ' . implode(', ', $missingKeys)
            );
    }
)->with($testPasserCasesP7);
