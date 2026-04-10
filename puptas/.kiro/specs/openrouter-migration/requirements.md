# Requirements Document

## Introduction

This feature completes the migration of all AI API calls from direct Gemini API usage to OpenRouter. The codebase is partially migrated: `GeminiClient` already reads from `config('services.openrouter.*')` and uses the OpenRouter request format, but retains Gemini-branded class names, error messages, and is missing required OpenRouter HTTP headers (`HTTP-Referer` and `X-Title`). This migration finalizes the rename, adds the missing headers, and updates all error messages and exception handling to reference OpenRouter consistently.

## Glossary

- **OpenRouter_Client**: The HTTP client class responsible for sending requests to the OpenRouter API. Currently named `GeminiClient`.
- **OpenRouter_Exception**: The exception class thrown when the OpenRouter API returns an error or is unreachable. Currently named `GeminiApiException`.
- **Grade_Extraction_Service**: The service class that orchestrates image loading, prompt construction, API invocation, and response parsing. Currently `GradeExtractionService`.
- **Grade_Extraction_Controller**: The HTTP controller that handles grade extraction requests and catches API exceptions.
- **OpenRouter_API**: The external AI routing service at `https://openrouter.ai/api/v1/chat/completions`.
- **HTTP-Referer**: An OpenRouter-required request header identifying the referring application URL.
- **X-Title**: An OpenRouter-required request header identifying the application name.

---

## Requirements

### Requirement 1: Rename GeminiClient to OpenRouterClient

**User Story:** As a developer, I want the HTTP client class to be named after the service it calls, so that the codebase accurately reflects the underlying API provider.

#### Acceptance Criteria

1. THE OpenRouter_Client SHALL be defined in `app/Services/OpenRouterClient.php` with the class name `OpenRouterClient` in the `App\Services` namespace.
2. THE Grade_Extraction_Service SHALL reference `OpenRouterClient` via constructor injection instead of `GeminiClient`.
3. THE OpenRouter_Client SHALL be removed from `app/Services/GeminiClient.php` and that file SHALL be deleted.

---

### Requirement 2: Rename GeminiApiException to OpenRouterApiException

**User Story:** As a developer, I want the API exception class to be named after the service it represents, so that exception handling code is unambiguous.

#### Acceptance Criteria

1. THE OpenRouter_Exception SHALL be defined in `app/Exceptions/OpenRouterApiException.php` with the class name `OpenRouterApiException` in the `App\Exceptions` namespace.
2. THE OpenRouter_Exception SHALL expose `getStatusCode(): int` and `getResponseBody(): string` methods identical in behavior to the current `GeminiApiException`.
3. THE OpenRouter_Client SHALL throw `OpenRouterApiException` instead of `GeminiApiException`.
4. THE Grade_Extraction_Controller SHALL catch `OpenRouterApiException` instead of `GeminiApiException`.
5. THE `app/Exceptions/GeminiApiException.php` file SHALL be deleted after all references are updated.

---

### Requirement 3: Add Required OpenRouter HTTP Headers

**User Story:** As a developer, I want all requests to OpenRouter to include the required `HTTP-Referer` and `X-Title` headers, so that the application complies with OpenRouter's API requirements and is correctly identified in the OpenRouter dashboard.

#### Acceptance Criteria

1. WHEN the OpenRouter_Client sends a request, THE OpenRouter_Client SHALL include an `HTTP-Referer` header with the value of `config('app.url')`.
2. WHEN the OpenRouter_Client sends a request, THE OpenRouter_Client SHALL include an `X-Title` header with the value of `config('app.name')`.
3. WHEN the OpenRouter_Client sends a request, THE OpenRouter_Client SHALL include an `Authorization` header with the value `Bearer {OPENROUTER_API_KEY}`.
4. WHEN the OpenRouter_Client sends a request, THE OpenRouter_Client SHALL include a `Content-Type` header with the value `application/json`.

---

### Requirement 4: Update Error Messages to Reference OpenRouter

**User Story:** As a developer, I want all error messages and log entries to reference OpenRouter instead of Gemini, so that logs and user-facing errors are accurate and not misleading.

#### Acceptance Criteria

1. WHEN the OpenRouter_Client fails to connect, THE OpenRouter_Client SHALL throw an `OpenRouterApiException` with a message beginning with `"OpenRouter API connection failed:"`.
2. WHEN the OpenRouter API returns a non-2xx HTTP response, THE OpenRouter_Client SHALL throw an `OpenRouterApiException` with a message beginning with `"OpenRouter API returned HTTP"`.
3. WHEN the Grade_Extraction_Service receives a response that is not valid JSON, THE Grade_Extraction_Service SHALL throw a `\RuntimeException` with the message `"OpenRouter response is not valid JSON."`.
4. WHEN the Grade_Extraction_Service receives a response missing required top-level keys, THE Grade_Extraction_Service SHALL throw a `\RuntimeException` with the message `"OpenRouter response missing required keys: math, science, english, others."`.
5. WHEN the Grade_Extraction_Service receives a response with an invalid subject entry structure, THE Grade_Extraction_Service SHALL throw a `\RuntimeException` with the message `"OpenRouter response has invalid subject entry structure."`.
6. WHEN the Grade_Extraction_Controller catches an `OpenRouterApiException`, THE Grade_Extraction_Controller SHALL return a JSON response with the error message `"OpenRouter API is currently unavailable. Please try again later."` and HTTP status 503.
7. WHEN the Grade_Extraction_Controller logs an `OpenRouterApiException`, THE Grade_Extraction_Controller SHALL log the event under the key `"OpenRouter API error during grade extraction"`.

---

### Requirement 5: Handle OpenRouter-Specific Error Conditions

**User Story:** As a developer, I want the client to handle OpenRouter-specific HTTP error codes, so that the application can surface meaningful errors for rate limiting, authentication failures, and model unavailability.

#### Acceptance Criteria

1. WHEN the OpenRouter API returns HTTP 429, THE OpenRouter_Client SHALL throw an `OpenRouterApiException` with a message of `"OpenRouter API rate limit exceeded."`, status code 429, and the raw response body.
2. WHEN the OpenRouter API returns HTTP 401, THE OpenRouter_Client SHALL throw an `OpenRouterApiException` with a message of `"OpenRouter API authentication failed: invalid API key."`, status code 401, and the raw response body.
3. WHEN the OpenRouter API returns HTTP 503, THE OpenRouter_Client SHALL throw an `OpenRouterApiException` with a message of `"OpenRouter model is currently unavailable."`, status code 503, and the raw response body.
4. WHEN the OpenRouter API returns any other non-2xx HTTP status, THE OpenRouter_Client SHALL throw an `OpenRouterApiException` with a message beginning with `"OpenRouter API returned HTTP"`, the numeric status code, and the raw response body.

---

### Requirement 6: Preserve Existing Functional Behavior

**User Story:** As a developer, I want the grade extraction feature to behave identically after the migration, so that end users experience no change in functionality.

#### Acceptance Criteria

1. THE OpenRouter_Client SHALL send requests using the OpenRouter chat completions format with a `messages` array containing a single user message with interleaved `image_url` and `text` content parts.
2. THE OpenRouter_Client SHALL read the model identifier from `config('services.openrouter.model')`.
3. THE OpenRouter_Client SHALL read the API key from `config('services.openrouter.key')`.
4. THE OpenRouter_Client SHALL read the endpoint URL from `config('services.openrouter.endpoint')`.
5. THE Grade_Extraction_Service SHALL preserve all existing prompt text, sanitization, parsing, validation, and key-normalization logic without modification.
6. WHEN the OpenRouter API returns a successful response, THE OpenRouter_Client SHALL return the string value at `choices[0].message.content`.
