# Implementation Plan: OpenRouter Migration

## Overview

Finalize the migration from Gemini-branded code to OpenRouter-branded code. This is a targeted rename-and-update across four files, with no functional changes to the grade extraction pipeline.

## Tasks

- [x] 1. Create `OpenRouterApiException`
  - Create `app/Exceptions/OpenRouterApiException.php` with class `OpenRouterApiException extends \RuntimeException`
  - Implement constructor accepting `string $message`, `int $statusCode`, `string $responseBody`, `int $code`, `?\Throwable $previous`
  - Implement `getStatusCode(): int` and `getResponseBody(): string` methods
  - _Requirements: 2.1, 2.2_

  - [x] 1.1 Write unit tests for `OpenRouterApiException`
    - Create `tests/Unit/OpenRouterApiExceptionTest.php`
    - Test that `getStatusCode()` and `getResponseBody()` return the values passed to the constructor
    - _Requirements: 2.2_

  - [x] 1.2 Write property test for exception getter round-trip
    - **Property 1: Exception getter round-trip**
    - **Validates: Requirements 2.2**
    - Create `tests/Unit/OpenRouterClientPropertyTest.php`
    - Use `eris/eris` `forAll(Generator\int(), Generator\string())` to assert getters return constructor values unchanged

- [x] 2. Create `OpenRouterClient`
  - Create `app/Services/OpenRouterClient.php` with class `OpenRouterClient` in `App\Services`
  - Constructor reads `config('services.openrouter.key')`, `config('services.openrouter.endpoint')`, `config('services.openrouter.model')`; throws `\RuntimeException` if any are empty
  - Implement `send(array $images, string $prompt): string` building the OpenRouter chat completions request body
  - Include all four required headers on every request: `Authorization`, `Content-Type`, `HTTP-Referer` (`config('app.url')`), `X-Title` (`config('app.name')`)
  - Dispatch specific exceptions for HTTP 401, 429, 503, other non-2xx, and connection failures per the error table in the design
  - Return `choices[0].message.content` on success
  - Throw `OpenRouterApiException` (not `GeminiApiException`) in all error paths
  - _Requirements: 1.1, 3.1, 3.2, 3.3, 3.4, 4.1, 4.2, 5.1, 5.2, 5.3, 5.4, 6.1, 6.2, 6.3, 6.4, 6.6_

  - [x] 2.1 Write unit tests for `OpenRouterClient`
    - Create `tests/Unit/OpenRouterClientTest.php`
    - Test all four headers are present on every request (Requirements 3.1–3.4)
    - Test HTTP 401, 429, 503 each throw the correct message, status code, and body (Requirements 5.1–5.3)
    - Test connection failure throws exception with message beginning `"OpenRouter API connection failed:"` (Requirement 4.1)
    - Test successful response returns `choices[0].message.content` (Requirement 6.6)
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 4.1, 5.1, 5.2, 5.3, 6.6_

  - [x] 2.2 Write property test for generic non-2xx error message prefix
    - **Property 2: Generic non-2xx error message prefix**
    - **Validates: Requirements 4.2, 5.4**
    - Add to `tests/Unit/OpenRouterClientPropertyTest.php`
    - Use `forAll` over status codes 400–599 excluding 401, 429, 503; assert exception message starts with `"OpenRouter API returned HTTP"` and `getStatusCode()` matches

  - [x] 2.3 Write property test for request body structure
    - **Property 3: Request body structure for any image set**
    - **Validates: Requirements 6.1**
    - Add to `tests/Unit/OpenRouterClientPropertyTest.php`
    - Use `forAll` over non-empty arrays of mime types; assert `messages[0].content` has one `image_url` part per image followed by exactly one `text` part

  - [x] 2.4 Write property test for successful response content extraction
    - **Property 4: Successful response content extraction**
    - **Validates: Requirements 6.6**
    - Add to `tests/Unit/OpenRouterClientPropertyTest.php`
    - Use `forAll(Generator\string()->notEmpty())` with `Http::fake`; assert `send()` returns the exact content string

- [x] 3. Checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Update `GradeExtractionService`
  - In `app/Services/GradeExtractionService.php`, change the constructor type-hint from `GeminiClient` to `OpenRouterClient`
  - Update the `use` import to `App\Services\OpenRouterClient`
  - In `parse()`, update the three `\RuntimeException` messages to reference OpenRouter:
    - `"OpenRouter response is not valid JSON."`
    - `"OpenRouter response missing required keys: math, science, english, others."`
    - `"OpenRouter response has invalid subject entry structure."`
  - _Requirements: 1.2, 4.3, 4.4, 4.5, 6.5_

  - [x] 4.1 Update `GradeExtractionServiceTest`
    - In `tests/Unit/GradeExtractionServiceTest.php`, replace `GeminiClient` mock with `OpenRouterClient` mock
    - Add or update tests asserting the three updated error message strings (Requirements 4.3–4.5)
    - _Requirements: 4.3, 4.4, 4.5_

- [x] 5. Update `GradeExtractionController`
  - In `app/Http/Controllers/GradeExtractionController.php`, change `catch (GeminiApiException $e)` to `catch (OpenRouterApiException $e)`
  - Update the `use` import to `App\Exceptions\OpenRouterApiException`
  - Update the log key to `"OpenRouter API error during grade extraction"`
  - Update the user-facing error message to `"OpenRouter API is currently unavailable. Please try again later."`
  - _Requirements: 2.4, 4.6, 4.7_

  - [x] 5.1 Write feature tests for `GradeExtractionController`
    - Create or update `tests/Feature/GradeExtractionControllerTest.php`
    - Mock service throwing `OpenRouterApiException`; assert HTTP 503 response with correct error message (Requirement 4.6)
    - Assert log entry uses key `"OpenRouter API error during grade extraction"` (Requirement 4.7)
    - _Requirements: 4.6, 4.7_

- [x] 6. Delete legacy files
  - Delete `app/Services/GeminiClient.php`
  - Delete `app/Exceptions/GeminiApiException.php`
  - _Requirements: 1.3, 2.5_

  - [x] 6.1 Write smoke checks
    - Create `tests/Unit/OpenRouterMigrationSmokeTest.php`
    - Assert `class_exists(OpenRouterClient::class)` is true
    - Assert `class_exists(OpenRouterApiException::class)` is true
    - Assert `file_exists(app_path('Services/GeminiClient.php'))` is false
    - Assert `file_exists(app_path('Exceptions/GeminiApiException.php'))` is false
    - Assert `GradeExtractionService` constructor first parameter is type-hinted `OpenRouterClient`

- [x] 7. Final checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster MVP
- Each task references specific requirements for traceability
- Property tests use `eris/eris`; run a minimum of 100 iterations per property
- No functional changes to the grade extraction pipeline — only renames and error message updates
