<?php

/**
 * Property 2: Non-matching inputs always return found: false
 *
 * For any (referenceNumber, firstName, lastName) tuple that does not correspond to an existing
 * TestPasser record, the endpoint SHALL return found: false.
 */

function generateNonMatchingCases(): array
{
    $cases = [];
    $surnames = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Torres', 'Flores', 'Ramos', 'Dela Cruz'];
    $firstNames = ['Maria', 'Jose', 'Juan', 'Ana', 'Pedro', 'Rosa', 'Carlos', 'Elena', 'Miguel', 'Luz'];
    $years = ['2024', '2025', '2026', '2027'];

    $seed = 99;
    $iterations = propertyTestIterations();

    for ($i = 0; $i < $iterations; $i++) {
        // Deterministic pseudo-random generation
        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $surname = $surnames[$seed % count($surnames)];

        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $firstName = $firstNames[$seed % count($firstNames)];

        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $year = $years[$seed % count($years)];

        // Unique identifiers per iteration
        $uniqueId = str_pad((string)(9000 + $i + 1), 6, '0', STR_PAD_LEFT);
        $referenceNumber = $year . '-' . $uniqueId;

        $cases[] = [$referenceNumber, $firstName, $surname];
    }

    return $cases;
}

$nonMatchingCases = generateNonMatchingCases();

it(
    'returns found: false for any non-matching input details',
    function (string $referenceNumber, string $firstName, string $lastName) {
        $response = $this->postJson('/api/public/admission-results', [
            'referenceNumber' => $referenceNumber,
            'firstName'       => $firstName,
            'lastName'        => $lastName,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'found'     => false,
                'qualified' => false,
            ]);

        $response->assertJsonMissingPath('batch_number');
    }
)->with($nonMatchingCases);
