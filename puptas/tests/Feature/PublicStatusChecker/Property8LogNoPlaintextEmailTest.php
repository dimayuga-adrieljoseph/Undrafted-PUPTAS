<?php

/**
 * Property 8: Attempt log never contains plaintext email
 *
 * For any submitted email, the log entry written by the controller SHALL NOT
 * contain the plaintext email string; it SHALL contain only a hash or masked
 * representation (SHA-256 hash stored under the `email_hash` key).
 *
 * Validates: Requirements 5.2
 */

use Illuminate\Support\Facades\Log;

// ---------------------------------------------------------------------------
// Generate random email addresses for property-based testing
// ---------------------------------------------------------------------------

/**
 * Generate a varied set of email addresses covering different domains,
 * name patterns, and formats. Each email is unique per iteration.
 */
function generateEmailCasesForProperty8(): array
{
    $cases = [];
    $domains    = ['gmail.com', 'yahoo.com', 'outlook.com', 'example.com', 'test.edu.ph', 'hotmail.com', 'proton.me'];
    $surnames   = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Torres', 'Flores', 'Ramos', 'Dela Cruz'];
    $firstNames = ['Maria', 'Jose', 'Juan', 'Ana', 'Pedro', 'Rosa', 'Carlos', 'Elena', 'Miguel', 'Luz'];

    $seed       = 88;
    $iterations = propertyTestIterations();

    for ($i = 0; $i < $iterations; $i++) {
        // Deterministic pseudo-random generation
        $seed      = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $surname   = $surnames[$seed % count($surnames)];

        $seed      = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $firstName = $firstNames[$seed % count($firstNames)];

        $seed   = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $domain = $domains[$seed % count($domains)];

        // Unique identifiers per iteration — prefix with 'p8' to avoid collisions
        // with records created by other property test files
        $uniqueId        = 'p8-' . str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT);
        $referenceNumber = '2026-' . $uniqueId;

        // Sanitize name parts for a valid email local-part
        $emailSurname   = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $surname));
        $emailFirstName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName));
        $email          = $emailFirstName . '.' . $emailSurname . $uniqueId . '@' . $domain;

        $cases[] = [
            'email'           => $email,
            'referenceNumber' => $referenceNumber,
        ];
    }

    return $cases;
}

// Build the dataset once for use with ->with()
$emailCasesP8 = array_map(
    fn (array $case) => [$case],
    generateEmailCasesForProperty8()
);

// ===========================================================================
// Property 8 — Attempt log never contains plaintext email
//
// The log context written by PublicStatusCheckerController@check SHALL NOT
// contain the plaintext email string in any value. It SHALL contain the
// SHA-256 hash of the normalized email under the `email_hash` key.
// ===========================================================================

/**
 * **Validates: Requirements 5.2**
 */
it(
    'never logs the plaintext email and always logs the SHA-256 email_hash',
    function (array $case) {
        // Spy on the Log facade to capture all log calls
        Log::spy();

        $email           = $case['email'];
        $referenceNumber = $case['referenceNumber'];

        // POST to the endpoint — credentials may or may not match (non-matching
        // is fine; the controller still logs the attempt either way)
        $this->postJson('/api/public/check-status', [
            'referenceNumber' => $referenceNumber,
            'email'           => $email,
        ]);

        // Capture all calls made to Log::info
        $logCalls = Log::getFacadeRoot()->shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context = []) use ($email) {
                // Only inspect 'status_check_attempt' log entries
                if ($message !== 'status_check_attempt') {
                    return false;
                }

                // ASSERT: plaintext email MUST NOT appear anywhere in the context
                $contextAsString = json_encode($context);
                expect($contextAsString)->not->toContain(
                    $email,
                    "Log context must not contain the plaintext email '{$email}'"
                );

                // ASSERT: email_hash key MUST be present
                expect($context)->toHaveKey(
                    'email_hash',
                    "Log context must contain 'email_hash' key"
                );

                // ASSERT: email_hash value MUST equal the SHA-256 hash of the
                // normalized (trimmed + lowercased) email
                $normalizedEmail   = strtolower(trim($email));
                $expectedEmailHash = hash('sha256', $normalizedEmail);
                expect($context['email_hash'])->toBe(
                    $expectedEmailHash,
                    "email_hash must be the SHA-256 hash of the normalized email"
                );

                return true;
            })
            ->once();
    }
)->with($emailCasesP8);
