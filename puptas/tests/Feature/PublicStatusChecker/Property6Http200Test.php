<?php

/**
 * Property 6: Response always returns HTTP 200 for valid (non-rate-limited) requests
 *
 * For any valid request (passes validation, not rate-limited), the HTTP response
 * status SHALL be 200 regardless of whether the lookup matched or not.
 */

use App\Models\TestPasser;

function generateHttp200Cases(): array
{
    $cases = [];
    $batches = ['Batch 1', 'Batch 2', 'Batch 3'];
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
        $batch = $batches[$seed % count($batches)];

        $isMatching = ($i % 2 === 0);

        $uniqueId = str_pad((string)(7000 + $i + 1), 6, '0', STR_PAD_LEFT);
        $referenceNumber = '2026-' . $uniqueId;

        if ($isMatching) {
            $cases[] = [
                'type'  => 'matching',
                'attrs' => [
                    'surname'          => $surname,
                    'first_name'       => $firstName,
                    'email'            => 'test' . $uniqueId . '@example.com',
                    'reference_number' => $referenceNumber,
                    'batch_number'     => $batch,
                ],
                'referenceNumber' => $referenceNumber,
                'firstName'       => $firstName,
                'lastName'        => $surname,
            ];
        } else {
            $cases[] = [
                'type'            => 'non_matching',
                'attrs'           => null,
                'referenceNumber' => $referenceNumber,
                'firstName'       => $firstName,
                'lastName'        => $surname,
            ];
        }
    }

    return $cases;
}

$http200Cases = array_map(
    fn (array $case) => [$case],
    generateHttp200Cases()
);

it(
    'returns HTTP 200 for any valid non-rate-limited request regardless of match outcome',
    function (array $case) {
        if ($case['type'] === 'matching') {
            TestPasser::create($case['attrs']);
        }

        $response = $this->postJson('/api/public/admission-results', [
            'referenceNumber' => $case['referenceNumber'],
            'firstName'       => $case['firstName'],
            'lastName'        => $case['lastName'],
        ]);

        $response->assertStatus(200);

        if ($case['type'] === 'matching') {
            $response->assertJson(['found' => true]);
        } else {
            $response->assertJson(['found' => false]);
        }
    }
)->with($http200Cases);
