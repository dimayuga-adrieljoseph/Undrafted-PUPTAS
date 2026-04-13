<?php

use App\Services\GradeExtractionService;
use App\Services\OpenRouterClient;

// ---------------------------------------------------------------------------
// Exploratory Bug Condition Test — Bug 1
// This test is EXPECTED TO FAIL on unfixed code.
// Failure confirms the bug exists: buildPrompt() contains Grade 11 exclusion.
// ---------------------------------------------------------------------------

/**
 * Exposes the protected buildPrompt() method for testing.
 */
class TestableBugCondition1Service extends GradeExtractionService
{
    public function buildPrompt(): string
    {
        return parent::buildPrompt();
    }
}

function makeBugCondition1Service(): TestableBugCondition1Service
{
    $client = Mockery::mock(OpenRouterClient::class);
    return new TestableBugCondition1Service($client);
}

// ---------------------------------------------------------------------------
// Bug Condition 1: prompt must NOT contain Grade 11 exclusion instructions
// Validates: Requirements 2.1
// ---------------------------------------------------------------------------

test('buildPrompt does not contain "Ignore Grade 11"', function () {
    $svc = makeBugCondition1Service();
    $prompt = $svc->buildPrompt();

    expect($prompt)->not->toContain('Ignore Grade 11');
});

test('buildPrompt does not contain "Only consider Grade 12"', function () {
    $svc = makeBugCondition1Service();
    $prompt = $svc->buildPrompt();

    expect($prompt)->not->toContain('Only consider Grade 12');
});

// ---------------------------------------------------------------------------
// Bug Condition 1b: prompt must contain "Business Mathematics" in the mapping
// Validates: Requirements 2.1
// ---------------------------------------------------------------------------

test('buildPrompt contains "Business Mathematics" in predefined subject mapping', function () {
    $svc = makeBugCondition1Service();
    $prompt = $svc->buildPrompt();

    expect($prompt)->toContain('Business Mathematics');
});
