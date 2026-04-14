<?php

use App\Exceptions\OpenRouterApiException;

describe('OpenRouterApiException', function () {
    it('returns the status code passed to the constructor', function () {
        $ex = new OpenRouterApiException('error', 429, 'body');
        expect($ex->getStatusCode())->toBe(429);
    });

    it('returns the response body passed to the constructor', function () {
        $ex = new OpenRouterApiException('error', 503, '{"error":"unavailable"}');
        expect($ex->getResponseBody())->toBe('{"error":"unavailable"}');
    });

    it('returns the message passed to the constructor', function () {
        $ex = new OpenRouterApiException('OpenRouter API rate limit exceeded.', 429, 'body');
        expect($ex->getMessage())->toBe('OpenRouter API rate limit exceeded.');
    });

    it('defaults status code to 0 when not provided', function () {
        $ex = new OpenRouterApiException('error');
        expect($ex->getStatusCode())->toBe(0);
    });

    it('defaults response body to empty string when not provided', function () {
        $ex = new OpenRouterApiException('error');
        expect($ex->getResponseBody())->toBe('');
    });

    it('is an instance of RuntimeException', function () {
        $ex = new OpenRouterApiException('error', 401, 'body');
        expect($ex)->toBeInstanceOf(\RuntimeException::class);
    });

    it('passes code and previous to parent constructor', function () {
        $previous = new \Exception('previous');
        $ex = new OpenRouterApiException('error', 401, 'body', 42, $previous);
        expect($ex->getCode())->toBe(42);
        expect($ex->getPrevious())->toBe($previous);
    });
});
