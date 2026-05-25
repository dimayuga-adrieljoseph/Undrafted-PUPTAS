<?php

/**
 * Property 8: Attempt log never contains plaintext first/last name
 *
 * For any submitted name, the log entry written by the controller SHALL NOT
 * contain the plaintext names; it SHALL contain only a hash or masked
 * representation (SHA-256 hash stored under the `first_name_hash` and `last_name_hash` keys).
 */

use Illuminate\Support\Facades\Log;

function generateNameCasesForProperty8(): array
{
    $cases = [];
    $surnames   = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Torres', 'Flores', 'Ramos', 'Dela Cruz'];
    $firstNames = ['Maria', 'Jose', 'Juan', 'Ana', 'Pedro', 'Rosa', 'Carlos', 'Elena', 'Miguel', 'Luz'];

    $seed       = 88;
    $iterations = propertyTestIterations();

    for ($i = 0; $i < $iterations; $i++) {
        $seed      = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $surname   = $surnames[$seed % count($surnames)];

        $seed      = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $firstName = $firstNames[$seed % count($firstNames)];

        // Unique identifiers per iteration — prefix with '8' to avoid collisions
        // with records created by other property test files
        $uniqueId        = str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT);
        $referenceNumber = '2026-8' . $uniqueId;

        $cases[] = [
            'firstName'       => $firstName,
            'lastName'        => $surname,
            'referenceNumber' => $referenceNumber,
        ];
    }

    return $cases;
}

$nameCasesP8 = array_map(
    fn (array $case) => [$case],
    generateNameCasesForProperty8()
);

it(
    'never logs the plaintext names and always logs the SHA-256 hashes',
    function (array $case) {
        Log::spy();

        $firstName       = $case['firstName'];
        $lastName        = $case['lastName'];
        $referenceNumber = $case['referenceNumber'];

        $this->postJson('/api/public/admission-results', [
            'referenceNumber' => $referenceNumber,
            'firstName'       => $firstName,
            'lastName'        => $lastName,
        ]);

        Log::getFacadeRoot()->shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context = []) use ($firstName, $lastName) {
                if ($message !== 'status_check_attempt') {
                    return false;
                }

                $contextAsString = json_encode($context);
                expect($contextAsString)->not->toContain($firstName);
                expect($contextAsString)->not->toContain($lastName);

                expect($context)->toHaveKey('first_name_hash');
                expect($context)->toHaveKey('last_name_hash');

                $expectedFirstHash = hash('sha256', strtolower(trim($firstName)));
                $expectedLastHash  = hash('sha256', strtolower(trim($lastName)));

                expect($context['first_name_hash'])->toBe($expectedFirstHash);
                expect($context['last_name_hash'])->toBe($expectedLastHash);

                return true;
            })
            ->once();
    }
)->with($nameCasesP8);
