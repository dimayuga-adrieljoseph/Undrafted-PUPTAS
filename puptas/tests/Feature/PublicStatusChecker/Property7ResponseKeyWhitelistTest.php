<?php

/**
 * Property 7: Response body never exposes fields beyond the allowed set
 */

use App\Models\TestPasser;

function generateTestPasserCasesForProperty7(): array
{
    $cases = [];
    $batches    = ['Batch 1', 'Batch 2', 'Batch 3'];
    $surnames   = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Torres', 'Flores', 'Ramos', 'Dela Cruz'];
    $firstNames = ['Maria', 'Jose', 'Juan', 'Ana', 'Pedro', 'Rosa', 'Carlos', 'Elena', 'Miguel', 'Luz'];

    $seed       = 77;
    $iterations = 20;

    for ($i = 0; $i < $iterations; $i++) {
        $seed    = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $surname = $surnames[$seed % count($surnames)];

        $seed      = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $firstName = $firstNames[$seed % count($firstNames)];

        $seed  = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $batch = $batches[$seed % count($batches)];

        $uniqueId        = str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT);
        $referenceNumber = '2026-7' . $uniqueId;

        $cases[] = [
            'surname'          => $surname,
            'first_name'       => $firstName,
            'email'            => 'test' . $uniqueId . '@example.com',
            'reference_number' => $referenceNumber,
            'batch_number'     => $batch,
        ];
    }

    return $cases;
}

$testPasserCasesP7 = array_map(
    fn (array $attrs) => [$attrs],
    generateTestPasserCasesForProperty7()
);

it(
    'returns exactly the allowed keys for a matching lookup',
    function (array $attrs) {
        $passer = TestPasser::create($attrs);

        $response = $this->postJson('/api/public/admission-results', [
            'referenceNumber' => $passer->reference_number,
            'firstName'       => $passer->first_name,
            'lastName'        => $passer->surname,
        ]);

        $response->assertStatus(200)
            ->assertJson(['found' => true]);

        $body = $response->json();

        $allowedKeys = [
            'found',
            'qualified',
            'waitlisted',
            'not_qualified',
            'waitlisted_below_cutoff',
            'status',
            'passer_status_id',
            'first_name',
            'last_name',
            'full_name',
            'reference_number',
            'batch_number',
            'confirmation_url',
        ];

        $extraKeys = array_diff(array_keys($body), $allowedKeys);
        expect($extraKeys)->toBeEmpty('Response contained unexpected keys: ' . implode(', ', $extraKeys));

        $missingKeys = array_diff($allowedKeys, array_keys($body));
        expect($missingKeys)->toBeEmpty('Response was missing expected keys: ' . implode(', ', $missingKeys));
    }
)->with($testPasserCasesP7);
