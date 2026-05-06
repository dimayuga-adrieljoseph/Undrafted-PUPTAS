<?php

// ---------------------------------------------------------------------------
// Test doubles
// ---------------------------------------------------------------------------

class TestableDoclingParser extends \App\Services\DoclingParser
{
    public function resolveSubject(string $raw): ?array { return parent::resolveSubject($raw); }
    public function validateGrade(mixed $raw): ?float { return parent::validateGrade($raw); }
    public function scanTextNode(string $text): array { return parent::scanTextNode($text); }
    public function scanTable(array $table): array { return parent::scanTable($table); }
    public function parseJsonContent(array $jsonContent): array { return parent::parseJsonContent($jsonContent); }
    public function buildResult(array $flat): array { return parent::buildResult($flat); }
    public function normalizeKey(string $key): string { return parent::normalizeKey($key); }
}

class TestableDoclingParserWithFiles extends \App\Services\DoclingParser
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

function makeParser(): TestableDoclingParser
{
    return new TestableDoclingParser();
}

// ---------------------------------------------------------------------------
// resolveSubject
// ---------------------------------------------------------------------------

describe('DoclingParser::resolveSubject()', function () {

    // Task 3.1 — every alias in SUBJECT_MAPPING
    test('resolves math aliases', function (string $alias, string $expectedName) {
        $result = makeParser()->resolveSubject($alias);
        expect($result)->not->toBeNull();
        expect($result['category'])->toBe('math');
        expect($result['name'])->toBe($expectedName);
    })->with([
        ['general mathematics', 'general mathematics'],
        ['gen math',            'general mathematics'],
        ['math',                'general mathematics'],
        ['mathematics',         'general mathematics'],
        ['business mathematics','business mathematics'],
        ['business math',       'business mathematics'],
        ['statistics and probability', 'statistics and probability'],
        ['statistics',          'statistics and probability'],
        ['stats',               'statistics and probability'],
        ['stat and prob',       'statistics and probability'],
        ['pre-calculus',        'pre-calculus'],
        ['precalculus',         'pre-calculus'],
        ['pre-cal',             'pre-calculus'],
        ['pre cal',             'pre-calculus'],
        ['basic calculus',      'basic calculus'],
        ['basic cal',           'basic calculus'],
    ]);

    test('resolves science aliases', function (string $alias, string $expectedName) {
        $result = makeParser()->resolveSubject($alias);
        expect($result)->not->toBeNull();
        expect($result['category'])->toBe('science');
        expect($result['name'])->toBe($expectedName);
    })->with([
        ['earth and life science',   'earth and life science'],
        ['earth & life science',     'earth and life science'],
        ['els',                      'earth and life science'],
        ['physical science',         'physical science'],
        ['phys sci',                 'physical science'],
        ['earth science',            'earth science'],
        ['earth sci',                'earth science'],
        ['general chemistry 1',      'general chemistry 1'],
        ['gen chem 1',               'general chemistry 1'],
        ['gen chem',                 'general chemistry 1'],
        ['chemistry',                'general chemistry 1'],
    ]);

    test('resolves english aliases', function (string $alias, string $expectedName) {
        $result = makeParser()->resolveSubject($alias);
        expect($result)->not->toBeNull();
        expect($result['category'])->toBe('english');
        expect($result['name'])->toBe($expectedName);
    })->with([
        ['oral communication',  'oral communication'],
        ['oral comm',           'oral communication'],
        ['21st century literature', '21st century literature'],
        ['21st century lit',    '21st century literature'],
        ['21st lit',            '21st century literature'],
        ['21st century literature from the philippines and the world', '21st century literature'],
        ['english for academic purposes', 'english for academic purposes'],
        ['eapp',                'english for academic purposes'],
        ['reading and writing', 'reading and writing'],
        ['reading & writing',   'reading and writing'],
    ]);

    // Task 3.2 — unknown string returns null
    test('returns null for unknown subject', function () {
        expect(makeParser()->resolveSubject('unknown subject xyz'))->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// validateGrade
// ---------------------------------------------------------------------------

describe('DoclingParser::validateGrade()', function () {

    // Task 3.3 — valid values return floats
    test('returns float for valid numeric values', function (mixed $input) {
        $result = makeParser()->validateGrade($input);
        expect($result)->toBeFloat();
    })->with([0, 50, 100, 75.5, '90']);

    // Task 3.4 — non-numeric strings return null
    test('returns null for non-numeric strings', function (mixed $input) {
        expect(makeParser()->validateGrade($input))->toBeNull();
    })->with(['abc', '', 'A+']);

    // Task 3.5 — out-of-range values return null
    test('returns null for out-of-range values', function (mixed $input) {
        expect(makeParser()->validateGrade($input))->toBeNull();
    })->with([-1, 101, 200]);
});

// ---------------------------------------------------------------------------
// scanTextNode
// ---------------------------------------------------------------------------

describe('DoclingParser::scanTextNode()', function () {

    // Task 3.6 — primary regex format
    test('extracts subject-grade pair from primary regex format', function () {
        $result = makeParser()->scanTextNode('Subject: Mathematics  Grade: 90');
        expect($result)->not->toBeEmpty();
        $grade = array_values($result)[0];
        expect($grade)->toBe(90.0);
    });

    // Task 3.7 — multiple pairs
    test('extracts multiple subject-grade pairs from multiline text', function () {
        $text = "Subject: Gen Math  Grade: 90\nSubject: Oral Communication  Grade: 85";
        $result = makeParser()->scanTextNode($text);
        expect($result)->toHaveCount(2);
        expect(array_values($result))->toContain(90.0);
        expect(array_values($result))->toContain(85.0);
    });

    // Task 3.8 — no recognizable pairs
    test('returns empty array for text with no recognizable pairs', function () {
        $result = makeParser()->scanTextNode('This is just some random text with no grades');
        expect($result)->toBeEmpty();
    });
});

// ---------------------------------------------------------------------------
// scanTable
// ---------------------------------------------------------------------------

describe('DoclingParser::scanTable()', function () {

    // Task 3.9 — subject adjacent to numeric cell
    test('extracts subject-grade pair from table cells in same row', function () {
        $table = [
            'data' => [
                'table_cells' => [
                    ['text' => 'Gen Math', 'start_row_offset_idx' => 0],
                    ['text' => '90',       'start_row_offset_idx' => 0],
                ],
            ],
        ];
        $result = makeParser()->scanTable($table);
        expect($result)->toHaveKey('general mathematics');
        expect($result['general mathematics'])->toBe(90.0);
    });

    // Task 3.10 — no recognizable pairs
    test('returns empty array when no recognizable subject-grade pairs', function () {
        $table = [
            'data' => [
                'table_cells' => [
                    ['text' => 'foo'],
                    ['text' => 'bar'],
                ],
            ],
        ];
        expect(makeParser()->scanTable($table))->toBeEmpty();
    });
});

// ---------------------------------------------------------------------------
// parseJsonContent
// ---------------------------------------------------------------------------

describe('DoclingParser::parseJsonContent()', function () {

    // Task 3.11 — null/absent texts and tables
    test('returns empty array for empty input', function () {
        expect(makeParser()->parseJsonContent([]))->toBeEmpty();
    });

    test('returns empty array for empty texts and tables', function () {
        expect(makeParser()->parseJsonContent(['texts' => [], 'tables' => []]))->toBeEmpty();
    });
});

// ---------------------------------------------------------------------------
// extract via TestableDoclingParserWithFiles
// ---------------------------------------------------------------------------

describe('DoclingParser::extract() via TestableDoclingParserWithFiles', function () {

    // Task 3.12 — last-value-wins for overlapping subjects
    test('last value wins when multiple files have the same subject', function () {
        $parser = new TestableDoclingParserWithFiles([
            [
                'docling_json' => [
                    'texts' => [
                        ['text' => 'Subject: Gen Math  Grade: 80'],
                    ],
                    'tables' => [],
                ],
            ],
            [
                'docling_json' => [
                    'texts' => [
                        ['text' => 'Subject: Gen Math  Grade: 90'],
                    ],
                    'tables' => [],
                ],
            ],
        ]);

        $user = new \App\Models\User();
        $result = $parser->extract($user);
        expect($result['subjects']['math']['general mathematics'])->toBe(90.0);
    });

    // Task 3.13 — throws InvalidArgumentException when no valid pairs
    test('throws InvalidArgumentException when no valid subject-grade pairs found', function () {
        $parser = new TestableDoclingParserWithFiles([
            ['docling_json' => ['texts' => [['text' => 'no grades here']], 'tables' => []]],
        ]);

        $user = new \App\Models\User();
        expect(fn () => $parser->extract($user))
            ->toThrow(\InvalidArgumentException::class, 'No valid subject-grade pairs found in Docling JSON.');
    });

    // Task 3.14 — files with empty docling_json are skipped
    test('skips files with empty docling_json and processes valid ones', function () {
        $parser = new TestableDoclingParserWithFiles([
            ['docling_json' => []],
            [
                'docling_json' => [
                    'texts' => [
                        ['text' => 'Subject: Gen Math  Grade: 88'],
                    ],
                    'tables' => [],
                ],
            ],
        ]);

        $user = new \App\Models\User();
        $result = $parser->extract($user);
        expect($result['subjects']['math']['general mathematics'])->toBe(88.0);
    });
});

// ---------------------------------------------------------------------------
// buildResult
// ---------------------------------------------------------------------------

describe('DoclingParser::buildResult()', function () {

    // Task 3.15 — output shape
    test('returns array with exactly the four subject category keys', function () {
        $result = makeParser()->buildResult(['general mathematics' => 90.0, 'filipino' => 85.0]);
        expect($result)->toHaveKey('subjects');
        expect($result['subjects'])->toHaveKeys(['math', 'science', 'english', 'others']);
    });

    test('places known subject in correct category', function () {
        $result = makeParser()->buildResult(['general mathematics' => 90.0]);
        expect($result['subjects']['math'])->toHaveKey('general mathematics');
        expect($result['subjects']['math']['general mathematics'])->toBe(90.0);
    });

    test('places unknown subject in others with lowercased/trimmed key', function () {
        $result = makeParser()->buildResult(['filipino' => 85.0]);
        expect($result['subjects']['others'])->toHaveKey('filipino');
        expect($result['subjects']['others']['filipino'])->toBe(85.0);
    });
});
