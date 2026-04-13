# Bugfix Requirements Document

## Introduction

The grade extraction feature uses an AI/LLM service (via GeminiClient → OpenRouter API) to extract
subject grades from uploaded report card images. Users are seeing the generic error message
"Grade extraction failed. Please try again." This message surfaces from a `\RuntimeException`
thrown anywhere in the pipeline: API connectivity issues, malformed/non-JSON responses from the
model, structurally invalid responses (missing required keys or entry fields), out-of-range grade
or confidence values, or the absence of valid image files for the user. The bug covers all
conditions that cause the extraction pipeline to fail and surface that error to the user.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN the OpenRouter API is unreachable or returns a non-2xx HTTP status THEN the system throws a `GeminiApiException` and returns a 503 with "Gemini API is currently unavailable. Please try again later." — however the root connectivity or auth failure is silently swallowed with no actionable detail logged beyond the raw HTTP status.

1.2 WHEN the model returns a response that contains no JSON object (e.g. pure prose, empty string, or only markdown fences with no braces) THEN the system throws a `RuntimeException` with "Gemini response is not valid JSON." and returns a 422 with that message.

1.3 WHEN the model returns a JSON object that is missing one or more of the required top-level keys (`math`, `science`, `english`, `others`) THEN the system throws a `RuntimeException` with "Gemini response missing required keys: math, science, english, others." and returns a 422.

1.4 WHEN the model returns a JSON object where a subject entry is missing the `grade` or `confidence` field, or those fields are non-numeric THEN the system throws a `RuntimeException` with "Gemini response has invalid subject entry structure." and returns a 422.

1.5 WHEN a subject entry contains a `grade` value outside [0, 100] or a `confidence` value outside [0.0, 1.0] THEN the system throws a `RuntimeException` with a range-specific message and returns a 422.

1.6 WHEN the user has no uploaded files, or all uploaded files are missing from storage, or all uploaded files are non-image MIME types THEN the system throws an `InvalidArgumentException` with "No valid image files found for extraction." and returns a 422.

1.7 WHEN the OpenRouter API key, endpoint, or model is not configured (null/empty config values) THEN the system sends a malformed or unauthenticated request, causing an API error that propagates as a `GeminiApiException` or an unexpected response structure.

### Expected Behavior (Correct)

2.1 WHEN the OpenRouter API is unreachable or returns a non-2xx HTTP status THEN the system SHALL throw a `GeminiApiException`, log the full error details (status code, response body, user ID), and return a 503 response so the user knows the service is temporarily unavailable.

2.2 WHEN the model returns a response containing no parseable JSON object THEN the system SHALL throw a `RuntimeException`, log the raw response and user ID, and return a 422 so the issue can be diagnosed and the prompt or sanitization logic improved.

2.3 WHEN the model returns a JSON object missing required top-level keys THEN the system SHALL throw a `RuntimeException`, log the decoded structure and user ID, and return a 422 so the structural mismatch can be identified.

2.4 WHEN the model returns a JSON object with an invalid subject entry structure THEN the system SHALL throw a `RuntimeException`, log the offending entry and user ID, and return a 422 so the entry-level schema mismatch can be diagnosed.

2.5 WHEN a subject entry contains an out-of-range `grade` or `confidence` value THEN the system SHALL throw a `RuntimeException`, log the subject name and value, and return a 422 so the validation failure can be traced.

2.6 WHEN the user has no uploaded files, all files are missing from storage, or all files are non-image MIME types THEN the system SHALL throw an `InvalidArgumentException`, log the user ID and file count, and return a 422 so missing-file scenarios can be distinguished from API or parsing failures.

2.7 WHEN the OpenRouter API key, endpoint, or model config values are missing or empty THEN the system SHALL detect the misconfiguration at construction time or before sending the request, throw a descriptive exception, and log the configuration issue so operators can identify and fix the deployment problem.

### Unchanged Behavior (Regression Prevention)

3.1 WHEN the user has valid image files and the model returns a well-formed JSON response with all required keys and valid entry structures THEN the system SHALL CONTINUE TO successfully extract grades, store the result in the session, and return a 200 JSON response with the redirect URL.

3.2 WHEN the model response contains markdown code fences around the JSON THEN the system SHALL CONTINUE TO sanitize and parse the response correctly without error.

3.3 WHEN subject names in the model response contain leading/trailing whitespace or uppercase characters THEN the system SHALL CONTINUE TO normalize them to lowercase trimmed keys in the extraction result.

3.4 WHEN the user's strand profile determines a specific grade URL (ICT, HUMSS, GAS, STEM, TVL, or default ABM) THEN the system SHALL CONTINUE TO return the correct strand-specific redirect URL in the success response.

3.5 WHEN a `GeminiApiException` is thrown THEN the system SHALL CONTINUE TO return a 503 response (not a 422), keeping the distinction between API-level failures and parsing/validation failures.
