<?php

/**
 * Property 1: Qualified lookup round-trip
 *
 * For any TestPasser record in the database, submitting its reference_number
 * and its stored email (after normalization) to the status check endpoint
 * SHALL return qualified: true and the correct batch_number.
 *
 * Validates: Requirements 1.2
 */

use App\Models\TestPasser;

// ---------------------------------------------------------------------------
// Generate random TestPasser data for property-based testing
// ---------------------------------------------------------------------------

/**
 * Generate a deterministic but varied set of TestPasser attribute arrays.
 * Each entry has a unique reference_number, email, and batch_number.
 */
function generateTestPasserCases(): array
{
    $cases = [];
    $batches = ['Batch 1', 'Batch 2', 'Batch 3'];
    $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'example.com', 'test.edu.ph'];
    $surnames = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Torres', 'Flores', 'Ramos', 'Dela Cruz'];
    $firstNames = ['Maria', 'Jose', 'Juan', 'Ana', 'Pedro', 'Rosa', 'Carlos', 'Elena', 'Miguel', 'Luz'];

    $seed = 42;
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

        // Unique identifiers per iteration
        $uniqueId = str_pad((string)($i + 1), 6, '0', STR_PAD_LEFT);
        $referenceNumber = '2026-' . $uniqueId;
        // Sanitize name parts: remove spaces and special characters for valid email local-part
        $emailSurname = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $surname));
        $emailFirstName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName));
        $email = $emailFirstName . '.' . $emailSurname . $uniqueId . '@' . $domain;

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
$testPasserCases = array_map(
    fn (array $attrs) => [$attrs],
    generateTestPasserCases()
);

// ===========================================================================
// Property 1 — Qualified lookup round-trip
// Submitting a stored record's reference_number + normalized email returns
// qualified: true and the correct batch_number.
// ===========================================================================

/**
 * **Validates: Requirements 1.2**
 */
it(
    'returns qualified: true and correct batch_number for any existing TestPasser record',
    function (array $attrs) {
        // Insert the TestPasser record directly (no factory exists)
        $passer = TestPasser::create($attrs);

        // POST to the endpoint with the exact stored credentials
        $response = $this->postJson('/api/public/admission-results', [
            'referenceNumber' => $passer->reference_number,
            'email'           => $passer->email,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'qualified'    => true,
                'batch_number' => $passer->batch_number,
                'message'      => 'You are qualified for the entrance exam.',
            ]);
    }
)->with($testPasserCases);
