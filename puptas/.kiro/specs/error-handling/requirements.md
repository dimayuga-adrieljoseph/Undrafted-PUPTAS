# Requirements Document

## Introduction

This feature adds comprehensive error handling to the PUPTAS Laravel + Vue full stack application. The goal is to surface generic, user-friendly messages to end users while capturing detailed technical information internally for debugging. No existing business logic will be modified unless strictly required for stability.

## Glossary

- **Handler**: Laravel's global exception handler located at `app/Exceptions/Handler.php`
- **ErrorResponse**: The standardized JSON structure returned by the API on any error: `{ "success": false, "message": "...", "errorCode": "..." }`
- **Interceptor**: The Axios request/response interceptor registered globally on the Vue frontend
- **ErrorStore**: The Vue/Pinia (or reactive) store that holds the current error state displayed to the user
- **SensitiveFields**: Request fields that must never be logged: `password`, `password_confirmation`, `token`, `secret`, `api_key`, `authorization`
- **StructuredLog**: A log entry written as a JSON object containing `message`, `exception`, `trace`, `timestamp`, `method`, `endpoint`, `user_id`, and sanitized `request_data`

---

## Requirements

### Requirement 1: Centralized Exception Handling

**User Story:** As a backend developer, I want all unhandled exceptions to be caught in one place, so that every API error returns a consistent, safe response.

#### Acceptance Criteria

1. THE Handler SHALL intercept every unhandled exception thrown during an HTTP request lifecycle.
2. WHEN an unhandled exception is caught, THE Handler SHALL return an HTTP response with a JSON body matching the ErrorResponse structure.
3. WHEN an unhandled exception is caught, THE Handler SHALL set `"success"` to `false` in the ErrorResponse.
4. WHEN an unhandled exception is caught, THE Handler SHALL set `"message"` to `"Something went wrong. Please try again later."` in the ErrorResponse.
5. WHEN an unhandled exception is caught, THE Handler SHALL set `"errorCode"` to `"INTERNAL_ERROR"` in the ErrorResponse.
6. THE Handler SHALL return all error responses with the `Content-Type: application/json` header.

---

### Requirement 2: Structured Internal Logging

**User Story:** As a backend developer, I want detailed technical information logged for every exception, so that I can diagnose production issues without exposing internals to users.

#### Acceptance Criteria

1. WHEN an exception is caught, THE Handler SHALL write a StructuredLog entry to the application log channel.
2. THE Handler SHALL include the exception message in every StructuredLog entry.
3. THE Handler SHALL include the full stack trace in every StructuredLog entry.
4. THE Handler SHALL include the UTC timestamp in every StructuredLog entry.
5. THE Handler SHALL include the HTTP method and endpoint path in every StructuredLog entry.
6. THE Handler SHALL include the authenticated user ID in every StructuredLog entry, or `null` when no user is authenticated.
7. THE Handler SHALL include sanitized request data in every StructuredLog entry, with all SensitiveFields replaced by `"[REDACTED]"`.
8. THE Handler SHALL NOT log the raw value of any SensitiveField.

---

### Requirement 3: HTTP Status-Specific Error Responses

**User Story:** As a frontend developer, I want each HTTP error status to return a predictable ErrorResponse, so that the Interceptor can handle them uniformly.

#### Acceptance Criteria

1. WHEN a validation exception occurs, THE Handler SHALL return HTTP 422 with `"errorCode": "VALIDATION_ERROR"` and a `"errors"` field containing field-level messages.
2. WHEN an authentication exception occurs, THE Handler SHALL return HTTP 401 with `"errorCode": "UNAUTHENTICATED"` and `"message": "You are not authenticated. Please log in."`.
3. WHEN an authorization exception occurs, THE Handler SHALL return HTTP 403 with `"errorCode": "FORBIDDEN"` and `"message": "You do not have permission to perform this action."`.
4. WHEN a model-not-found or route-not-found exception occurs, THE Handler SHALL return HTTP 404 with `"errorCode": "NOT_FOUND"` and `"message": "The requested resource was not found."`.
5. WHEN an unclassified server exception occurs, THE Handler SHALL return HTTP 500 with `"errorCode": "INTERNAL_ERROR"` and `"message": "Something went wrong. Please try again later."`.
6. THE Handler SHALL NOT include stack traces, SQL query text, or filesystem paths in any ErrorResponse field.

---

### Requirement 4: Production Security Configuration

**User Story:** As a system administrator, I want the application to suppress debug output in production, so that internal implementation details are never exposed to end users.

#### Acceptance Criteria

1. WHILE `APP_ENV` is `production`, THE Handler SHALL set `APP_DEBUG` to `false`.
2. WHILE `APP_DEBUG` is `false`, THE Handler SHALL NOT include exception class names, stack traces, or SQL errors in any API response body.
3. THE Handler SHALL return the same generic ErrorResponse regardless of whether `APP_DEBUG` is `true` or `false` for API requests.

---

### Requirement 5: Global Frontend HTTP Interceptor

**User Story:** As a frontend developer, I want all Axios HTTP errors caught in one place, so that no raw error objects or stack traces are ever shown to users.

#### Acceptance Criteria

1. THE Interceptor SHALL be registered once at application bootstrap and apply to all outgoing Axios requests.
2. WHEN an Axios response has an HTTP status of 4xx or 5xx, THE Interceptor SHALL extract the `message` field from the ErrorResponse body.
3. WHEN the `message` field is absent or the response body is not parseable, THE Interceptor SHALL use the fallback message `"An unexpected error occurred. Please try again."`.
4. THE Interceptor SHALL NOT expose raw error objects, JavaScript stack traces, or HTTP response bodies to the user interface.
5. WHEN a network error occurs with no HTTP response, THE Interceptor SHALL display `"Unable to connect. Please check your connection and try again."`.

---

### Requirement 6: User-Facing Error Display

**User Story:** As an end user, I want to see clear, actionable error messages when something goes wrong, so that I know what happened and what I can do next.

#### Acceptance Criteria

1. WHEN an API error is intercepted, THE ErrorStore SHALL store the error message for display in the UI.
2. WHEN an error message is stored, THE ErrorStore SHALL make it available to all Vue components that subscribe to it.
3. WHEN an error is displayed, THE ErrorStore SHALL provide a dismiss action that clears the error state.
4. WHERE a failed API call is retriable, THE ErrorStore SHALL expose a retry callback so the UI can offer a "Try Again" action.
5. WHEN a Vue component receives `null` or `undefined` data from an API response, THE component SHALL render a safe empty or placeholder state rather than throwing a runtime error.

---

### Requirement 7: Consistent Error Response Contract

**User Story:** As a developer, I want every API endpoint to return errors in the same structure, so that the frontend never needs endpoint-specific error parsing.

#### Acceptance Criteria

1. THE Handler SHALL apply the ErrorResponse structure to all API routes, including routes protected by middleware.
2. WHEN middleware rejects a request, THE Handler SHALL return an ErrorResponse rather than a plain text or HTML response.
3. FOR ALL ErrorResponse objects, the `"success"` field SHALL be `false`, the `"message"` field SHALL be a non-empty string, and the `"errorCode"` field SHALL be a non-empty string.
4. THE Handler SHALL NOT modify the response structure of successful (2xx) API responses.
