# Tasks

## Task List

- [x] 1. Create Laravel Exception Handler
  - [x] 1.1 Create `app/Exceptions/Handler.php` with `render()` override that maps exception types to ErrorResponse JSON (ValidationException → 422, AuthenticationException → 401, AuthorizationException → 403, ModelNotFoundException/NotFoundHttpException → 404, catch-all → 500)
  - [x] 1.2 Implement `logStructured()` private method that writes a StructuredLog entry via `Log::error()` containing message, exception class, trace, UTC timestamp, HTTP method, endpoint, user_id, and sanitized request data
  - [x] 1.3 Implement `sanitize()` private method that recursively replaces all SensitiveFields (`password`, `password_confirmation`, `token`, `secret`, `api_key`, `authorization`) with `"[REDACTED]"` in request data arrays
  - [x] 1.4 Ensure `render()` always returns `Content-Type: application/json` and never includes stack traces, SQL text, or filesystem paths in the response body

- [x] 2. Create `useErrorStore` Composable
  - [x] 2.1 Create `resources/js/Composables/useErrorStore.js` using the same module-level `reactive()` singleton pattern as `useSnackbar.js`, with `errorState` containing `message` (null) and `retryCallback` (null)
  - [x] 2.2 Implement `setError(message, retryCallback = null)` action that sets both fields
  - [x] 2.3 Implement `clearError()` action that resets both fields to null
  - [x] 2.4 Implement `retry()` action that calls `retryCallback()` if it is set

- [x] 3. Register Axios Interceptor in `bootstrap.js`
  - [x] 3.1 Add a response interceptor to `window.axios` in `bootstrap.js` (after the existing CSRF setup) that extracts `error.response.data?.message` for 4xx/5xx responses, falls back to `"An unexpected error occurred. Please try again."` when the field is absent or the body is unparseable, and uses `"Unable to connect. Please check your connection and try again."` for network errors with no response
  - [x] 3.2 Ensure the interceptor calls `setError()` from `useErrorStore` and always re-rejects the promise so call-site `.catch()` handlers still work

- [x] 4. Write Backend Tests
  - [x] 4.1 Write PHPUnit feature tests for each exception-to-status mapping (one test per exception type: ValidationException, AuthenticationException, AuthorizationException, ModelNotFoundException, generic RuntimeException)
  - [x] 4.2 Write PHPUnit unit test for `sanitize()` verifying all SensitiveFields are redacted and non-sensitive fields are preserved — run with at least 100 generated input combinations (Property 4)
  - [x] 4.3 Write PHPUnit test verifying `logStructured()` writes the correct keys using `Log::fake()` (method, endpoint, user_id for authenticated and unauthenticated cases)
  - [x] 4.4 Write PHPUnit property test generating random exception types and asserting the ErrorResponse structural invariant (`success===false`, non-empty `message`, non-empty `errorCode`) holds for all (Property 1, Property 2)
  - [x] 4.5 Write PHPUnit property test generating exceptions with SQL-like and path-like messages and asserting the response body contains no internal details (Property 3)

- [x] 5. Write Frontend Tests
  - [x] 5.1 Write Vitest unit tests for `useErrorStore`: set/clear round-trip (Property 8) and retry callback invocation (Property 9)
  - [x] 5.2 Write Vitest property tests for the Axios interceptor: message extraction from any 4xx/5xx response (Property 6) and fallback for unparseable/missing message (Property 7), each running 100+ iterations with generated inputs
  - [x] 5.3 Write Vitest example test for network error (no response object) producing the connection error message
