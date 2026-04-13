# Design Document: OpenRouter Migration

## Overview

This migration finalizes the transition from Gemini-branded code to OpenRouter-branded code across the grade extraction feature. The codebase already routes requests through OpenRouter's API endpoint and reads from `config('services.openrouter.*')`, but retains `GeminiClient`, `GeminiApiException`, and Gemini-branded error messages. The work is a targeted rename-and-update with no functional changes to the grade extraction pipeline itself.

The scope is narrow:
- Rename `GeminiClient` → `OpenRouterClient` (new file, delete old)
- Rename `GeminiApiException` → `OpenRouterApiException` (new file, delete old)
- Add missing `HTTP-Referer` and `X-Title` headers to every outbound request
- Add specific error handling for HTTP 401, 429, and 503 responses
- Update all error messages and log keys to reference OpenRouter

## Architecture

The grade extraction feature follows a simple layered structure. Nothing in this architecture changes — only class names and error strings are updated.

```
HTTP Request
     │
     ▼
GradeExtractionController
     │  catches OpenRouterApiException → 503
     │  catches InvalidArgumentException → 422
     │  catches RuntimeException → 422
     ▼
GradeExtractionService
     │  loadImages() → buildPrompt() → send() → sanitize() → parse() → validate() → normalizeKeys()
     ▼
OpenRouterClient  (was GeminiClient)
     │  POST https://openrouter.ai/api/v1/chat/completions
     ▼
OpenRouter API
```

The `OpenRouterClient` is bound to `GradeExtractionService` via Laravel's service container through constructor injection. No explicit binding is needed — Laravel auto-resolves concrete classes.

## Components and Interfaces

### OpenRouterClient (`app/Services/OpenRouterClient.php`)

Replaces `GeminiClient`. Identical contract, updated class name, error messages, and headers.

```php
namespace App\Services;

use App\Exceptions\OpenRouterApiException;

class OpenRouterClient
{
    public function __construct(); // reads config('services.openrouter.*')

    /**
     * @param array<int, array{mime_type: string, data: string}> $images
     * @throws OpenRouterApiException
     */
    public function send(array $images, string $prompt): string;
}
```

Header set sent on every request:

| Header          | Value                          |
|-----------------|-------------------------------|
| Authorization   | `Bearer {openrouter.key}`     |
| Content-Type    | `application/json`            |
| HTTP-Referer    | `config('app.url')`           |
| X-Title         | `config('app.name')`          |

HTTP error dispatch table:

| Status | Exception message                                          |
|--------|------------------------------------------------------------|
| 401    | `"OpenRouter API authentication failed: invalid API key."` |
| 429    | `"OpenRouter API rate limit exceeded."`                    |
| 503    | `"OpenRouter model is currently unavailable."`             |
| other non-2xx | `"OpenRouter API returned HTTP {status}: {body}"`   |
| connection failure | `"OpenRouter API connection failed: {message}"`   |

### OpenRouterApiException (`app/Exceptions/OpenRouterApiException.php`)

Replaces `GeminiApiException`. Identical interface.

```php
namespace App\Exceptions;

class OpenRouterApiException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        private readonly int $statusCode = 0,
        private readonly string $responseBody = '',
        int $code = 0,
        ?\Throwable $previous = null
    );

    public function getStatusCode(): int;
    public function getResponseBody(): string;
}
```

### GradeExtractionService (`app/Services/GradeExtractionService.php`)

Constructor injection type-hint changes from `GeminiClient` to `OpenRouterClient`. Error messages in `parse()` updated to reference OpenRouter. All other logic is unchanged.

Updated error messages:
- `"OpenRouter response is not valid JSON."`
- `"OpenRouter response missing required keys: math, science, english, others."`
- `"OpenRouter response has invalid subject entry structure."`

### GradeExtractionController (`app/Http/Controllers/GradeExtractionController.php`)

`catch (GeminiApiException $e)` block updated to `catch (OpenRouterApiException $e)`.

Updated log key: `"OpenRouter API error during grade extraction"`

Updated user-facing error: `"OpenRouter API is currently unavailable. Please try again later."`

### Files to Delete

- `app/Services/GeminiClient.php`
- `app/Exceptions/GeminiApiException.php`

## Data Models

No data model changes. The request payload and response shape are unchanged:

**Request body** (OpenRouter chat completions format):
```json
{
  "model": "<config('services.openrouter.model')>",
  "messages": [
    {
      "role": "user",
      "content": [
        { "type": "image_url", "image_url": { "url": "data:<mime>;base64,<data>" } },
        "...(one per image)...",
        { "type": "text", "text": "<prompt>" }
      ]
    }
  ]
}
```

**Successful response** — the client reads `choices[0].message.content` as a string.

**Extraction result** (unchanged shape):
```json
{
  "math":    { "<subject>": { "grade": 0-100, "confidence": 0.0-1.0 } },
  "science": { ... },
  "english": { ... },
  "others":  { ... }
}
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

This feature is primarily a rename-and-update. Most acceptance criteria are structural checks (SMOKE) or specific behavioral examples (EXAMPLE). The following properties are the subset where input variation genuinely matters and running many iterations adds value.

### Property 1: Exception getter round-trip

*For any* integer status code and string response body, constructing an `OpenRouterApiException` with those values and calling `getStatusCode()` and `getResponseBody()` SHALL return the original values unchanged.

**Validates: Requirements 2.2**

### Property 2: Generic non-2xx error message prefix

*For any* non-2xx HTTP status code not specifically handled (i.e., not 401, 429, or 503), the `OpenRouterClient::send()` method SHALL throw an `OpenRouterApiException` whose message begins with `"OpenRouter API returned HTTP"` and whose `getStatusCode()` returns that status code.

**Validates: Requirements 4.2, 5.4**

### Property 3: Request body structure for any image set

*For any* non-empty array of images, `OpenRouterClient::send()` SHALL construct a request body containing a `messages` array with exactly one user message whose `content` array contains one `image_url` part per image followed by exactly one `text` part.

**Validates: Requirements 6.1**

### Property 4: Successful response content extraction

*For any* non-empty string value at `choices[0].message.content` in a mocked successful API response, `OpenRouterClient::send()` SHALL return that exact string.

**Validates: Requirements 6.6**

## Error Handling

| Scenario | Thrown by | Exception / Response |
|---|---|---|
| Connection failure (network, timeout) | `OpenRouterClient` | `OpenRouterApiException("OpenRouter API connection failed: ...")` |
| HTTP 401 | `OpenRouterClient` | `OpenRouterApiException("OpenRouter API authentication failed: invalid API key.", 401, body)` |
| HTTP 429 | `OpenRouterClient` | `OpenRouterApiException("OpenRouter API rate limit exceeded.", 429, body)` |
| HTTP 503 | `OpenRouterClient` | `OpenRouterApiException("OpenRouter model is currently unavailable.", 503, body)` |
| Other non-2xx | `OpenRouterClient` | `OpenRouterApiException("OpenRouter API returned HTTP {status}: {body}", status, body)` |
| Invalid JSON in response | `GradeExtractionService` | `\RuntimeException("OpenRouter response is not valid JSON.")` |
| Missing required keys | `GradeExtractionService` | `\RuntimeException("OpenRouter response missing required keys: math, science, english, others.")` |
| Invalid subject entry | `GradeExtractionService` | `\RuntimeException("OpenRouter response has invalid subject entry structure.")` |
| No valid image files | `GradeExtractionService` | `\InvalidArgumentException("No valid image files found for extraction.")` |
| `OpenRouterApiException` caught | `GradeExtractionController` | JSON 503: `"OpenRouter API is currently unavailable. Please try again later."` |
| `RuntimeException` caught | `GradeExtractionController` | JSON 422: exception message |
| `InvalidArgumentException` caught | `GradeExtractionController` | JSON 422: exception message |

The controller logs `OpenRouterApiException` under the key `"OpenRouter API error during grade extraction"` with `user_id`, `message`, `status_code`, and `response_body` fields.

## Testing Strategy

The project uses **Pest** (PHP). The existing `GradeExtractionServiceTest` covers the sanitize/parse/validate/normalizeKeys pipeline and must be updated to reference `OpenRouterClient` instead of `GeminiClient`.

### Unit Tests

New test file: `tests/Unit/OpenRouterClientTest.php`

Cover with example-based tests:
- All four required headers are present on every request (3.1–3.4)
- HTTP 401 → correct exception message, status code, and body (5.2)
- HTTP 429 → correct exception message, status code, and body (5.1)
- HTTP 503 → correct exception message, status code, and body (5.3)
- Connection failure → exception message begins with `"OpenRouter API connection failed:"` (4.1)
- Config values (model, key, endpoint) are read correctly (6.2–6.4)

New test file: `tests/Unit/OpenRouterApiExceptionTest.php`

Cover with example-based tests:
- `getStatusCode()` and `getResponseBody()` return constructor arguments (2.2)

Update `tests/Unit/GradeExtractionServiceTest.php`:
- Replace `GeminiClient` mock with `OpenRouterClient` mock
- Add tests for updated error message strings (4.3–4.5)

Update `tests/Feature/GradeExtractionControllerTest.php` (or create if absent):
- Mock service throwing `OpenRouterApiException` → assert 503 response and log key (4.6, 4.7)

### Property-Based Tests

The project uses Pest. For property-based testing, use **[eris/eris](https://github.com/giorgiosironi/eris)** (a PHP QuickCheck-style library compatible with PHPUnit/Pest).

New test file: `tests/Unit/OpenRouterClientPropertyTest.php`

Each property test runs a minimum of **100 iterations**.

**Property 1 — Exception getter round-trip**
```
// Feature: openrouter-migration, Property 1: Exception getter round-trip
forAll(
    Generator\int(),
    Generator\string()
)->then(function (int $statusCode, string $body) {
    $ex = new OpenRouterApiException('msg', $statusCode, $body);
    expect($ex->getStatusCode())->toBe($statusCode);
    expect($ex->getResponseBody())->toBe($body);
});
```

**Property 2 — Generic non-2xx error message prefix**
```
// Feature: openrouter-migration, Property 2: Generic non-2xx error message prefix
// Generate status codes in ranges 400-400, 402-428, 430-502, 504-599
forAll(Generator\choose(400, 599)->filter(fn($s) => !in_array($s, [401, 429, 503])))
->then(function (int $status) {
    Http::fake([...]);
    $client = new OpenRouterClient();
    expect(fn() => $client->send([$image], 'prompt'))
        ->toThrow(OpenRouterApiException::class)
        ->and(fn($e) => str_starts_with($e->getMessage(), 'OpenRouter API returned HTTP'))->toBeTrue()
        ->and(fn($e) => $e->getStatusCode())->toBe($status);
});
```

**Property 3 — Request body structure for any image set**
```
// Feature: openrouter-migration, Property 3: Request body structure for any image set
forAll(Generator\seq(Generator\elements(['image/jpeg', 'image/png', 'image/webp']))
    ->notEmpty()
)->then(function (array $mimeTypes) {
    // Build image array, capture request body via Http::fake()
    // Assert: messages[0].role === 'user'
    // Assert: content has count($mimeTypes) image_url parts + 1 text part
    // Assert: last content part is type 'text'
});
```

**Property 4 — Successful response content extraction**
```
// Feature: openrouter-migration, Property 4: Successful response content extraction
forAll(Generator\string()->notEmpty())->then(function (string $content) {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => $content]]]])]);
    $client = new OpenRouterClient();
    expect($client->send([$image], 'prompt'))->toBe($content);
});
```

### Smoke Checks

These can be simple assertions in a dedicated `tests/Unit/OpenRouterMigrationSmokeTest.php`:
- `class_exists(OpenRouterClient::class)` is true
- `class_exists(OpenRouterApiException::class)` is true
- `file_exists(app_path('Services/GeminiClient.php'))` is false
- `file_exists(app_path('Exceptions/GeminiApiException.php'))` is false
- `GradeExtractionService` constructor first parameter is type-hinted `OpenRouterClient`
