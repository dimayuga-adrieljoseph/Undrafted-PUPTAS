<?php

/**
 * Property 3: Partial match is never qualified
 *
 * For any TestPasser record, submitting the correct reference_number with a
 * different email, or the correct email with a different reference_number,
 * SHALL return qualified: false.
 *
 * Validates: Requirements 1.5
 */

use App\Models\TestPasser;

// ---------------------------------------------------------------------------
// Generate random TestPasser data for property-based testing.
// Each case produces a stored record plus two "partial match" variants:
//   - correct reference_number + wrong email
//   - wrong reference_number + correct email
// ---------------------------------------------------------------------------

/**
 * Generate a deterministic but varied set of TestPasser attribute arrays.
 * Each entry has a unique reference_number, email, and batch_number.
 */
function generatePartialMatchCases(): array
{
    $cases = [];
    $batches = ['Batch 1', 'Batch 2', 'Batch 3'];
    $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'example.com', 'test.edu.ph'];
    $altDomains = ['hotmail.com', 'protonmail.com', 'icloud.com', 'live.com', 'mail.com'];
    $surnames = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Torres', 'Flores', 'Ramos', 'Dela Cruz'];
    $firstNames = ['Maria', 'Jose', 'Juan', 'Ana', 'Pedro', 'Rosa', 'Carlos', 'Elena', 'Miguel', 'Luz'];

    $seed = 77;
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
        $altDomain = $altDomains[$seed % count($altDomains)];

        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $batch = $batches[$seed % count($batches)];

        // Unique identifiers per iteration — offset by 5000 to avoid collision
        // with Property 1 (2026-000001..N) and Property 2 (YYYY-009001..N)
        $uniqueId = str_pad((string)(5000 + $i + 1), 6, '0', STR_PAD_LEFT);
        $referenceNumber = '2026-' . $uniqueId;

        // Sanitize name parts for valid email local-part
        $emailSurname = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $surname));
        $emailFirstName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName));
        $email = $emailFirstName . '.' . $emailSurname . $uniqueId . '@' . $domain;

        // A different email: same local-part, different domain
        $differentEmail = $emailFirstName . '.' . $emailSurname . $uniqueId . '@' . $altDomain;

        // A different reference_number: increment the numeric part
        $differentRefNumber = '2026-' . str_pad((string)(5000 + $i + 1 + 1000), 6, '0', STR_PAD_LEFT);

        $cases[] = [
            'attrs' => [
                'surname'          => $surname,
                'first_name'       => $firstName,
                'email'            => $email,
                'reference_number' => $referenceNumber,
                'batch_number'     => $batch,
            ],
            'different_email'      => $differentEmail,
            'different_ref_number' => $differentRefNumber,
        ];
    }

    return $cases;
}

// Build the dataset once for use with ->with()
$partialMatchCases = array_map(
    fn (array $case) => [$case],
    generatePartialMatchCases()
);

// ===========================================================================
// Property 3 — Partial match is never qualified
// Submitting only one correct field (reference_number OR email) while the
// other field is wrong SHALL return qualified: false.
// ===========================================================================

/**
 * **Validates: Requirements 1.5**
 */
it(
    'returns qualified: false when correct reference_number is submitted with a different email',
    function (array $case) {
        // Insert the TestPasser record
        TestPasser::create($case['attrs']);

        // Test 1: correct reference_number + DIFFERENT email → qualified: false
        $response = $this->postJson('/api/public/admission-results', [
            'referenceNumber' => $case['attrs']['reference_number'],
            'email'           => $case['different_email'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'qualified' => false,
                'message'   => 'No matching record found. Please verify your details.',
            ]);

        // Ensure qualified: true is NOT returned
        $response->assertJsonPath('qualified', false);
    }
)->with($partialMatchCases);

/**
 * **Validates: Requirements 1.5**
 */
it(
    'returns qualified: false when correct email is submitted with a different reference_number',
    function (array $case) {
        // Insert the TestPasser record (may already exist from the previous test
        // in a different iteration, but RefreshDatabase ensures a clean slate per test)
        TestPasser::create($case['attrs']);

        // Test 2: DIFFERENT reference_number + correct email → qualified: false
        $response = $this->postJson('/api/public/admission-results', [
            'referenceNumber' => $case['different_ref_number'],
            'email'           => $case['attrs']['email'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'qualified' => false,
                'message'   => 'No matching record found. Please verify your details.',
            ]);

        // Ensure qualified: true is NOT returned
        $response->assertJsonPath('qualified', false);
    }
)->with($partialMatchCases);
