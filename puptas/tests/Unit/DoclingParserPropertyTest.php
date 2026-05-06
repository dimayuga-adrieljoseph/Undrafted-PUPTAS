<?php

/**
 * Property-based tests for DoclingParser.
 *
 * Feature: docling-grade-autofill
 * Properties are tested manually using 100+ iterations with random values.
 */

uses(Tests\TestCase::class);

if (!class_exists('PbtTestableDoclingParser')) {
    class PbtTestableDoclingParser extends \App\Services\DoclingParser
    {
        public function resolveSubject(string $raw): ?array { return parent::resolveSubject($raw); }
        public function validateGrade(mixed $raw): ?float { return parent::validateGrade($raw); }
        public function scanTextNode(string $text): array { return parent::scanTextNode($text); }
        public function scanTable(array $table): array { return parent::scanTable($table); }
        public function parseJsonContent(array $jsonContent): array { return parent::parseJsonContent($jsonContent); }
        public function buildResult(array $flat): array { return parent::buildResult($flat); }
        public function normalizeKey(string $key): string { return parent::normalizeKey($key); }
    }
}

if (!class_exists('PbtTestableDoclingParserWithFiles')) {
    class PbtTestableDoclingParserWithFiles extends \App\Services\DoclingParser
    {
        private array $mockFiles;
        public function __construct(array $mockFiles) { $this->mockFiles = $mockFiles; }
        public function extract(\App\Models\User $user): array {
            $accumulator = [];
            foreach ($this->mockFiles as $file) {
                if (empty($file['docling_json'])) continue;
                $pairs = $this->parseJsonContent($file['docling_json']);
                $accumulator = array_merge($accumulator, $pairs);
            }
            if (empty($accumulator)) {
                throw new \InvalidArgumentException('No valid subject-grade pairs found in Docling JSON.');
            }
            return $this->buildResult($accumulator);
        }
        public function resolveSubject(string $raw): ?array { return parent::resolveSubject($raw); }
        public function validateGrade(mixed $raw): ?float { return parent::validateGrade($raw); }
        public function scanTextNode(string $text): array { return parent::scanTextNode($text); }
        public function scanTable(array $table): array { return parent::scanTable($table); }
        public function parseJsonContent(array $jsonContent): array { return parent::parseJsonContent($jsonContent); }
        public function buildResult(array $flat): array { return parent::buildResult($flat); }
    }
}

function pbtAllAliasTriples(): array
{
    return [
        ['general mathematics', 'math', 'general mathematics'],
        ['gen math',            'math', 'general mathematics'],
        ['math',                'math', 'general mathematics'],
        ['mathematics',         'math', 'general mathematics'],
        ['business mathematics','math', 'business mathematics'],
        ['business math',       'math', 'business mathematics'],
        ['statistics and probability', 'math', 'statistics and probability'],
        ['statistics',          'math', 'statistics and probability'],
        ['stats',               'math', 'statistics and probability'],
        ['stat and prob',       'math', 'statistics and probability'],
        ['pre-calculus',        'math', 'pre-calculus'],
        ['precalculus',         'math', 'pre-calculus'],
        ['pre-cal',             'math', 'pre-calculus'],
        ['pre cal',             'math', 'pre-calculus'],
        ['basic calculus',      'math', 'basic calculus'],
        ['basic cal',           'math', 'basic calculus'],
        ['earth and life science',   'science', 'earth and life science'],
        ['earth & life science',     'science', 'earth and life science'],
        ['els',                      'science', 'earth and life science'],
        ['physical science',         'science', 'physical science'],
        ['phys sci',                 'science', 'physical science'],
        ['earth science',            'science', 'earth science'],
        ['earth sci',                'science', 'earth science'],
        ['general chemistry 1',      'science', 'general chemistry 1'],
        ['gen chem 1',               'science', 'general chemistry 1'],
        ['gen chem',                 'science', 'general chemistry 1'],
        ['chemistry',                'science', 'general chemistry 1'],
        ['oral communication',  'english', 'oral communication'],
        ['oral comm',           'english', 'oral communication'],
        ['21st century literature',  'english', '21st century literature'],
        ['21st century lit',         'english', '21st century literature'],
        ['21st lit',                 'english', '21st century literature'],
        ['21st century literature from the philippines and the world', 'english', '21st century literature'],
        ['english for academic purposes', 'english', 'english for academic purposes'],
        ['eapp',                'english', 'english for academic purposes'],
        ['reading and writing', 'english', 'reading and writing'],
        ['reading & writing',   'english', 'reading and writing'],
    ];
}

// ---------------------------------------------------------------------------
// Property 1 — Subject Resolution Correctness
// Feature: docling-grade-autofill, Property 1: Subject Resolution Correctness
// ---------------------------------------------------------------------------

it('Property 1: resolveSubject returns correct category and name for every alias', function () {
    $parser = new PbtTestableDoclingParser();
    $triples = pbtAllAliasTriples();
    $iterations = 0;
    $idx = 0;
    $targetIterations = propertyTestIterations();
    while ($iterations < $targetIterations) {
        [$alias, $expectedCategory, $expectedName] = $triples[$idx % count($triples)];
        $idx++;
        $result = $parser->resolveSubject($alias);
        expect($result)->not->toBeNull();
        expect($result['category'])->toBe($expectedCategory);
        expect($result['name'])->toBe($expectedName);
        $iterations++;
    }
    expect($iterations)->toBeGreaterThanOrEqual($targetIterations);
});

// ---------------------------------------------------------------------------
// Property 2 — Grade Output Invariant
// Feature: docling-grade-autofill, Property 2: Grade Output Invariant
// ---------------------------------------------------------------------------

it('Property 2: every grade in result is a float in [0, 100] for any valid input ', function () {
    $parser = new PbtTestableDoclingParser();
    $aliases = array_column(pbtAllAliasTriples(), 0);
    for ($i = 0; $i < propertyTestIterations(); $i++) {
        $nodeCount = rand(1, 5);
        $texts = [];
        for ($j = 0; $j < $nodeCount; $j++) {
            $alias = $aliases[array_rand($aliases)];
            $grade = round(mt_rand(0, 10000) / 100, 2);
            $texts[] = ['text' => "Subject: $alias  Grade: $grade"];
        }
        $flat = $parser->parseJsonContent(['texts' => $texts, 'tables' => []]);
        if (empty($flat)) continue;
        $result = $parser->buildResult($flat);
        foreach (['math', 'science', 'english', 'others'] as $cat) {
            foreach ($result['subjects'][$cat] as $name => $gradeValue) {
                expect($gradeValue)->toBeFloat();
                expect($gradeValue)->toBeGreaterThanOrEqual(0.0);
                expect($gradeValue)->toBeLessThanOrEqual(100.0);
            }
        }
    }
});

// ---------------------------------------------------------------------------
// Property 3 — Last-Value-Wins Merge
// Feature: docling-grade-autofill, Property 3: Last-Value-Wins Merge
// ---------------------------------------------------------------------------

it('Property 3: last-value-wins merge holds for any sequence of grades for the same subject ', function () {
    $alias = 'gen math';
    $expectedKey = 'general mathematics';
    for ($i = 0; $i < propertyTestIterations(); $i++) {
        $grades = [];
        for ($j = 0; $j < rand(2, 5); $j++) {
            $grades[] = round(mt_rand(0, 10000) / 100, 2);
        }
        $accumulator = [];
        foreach ($grades as $grade) {
            $parser = new PbtTestableDoclingParser();
            $pairs = $parser->parseJsonContent(['texts' => [['text' => "Subject: $alias  Grade: $grade"]], 'tables' => []]);
            $accumulator = array_merge($accumulator, $pairs);
        }
        expect(array_key_exists($expectedKey, $accumulator))->toBeTrue();
        expect($accumulator[$expectedKey])->toBe((float) $grades[count($grades) - 1]);
    }
});

// ---------------------------------------------------------------------------
// Property 4 — Deterministic Multi-File Merge
// Feature: docling-grade-autofill, Property 4: Deterministic Multi-File Merge
// ---------------------------------------------------------------------------

it('Property 4: processing files in ascending id order is deterministic ', function () {
    $allAliases = array_column(pbtAllAliasTriples(), 0);
    for ($i = 0; $i < propertyTestIterations(); $i++) {
        $files = [];
        for ($id = 1; $id <= rand(2, 4); $id++) {
            $alias = $allAliases[array_rand($allAliases)];
            $grade = round(mt_rand(0, 10000) / 100, 2);
            $files[] = ['id' => $id, 'docling_json' => ['texts' => [['text' => "Subject: $alias  Grade: $grade"]], 'tables' => []]];
        }
        usort($files, fn($a, $b) => $a['id'] <=> $b['id']);
        $p1 = new PbtTestableDoclingParser();
        $acc1 = [];
        foreach ($files as $f) { $acc1 = array_merge($acc1, $p1->parseJsonContent($f['docling_json'])); }
        $r1 = $p1->buildResult($acc1);

        shuffle($files);
        usort($files, fn($a, $b) => $a['id'] <=> $b['id']);
        $p2 = new PbtTestableDoclingParser();
        $acc2 = [];
        foreach ($files as $f) { $acc2 = array_merge($acc2, $p2->parseJsonContent($f['docling_json'])); }
        $r2 = $p2->buildResult($acc2);

        expect($r2)->toBe($r1);
    }
});

// ---------------------------------------------------------------------------
// Property 5 — Empty Input Exception
// Feature: docling-grade-autofill, Property 5: Empty Input Exception
// ---------------------------------------------------------------------------

it('Property 5: extract throws InvalidArgumentException for any input with zero valid pairs ', function () {
    $emptyInputs = [
        [],
        ['texts' => [['text' => 'no grades here']], 'tables' => []],
        ['texts' => [['text' => 'lorem ipsum dolor sit amet']], 'tables' => []],
        ['texts' => [], 'tables' => []],
        ['texts' => [], 'tables' => [['data' => ['table_cells' => [['text' => 'foo'], ['text' => 'bar']]]]]],
        ['texts' => [['text' => '90 85 78 92']], 'tables' => []],
        ['texts' => [['text' => '']], 'tables' => []],
    ];
    $user = new \App\Models\User();
    for ($i = 0; $i < propertyTestIterations(); $i++) {
        $input = $emptyInputs[array_rand($emptyInputs)];
        $files = empty($input) ? [] : [['docling_json' => $input]];
        $parser = new PbtTestableDoclingParserWithFiles($files);
        expect(fn () => $parser->extract($user))->toThrow(\InvalidArgumentException::class);
    }
});

// ---------------------------------------------------------------------------
// Property 6 — Table Scanning
// Feature: docling-grade-autofill, Property 6: Table Scanning
// ---------------------------------------------------------------------------

it('Property 6: table scanning extracts subject-grade pair from adjacent cells ', function () {
    $parser = new PbtTestableDoclingParser();
    $triples = pbtAllAliasTriples();
    for ($i = 0; $i < propertyTestIterations(); $i++) {
        [$alias, , $expectedName] = $triples[array_rand($triples)];
        $grade = round(mt_rand(0, 10000) / 100, 2);
        $jsonContent = [
            'texts'  => [],
            'tables' => [[
                'data' => ['table_cells' => [
                    ['text' => $alias,          'start_row_offset_idx' => 0],
                    ['text' => (string) $grade, 'start_row_offset_idx' => 0],
                ]],
            ]],
        ];
        $result = $parser->parseJsonContent($jsonContent);
        expect(array_key_exists($expectedName, $result))->toBeTrue();
        expect($result[$expectedName])->toBe((float) $grade);
    }
});

// ---------------------------------------------------------------------------
// Property 7 — Output Shape Invariant
// Feature: docling-grade-autofill, Property 7: Output Shape Invariant
// ---------------------------------------------------------------------------

it('Property 7: output shape always has exactly the four category keys with lowercased/trimmed subject keys ', function () {
    $parser = new PbtTestableDoclingParser();
    $aliases = array_column(pbtAllAliasTriples(), 0);
    for ($i = 0; $i < propertyTestIterations(); $i++) {
        $texts = [];
        for ($j = 0; $j < rand(1, 5); $j++) {
            $alias = $aliases[array_rand($aliases)];
            $grade = round(mt_rand(0, 10000) / 100, 2);
            $texts[] = ['text' => "Subject: $alias  Grade: $grade"];
        }
        $flat = $parser->parseJsonContent(['texts' => $texts, 'tables' => []]);
        if (empty($flat)) continue;
        $result = $parser->buildResult($flat);
        expect($result)->toHaveKey('subjects');
        $keys = array_keys($result['subjects']);
        sort($keys);
        expect($keys)->toBe(['english', 'math', 'others', 'science']);
        foreach (['math', 'science', 'english', 'others'] as $cat) {
            foreach (array_keys($result['subjects'][$cat]) as $subjectName) {
                expect($subjectName)->toBe(strtolower(trim($subjectName)));
            }
        }
    }
});

// ---------------------------------------------------------------------------
// Property 8 — Null json_content Skipped
// Feature: docling-grade-autofill, Property 8: Null json_content Skipped
// ---------------------------------------------------------------------------

it('Property 8: null/empty docling_json records are skipped and result equals valid-only processing ', function () {
    $aliases = array_column(pbtAllAliasTriples(), 0);
    $user = new \App\Models\User();
    for ($i = 0; $i < propertyTestIterations(); $i++) {
        $validFiles = [];
        for ($j = 0; $j < rand(1, 3); $j++) {
            $alias = $aliases[array_rand($aliases)];
            $grade = round(mt_rand(0, 10000) / 100, 2);
            $validFiles[] = ['docling_json' => ['texts' => [['text' => "Subject: $alias  Grade: $grade"]], 'tables' => []]];
        }
        $nullFiles = array_fill(0, rand(1, 3), ['docling_json' => []]);
        // Interleave null files while preserving valid file order
        $mixedFiles = $validFiles;
        foreach ($nullFiles as $nullFile) {
            $pos = rand(0, count($mixedFiles));
            array_splice($mixedFiles, $pos, 0, [$nullFile]);
        }
        $resultMixed = (new PbtTestableDoclingParserWithFiles($mixedFiles))->extract($user);
        $resultValid = (new PbtTestableDoclingParserWithFiles($validFiles))->extract($user);
        expect($resultMixed)->toBe($resultValid);
    }
});

// ---------------------------------------------------------------------------
// Property 9 — Round-Trip Text Extraction
// Feature: docling-grade-autofill, Property 9: Round-Trip Text Extraction
// ---------------------------------------------------------------------------

it('Property 9: all text nodes (including orig-only nodes) are processed during parseJsonContent ', function () {
    $parser = new PbtTestableDoclingParser();
    $triples = pbtAllAliasTriples();
    for ($i = 0; $i < propertyTestIterations(); $i++) {
        [$alias, , $expectedName] = $triples[array_rand($triples)];
        $grade = round(mt_rand(0, 10000) / 100, 2);
        $textContent = "Subject: $alias  Grade: $grade";
        // Alternate between 'text' and 'orig'-only nodes
        $node = $i % 2 === 0 ? ['text' => $textContent] : ['orig' => $textContent];
        $result = $parser->parseJsonContent(['texts' => [$node], 'tables' => []]);
        expect(array_key_exists($expectedName, $result))->toBeTrue();
        expect($result[$expectedName])->toBe((float) $grade);
    }
});

