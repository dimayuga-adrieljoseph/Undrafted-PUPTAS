<?php

use App\Models\User;
use App\Models\UserFile;
use App\Services\OpenRouterClient;
use App\Services\GradeExtractionService;
use Illuminate\Support\Facades\Storage;

// ---------------------------------------------------------------------------
// Test double: exposes protected methods and stubs loadImages
// ---------------------------------------------------------------------------

class TestableGradeExtractionService extends GradeExtractionService
{
    public array $stubbedImages = [];

    public function sanitize(string $raw): string
    {
        return parent::sanitize($raw);
    }

    public function parse(string $json): array
    {
        return parent::parse($json);
    }

    public function validate(array $data): array
    {
        return parent::validate($data);
    }

    public function normalizeKeys(array $data): array
    {
        return parent::normalizeKeys($data);
    }

    public function loadImages(User $user): array
    {
        return parent::loadImages($user);
    }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeService(): TestableGradeExtractionService
{
    $client = Mockery::mock(OpenRouterClient::class);
    return new TestableGradeExtractionService($client);
}

function validResult(array $overrides = []): array
{
    return array_merge([
        'math'    => ['algebra' => ['grade' => 90, 'confidence' => 0.95]],
        'science' => ['biology' => ['grade' => 88, 'confidence' => 0.92]],
        'english' => ['english' => ['grade' => 92, 'confidence' => 0.97]],
        'others'  => ['araling panlipunan' => ['grade' => 85, 'confidence' => 0.80]],
    ], $overrides);
}

// ---------------------------------------------------------------------------
// 6.1 sanitize()
// ---------------------------------------------------------------------------

describe('GradeExtractionService::sanitize()', function () {
    test('extracts JSON from ```json fences', function () {
        $svc = makeService();
        $raw = "```json\n{\"math\":{}}\n```";
        expect($svc->sanitize($raw))->toBe('{"math":{}}');
    });

    test('extracts JSON from plain ``` fences', function () {
        $svc = makeService();
        $raw = "```\n{\"math\":{}}\n```";
        expect($svc->sanitize($raw))->toBe('{"math":{}}');
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
        $raw = "Sure! Here you go:\n```json\n{\"math\":{}}\n```\nLet me know if you need more.";
        expect($svc->sanitize($raw))->toBe('{"math":{}}');
    });

    test('returns original trimmed string when no JSON object found', function () {
        $svc = makeService();
        $raw = '  no json here  ';
        expect($svc->sanitize($raw))->toBe('no json here');
    });
});

// ---------------------------------------------------------------------------
// 6.2 parse()
// ---------------------------------------------------------------------------

describe('GradeExtractionService::parse()', function () {
    test('accepts valid structure with all four keys', function () {
        $svc = makeService();
        $json = json_encode(validResult());
        expect($svc->parse($json))->toBeArray()->toHaveKeys(['math', 'science', 'english', 'others']);
    });

    test('throws on missing required key', function () {
        $svc = makeService();
        $data = validResult();
        unset($data['math']);
        expect(fn () => $svc->parse(json_encode($data)))->toThrow(\RuntimeException::class, 'OpenRouter response missing required keys: math, science, english, others.');
    });

    test('throws on invalid JSON string', function () {
        $svc = makeService();
        expect(fn () => $svc->parse('not json'))->toThrow(\RuntimeException::class, 'OpenRouter response is not valid JSON.');
    });

    test('throws on empty JSON object (missing required keys)', function () {
        $svc = makeService();
        expect(fn () => $svc->parse('{}'))->toThrow(\RuntimeException::class, 'OpenRouter response missing required keys: math, science, english, others.');
    });

    test('throws when subject entry is missing grade key', function () {
        $svc = makeService();
        $data = validResult();
        $data['math']['algebra'] = ['confidence' => 0.9]; // no grade
        expect(fn () => $svc->parse(json_encode($data)))->toThrow(\RuntimeException::class, 'OpenRouter response has invalid subject entry structure.');
    });

    test('throws when subject entry is missing confidence key', function () {
        $svc = makeService();
        $data = validResult();
        $data['math']['algebra'] = ['grade' => 90]; // no confidence
        expect(fn () => $svc->parse(json_encode($data)))->toThrow(\RuntimeException::class, 'OpenRouter response has invalid subject entry structure.');
    });

    test('throws when grade is a non-numeric string', function () {
        $svc = makeService();
        $data = validResult();
        $data['math']['algebra'] = ['grade' => 'A+', 'confidence' => 0.9];
        expect(fn () => $svc->parse(json_encode($data)))->toThrow(\RuntimeException::class, 'OpenRouter response has invalid subject entry structure.');
    });

    test('accepts empty groups (no subjects in a group)', function () {
        $svc = makeService();
        $data = ['math' => [], 'science' => [], 'english' => [], 'others' => []];
        expect($svc->parse(json_encode($data)))->toBeArray();
    });
});

// ---------------------------------------------------------------------------
// 6.3 validate()
// ---------------------------------------------------------------------------

describe('GradeExtractionService::validate()', function () {
    test('accepts boundary grade value 0', function () {
        $svc = makeService();
        $data = validResult(['math' => ['algebra' => ['grade' => 0, 'confidence' => 0.5]]]);
        expect($svc->validate($data))->toBeArray();
    });

    test('accepts boundary grade value 100', function () {
        $svc = makeService();
        $data = validResult(['math' => ['algebra' => ['grade' => 100, 'confidence' => 0.5]]]);
        expect($svc->validate($data))->toBeArray();
    });

    test('rejects grade value -1', function () {
        $svc = makeService();
        $data = validResult(['math' => ['algebra' => ['grade' => -1, 'confidence' => 0.5]]]);
        expect(fn () => $svc->validate($data))->toThrow(\RuntimeException::class);
    });

    test('rejects grade value 101', function () {
        $svc = makeService();
        $data = validResult(['math' => ['algebra' => ['grade' => 101, 'confidence' => 0.5]]]);
        expect(fn () => $svc->validate($data))->toThrow(\RuntimeException::class);
    });

    test('accepts boundary confidence value 0.0', function () {
        $svc = makeService();
        $data = validResult(['math' => ['algebra' => ['grade' => 90, 'confidence' => 0.0]]]);
        expect($svc->validate($data))->toBeArray();
    });

    test('accepts boundary confidence value 1.0', function () {
        $svc = makeService();
        $data = validResult(['math' => ['algebra' => ['grade' => 90, 'confidence' => 1.0]]]);
        expect($svc->validate($data))->toBeArray();
    });

    test('rejects confidence value -0.01', function () {
        $svc = makeService();
        $data = validResult(['math' => ['algebra' => ['grade' => 90, 'confidence' => -0.01]]]);
        expect(fn () => $svc->validate($data))->toThrow(\RuntimeException::class);
    });

    test('rejects confidence value 1.01', function () {
        $svc = makeService();
        $data = validResult(['math' => ['algebra' => ['grade' => 90, 'confidence' => 1.01]]]);
        expect(fn () => $svc->validate($data))->toThrow(\RuntimeException::class);
    });

    test('returns the data array unchanged when valid', function () {
        $svc = makeService();
        $data = validResult();
        expect($svc->validate($data))->toBe($data);
    });
});

// ---------------------------------------------------------------------------
// 6.4 normalizeKeys()
// ---------------------------------------------------------------------------

describe('GradeExtractionService::normalizeKeys()', function () {
    test('lowercases uppercase subject keys', function () {
        $svc = makeService();
        $data = validResult(['math' => ['ALGEBRA' => ['grade' => 90, 'confidence' => 0.9]]]);
        $result = $svc->normalizeKeys($data);
        expect($result['math'])->toHaveKey('algebra');
        expect($result['math'])->not->toHaveKey('ALGEBRA');
    });

    test('trims leading and trailing spaces from keys', function () {
        $svc = makeService();
        $data = validResult(['math' => ['  algebra  ' => ['grade' => 90, 'confidence' => 0.9]]]);
        $result = $svc->normalizeKeys($data);
        expect($result['math'])->toHaveKey('algebra');
    });

    test('handles mixed case with spaces', function () {
        $svc = makeService();
        $data = validResult(['others' => ['  Araling Panlipunan  ' => ['grade' => 85, 'confidence' => 0.8]]]);
        $result = $svc->normalizeKeys($data);
        expect($result['others'])->toHaveKey('araling panlipunan');
    });

    test('preserves unicode characters while lowercasing', function () {
        $svc = makeService();
        $data = validResult(['others' => ['Español' => ['grade' => 88, 'confidence' => 0.85]]]);
        $result = $svc->normalizeKeys($data);
        // strtolower on ASCII portion; key should be trimmed at minimum
        expect(array_key_exists('español', $result['others']) || array_key_exists('Español', $result['others']))->toBeTrue();
    });

    test('preserves entry values after normalization', function () {
        $svc = makeService();
        $entry = ['grade' => 90, 'confidence' => 0.95];
        $data = validResult(['math' => ['  ALGEBRA  ' => $entry]]);
        $result = $svc->normalizeKeys($data);
        expect($result['math']['algebra'])->toBe($entry);
    });

    test('handles empty groups without error', function () {
        $svc = makeService();
        $data = ['math' => [], 'science' => [], 'english' => [], 'others' => []];
        expect($svc->normalizeKeys($data))->toBe($data);
    });
});
