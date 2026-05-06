<?php

use App\Models\User;
use App\Services\OpenRouterClient;
use App\Services\GradeExtractionService;

// ---------------------------------------------------------------------------
// Test double: exposes protected methods
// ---------------------------------------------------------------------------

class TestableGradeExtractionService extends GradeExtractionService
{
    public function sanitize(string $raw): string     { return parent::sanitize($raw); }
    public function parse(string $json): array        { return parent::parse($json); }
    public function validate(array $data): array      { return parent::validate($data); }
    public function normalizeKeys(array $data): array { return parent::normalizeKeys($data); }
}

function makeService(): TestableGradeExtractionService
{
    return new TestableGradeExtractionService(Mockery::mock(OpenRouterClient::class));
}

// Flat format: "Subject Name" => "grade_string"
function validFlatResult(array $overrides = []): array
{
    return array_merge([
        'math'    => ['General Mathematics' => '90'],
        'science' => ['Earth and Life Science' => '88'],
        'english' => ['Oral Communication' => '92'],
        'others'  => ['Filipino' => '85'],
    ], $overrides);
}

// ---------------------------------------------------------------------------
// sanitize()
// ---------------------------------------------------------------------------

describe('GradeExtractionService::sanitize()', function () {
    test('extracts JSON from ```json fences', function () {
        $svc = makeService();
        expect($svc->sanitize("```json\n{\"math\":{}}\n```"))->toBe('{"math":{}}');
    });

    test('extracts JSON from plain ``` fences', function () {
        $svc = makeService();
        expect($svc->sanitize("```\n{\"math\":{}}\n```"))->toBe('{"math":{}}');
    });

    test('returns plain JSON unchanged', function () {
        $svc = makeService();
        $raw = '{"math":{},"science":{},"english":{},"others":{}}';
        expect($svc->sanitize($raw))->toBe($raw);
    });

    test('strips leading prose before JSON object', function () {
        $svc = makeService();
        $raw = 'Here are the grades: {"math":{},"science":{},"english":{},"others":{}}';
        expect($svc->sanitize($raw))->toBe('{"math":{},"science":{},"english":{},"others":{}}');
    });

    test('strips trailing prose after JSON object', function () {
        $svc = makeService();
        $raw = '{"math":{},"science":{},"english":{},"others":{}} Hope that helps!';
        expect($svc->sanitize($raw))->toBe('{"math":{},"science":{},"english":{},"others":{}}');
    });

    test('handles mixed content: fences + prose', function () {
        $svc = makeService();
        $raw = "Sure! Here you go:\n```json\n{\"math\":{}}\n```\nLet me know.";
        expect($svc->sanitize($raw))->toBe('{"math":{}}');
    });

    test('returns original trimmed string when no JSON object found', function () {
        $svc = makeService();
        expect($svc->sanitize('  no json here  '))->toBe('no json here');
    });
});

// ---------------------------------------------------------------------------
// parse() — expects flat "Subject" => "grade" format wrapped in "subjects"
// ---------------------------------------------------------------------------

describe('GradeExtractionService::parse()', function () {
    test('accepts valid flat structure with all four keys', function () {
        $svc = makeService();
        $payload = ['subjects' => validFlatResult()];
        $result = $svc->parse(json_encode($payload));
        expect($result)->toBeArray()->toHaveKeys(['math', 'science', 'english', 'others']);
    });

    test('throws on missing subjects root key', function () {
        $svc = makeService();
        expect(fn () => $svc->parse(json_encode(validFlatResult())))
            ->toThrow(\RuntimeException::class, 'missing required "subjects" root key');
    });

    test('throws on missing required group key inside subjects', function () {
        $svc = makeService();
        $data = validFlatResult();
        unset($data['math']);
        expect(fn () => $svc->parse(json_encode(['subjects' => $data])))
            ->toThrow(\RuntimeException::class, 'missing required keys');
    });

    test('throws on invalid JSON string', function () {
        $svc = makeService();
        expect(fn () => $svc->parse('not json'))
            ->toThrow(\RuntimeException::class, 'not valid JSON');
    });

    test('throws on empty JSON object', function () {
        $svc = makeService();
        expect(fn () => $svc->parse('{}'))
            ->toThrow(\RuntimeException::class);
    });

    test('accepts empty groups', function () {
        $svc = makeService();
        $payload = ['subjects' => ['math' => [], 'science' => [], 'english' => [], 'others' => []]];
        expect($svc->parse(json_encode($payload)))->toBeArray();
    });

    test('accepts numeric grade values', function () {
        $svc = makeService();
        $payload = ['subjects' => validFlatResult(['math' => ['General Mathematics' => 90]])];
        expect($svc->parse(json_encode($payload)))->toBeArray();
    });

    test('throws when grade value is a non-numeric string', function () {
        $svc = makeService();
        $payload = ['subjects' => validFlatResult(['math' => ['General Mathematics' => 'A+']])];
        // parse() accepts any string/numeric value — range validation is done by validate()
        $parsed = $svc->parse(json_encode($payload));
        expect(fn () => $svc->validate($parsed))
            ->toThrow(\RuntimeException::class);
    });
});

// ---------------------------------------------------------------------------
// validate() — flat format, grade in [0,100]
// ---------------------------------------------------------------------------

describe('GradeExtractionService::validate()', function () {
    test('accepts boundary grade value 0', function () {
        $svc = makeService();
        expect($svc->validate(validFlatResult(['math' => ['subject' => '0']])))->toBeArray();
    });

    test('accepts boundary grade value 100', function () {
        $svc = makeService();
        expect($svc->validate(validFlatResult(['math' => ['subject' => '100']])))->toBeArray();
    });

    test('rejects grade value -1', function () {
        $svc = makeService();
        expect(fn () => $svc->validate(validFlatResult(['math' => ['subject' => '-1']])))
            ->toThrow(\RuntimeException::class);
    });

    test('rejects grade value 101', function () {
        $svc = makeService();
        expect(fn () => $svc->validate(validFlatResult(['math' => ['subject' => '101']])))
            ->toThrow(\RuntimeException::class);
    });

    test('returns the data array unchanged when valid', function () {
        $svc = makeService();
        $data = validFlatResult();
        expect($svc->validate($data))->toBe($data);
    });
});

// ---------------------------------------------------------------------------
// normalizeKeys() — flat format
// ---------------------------------------------------------------------------

describe('GradeExtractionService::normalizeKeys()', function () {
    test('lowercases uppercase subject keys', function () {
        $svc = makeService();
        $data = validFlatResult(['math' => ['GENERAL MATHEMATICS' => '90']]);
        $result = $svc->normalizeKeys($data);
        expect($result['subjects']['math'])->toHaveKey('general mathematics');
    });

    test('trims leading and trailing spaces from keys', function () {
        $svc = makeService();
        $data = validFlatResult(['math' => ['  General Mathematics  ' => '90']]);
        $result = $svc->normalizeKeys($data);
        expect($result['subjects']['math'])->toHaveKey('general mathematics');
    });

    test('preserves grade values after normalization', function () {
        $svc = makeService();
        $data = validFlatResult(['math' => ['  ALGEBRA  ' => '90']]);
        $result = $svc->normalizeKeys($data);
        expect($result['subjects']['math']['algebra'])->toBe(90.0);
    });

    test('handles empty groups without error', function () {
        $svc = makeService();
        $data = ['math' => [], 'science' => [], 'english' => [], 'others' => []];
        $result = $svc->normalizeKeys($data);
        expect($result['subjects'])->toBeArray();
    });

    test('wraps result in subjects key', function () {
        $svc = makeService();
        $result = $svc->normalizeKeys(validFlatResult());
        expect($result)->toHaveKey('subjects');
        expect($result['subjects'])->toHaveKeys(['math', 'science', 'english', 'others']);
    });
});
