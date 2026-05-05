<?php

/**
 * Property 5: Mixed-case and whitespace-padded emails match stored records
 *
 * For any TestPasser record stored with a lowercase email, submitting the
 * same email with random leading/trailing whitespace and/or mixed casing
 * SHALL return qualified: true.
 *
 * Validates: Requirements 1.4, 8.3, 8.4
 */

use App\Models\TestPasser;

// ---------------------------------------------------------------------------
// Helper: apply random mixed-casing to a string
// ---------------------------------------------------------------------------

/**
 * Randomly toggle the case of each alphabetic character in a string.
 * Uses a seeded approach so the output is deterministic per iteration.
 */
function randomlyMixCase(string $s, int $seed): string
{
    $result = '';
    for ($i = 0; $i < strlen($s); $i++) {
        $seed   = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $char   = $s[$i];
        $result .= ($seed % 2 === 0) ? strtoupper($char) : strtolower($char);
    }
    return $result;
}

// ---------------------------------------------------------------------------
// Generate test cases: stored lowercase email + variants with whitespace/case
// ---------------------------------------------------------------------------

/**
 * Each case contains:
 *  - attrs: TestPasser attributes (email is always lowercase, no padding)
 *  - variants: array of email strings that should normalize to the stored email
 *
 * Variants cover:
 *  1. Leading whitespace only
 *  2. Trailing whitespace only
 *  3. Both leading and trailing whitespace
 *  4. Mixed case only (no whitespace)
 *  5. Mixed case + leading whitespace
 *  6. Mixed case + trailing whitespace
 *  7. Mixed case + both leading and trailing whitespace
 */
function generateEmailNormalizationCases(): array
{
    $cases   = [];
    $batches = ['Batch 1', 'Batch 2', 'Batch 3'];
    $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'example.com', 'test.edu.ph'];
    $surnames    = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Torres', 'Flores', 'Ramos', 'Delacruz'];
    $firstNames  = ['Maria', 'Jose', 'Juan', 'Ana', 'Pedro', 'Rosa', 'Carlos', 'Elena', 'Miguel', 'Luz'];
    $leadPads    = [' ', '  ', "\t", " \t"];
    $trailPads   = [' ', '  ', "\t", "\t "];

    $seed       = 13;
    $iterations = propertyTestIterations();

    for ($i = 0; $i < $iterations; $i++) {
        // Deterministic pseudo-random generation
        $seed     = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $surname  = $surnames[$seed % count($surnames)];

        $seed      = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $firstName = $firstNames[$seed % count($firstNames)];

        $seed   = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $domain = $domains[$seed % count($domains)];

        $seed  = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $batch = $batches[$seed % count($batches)];

        // Unique identifiers — offset by 8000 to avoid collision with other property tests
        $uniqueId        = str_pad((string)(8000 + $i + 1), 6, '0', STR_PAD_LEFT);
        $referenceNumber = '2026-' . $uniqueId;

        // Sanitize name parts for valid email local-part
        $emailSurname   = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $surname));
        $emailFirstName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName));

        // Stored email is always lowercase, no padding
        $storedEmail = $emailFirstName . '.' . $emailSurname . $uniqueId . '@' . $domain;

        // Pick padding characters for this iteration
        $seed     = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $leadPad  = $leadPads[$seed % count($leadPads)];

        $seed      = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $trailPad  = $trailPads[$seed % count($trailPads)];

        // Mixed-case version of the stored email
        $mixedCaseEmail = randomlyMixCase($storedEmail, $seed);

        $variants = [
            // Whitespace variants (exact case)
            $leadPad . $storedEmail,
            $storedEmail . $trailPad,
            $leadPad . $storedEmail . $trailPad,
            // Mixed-case variant (no whitespace)
            $mixedCaseEmail,
            // Mixed-case + whitespace variants
            $leadPad . $mixedCaseEmail,
            $mixedCaseEmail . $trailPad,
            $leadPad . $mixedCaseEmail . $trailPad,
        ];

        $cases[] = [[
            'attrs' => [
                'surname'          => $surname,
                'first_name'       => $firstName,
                'email'            => $storedEmail,
                'reference_number' => $referenceNumber,
                'batch_number'     => $batch,
            ],
            'variants' => $variants,
        ]];
    }

    return $cases;
}

$emailNormalizationCases = generateEmailNormalizationCases();

// ===========================================================================
// Property 5 — Mixed-case and whitespace-padded emails match stored records
// Submitting the stored email with any combination of leading/trailing
// whitespace and/or mixed casing SHALL return qualified: true.
// ===========================================================================

/**
 * **Validates: Requirements 1.4, 8.3, 8.4**
 */
it(
    'returns qualified: true for emails with leading/trailing whitespace or mixed casing',
    function (array $case) {
        // Insert the TestPasser record with a clean lowercase email
        $passer = TestPasser::create($case['attrs']);

        foreach ($case['variants'] as $variantEmail) {
            $response = $this->postJson('/api/public/check-status', [
                'referenceNumber' => $passer->reference_number,
                'email'           => $variantEmail,
            ]);

            $response->assertStatus(200)
                ->assertJson([
                    'qualified'    => true,
                    'batch_number' => $passer->batch_number,
                    'message'      => 'You are qualified for the entrance exam.',
                ]);
        }
    }
)->with($emailNormalizationCases);
