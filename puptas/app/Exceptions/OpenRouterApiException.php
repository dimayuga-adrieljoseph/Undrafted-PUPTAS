<?php

namespace App\Exceptions;

class OpenRouterApiException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        private readonly int $statusCode = 0,
        private readonly string $responseBody = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}
