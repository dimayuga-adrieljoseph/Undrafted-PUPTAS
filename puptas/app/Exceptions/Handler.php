<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Sensitive request fields that must never appear in logs.
     */
    private const SENSITIVE_FIELDS = [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'api_key',
        'authorization',
    ];

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e): JsonResponse
    {
        $this->logStructured($e, $request);

        if ($e instanceof ValidationException) {
            return response()->json([
                'success'   => false,
                'message'   => $e->getMessage(),
                'errorCode' => 'VALIDATION_ERROR',
                'errors'    => $e->errors(),
            ], 422, ['Content-Type' => 'application/json']);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'success'   => false,
                'message'   => 'You are not authenticated. Please log in.',
                'errorCode' => 'UNAUTHENTICATED',
            ], 401, ['Content-Type' => 'application/json']);
        }

        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
            return response()->json([
                'success'   => false,
                'message'   => 'You do not have permission to perform this action.',
                'errorCode' => 'FORBIDDEN',
            ], 403, ['Content-Type' => 'application/json']);
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return response()->json([
                'success'   => false,
                'message'   => 'The requested resource was not found.',
                'errorCode' => 'NOT_FOUND',
            ], 404, ['Content-Type' => 'application/json']);
        }

        return response()->json([
            'success'   => false,
            'message'   => 'Something went wrong. Please try again later.',
            'errorCode' => 'INTERNAL_ERROR',
        ], 500, ['Content-Type' => 'application/json']);
    }

    /**
     * Write a structured log entry for the given exception and request.
     */
    private function logStructured(Throwable $e, Request $request): void
    {
        Log::error('exception', [
            'message'      => $e->getMessage(),
            'exception'    => get_class($e),
            'trace'        => $e->getTraceAsString(),
            'timestamp'    => now()->utc()->toIso8601String(),
            'method'       => $request->method(),
            'endpoint'     => $request->path(),
            'user_id'      => optional(Auth::user())->id,
            'request_data' => $this->sanitize($request->all()),
        ]);
    }

    /**
     * Recursively replace all SensitiveFields in the given array with "[REDACTED]".
     */
    private function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_FIELDS, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            }
        }

        return $data;
    }
}
