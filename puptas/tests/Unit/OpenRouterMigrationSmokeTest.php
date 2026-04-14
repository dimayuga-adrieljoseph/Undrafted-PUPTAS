<?php

use App\Exceptions\OpenRouterApiException;
use App\Services\GradeExtractionService;
use App\Services\OpenRouterClient;

// ---------------------------------------------------------------------------
// Smoke checks — OpenRouter migration (Task 6.1)
// ---------------------------------------------------------------------------

test('OpenRouterClient class exists', function () {
    expect(class_exists(OpenRouterClient::class))->toBeTrue();
});

test('OpenRouterApiException class exists', function () {
    expect(class_exists(OpenRouterApiException::class))->toBeTrue();
});

test('GeminiClient.php file has been deleted', function () {
    $path = dirname(__DIR__, 2) . '/app/Services/GeminiClient.php';
    expect(file_exists($path))->toBeFalse();
});

test('GeminiApiException.php file has been deleted', function () {
    $path = dirname(__DIR__, 2) . '/app/Exceptions/GeminiApiException.php';
    expect(file_exists($path))->toBeFalse();
});

test('GradeExtractionService constructor first parameter is type-hinted OpenRouterClient', function () {
    $ref = new ReflectionClass(GradeExtractionService::class);
    $constructor = $ref->getConstructor();

    expect($constructor)->not->toBeNull();

    $params = $constructor->getParameters();
    expect($params)->not->toBeEmpty();

    $firstParam = $params[0];
    $type = $firstParam->getType();

    expect($type)->not->toBeNull();
    expect($type->getName())->toBe(OpenRouterClient::class);
});
