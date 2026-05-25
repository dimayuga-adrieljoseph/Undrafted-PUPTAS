<?php

/**
 * Property 3: Partial match is never qualified/found
 *
 * For any TestPasser record, submitting the correct reference_number with a
 * different firstName/lastName, or wrong reference_number with correct names,
 * SHALL return found: false.
 */

use App\Models\TestPasser;

function generatePartialMatchCases(): array
{
    $cases = [];
    $batches = ['Batch 1', 'Batch 2', 'Batch 3'];
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
        $batch = $batches[$seed % count($batches)];

        // Unique identifiers per iteration
        $uniqueId = str_pad((string)(5000 + $i + 1), 6, '0', STR_PAD_LEFT);
        $referenceNumber = '2026-' . $uniqueId;

        // A different firstName/lastName
        $differentFirstName = $firstName . 'X';
        $differentLastName = $surname . 'X';

        // A different reference_number
        $differentRefNumber = '2026-' . str_pad((string)(5000 + $i + 1 + 1000), 6, '0', STR_PAD_LEFT);

        $cases[] = [
            'attrs' => [
                'surname'          => $surname,
                'first_name'       => $firstName,
                'email'            => 'test' . $uniqueId . '@example.com',
                'reference_number' => $referenceNumber,
                'batch_number'     => $batch,
            ],
            'different_first_name' => $differentFirstName,
            'different_last_name'  => $differentLastName,
            'different_ref_number' => $differentRefNumber,
        ];
    }

    return $cases;
}

$partialMatchCases = array_map(
    fn (array $case) => [$case],
    generatePartialMatchCases()
);

it(
    'returns found: false when correct reference_number is submitted with a different name',
    function (array $case) {
        TestPasser::create($case['attrs']);

        $response = $this->postJson('/api/public/admission-results', [
            'referenceNumber' => $case['attrs']['reference_number'],
            'firstName'       => $case['different_first_name'],
            'lastName'        => $case['attrs']['surname'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'found'     => false,
                'qualified' => false,
            ]);
    }
)->with($partialMatchCases);

it(
    'returns found: false when correct name is submitted with a different reference_number',
    function (array $case) {
        TestPasser::create($case['attrs']);

        $response = $this->postJson('/api/public/admission-results', [
            'referenceNumber' => $case['different_ref_number'],
            'firstName'       => $case['attrs']['first_name'],
            'lastName'        => $case['attrs']['surname'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'found'     => false,
                'qualified' => false,
            ]);
    }
)->with($partialMatchCases);
