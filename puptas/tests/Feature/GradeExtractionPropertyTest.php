<?php

// Grade extraction property-based tests — flat "Subject" => "grade" format

use App\Models\User;
use App\Models\UserFile;
use App\Models\Program;
use App\Services\OpenRouterClient;
use App\Services\GradeExtractionService;
use Illuminate\Support\Facades\Storage;

// ---------------------------------------------------------------------------
// Test double
// ---------------------------------------------------------------------------

if (! class_exists('PbtGradeExtractionService')) {
    class PbtGradeExtractionService extends GradeExtractionService
    {
        public function sanitize(string $raw): string     { return parent::sanitize($raw); }
        public function parse(string $json): array        { return parent::parse($json); }
        public function validate(array $data): array      { return parent::validate($data); }
        public function normalizeKeys(array $data): array { return parent::normalizeKeys($data); }
        public function loadImages(User $user): array     { return parent::loadImages($user); }
    }
}

function pbtService(): PbtGradeExtractionService
{
    return new PbtGradeExtractionService(Mockery::mock(OpenRouterClient::class));
}

function pbtFlatResult(array $overrides = []): array
{
    return array_merge([
        'math'    => ['General Mathematics' => '90'],
        'science' => ['Earth and Life Science' => '88'],
        'english' => ['Oral Communication' => '92'],
        'others'  => ['Filipino' => '85'],
    ], $overrides);
}

if (! function_exists('minimalJpeg')) {
    function minimalJpeg(): string
    {
        return "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 12) . "\xFF\xD9";
    }
}

function minimalPng(): string
{
    return "\x89PNG\r\n\x1a\n" . str_repeat("\x00", 8);
}

function minimalWebp(): string
{
    return "RIFF\x24\x00\x00\x00WEBPVP8 \x18\x00\x00\x00\x30\x01\x00\x9d\x01\x2a\x01\x00\x01\x00\x00\x34\x25\x9f\x11\x00\x00";
}

// ===========================================================================
// Property 2 — File ownership filter
// loadImages() returns only files belonging to the requesting user
// ===========================================================================

$ownershipCases = [];
for ($own = 0; $own <= 5; $own++) {
    for ($other = 1; $other <= 5; $other++) {
        $ownershipCases[] = [$own, $other];
        if (count($ownershipCases) >= 100) break 2;
    }
}

it(
    'loadImages() returns only files owned by the requesting user',
    function (int $ownCount, int $otherCount) {
        Storage::fake('public');

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        for ($i = 0; $i < $ownCount; $i++) {
            $path = "uploads/userA_{$i}.jpg";
            Storage::disk('public')->put($path, minimalJpeg());
            UserFile::create(['user_id' => $userA->id, 'file_path' => $path, 'type' => "photo_{$i}", 'original_name' => 'a.jpg', 'status' => 'pending']);
        }

        for ($i = 0; $i < $otherCount; $i++) {
            $path = "uploads/userB_{$i}.jpg";
            Storage::disk('public')->put($path, minimalJpeg());
            UserFile::create(['user_id' => $userB->id, 'file_path' => $path, 'type' => "photo_{$i}", 'original_name' => 'b.jpg', 'status' => 'pending']);
        }

        $ref = new ReflectionMethod(GradeExtractionService::class, 'loadImages');
        $ref->setAccessible(true);
        $result = $ref->invoke(pbtService(), $userA);

        expect(count($result))->toBeLessThanOrEqual($ownCount);
    }
)->with($ownershipCases);

// ===========================================================================
// Property 3 — MIME type filter
// loadImages() returns only image/jpeg, image/png, image/webp files
// ===========================================================================

$mimeFilterCases = [];
$mimeOptions = ['jpeg', 'png', 'pdf', 'txt', 'gif', 'webp'];
$seed = 42;
for ($i = 0; $i < propertyTestIterations(); $i++) {
    $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
    $count = ($seed % 5) + 1;
    $types = [];
    for ($j = 0; $j < $count; $j++) {
        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $types[] = $mimeOptions[$seed % count($mimeOptions)];
    }
    $mimeFilterCases[] = [$types];
}

it(
    'loadImages() returns only image/jpeg, image/png, and image/webp files',
    function (array $mimeLabels) {
        Storage::fake('public');
        $user = User::factory()->create();
        $allowedLabels = ['jpeg', 'png', 'webp'];
        $expectedCount = 0;

        foreach ($mimeLabels as $idx => $label) {
            [$content, $ext] = match ($label) {
                'jpeg'  => [minimalJpeg(), 'jpg'],
                'png'   => [minimalPng(), 'png'],
                'pdf'   => ['%PDF-1.4 fake', 'pdf'],
                'txt'   => ['plain text', 'txt'],
                'gif'   => ["GIF89a\x01\x00\x01\x00\x00\xff\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x00;", 'gif'],
                'webp'  => [minimalWebp(), 'webp'],
                default => ['unknown', 'bin'],
            };

            $path = "uploads/mime_{$idx}.{$ext}";
            Storage::disk('public')->put($path, $content);
            UserFile::create(['user_id' => $user->id, 'file_path' => $path, 'type' => "photo_{$idx}", 'original_name' => "f.{$ext}", 'status' => 'pending']);

            if (in_array($label, $allowedLabels, true)) {
                $expectedCount++;
            }
        }

        $ref = new ReflectionMethod(GradeExtractionService::class, 'loadImages');
        $ref->setAccessible(true);
        $result = $ref->invoke(pbtService(), $user);

        foreach ($result as $item) {
            expect($item['mime_type'])->toBeIn(['image/jpeg', 'image/png', 'image/webp']);
        }
        expect(count($result))->toBeLessThanOrEqual($expectedCount);
    }
)->with($mimeFilterCases);

// ===========================================================================
// Property 4 — Structural validation
// parse() accepts valid flat structures and rejects invalid ones
// ===========================================================================

$subjectNames = ['algebra', 'geometry', 'calculus', 'trigonometry', 'statistics'];
$validStructures = [];
$seed2 = 7;
for ($i = 0; $i < 50; $i++) {
    $seed2 = ($seed2 * 6364136223846793005 + 1442695040888963407) % PHP_INT_MAX;
    $data = ['math' => [], 'science' => [], 'english' => [], 'others' => []];
    foreach (array_keys($data) as $group) {
        $count = abs($seed2) % 4;
        $seed2 = ($seed2 * 6364136223846793005 + 1442695040888963407) % PHP_INT_MAX;
        for ($j = 0; $j < $count; $j++) {
            $name = $subjectNames[abs($seed2) % count($subjectNames)] . "_{$i}_{$j}";
            $seed2 = ($seed2 * 6364136223846793005 + 1442695040888963407) % PHP_INT_MAX;
            $grade = (string)(abs($seed2) % 101);
            $data[$group][$name] = $grade;
        }
    }
    $validStructures[] = [json_encode(['subjects' => $data])];
}

$invalidStructures = [];
// Missing required group keys
foreach (['math', 'science', 'english', 'others'] as $missing) {
    $base = pbtFlatResult();
    unset($base[$missing]);
    $invalidStructures[] = [json_encode(['subjects' => $base])];
}
// Missing subjects root key
$invalidStructures[] = [json_encode(pbtFlatResult())];
// Non-numeric grade
$base = pbtFlatResult(['math' => ['General Mathematics' => 'A+']]);
$invalidStructures[] = [json_encode(['subjects' => $base])];
// Invalid JSON
foreach (['not json', '', '[]', 'null', '{]'] as $bad) {
    $invalidStructures[] = [$bad];
}
// Pad to 50
$seed3 = 13;
while (count($invalidStructures) < 50) {
    $seed3 = ($seed3 * 1103515245 + 12345) & 0x7fffffff;
    $base = pbtFlatResult();
    $keys = ['math', 'science', 'english', 'others'];
    unset($base[$keys[$seed3 % 4]]);
    $invalidStructures[] = [json_encode(['subjects' => $base])];
}

it(
    'parse() accepts valid flat ExtractionResult structures',
    function (string $json) {
        $result = pbtService()->parse($json);
        expect($result)->toBeArray()->toHaveKeys(['math', 'science', 'english', 'others']);
    }
)->with($validStructures);

it(
    'parse() rejects structurally invalid JSON strings',
    function (string $json) {
        expect(fn () => pbtService()->parse($json))->toThrow(\RuntimeException::class);
    }
)->with($invalidStructures);

// ===========================================================================
// Property 5 — Range validation
// validate() rejects grades outside [0,100]
// ===========================================================================

$validGradeStrings = array_map(fn ($v) => [(string)$v], array_merge([0, 1, 50, 99, 100], range(0, 100, 5)));
$invalidGradeStrings = array_merge(
    array_map(fn ($v) => [(string)$v], range(-50, -1)),
    array_map(fn ($v) => [(string)$v], range(101, 150))
);

it(
    'validate() accepts flat grades in [0,100]',
    function (string $grade) {
        $data = pbtFlatResult(['math' => ['subject' => $grade]]);
        expect(pbtService()->validate($data))->toBeArray();
    }
)->with($validGradeStrings);

it(
    'validate() rejects flat grades outside [0,100]',
    function (string $grade) {
        $data = pbtFlatResult(['math' => ['subject' => $grade]]);
        expect(fn () => pbtService()->validate($data))->toThrow(\RuntimeException::class);
    }
)->with($invalidGradeStrings);

// ===========================================================================
// Property 6 — Key normalization
// normalizeKeys() produces strtolower(trim($subject)) keys
// ===========================================================================

$subjectNameCases = [
    ['ALGEBRA'], ['BIOLOGY'], ['ENGLISH'], ['ARALING PANLIPUNAN'],
    ['Algebra'], ['BiOlOgY'], ['English Literature'],
    ['  algebra  '], [' Biology '], ['  ENGLISH  '],
    ['  ALGEBRA  '], ['  Mixed Case  '],
    ['Math 101'], ['Science 2'],
    ['A'], ['z'], [' B '],
    ['algebra'], ['biology'], ['english'],
    ['Filipino'], ['Edukasyon sa Pagpapakatao'],
    ['  Filipino  '], ['EDUKASYON SA PAGPAPAKATAO'],
    ['Earth-and-Life-Science'], ['Mathematics (Core)'],
    ['Reading/Writing'], ['Science, Technology'],
];

$seed4 = 99;
while (count($subjectNameCases) < propertyTestIterations()) {
    $seed4 = ($seed4 * 1103515245 + 12345) & 0x7fffffff;
    $len = ($seed4 % 15) + 3;
    $name = '';
    for ($i = 0; $i < $len; $i++) {
        $seed4 = ($seed4 * 1103515245 + 12345) & 0x7fffffff;
        $char = chr(97 + ($seed4 % 26));
        $seed4 = ($seed4 * 1103515245 + 12345) & 0x7fffffff;
        $name .= $seed4 % 2 === 0 ? strtoupper($char) : $char;
    }
    $seed4 = ($seed4 * 1103515245 + 12345) & 0x7fffffff;
    if ($seed4 % 3 === 0) $name = '  ' . $name . '  ';
    $subjectNameCases[] = [$name];
}

it(
    'normalizeKeys() produces a lowercase trimmed key for any subject name',
    function (string $subjectName) {
        $data = [
            'math'    => [$subjectName => '90'],
            'science' => [],
            'english' => [],
            'others'  => [],
        ];

        $result = pbtService()->normalizeKeys($data);
        $expectedKey = strtolower(trim($subjectName));

        expect($result['subjects']['math'])->toHaveKey($expectedKey);
        expect($result['subjects']['math'][$expectedKey])->toBe(90.0);
    }
)->with($subjectNameCases);

// ===========================================================================
// Property 7 — Sanitization
// sanitize() extracts the JSON object from any wrapped string
// ===========================================================================

$canonicalJson = '{"subjects":{"math":{},"science":{},"english":{},"others":{}}}';

$sanitizeCases = [
    [$canonicalJson, $canonicalJson],
    ["```json\n{$canonicalJson}\n```", $canonicalJson],
    ["```\n{$canonicalJson}\n```", $canonicalJson],
    ["Here are the grades:\n{$canonicalJson}", $canonicalJson],
    ["{$canonicalJson}\nHope that helps!", $canonicalJson],
    ["Sure! Here:\n{$canonicalJson}\nLet me know.", $canonicalJson],
    ["Sure!\n```json\n{$canonicalJson}\n```", $canonicalJson],
    ["```json\n{$canonicalJson}\n```\nDone.", $canonicalJson],
    ["\n\n\n{$canonicalJson}\n\n\n", $canonicalJson],
];

$prosePrefixes = ['Here are the grades: ', 'Based on the document, ', "Result:\n", "```json\n", "```\n"];
$proseSuffixes = [' Hope this helps!', "\nDone.", "\n```", ''];
$seed5 = 17;
while (count($sanitizeCases) < propertyTestIterations()) {
    $seed5 = ($seed5 * 1103515245 + 12345) & 0x7fffffff;
    $prefix = $prosePrefixes[$seed5 % count($prosePrefixes)];
    $seed5 = ($seed5 * 1103515245 + 12345) & 0x7fffffff;
    $suffix = $proseSuffixes[$seed5 % count($proseSuffixes)];
    if (str_contains($prefix, '```')) $suffix = "\n```" . $suffix;
    $sanitizeCases[] = [$prefix . $canonicalJson . $suffix, $canonicalJson];
}

it(
    'sanitize() extracts the JSON object from any string containing embedded JSON',
    function (string $raw, string $expectedJson) {
        $sanitized = pbtService()->sanitize($raw);
        $decoded = json_decode($sanitized, true);
        expect(json_last_error())->toBe(JSON_ERROR_NONE);
        expect($decoded)->toBe(json_decode($expectedJson, true));
    }
)->with($sanitizeCases);

// ===========================================================================
// Property 12 — Grade submission range
// GradesController validates grades are numeric and in [0,100]
// ===========================================================================

$validGradeValues = array_values(array_unique(array_merge(
    [[0], [1], [50], [99], [100]],
    array_map(fn ($v) => [$v], range(0, 100, 5))
), SORT_REGULAR));

$invalidGradeValues = array_merge(
    array_map(fn ($v) => [$v], range(-50, -1)),
    array_map(fn ($v) => [$v], range(101, 150))
);

function buildGradePayload(int $grade, int $prog1Id, int $prog2Id): array
{
    return [
        'mathematics'           => $grade,
        'english'               => $grade,
        'science'               => $grade,
        'g12_first_sem'         => $grade,
        'g12_second_sem'        => $grade,
        'first_choice_program'  => $prog1Id,
        'second_choice_program' => $prog2Id,
    ];
}

it(
    'GradesController accepts grade submissions with values in [0,100]',
    function (int $grade) {
        $user = User::factory()->create(['role_id' => 1]);
        $prog1 = Program::create(['code' => 'P1_' . $grade . '_' . uniqid(), 'name' => 'Program 1', 'slots' => 100]);
        $prog2 = Program::create(['code' => 'P2_' . $grade . '_' . uniqid(), 'name' => 'Program 2', 'slots' => 100]);

        \App\Models\ApplicantProfile::create([
            'user_id'   => $user->id,
            'email'     => $user->email,
            'firstname' => $user->firstname,
            'lastname'  => $user->lastname,
            'strand'    => 'ABM',
        ]);

        $this->actingAs($user)
            ->post('/grades/abm', buildGradePayload($grade, $prog1->id, $prog2->id))
            ->assertSessionHasNoErrors();
    }
)->with($validGradeValues);

it(
    'GradesController rejects grade submissions with values outside [0,100]',
    function (int $grade) {
        $user = User::factory()->create(['role_id' => 1]);
        $prog1 = Program::create(['code' => 'P1_OOR_' . $grade . '_' . uniqid(), 'name' => 'Program 1', 'slots' => 100]);
        $prog2 = Program::create(['code' => 'P2_OOR_' . $grade . '_' . uniqid(), 'name' => 'Program 2', 'slots' => 100]);

        \App\Models\ApplicantProfile::create([
            'user_id'   => $user->id,
            'email'     => $user->email,
            'firstname' => $user->firstname,
            'lastname'  => $user->lastname,
            'strand'    => 'ABM',
        ]);

        $this->actingAs($user)
            ->post('/grades/abm', buildGradePayload($grade, $prog1->id, $prog2->id))
            ->assertSessionHasErrors(['mathematics']);
    }
)->with($invalidGradeValues);

