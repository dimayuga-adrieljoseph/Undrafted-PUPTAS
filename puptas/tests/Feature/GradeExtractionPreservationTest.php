<?php

/**
 * Preservation Property Tests — Grade Extraction Failure Bugfix
 *
 * Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5
 *
 * These tests capture BASELINE behavior that MUST be preserved after the fix.
 * On UNFIXED code, ALL tests PASS — confirming the happy path is intact.
 * On FIXED code, all tests MUST STILL PASS — confirming no regressions.
 *
 * Property 2: Preservation — Non-Failure-Path Behavior Unchanged
 */

use App\Exceptions\OpenRouterApiException;
use App\Models\ApplicantProfile;
use App\Models\User;
use App\Services\OpenRouterClient;
use App\Services\GradeExtractionService;

// ---------------------------------------------------------------------------
// Test double: exposes protected methods for direct unit testing
// ---------------------------------------------------------------------------

if (! class_exists('PreservationGradeExtractionService')) {
    class PreservationGradeExtractionService extends GradeExtractionService
    {
        public function sanitize(string $raw): string      { return parent::sanitize($raw); }
        public function normalizeKeys(array $data): array  { return parent::normalizeKeys($data); }
    }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function preservationService(): PreservationGradeExtractionService
{
    return new PreservationGradeExtractionService(Mockery::mock(OpenRouterClient::class));
}

/**
 * Build a well-formed extraction result array.
 */
function wellFormedResult(array $overrides = []): array
{
    return array_merge([
        'math'    => ['algebra'            => ['grade' => 90,  'confidence' => 0.95]],
        'science' => ['biology'            => ['grade' => 88,  'confidence' => 0.92]],
        'english' => ['english'            => ['grade' => 92,  'confidence' => 0.97]],
        'others'  => ['araling panlipunan' => ['grade' => 85,  'confidence' => 0.80]],
    ], $overrides);
}

/**
 * Create a user with an ApplicantProfile for the given strand.
 */
function userWithStrand(string $strand): User
{
    $user = User::factory()->create();
    ApplicantProfile::create([
        'user_id'   => $user->id,
        'email'     => $user->email,
        'firstname' => $user->firstname ?? 'Test',
        'lastname'  => $user->lastname  ?? 'User',
        'strand'    => $strand,
    ]);
    return $user;
}

// ===========================================================================
// Preservation 3.1 — Happy path: HTTP 200 + redirect URL + session state
//
// Generate random well-formed model responses; verify HTTP 200, correct
// redirect URL, and session state.
//
// Validates: Requirement 3.1
// ===========================================================================

/**
 * Build 30 varied well-formed extraction results to use as property inputs.
 * Each result has different subject names, grades, and confidence values.
 */
$wellFormedCases = [];

$subjectSets = [
    ['math'    => ['algebra'       => ['grade' => 90, 'confidence' => 0.95]],
     'science' => ['biology'       => ['grade' => 88, 'confidence' => 0.92]],
     'english' => ['english'       => ['grade' => 92, 'confidence' => 0.97]],
     'others'  => ['history'       => ['grade' => 85, 'confidence' => 0.80]]],

    ['math'    => ['calculus'      => ['grade' => 78, 'confidence' => 0.88]],
     'science' => ['chemistry'     => ['grade' => 82, 'confidence' => 0.91]],
     'english' => ['literature'    => ['grade' => 75, 'confidence' => 0.85]],
     'others'  => ['pe'            => ['grade' => 95, 'confidence' => 0.99]]],

    ['math'    => ['statistics'    => ['grade' => 0,   'confidence' => 0.50]],
     'science' => ['physics'       => ['grade' => 100, 'confidence' => 1.0]],
     'english' => ['reading'       => ['grade' => 50,  'confidence' => 0.70]],
     'others'  => ['values'        => ['grade' => 88,  'confidence' => 0.93]]],

    ['math'    => [],
     'science' => [],
     'english' => [],
     'others'  => []],

    ['math'    => ['algebra' => ['grade' => 90, 'confidence' => 0.95],
                   'geometry'=> ['grade' => 87, 'confidence' => 0.89]],
     'science' => ['biology' => ['grade' => 88, 'confidence' => 0.92],
                   'physics' => ['grade' => 76, 'confidence' => 0.84]],
     'english' => ['english' => ['grade' => 92, 'confidence' => 0.97]],
     'others'  => ['araling panlipunan' => ['grade' => 85, 'confidence' => 0.80],
                   'mapeh'              => ['grade' => 91, 'confidence' => 0.94]]],
];

// Generate 30 cases by varying grades/confidence across the base sets
$seed = 42;
for ($i = 0; $i < min(30, propertyTestIterations()); $i++) {
    $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
    $base = $subjectSets[$seed % count($subjectSets)];

    // Vary grade and confidence values deterministically
    $mutated = [];
    foreach ($base as $group => $subjects) {
        $mutated[$group] = [];
        foreach ($subjects as $name => $entry) {
            $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
            $grade = $seed % 101; // 0–100
            $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
            $confidence = round(($seed % 101) / 100, 2); // 0.00–1.00
            $mutated[$group][$name] = ['grade' => $grade, 'confidence' => $confidence];
        }
    }
    $wellFormedCases[] = [$mutated];
}

// **Validates: Requirements 3.1**
it(
    'Preservation 3.1: controller returns HTTP 200 with redirect key and stores extraction_result in session for well-formed responses',
    function (array $extractionResult) {
        $user = userWithStrand('ABM');

        $this->mock(GradeExtractionService::class)
            ->shouldReceive('extract')
            ->once()
            ->andReturn($extractionResult);

        $response = $this->actingAs($user)->postJson('/api/grades/extract');

        // Must return HTTP 200
        $response->assertStatus(200);

        // Must return a JSON object with a 'redirect' key
        $response->assertJsonStructure(['redirect']);

        // The redirect value must be a non-empty string (a URL path)
        $data = $response->json();
        expect($data['redirect'])->toBeString()->not->toBeEmpty();

        // Session must contain extraction_result equal to what the service returned
        $response->assertSessionHas('extraction_result', $extractionResult);
    }
)->with($wellFormedCases);

// ===========================================================================
// Preservation 3.2 — sanitize() strips markdown code fences
//
// Generate many fence variants; verify sanitize() output is always valid JSON.
//
// Validates: Requirement 3.2
// ===========================================================================

$canonicalJson = '{"math":{},"science":{},"english":{},"others":{}}';

// Build fence variant cases
$fenceCases = [
    // No fences — plain JSON
    [$canonicalJson],
    // Standard ```json fence
    ["```json\n{$canonicalJson}\n```"],
    // Plain ``` fence
    ["```\n{$canonicalJson}\n```"],
    // Uppercase JSON label
    ["```JSON\n{$canonicalJson}\n```"],
    // Mixed case label
    ["```Json\n{$canonicalJson}\n```"],
    // Fence with leading prose
    ["Here are the grades:\n```json\n{$canonicalJson}\n```"],
    // Fence with trailing prose
    ["```json\n{$canonicalJson}\n```\nHope that helps!"],
    // Fence with both prose
    ["Sure!\n```json\n{$canonicalJson}\n```\nDone."],
    // Extra whitespace around fence markers
    ["  ```json  \n{$canonicalJson}\n  ```  "],
    // Windows line endings
    ["```json\r\n{$canonicalJson}\r\n```"],
    // Multiple newlines
    ["\n\n```json\n\n{$canonicalJson}\n\n```\n\n"],
    // No fence, just leading prose
    ["Here are the grades:\n{$canonicalJson}"],
    // No fence, just trailing prose
    ["{$canonicalJson}\nEnd of response."],
    // No fence, both prose
    ["Result:\n{$canonicalJson}\nDone."],
    // Nested JSON content with fences
    ['```json' . "\n" . '{"math":{"algebra":{"grade":90,"confidence":0.95}},"science":{},"english":{},"others":{}}' . "\n```"],
    // Tab before fence
    ["\t```json\n{$canonicalJson}\n```"],
    // Fence with space after backticks
    ["``` json\n{$canonicalJson}\n```"],
];

// Generate more fence variants programmatically
$prefixes = [
    "```json\n", "```\n", "```JSON\n", "```Json\n",
    "Here:\n```json\n", "Result:\n```\n",
    "Based on the document:\n```json\n",
];
$suffixes = ["\n```", "\n```\nDone.", "\n``` ", "\n```\n"];

$seed2 = 17;
while (count($fenceCases) < 60) {
    $seed2 = ($seed2 * 1103515245 + 12345) & 0x7fffffff;
    $prefix = $prefixes[$seed2 % count($prefixes)];
    $seed2 = ($seed2 * 1103515245 + 12345) & 0x7fffffff;
    $suffix = $suffixes[$seed2 % count($suffixes)];
    $fenceCases[] = [$prefix . $canonicalJson . $suffix];
}

// **Validates: Requirements 3.2**
it(
    'Preservation 3.2: sanitize() output is always valid JSON for any fence variant',
    function (string $raw) {
        $sanitized = preservationService()->sanitize($raw);

        // The sanitized output must be parseable JSON
        $decoded = json_decode($sanitized, true);
        expect(json_last_error())
            ->toBe(JSON_ERROR_NONE, "sanitize() output is not valid JSON for input: " . substr($raw, 0, 80));

        // Must decode to an array (object)
        expect($decoded)->toBeArray();
    }
)->with($fenceCases);

// ===========================================================================
// Preservation 3.3 — normalizeKeys() lowercases and trims subject name keys
//
// Generate random subject names with mixed case/whitespace; verify output
// is lowercase-trimmed and idempotent.
//
// Validates: Requirement 3.3
// ===========================================================================

$subjectNameCases = [
    // Pure uppercase
    ['ALGEBRA'], ['BIOLOGY'], ['ENGLISH'], ['ARALING PANLIPUNAN'],
    // Mixed case
    ['Algebra'], ['BiOlOgY'], ['English Literature'], ['Araling Panlipunan'],
    // Leading/trailing spaces
    ['  algebra  '], ["\talgebra\t"], [' Biology '], ['  ENGLISH  '],
    // Both case and spaces
    ['  ALGEBRA  '], ['  Mixed Case  '], ["\t STEM \t"],
    // Numbers in name
    ['Math 101'], ['Science 2'], ['English 3A'],
    // Single character
    ['A'], ['z'], [' B '],
    // Already normalized
    ['algebra'], ['biology'], ['english'], ['others'],
    // Filipino subject names
    ['Filipino'], ['Edukasyon sa Pagpapakatao'], ['Kasaysayan ng Pilipinas'],
    ['  Filipino  '], ['EDUKASYON SA PAGPAPAKATAO'],
    // Hyphenated
    ['Earth-and-Life-Science'], ['EARTH-AND-LIFE-SCIENCE'],
    // Parentheses
    ['Mathematics (Core)'], ['MATHEMATICS (CORE)'],
    // Slash
    ['Reading/Writing'], ['READING/WRITING'],
    // Comma
    ['Science, Technology'], ['SCIENCE, TECHNOLOGY'],
    // Numbers only
    ['123'], ['  456  '],
    // Long names
    ['Introduction to the Philosophy of the Human Person'],
    ['INTRODUCTION TO THE PHILOSOPHY OF THE HUMAN PERSON'],
];

// Pad to 80 cases with programmatic variations
$alphabet = 'abcdefghijklmnopqrstuvwxyz';
$seed3 = 99;
while (count($subjectNameCases) < 80) {
    $seed3 = ($seed3 * 1103515245 + 12345) & 0x7fffffff;
    $len = ($seed3 % 15) + 3;
    $name = '';
    for ($i = 0; $i < $len; $i++) {
        $seed3 = ($seed3 * 1103515245 + 12345) & 0x7fffffff;
        $char = $alphabet[$seed3 % 26];
        $seed3 = ($seed3 * 1103515245 + 12345) & 0x7fffffff;
        if ($seed3 % 2 === 0) {
            $char = strtoupper($char);
        }
        $name .= $char;
    }
    $seed3 = ($seed3 * 1103515245 + 12345) & 0x7fffffff;
    if ($seed3 % 3 === 0) {
        $name = '  ' . $name . '  ';
    }
    $subjectNameCases[] = [$name];
}

// **Validates: Requirements 3.3**
it(
    'Preservation 3.3: normalizeKeys() produces lowercase-trimmed keys for any subject name',
    function (string $subjectName) {
        $entry = ['grade' => 90, 'confidence' => 0.9];
        $data = [
            'math'    => [$subjectName => $entry],
            'science' => [],
            'english' => [],
            'others'  => [],
        ];

        $result = preservationService()->normalizeKeys($data);

        $expectedKey = strtolower(trim($subjectName));

        // Normalized key must exist
        expect($result['math'])->toHaveKey($expectedKey);

        // Original key must not appear if it differs from normalized form
        if ($subjectName !== $expectedKey) {
            expect($result['math'])->not->toHaveKey($subjectName);
        }

        // Entry values must be preserved unchanged
        expect($result['math'][$expectedKey])->toBe($entry);
    }
)->with($subjectNameCases);

// **Validates: Requirements 3.3** (idempotency)
it(
    'Preservation 3.3: normalizeKeys() is idempotent — applying it twice yields the same result',
    function (string $subjectName) {
        $entry = ['grade' => 75, 'confidence' => 0.85];
        $data = [
            'math'    => [$subjectName => $entry],
            'science' => [],
            'english' => [],
            'others'  => [],
        ];

        $svc = preservationService();
        $once  = $svc->normalizeKeys($data);
        $twice = $svc->normalizeKeys($once);

        expect($twice)->toBe($once);
    }
)->with($subjectNameCases);

// ===========================================================================
// Preservation 3.4 — getStrandGradeUrl() returns correct URL for all 6 strands
//
// Assert all six strand values map to the correct URL.
//
// Validates: Requirement 3.4
// ===========================================================================

$strandUrlCases = [
    ['ICT',   '/grades/ict'],
    ['HUMSS', '/grades/humss'],
    ['GAS',   '/grades/gas'],
    ['STEM',  '/grades/stem'],
    ['TVL',   '/grades/tvl'],
    ['ABM',   '/grades/abm'],
    // Default (unknown strand) → ABM path
    ['UNKNOWN', '/grades/abm'],
    ['',        '/grades/abm'],
    // Case variations — controller does strtoupper() so these should all work
    ['ict',   '/grades/ict'],
    ['humss', '/grades/humss'],
    ['gas',   '/grades/gas'],
    ['stem',  '/grades/stem'],
    ['tvl',   '/grades/tvl'],
    ['abm',   '/grades/abm'],
];

// **Validates: Requirements 3.4**
it(
    'Preservation 3.4: controller returns correct strand-specific redirect URL for all six strand values',
    function (string $strand, string $expectedUrl) {
        $user = userWithStrand($strand);

        $this->mock(GradeExtractionService::class)
            ->shouldReceive('extract')
            ->once()
            ->andReturn(wellFormedResult());

        $response = $this->actingAs($user)->postJson('/api/grades/extract');

        $response->assertStatus(200);
        $response->assertJson(['redirect' => $expectedUrl]);
    }
)->with($strandUrlCases);

// ===========================================================================
// Preservation 3.5 — Exception-to-HTTP-status mapping preserved
//
// Assert OpenRouterApiException → 503 and RuntimeException → 422.
//
// Validates: Requirement 3.5
// ===========================================================================

// **Validates: Requirements 3.5**
test('Preservation 3.5: OpenRouterApiException maps to HTTP 503', function () {
    $user = User::factory()->create();

    $this->mock(GradeExtractionService::class)
        ->shouldReceive('extract')
        ->once()
        ->andThrow(new OpenRouterApiException('OpenRouter API returned HTTP 503: Service Unavailable'));

    $response = $this->actingAs($user)->postJson('/api/grades/extract');

    $response->assertStatus(503);
    $response->assertJsonFragment(['error' => 'OpenRouter API is currently unavailable. Please try again later.']);
});

// **Validates: Requirements 3.5**
test('Preservation 3.5: RuntimeException maps to HTTP 422', function () {
    $user = User::factory()->create();

    $this->mock(GradeExtractionService::class)
        ->shouldReceive('extract')
        ->once()
        ->andThrow(new \RuntimeException('OpenRouter response is not valid JSON.'));

    $response = $this->actingAs($user)->postJson('/api/grades/extract');

    $response->assertStatus(422);
    $response->assertJsonFragment(['error' => 'OpenRouter response is not valid JSON.']);
});

// **Validates: Requirements 3.5** — OpenRouterApiException is a subclass of RuntimeException;
// it must still map to 503 (not 422) because the controller catches it first.
test('Preservation 3.5: OpenRouterApiException (RuntimeException subclass) still maps to 503 not 422', function () {
    $user = User::factory()->create();

    $this->mock(GradeExtractionService::class)
        ->shouldReceive('extract')
        ->once()
        ->andThrow(new OpenRouterApiException('Connection refused'));

    $response = $this->actingAs($user)->postJson('/api/grades/extract');

    // Must be 503, NOT 422
    $response->assertStatus(503);
    $response->assertStatus(503)->assertJsonMissing(['error' => 'Connection refused']);
});

// **Validates: Requirements 3.5** — InvalidArgumentException maps to 422
test('Preservation 3.5: InvalidArgumentException maps to HTTP 422', function () {
    $user = User::factory()->create();

    $this->mock(GradeExtractionService::class)
        ->shouldReceive('extract')
        ->once()
        ->andThrow(new \InvalidArgumentException('No valid image files found for extraction.'));

    $response = $this->actingAs($user)->postJson('/api/grades/extract');

    $response->assertStatus(422);
    $response->assertJsonFragment(['error' => 'No valid image files found for extraction.']);
});

// **Validates: Requirements 3.5** — property: many RuntimeException messages all map to 422
$runtimeMessages = [
    ['OpenRouter response is not valid JSON.'],
    ['OpenRouter response missing required keys: math, science, english, others.'],
    ['OpenRouter response has invalid subject entry structure.'],
    ["Grade value out of range [0,100] for subject 'algebra': 105"],
    ["Confidence value out of range [0.0,1.0] for subject 'biology': 1.5"],
    ["Grade value out of range [0,100] for subject 'calculus': -1"],
    ['Some other runtime error from the extraction pipeline'],
    ['Unexpected structure in model response'],
    ['Model returned empty content'],
    ['JSON decode failed unexpectedly'],
];

it(
    'Preservation 3.5: any RuntimeException from the service maps to HTTP 422',
    function (string $message) {
        $user = User::factory()->create();

        $this->mock(GradeExtractionService::class)
            ->shouldReceive('extract')
            ->once()
            ->andThrow(new \RuntimeException($message));

        $response = $this->actingAs($user)->postJson('/api/grades/extract');

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => $message]);
    }
)->with($runtimeMessages);

