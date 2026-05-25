<?php

/**
 * Property 5: Mixed-case and whitespace-padded names match stored records
 *
 * For any TestPasser record, submitting the firstName and lastName with random
 * leading/trailing whitespace and/or mixed casing SHALL successfully match the record.
 */

use App\Models\TestPasser;

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

function generateNameNormalizationCases(): array
{
    $cases   = [];
    $batches = ['Batch 1', 'Batch 2', 'Batch 3'];
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

        $seed  = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $batch = $batches[$seed % count($batches)];

        $uniqueId        = str_pad((string)(8000 + $i + 1), 6, '0', STR_PAD_LEFT);
        $referenceNumber = '2026-' . $uniqueId;

        // Pick padding characters for this iteration
        $seed     = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $leadPad  = $leadPads[$seed % count($leadPads)];

        $seed      = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $trailPad  = $trailPads[$seed % count($trailPads)];

        // Mixed-case versions of the names
        $mixedFirstName = randomlyMixCase($firstName, $seed);
        $mixedLastName  = randomlyMixCase($surname, $seed);

        $variants = [
            [
                'firstName' => $leadPad . $firstName . $trailPad,
                'lastName'  => $leadPad . $surname . $trailPad,
            ],
            [
                'firstName' => $mixedFirstName,
                'lastName'  => $mixedLastName,
            ],
            [
                'firstName' => $leadPad . $mixedFirstName . $trailPad,
                'lastName'  => $leadPad . $mixedLastName . $trailPad,
            ],
        ];

        $cases[] = [[
            'attrs' => [
                'surname'          => $surname,
                'first_name'       => $firstName,
                'email'            => 'test' . $uniqueId . '@example.com',
                'reference_number' => $referenceNumber,
                'batch_number'     => $batch,
            ],
            'variants' => $variants,
        ]];
    }

    return $cases;
}

$nameNormalizationCases = generateNameNormalizationCases();

it(
    'returns found: true for names with leading/trailing whitespace or mixed casing',
    function (array $case) {
        $passer = TestPasser::create($case['attrs']);

        foreach ($case['variants'] as $variant) {
            $response = $this->postJson('/api/public/admission-results', [
                'referenceNumber' => $passer->reference_number,
                'firstName'       => $variant['firstName'],
                'lastName'        => $variant['lastName'],
            ]);

            $response->assertStatus(200)
                ->assertJson([
                    'found' => true,
                ]);
        }
    }
)->with($nameNormalizationCases);
