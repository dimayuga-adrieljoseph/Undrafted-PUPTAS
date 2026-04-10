# Grade Extraction Failure Bugfix Design

## Overview

The grade extraction pipeline (`GradeExtractionService` → `GeminiClient` → OpenRouter API) fails
silently across multiple error paths, surfacing only the generic message "Grade extraction failed.
Please try again." to users. The fix adds structured logging with `user_id` and contextual detail
to every failure path, and adds early config-validation in `GeminiClient` so misconfigured
deployments are caught before a request is sent. No behavioral changes are made to the happy path
or to any existing exception-to-HTTP-status mappings.

## Glossary

- **Bug_Condition (C)**: Any input or system state that causes the extraction pipeline to fail
  without emitting a structured log entry containing `user_id` and actionable diagnostic detail.
- **Property (P)**: For every failure path, the system SHALL emit a structured `Log::error` entry
  with `user_id` and path-specific context, and return the correct HTTP status code.
- **Preservation**: All behaviors that must remain unchanged — happy-path extraction, markdown
  fence sanitization, key normalization, strand URL routing, and the 503 vs 422 distinction.
- **GradeExtractionService**: `app/Services/GradeExtractionService.php` — orchestrates image
  loading, prompt building, API call, sanitization, parsing, validation, and key normalization.
- **GeminiClient**: `app/Services/GeminiClient.php` — wraps the OpenRouter HTTP call and throws
  `GeminiApiException` on connectivity or non-2xx failures.
- **GradeExtractionController**: `app/Http/Controllers/GradeExtractionController.php` — catches
  exceptions and maps them to JSON responses; the primary site for logging fixes.
- **isBugCondition**: Pseudocode predicate that returns `true` when an input triggers a failure
  path that currently lacks adequate logging.

## Bug Details

### Bug Condition

The bug manifests across seven distinct failure paths in the extraction pipeline. In each case the
system either logs nothing, logs without `user_id`, or logs without the contextual detail needed
to diagnose the failure. The controller's `catch` blocks are the primary fix site.

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type ExtractionRequest
         (user, uploaded files, API config, raw model response)
  OUTPUT: boolean

  IF input.apiUnreachableOrNon2xx
     AND logEntryLacksStatusCodeOrResponseBody(input)
    RETURN true   -- 1.1: connectivity/HTTP failure not fully logged

  IF input.rawResponse contains no JSON object
     AND NOT logEntryExists(user_id=input.user.id, raw_response=input.rawResponse)
    RETURN true   -- 1.2: unparseable response not logged

  IF input.decodedJson missing ANY OF [math, science, english, others]
     AND NOT logEntryExists(user_id=input.user.id, decoded_structure=input.decodedJson)
    RETURN true   -- 1.3: missing required keys not logged

  IF input.subjectEntry missing grade OR confidence OR non-numeric
     AND NOT logEntryExists(user_id=input.user.id, offending_entry=input.subjectEntry)
    RETURN true   -- 1.4: invalid entry structure not logged

  IF (input.grade NOT IN [0,100] OR input.confidence NOT IN [0.0,1.0])
     AND NOT logEntryExists(user_id=input.user.id, subject=input.subject, value=input.value)
    RETURN true   -- 1.5: out-of-range value not logged

  IF input.validImageCount = 0
     AND NOT logEntryExists(user_id=input.user.id, file_count=input.fileCount)
    RETURN true   -- 1.6: no-valid-images not logged

  IF input.apiKey OR input.endpoint OR input.model is null/empty
     AND NOT configValidationThrowsDescriptiveException
    RETURN true   -- 1.7: missing config not caught early

  RETURN false
END FUNCTION
```

### Examples

- **1.1** API returns HTTP 401: controller logs `['user_id' => 5, 'message' => 'Gemini API returned HTTP 401: ...']` but omits the response body — operator cannot tell if it is an auth failure or a quota error.
- **1.2** Model returns `"Sorry, I cannot process images."`: `parse()` throws `RuntimeException('Gemini response is not valid JSON.')` but the raw string is never logged — impossible to know what the model actually said.
- **1.3** Model returns `{"math": {}, "science": {}}` (missing `english`, `others`): exception is thrown but the decoded structure is not logged.
- **1.4** Model returns `{"math": {"algebra": {"grade": "A"}}}` (missing `confidence`, non-numeric `grade`): exception thrown, offending entry not logged.
- **1.5** Model returns `{"math": {"algebra": {"grade": 105, "confidence": 0.9}}}`: exception thrown, subject name and value not logged.
- **1.6** User has three uploaded files, all PDFs: `InvalidArgumentException` thrown, but `user_id` and file count are not logged.
- **1.7** `OPENROUTER_KEY` env var is missing: `GeminiClient` sends `Authorization: Bearer ` (empty), API returns 401, which is only caught as a generic `GeminiApiException` with no config-level detail.

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- A well-formed API response with all required keys and valid entries MUST continue to return
  HTTP 200 with `{'redirect': '<strand-url>'}` and store `extraction_result` in the session.
- `sanitize()` MUST continue to strip markdown code fences before parsing.
- `normalizeKeys()` MUST continue to lowercase and trim all subject name keys.
- `getStrandGradeUrl()` MUST continue to return the correct strand-specific URL for ICT, HUMSS,
  GAS, STEM, TVL, and the default ABM path.
- `GeminiApiException` MUST continue to map to HTTP 503; `RuntimeException` and
  `InvalidArgumentException` MUST continue to map to HTTP 422.

**Scope:**
All inputs that do NOT trigger a failure path are completely unaffected by this fix. This includes:
- Valid image uploads with a well-formed model response
- Any request that does not reach the `catch` blocks in the controller
- The prompt text returned by `buildPrompt()`

## Hypothesized Root Cause

1. **Incomplete logging in controller catch blocks**: The `GeminiApiException` catch block logs
   `user_id` and `message` but omits `status_code` and `response_body`. The `RuntimeException`
   catch block logs `user_id` and `message` but omits the raw/decoded payload that caused the
   failure. The `InvalidArgumentException` is caught and returned as 422 with no logging at all.

2. **No logging for InvalidArgumentException (1.6)**: The controller catches
   `\InvalidArgumentException` and immediately returns a 422 without any `Log::` call, so
   missing-file scenarios leave no server-side trace.

3. **No early config validation in GeminiClient (1.7)**: The constructor assigns config values
   without checking for null/empty. A missing `OPENROUTER_KEY` results in a silent empty-bearer
   request that only fails at the HTTP layer, with no indication that the root cause is a
   misconfigured deployment.

4. **GeminiApiException message does not always include response body (1.1)**: When the API
   returns a non-2xx response, the exception message includes the body, but the controller's log
   call only logs `$e->getMessage()` — if the message is truncated or the body is large, the
   full context is lost. Adding `context` fields to the log call is safer.

## Correctness Properties

Property 1: Bug Condition - All Failure Paths Emit Structured Logs

_For any_ extraction request where `isBugCondition(input)` returns true (i.e., a failure path is
triggered), the fixed system SHALL emit a `Log::error` entry containing `user_id` and the
path-specific diagnostic context (status code + response body for API failures; raw response for
JSON parse failures; decoded structure for missing-key failures; offending entry for invalid-entry
failures; subject + value for range failures; file count for no-valid-images failures; config key
name for missing-config failures), and SHALL return the correct HTTP status code (503 for
`GeminiApiException`, 422 for all others).

**Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7**

Property 2: Preservation - Non-Failure-Path Behavior Unchanged

_For any_ extraction request where `isBugCondition(input)` returns false (i.e., the happy path or
any path not modified by the fix), the fixed system SHALL produce exactly the same result as the
original system: same HTTP status, same response body, same session state, same strand URL, same
sanitization and normalization behavior, and the same 503 vs 422 exception-to-status mapping.

**Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**

## Fix Implementation

### Changes Required

**File**: `app/Http/Controllers/GradeExtractionController.php`

**Function**: `extract(Request $request)`

**Specific Changes**:

1. **Add logging to InvalidArgumentException catch (fixes 1.6)**:
   ```php
   catch (\InvalidArgumentException $e) {
       Log::warning('Grade extraction: no valid image files', [
           'user_id'    => $user?->id,
           'message'    => $e->getMessage(),
           'file_count' => \App\Models\UserFile::where('user_id', $user?->id)->count(),
       ]);
       return response()->json(['error' => $e->getMessage()], 422);
   }
   ```

2. **Enrich GeminiApiException log (fixes 1.1)**:
   Add `status_code` and `response_body` context fields. These can be stored on the exception
   or extracted from the message; alternatively, add custom properties to `GeminiApiException`.

3. **Enrich RuntimeException log (fixes 1.2, 1.3, 1.4, 1.5)**:
   The `RuntimeException` message already contains the relevant detail (raw response, missing
   keys, offending entry, subject + value). Ensure the full message is logged; optionally pass
   additional context fields for structured querying.

---

**File**: `app/Services/GeminiClient.php`

**Function**: `__construct()`

**Specific Changes**:

4. **Add early config validation (fixes 1.7)**:
   ```php
   public function __construct()
   {
       $this->apiKey   = config('services.openrouter.key');
       $this->endpoint = config('services.openrouter.endpoint');
       $this->model    = config('services.openrouter.model');

       if (empty($this->apiKey) || empty($this->endpoint) || empty($this->model)) {
           throw new \RuntimeException(
               'OpenRouter configuration is incomplete: key, endpoint, and model are required.'
           );
       }
   }
   ```

5. **Optionally enrich GeminiApiException with HTTP context**:
   Store `$response->status()` and a truncated `$response->body()` on the exception so the
   controller can log them as structured fields rather than parsing the message string.

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate
the missing logging on unfixed code, then verify the fix emits the correct log entries and
preserves all existing behavior.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate the missing logging BEFORE implementing the
fix. Confirm or refute the root cause analysis.

**Test Plan**: Write tests that mock `Log::error`/`Log::warning` and assert the expected context
fields are present. Run these tests on the UNFIXED code to observe failures.

**Test Cases**:
1. **API failure log test**: Simulate a `GeminiApiException` and assert the log contains
   `status_code` and `response_body` fields (will fail on unfixed code — these fields are absent).
2. **No-images log test**: Simulate `InvalidArgumentException` from `loadImages` and assert
   `Log::warning` is called with `user_id` and `file_count` (will fail — no log call exists).
3. **Invalid JSON log test**: Simulate a `RuntimeException('Gemini response is not valid JSON.')`
   and assert the log contains the raw response string (will fail — raw response not in context).
4. **Missing config test**: Construct `GeminiClient` with empty config and assert a descriptive
   exception is thrown before any HTTP call is made (will fail — no validation exists).

**Expected Counterexamples**:
- `Log::warning` is never called for `InvalidArgumentException` paths.
- `Log::error` for `GeminiApiException` lacks `status_code` / `response_body` fields.
- `GeminiClient` constructor does not throw when config values are empty.

### Fix Checking

**Goal**: Verify that for all inputs where the bug condition holds, the fixed system emits the
correct log entry and returns the correct HTTP status.

**Pseudocode:**
```
FOR ALL input WHERE isBugCondition(input) DO
  result := extract_fixed(input)
  ASSERT correctHttpStatus(result)
  ASSERT logEntryContains(user_id, path_specific_context)
END FOR
```

### Preservation Checking

**Goal**: Verify that for all inputs where the bug condition does NOT hold, the fixed system
produces the same result as the original system.

**Pseudocode:**
```
FOR ALL input WHERE NOT isBugCondition(input) DO
  ASSERT extract_original(input) = extract_fixed(input)
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many random valid responses and verifies the happy path is unaffected.
- It catches edge cases in sanitization and normalization that manual tests might miss.
- It provides strong guarantees that the logging additions do not alter return values.

**Test Plan**: Observe behavior on UNFIXED code for happy-path inputs, then write property-based
tests capturing that behavior.

**Test Cases**:
1. **Happy path preservation**: Generate random well-formed model responses and verify HTTP 200
   with correct redirect URL and session state.
2. **Sanitization preservation**: Verify `sanitize()` continues to strip markdown fences across
   many fence variants.
3. **Key normalization preservation**: Generate random subject names with mixed case/whitespace
   and verify `normalizeKeys()` output is unchanged.
4. **Strand URL preservation**: Verify all six strand values map to the correct URL.
5. **503 vs 422 preservation**: Verify `GeminiApiException` → 503 and `RuntimeException` → 422
   after the fix.

### Unit Tests

- Test each controller catch block emits the expected `Log` call with correct context fields.
- Test `GeminiClient` constructor throws when any config value is null or empty.
- Test edge cases: empty string response, response with only fences, response with extra keys.

### Property-Based Tests

- Generate random invalid raw response strings and verify `parse()` always throws `RuntimeException`.
- Generate random valid extraction arrays and verify `normalizeKeys()` is idempotent on already-normalized keys.
- Generate random grade/confidence pairs and verify `validate()` accepts exactly the values in
  [0,100] × [0.0,1.0] and rejects all others.

### Integration Tests

- Full controller test: mock `GradeExtractionService` to throw each exception type and assert
  the correct HTTP status, response body, and log entry.
- Full service test: mock `GeminiClient` to return various raw strings and assert the correct
  exception or result is produced end-to-end.
- Config validation test: bind `GeminiClient` with empty config and assert the request never
  reaches the HTTP layer.
