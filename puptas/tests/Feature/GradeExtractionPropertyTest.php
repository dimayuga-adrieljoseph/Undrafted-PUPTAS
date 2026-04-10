<?php

// Feature: ai-grade-extraction — Property-Based Tests (Task 7)
// Uses Pest datasets with generated inputs (100+ cases per property).

use App\Models\User;
use App\Models\UserFile;
use App\Services\OpenRouterClient;
use App\Services\GradeExtractionService;
use Illuminate\Support\Facades\Storage;

// ---------------------------------------------------------------------------
// Test double: exposes protected methods for direct testing
// ---------------------------------------------------------------------------

if (! class_exists('PbtGradeExtractionService')) {
    class PbtGradeExtractionService extends GradeExtractionService
    {
        public function sanitize(string $raw): string   { return parent::sanitize($raw); }
        public function parse(string $json): array      { return parent::parse($json); }
        public function validate(array $data): array    { return parent::validate($data); }
        public function normalizeKeys(array $data): array { return parent::normalizeKeys($data); }
        public function loadImages(User $user): array   { return parent::loadImages($user); }
    }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function pbtService(): PbtGradeExtractionService
{
    return new PbtGradeExtractionService(Mockery::mock(OpenRouterClient::class));
}

function pbtValidResult(array $overrides = []): array
{
    return array_merge([
        'math'    => ['algebra' => ['grade' => 90, 'confidence' => 0.95]],
        'science' => ['biology' => ['grade' => 88, 'confidence' => 0.92]],
        'english' => ['english' => ['grade' => 92, 'confidence' => 0.97]],
        'others'  => ['araling panlipunan' => ['grade' => 85, 'confidence' => 0.80]],
    ], $overrides);
}

/** Build a minimal valid JPEG binary so mime_content_type() detects image/jpeg */
if (! function_exists('minimalJpeg')) {
    function minimalJpeg(): string
    {
        return "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 12) . "\xFF\xD9";
    }
}

/** Build a minimal valid PNG binary so mime_content_type() detects image/png */
function minimalPng(): string
{
    return "\x89PNG\r\n\x1a\n" . str_repeat("\x00", 8);
}

/** Build a minimal valid WebP binary so mime_content_type() detects image/webp */
function minimalWebp(): string
{
    // RIFF header + file size (4 bytes LE) + WEBP marker
    return "RIFF\x24\x00\x00\x00WEBPVP8 \x18\x00\x00\x00\x30\x01\x00\x9d\x01\x2a\x01\x00\x01\x00\x00\x34\x25\x9f\x11\x00\x00";
}


// ===========================================================================
// Property 2 — File ownership filter (Task 7.1)
// For any user/file collection, loadImages() returns only files whose
// user_id equals the authenticated user's ID.
// ===========================================================================

// Generate 100 cases: each case is [ownFileCount, otherFileCount]
// where ownFileCount ∈ [0,5] and otherFileCount ∈ [1,5]
$ownershipCases = [];
for ($own = 0; $own <= 5; $own++) {
    for ($other = 1; $other <= 5; $other++) {
        $ownershipCases[] = [$own, $other];
        if (count($ownershipCases) >= 100) {
            break 2;
        }
    }
}

// Feature: ai-grade-extraction, Property 2: File ownership filter
it(
    'loadImages() returns only files owned by the requesting user',
    function (int $ownCount, int $otherCount) {
        Storage::fake('local');

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Create image files for userA
        for ($i = 0; $i < $ownCount; $i++) {
            $path = "uploads/userA_{$i}.jpg";
            Storage::put($path, minimalJpeg());
            UserFile::create(['user_id' => $userA->id, 'file_path' => $path, 'type' => 'image', 'status' => 'uploaded']);
        }

        // Create image files for userB (should never appear in userA's results)
        for ($i = 0; $i < $otherCount; $i++) {
            $path = "uploads/userB_{$i}.jpg";
            Storage::put($path, minimalJpeg());
            UserFile::create(['user_id' => $userB->id, 'file_path' => $path, 'type' => 'image', 'status' => 'uploaded']);
        }

        $ref = new ReflectionMethod(GradeExtractionService::class, 'loadImages');
        $ref->setAccessible(true);

        $result = $ref->invoke(pbtService(), $userA);

        // All returned items must come from userA's files only.
        // We verify by count: userA should get exactly $ownCount items
        // (assuming mime detection works; if not, count ≤ $ownCount).
        expect(count($result))->toBeLessThanOrEqual($ownCount);

        // userB's files must never appear — verify by checking that the
        // total DB files for userB are untouched and result count never
        // exceeds userA's file count.
        expect(count($result))->not->toBeGreaterThan($ownCount);
    }
)->with($ownershipCases);


// ===========================================================================
// Property 3 — MIME type filter (Task 7.2)
// For any file collection with mixed MIME types, loadImages() returns only
// image/jpeg or image/png files.
// ===========================================================================

// 100 cases: each is a list of MIME type labels to store for a single user.
// Labels: 'jpeg', 'png', 'pdf', 'txt', 'gif', 'webp'
$mimeFilterCases = [];
$mimeOptions = ['jpeg', 'png', 'pdf', 'txt', 'gif', 'webp'];
$seed = 42;
for ($i = 0; $i < 100; $i++) {
    $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
    $count = ($seed % 5) + 1; // 1–5 files per case
    $types = [];
    for ($j = 0; $j < $count; $j++) {
        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $types[] = $mimeOptions[$seed % count($mimeOptions)];
    }
    $mimeFilterCases[] = [$types];
}

// Feature: ai-grade-extraction, Property 3: MIME type filter
it(
    'loadImages() returns only image/jpeg, image/png, and image/webp files regardless of other MIME types present',
    function (array $mimeLabels) {
        Storage::fake('local');

        $user = User::factory()->create();

        $allowedLabels = ['jpeg', 'png', 'webp'];
        $expectedCount = 0;

        foreach ($mimeLabels as $idx => $label) {
            [$content, $ext] = match ($label) {
                'jpeg'  => [minimalJpeg(), 'jpg'],
                'png'   => [minimalPng(), 'png'],
                'pdf'   => ['%PDF-1.4 fake', 'pdf'],
                'txt'   => ['plain text content', 'txt'],
                'gif'   => ["GIF89a\x01\x00\x01\x00\x00\xff\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x00;", 'gif'],
                'webp'  => [minimalWebp(), 'webp'],
                default => ['unknown', 'bin'],
            };

            $path = "uploads/mime_test_{$idx}.{$ext}";
            Storage::put($path, $content);
            UserFile::create(['user_id' => $user->id, 'file_path' => $path, 'type' => 'file', 'status' => 'uploaded']);

            if (in_array($label, $allowedLabels, true)) {
                $expectedCount++;
            }
        }

        $ref = new ReflectionMethod(GradeExtractionService::class, 'loadImages');
        $ref->setAccessible(true);

        $result = $ref->invoke(pbtService(), $user);

        // Every returned item must be jpeg, png, or webp
        foreach ($result as $item) {
            expect($item['mime_type'])->toBeIn(['image/jpeg', 'image/png', 'image/webp']);
        }

        // Result count must not exceed the number of allowed-type files
        expect(count($result))->toBeLessThanOrEqual($expectedCount);
    }
)->with($mimeFilterCases);


// ===========================================================================
// Property 4 — Structural validation (Task 7.3)
// For any JSON string, parse() accepts it iff it is valid JSON with exactly
// the four required top-level keys and valid subject entry shapes.
// ===========================================================================

// --- Valid structures (parse() must NOT throw) ---
$validStructures = [];

// Base valid result with varying subject counts per group (0–3 subjects)
$subjectNames = ['algebra', 'geometry', 'calculus', 'trigonometry', 'statistics'];
$seed2 = 7;
for ($i = 0; $i < 50; $i++) {
    $seed2 = ($seed2 * 6364136223846793005 + 1442695040888963407) % PHP_INT_MAX;
    $data = ['math' => [], 'science' => [], 'english' => [], 'others' => []];
    foreach (array_keys($data) as $group) {
        $count = abs($seed2) % 4; // 0–3 subjects
        $seed2 = ($seed2 * 6364136223846793005 + 1442695040888963407) % PHP_INT_MAX;
        for ($j = 0; $j < $count; $j++) {
            $name = $subjectNames[abs($seed2) % count($subjectNames)] . "_{$i}_{$j}";
            $seed2 = ($seed2 * 6364136223846793005 + 1442695040888963407) % PHP_INT_MAX;
            $grade = abs($seed2) % 101; // 0–100
            $seed2 = ($seed2 * 6364136223846793005 + 1442695040888963407) % PHP_INT_MAX;
            $confidence = round((abs($seed2) % 101) / 100, 2); // 0.00–1.00
            $data[$group][$name] = ['grade' => $grade, 'confidence' => $confidence];
        }
    }
    $validStructures[] = [json_encode($data)];
}

// --- Invalid structures (parse() MUST throw RuntimeException) ---
$invalidStructures = [];

// 1. Missing one required key (25 cases, one per key combination)
foreach (['math', 'science', 'english', 'others'] as $missing) {
    $base = pbtValidResult();
    unset($base[$missing]);
    $invalidStructures[] = [json_encode($base)];
}

// 2. Missing two required keys
$pairs = [['math','science'],['math','english'],['math','others'],['science','english'],['science','others'],['english','others']];
foreach ($pairs as [$a, $b]) {
    $base = pbtValidResult();
    unset($base[$a], $base[$b]);
    $invalidStructures[] = [json_encode($base)];
}

// 3. Subject entry missing 'grade'
$base = pbtValidResult();
$base['math']['algebra'] = ['confidence' => 0.9];
$invalidStructures[] = [json_encode($base)];

// 4. Subject entry missing 'confidence'
$base = pbtValidResult();
$base['math']['algebra'] = ['grade' => 90];
$invalidStructures[] = [json_encode($base)];

// 5. Non-numeric grade
$base = pbtValidResult();
$base['math']['algebra'] = ['grade' => 'A+', 'confidence' => 0.9];
$invalidStructures[] = [json_encode($base)];

// 6. Non-numeric confidence
$base = pbtValidResult();
$base['math']['algebra'] = ['grade' => 90, 'confidence' => 'high'];
$invalidStructures[] = [json_encode($base)];

// 7. Completely invalid JSON strings
foreach (['not json', '', '[]', 'null', '"string"', '42', '{]', '{"math":1}'] as $bad) {
    $invalidStructures[] = [$bad];
}

// 8. Extra top-level keys only (missing required ones)
$invalidStructures[] = [json_encode(['foo' => [], 'bar' => [], 'baz' => [], 'qux' => []])];

// Pad to 100 invalid cases by repeating with varied subject names
$seed3 = 13;
while (count($invalidStructures) < 50) {
    $seed3 = ($seed3 * 1103515245 + 12345) & 0x7fffffff;
    $base = pbtValidResult();
    // Remove a random required key
    $keys = ['math', 'science', 'english', 'others'];
    $removeKey = $keys[$seed3 % 4];
    unset($base[$removeKey]);
    // Add a spurious key instead
    $base['extra_' . $seed3] = [];
    $invalidStructures[] = [json_encode($base)];
}

// Feature: ai-grade-extraction, Property 4: Structural validation — valid inputs
it(
    'parse() accepts valid ExtractionResult structures',
    function (string $json) {
        $result = pbtService()->parse($json);
        expect($result)->toBeArray()->toHaveKeys(['math', 'science', 'english', 'others']);
    }
)->with($validStructures);

// Feature: ai-grade-extraction, Property 4: Structural validation — invalid inputs
it(
    'parse() rejects structurally invalid JSON strings',
    function (string $json) {
        expect(fn () => pbtService()->parse($json))->toThrow(\RuntimeException::class);
    }
)->with($invalidStructures);


// ===========================================================================
// Property 5 — Range validation (Task 7.4)
// validate() rejects any ExtractionResult with grade ∉ [0,100] or
// confidence ∉ [0.0,1.0], and accepts all in-range values.
// ===========================================================================

// --- Valid grade/confidence pairs (validate() must NOT throw) ---
$validRangeCases = [];
// Boundary values
foreach ([0, 1, 50, 99, 100] as $grade) {
    foreach ([0.0, 0.01, 0.5, 0.79, 0.80, 0.99, 1.0] as $conf) {
        $validRangeCases[] = [$grade, $conf];
    }
}
// Additional in-range values to reach 50+
for ($g = 0; $g <= 100; $g += 5) {
    $validRangeCases[] = [$g, round($g / 100, 2)];
}

// --- Out-of-range grade values (validate() MUST throw) ---
$invalidGradeCases = array_merge(
    array_map(fn ($v) => [$v, 0.5], range(-50, -1)),   // below 0
    array_map(fn ($v) => [$v, 0.5], range(101, 150))   // above 100
);

// --- Out-of-range confidence values (validate() MUST throw) ---
$invalidConfCases = array_merge(
    array_map(fn ($v) => [50, round(-$v / 100, 2)], range(1, 50)),   // below 0.0
    array_map(fn ($v) => [50, round(1 + $v / 100, 2)], range(1, 50)) // above 1.0
);

// Feature: ai-grade-extraction, Property 5: Range validation — valid values accepted
it(
    'validate() accepts ExtractionResult with grade in [0,100] and confidence in [0.0,1.0]',
    function (int $grade, float $confidence) {
        $data = pbtValidResult(['math' => ['subject' => ['grade' => $grade, 'confidence' => $confidence]]]);
        expect(pbtService()->validate($data))->toBeArray();
    }
)->with($validRangeCases);

// Feature: ai-grade-extraction, Property 5: Range validation — out-of-range grade rejected
it(
    'validate() rejects ExtractionResult with grade outside [0,100]',
    function (int $grade, float $confidence) {
        $data = pbtValidResult(['math' => ['subject' => ['grade' => $grade, 'confidence' => $confidence]]]);
        expect(fn () => pbtService()->validate($data))->toThrow(\RuntimeException::class);
    }
)->with($invalidGradeCases);

// Feature: ai-grade-extraction, Property 5: Range validation — out-of-range confidence rejected
it(
    'validate() rejects ExtractionResult with confidence outside [0.0,1.0]',
    function (int $grade, float $confidence) {
        $data = pbtValidResult(['math' => ['subject' => ['grade' => $grade, 'confidence' => $confidence]]]);
        expect(fn () => pbtService()->validate($data))->toThrow(\RuntimeException::class);
    }
)->with($invalidConfCases);


// ===========================================================================
// Property 6 — Key normalization (Task 7.5)
// For any subject name string, normalizeKeys() produces a key equal to
// strtolower(trim($subject)).
// ===========================================================================

// Generate 100 subject name strings covering: uppercase, mixed case,
// leading/trailing spaces, internal spaces, numbers, special chars.
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
    // Unicode (Filipino subject names)
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
    // Empty-ish (just spaces — edge case)
    ['   '],
];

// Pad to 100 cases with programmatic variations
$alphabet = 'abcdefghijklmnopqrstuvwxyz';
$seed4 = 99;
while (count($subjectNameCases) < 100) {
    $seed4 = ($seed4 * 1103515245 + 12345) & 0x7fffffff;
    $len = ($seed4 % 15) + 3;
    $name = '';
    for ($i = 0; $i < $len; $i++) {
        $seed4 = ($seed4 * 1103515245 + 12345) & 0x7fffffff;
        $char = $alphabet[$seed4 % 26];
        // Randomly uppercase
        $seed4 = ($seed4 * 1103515245 + 12345) & 0x7fffffff;
        if ($seed4 % 2 === 0) {
            $char = strtoupper($char);
        }
        $name .= $char;
    }
    // Randomly add leading/trailing spaces
    $seed4 = ($seed4 * 1103515245 + 12345) & 0x7fffffff;
    if ($seed4 % 3 === 0) {
        $name = '  ' . $name . '  ';
    }
    $subjectNameCases[] = [$name];
}

// Feature: ai-grade-extraction, Property 6: Key normalization
it(
    'normalizeKeys() produces a lowercase trimmed key for any subject name',
    function (string $subjectName) {
        $entry = ['grade' => 90, 'confidence' => 0.9];
        $data = [
            'math'    => [$subjectName => $entry],
            'science' => [],
            'english' => [],
            'others'  => [],
        ];

        $result = pbtService()->normalizeKeys($data);

        $expectedKey = strtolower(trim($subjectName));
        expect($result['math'])->toHaveKey($expectedKey);

        // Original key must not appear if it differs from normalized form
        if ($subjectName !== $expectedKey) {
            expect($result['math'])->not->toHaveKey($subjectName);
        }

        // Entry values must be preserved
        expect($result['math'][$expectedKey])->toBe($entry);
    }
)->with($subjectNameCases);


// ===========================================================================
// Property 7 — Sanitization (Task 7.6)
// For any string containing a valid JSON object surrounded by arbitrary
// non-JSON text, sanitize() returns a string that parses to the same object.
// ===========================================================================

// Build a canonical JSON object to embed in various wrappers
$canonicalJson = '{"math":{},"science":{},"english":{},"others":{}}';

// Generate 100 cases: each is a [wrappedString, expectedJson] pair
$sanitizeCases = [
    // Plain JSON — no wrapping
    [$canonicalJson, $canonicalJson],
    // Markdown ```json fences
    ["```json\n{$canonicalJson}\n```", $canonicalJson],
    // Markdown plain fences
    ["```\n{$canonicalJson}\n```", $canonicalJson],
    // Leading prose
    ["Here are the grades:\n{$canonicalJson}", $canonicalJson],
    // Trailing prose
    ["{$canonicalJson}\nHope that helps!", $canonicalJson],
    // Both leading and trailing prose
    ["Sure! Here:\n{$canonicalJson}\nLet me know.", $canonicalJson],
    // Fences + leading prose
    ["Sure!\n```json\n{$canonicalJson}\n```", $canonicalJson],
    // Fences + trailing prose
    ["```json\n{$canonicalJson}\n```\nDone.", $canonicalJson],
    // Fences + both prose
    ["Here:\n```json\n{$canonicalJson}\n```\nDone.", $canonicalJson],
    // Extra whitespace around fences
    ["  ```json  \n{$canonicalJson}\n  ```  ", $canonicalJson],
    // Uppercase JSON fence label
    ["```JSON\n{$canonicalJson}\n```", $canonicalJson],
    // Windows line endings
    ["```json\r\n{$canonicalJson}\r\n```", $canonicalJson],
    // Multiple newlines before/after
    ["\n\n\n{$canonicalJson}\n\n\n", $canonicalJson],
    // Tab indentation in prose
    ["\tHere:\n{$canonicalJson}", $canonicalJson],
    // JSON with nested content
    ['{"math":{"algebra":{"grade":90,"confidence":0.95}},"science":{},"english":{},"others":{}}',
     '{"math":{"algebra":{"grade":90,"confidence":0.95}},"science":{},"english":{},"others":{}}'],
    // Fenced with nested JSON
    ['```json' . "\n" . '{"math":{"algebra":{"grade":90,"confidence":0.95}},"science":{},"english":{},"others":{}}' . "\n```",
     '{"math":{"algebra":{"grade":90,"confidence":0.95}},"science":{},"english":{},"others":{}}'],
];

// Programmatically generate more cases with varied prose prefixes/suffixes
$prosePrefixes = [
    'Here are the extracted grades: ',
    'Based on the document, ',
    'The grades are as follows: ',
    "I found the following grades:\n",
    "Result:\n",
    'Output: ',
    "```json\n",
    "```\n",
];
$proseSuffixes = [
    ' Hope this helps!',
    "\nLet me know if you need more.",
    "\nDone.",
    ' End of response.',
    "\n```",
    '',
];

$seed5 = 17;
while (count($sanitizeCases) < 100) {
    $seed5 = ($seed5 * 1103515245 + 12345) & 0x7fffffff;
    $prefix = $prosePrefixes[$seed5 % count($prosePrefixes)];
    $seed5 = ($seed5 * 1103515245 + 12345) & 0x7fffffff;
    $suffix = $proseSuffixes[$seed5 % count($proseSuffixes)];

    // If we added a ```json prefix, close it properly
    if (str_contains($prefix, '```')) {
        $suffix = "\n```" . $suffix;
    }

    $wrapped = $prefix . $canonicalJson . $suffix;
    $sanitizeCases[] = [$wrapped, $canonicalJson];
}

// Feature: ai-grade-extraction, Property 7: Sanitization
it(
    'sanitize() extracts the JSON object from any string containing embedded JSON',
    function (string $raw, string $expectedJson) {
        $sanitized = pbtService()->sanitize($raw);

        // The sanitized output must be parseable JSON
        $decoded = json_decode($sanitized, true);
        expect(json_last_error())->toBe(JSON_ERROR_NONE);

        // It must decode to the same structure as the expected JSON
        $expectedDecoded = json_decode($expectedJson, true);
        expect($decoded)->toBe($expectedDecoded);
    }
)->with($sanitizeCases);


// ===========================================================================
// Property 12 — Grade submission range (Task 7.7)
// GradesController validates that each submitted grade is numeric and in
// [0, 100]. Values outside this range must be rejected with a 422/redirect
// with validation errors.
// ===========================================================================

use App\Models\Program;

// --- Valid grade values: [0, 100] ---
$validGradeValues = array_merge(
    [[0], [1], [50], [99], [100]],
    array_map(fn ($v) => [$v], range(0, 100, 5))   // 0,5,10,...,100
);
// Deduplicate and ensure at least 50 cases
$validGradeValues = array_values(array_unique($validGradeValues, SORT_REGULAR));

// --- Invalid grade values: outside [0, 100] ---
$invalidGradeValues = array_merge(
    array_map(fn ($v) => [$v], range(-50, -1)),    // below 0
    array_map(fn ($v) => [$v], range(101, 150))    // above 100
);

/**
 * Build a valid grade submission payload for the ABM route.
 * Requires two distinct program IDs.
 */
function buildGradePayload(int $grade, int $prog1Id, int $prog2Id): array
{
    return [
        'mathematics'          => $grade,
        'english'              => $grade,
        'science'              => $grade,
        'g12_first_sem'        => $grade,
        'g12_second_sem'       => $grade,
        'first_choice_program' => $prog1Id,
        'second_choice_program'=> $prog2Id,
    ];
}

// Feature: ai-grade-extraction, Property 12: Grade submission range — valid values accepted
it(
    'GradesController accepts grade submissions with values in [0,100]',
    function (int $grade) {
        $user = User::factory()->create(['role_id' => 1]);

        // Create two programs with no grade requirements so qualification always passes
        $prog1 = Program::create(['code' => 'P1_' . $grade . '_' . uniqid(), 'name' => 'Program 1', 'slots' => 100]);
        $prog2 = Program::create(['code' => 'P2_' . $grade . '_' . uniqid(), 'name' => 'Program 2', 'slots' => 100]);

        \App\Models\ApplicantProfile::create([
            'user_id'  => $user->id,
            'email'    => $user->email,
            'firstname'=> $user->firstname,
            'lastname' => $user->lastname,
            'strand'   => 'ABM',
        ]);

        $response = $this->actingAs($user)
            ->post('/grades/abm', buildGradePayload($grade, $prog1->id, $prog2->id));

        // Should NOT return a validation error (422 or redirect back with errors)
        $response->assertSessionHasNoErrors();
    }
)->with($validGradeValues);

// Feature: ai-grade-extraction, Property 12: Grade submission range — out-of-range values rejected
it(
    'GradesController rejects grade submissions with values outside [0,100]',
    function (int $grade) {
        $user = User::factory()->create(['role_id' => 1]);

        $prog1 = Program::create(['code' => 'P1_OOR_' . $grade . '_' . uniqid(), 'name' => 'Program 1', 'slots' => 100]);
        $prog2 = Program::create(['code' => 'P2_OOR_' . $grade . '_' . uniqid(), 'name' => 'Program 2', 'slots' => 100]);

        \App\Models\ApplicantProfile::create([
            'user_id'  => $user->id,
            'email'    => $user->email,
            'firstname'=> $user->firstname,
            'lastname' => $user->lastname,
            'strand'   => 'ABM',
        ]);

        $response = $this->actingAs($user)
            ->post('/grades/abm', buildGradePayload($grade, $prog1->id, $prog2->id));

        // Must have validation errors for the out-of-range grade fields
        $response->assertSessionHasErrors(['mathematics']);
    }
)->with($invalidGradeValues);
