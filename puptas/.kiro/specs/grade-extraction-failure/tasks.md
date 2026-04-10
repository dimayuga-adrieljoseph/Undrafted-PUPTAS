# Implementation Plan

- [x] 1. Write bug condition exploration test
  - **Property 1: Bug Condition** - Missing Structured Logs on Failure Paths
  - **CRITICAL**: This test MUST FAIL on unfixed code — failure confirms the bugs exist
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior — it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate missing logging across all seven failure paths
  - **Scoped PBT Approach**: Scope each sub-case to a concrete failing scenario for reproducibility
  - Mock `Log::error` / `Log::warning` using Laravel's `Log::spy()` and assert expected context fields
  - Sub-case 1.1 — Simulate `GeminiApiException` in controller; assert `Log::error` is called with `status_code` and `response_body` fields (will FAIL — these fields are absent in unfixed code)
  - Sub-case 1.2/1.3/1.4/1.5 — Simulate `RuntimeException` with a raw-response message; assert `Log::error` context contains the payload detail (will FAIL — only `message` is logged)
  - Sub-case 1.6 — Simulate `InvalidArgumentException` from `loadImages`; assert `Log::warning` is called with `user_id` and `file_count` (will FAIL — no log call exists)
  - Sub-case 1.7 — Construct `GeminiClient` with empty config values; assert a descriptive `RuntimeException` is thrown before any HTTP call (will FAIL — no validation exists)
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests FAIL (this is correct — it proves the bugs exist)
  - Document counterexamples found (e.g., "Log::warning never called for InvalidArgumentException", "GeminiClient constructor does not throw on empty config")
  - Mark task complete when tests are written, run, and failures are documented
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7_

- [x] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Happy Path and Existing Behaviors Unchanged
  - **IMPORTANT**: Follow observation-first methodology
  - Observe: controller returns HTTP 200 with `{'redirect': '<strand-url>'}` and stores `extraction_result` in session on a well-formed response (unfixed code)
  - Observe: `sanitize()` strips markdown code fences correctly on unfixed code
  - Observe: `normalizeKeys()` lowercases and trims subject name keys on unfixed code
  - Observe: `getStrandGradeUrl()` returns correct URL for all six strand values on unfixed code
  - Observe: `GeminiApiException` → HTTP 503, `RuntimeException` → HTTP 422 on unfixed code
  - Write property-based tests:
    - Generate random well-formed model responses; verify HTTP 200, correct redirect URL, and session state (from Preservation Requirements in design)
    - Generate many fence variants; verify `sanitize()` output is always valid JSON string
    - Generate random subject names with mixed case/whitespace; verify `normalizeKeys()` output is lowercase-trimmed and idempotent
    - Assert all six strand values map to the correct URL
    - Assert `GeminiApiException` → 503 and `RuntimeException` → 422 (503 vs 422 distinction)
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [x] 3. Fix grade extraction failure — add structured logging and early config validation

  - [x] 3.1 Add logging to InvalidArgumentException catch block in GradeExtractionController
    - In `GradeExtractionController::extract()`, add `Log::warning` call inside the `\InvalidArgumentException` catch block
    - Log `user_id`, `message`, and `file_count` (query `UserFile::where('user_id', $user?->id)->count()`)
    - Return the existing `response()->json(['error' => $e->getMessage()], 422)` unchanged
    - _Bug_Condition: isBugCondition(input) where input.validImageCount = 0 AND no log entry exists with user_id and file_count_
    - _Expected_Behavior: Log::warning('Grade extraction: no valid image files', ['user_id' => ..., 'message' => ..., 'file_count' => ...])_
    - _Preservation: InvalidArgumentException MUST continue to map to HTTP 422; response body unchanged_
    - _Requirements: 1.6, 2.6_

  - [x] 3.2 Enrich GeminiApiException log with status_code and response_body context fields
    - In `GradeExtractionController::extract()`, update the `GeminiApiException` catch block's `Log::error` call
    - Add `status_code` and `response_body` as context fields (parse from `$e->getMessage()` or add properties to `GeminiApiException`)
    - Optionally add `$statusCode` and `$responseBody` properties to `GeminiApiException` and populate them in `GeminiClient::send()` when throwing on non-2xx
    - Return the existing HTTP 503 response unchanged
    - _Bug_Condition: isBugCondition(input) where input.apiUnreachableOrNon2xx AND logEntryLacksStatusCodeOrResponseBody_
    - _Expected_Behavior: Log::error context contains status_code and response_body fields_
    - _Preservation: GeminiApiException MUST continue to map to HTTP 503_
    - _Requirements: 1.1, 2.1_

  - [x] 3.3 Enrich RuntimeException log with payload context
    - In `GradeExtractionController::extract()`, update the `RuntimeException` catch block's `Log::error` call
    - Add a `payload` or `context` field containing the full exception message (which already embeds the raw response, missing keys, offending entry, or subject+value)
    - Ensure the full message is not truncated in the log context
    - Return the existing HTTP 422 response unchanged
    - _Bug_Condition: isBugCondition(input) where rawResponse/decodedJson/subjectEntry/rangeValue triggers RuntimeException AND log lacks payload detail_
    - _Expected_Behavior: Log::error context contains the diagnostic payload (raw response, decoded structure, offending entry, or subject+value)_
    - _Preservation: RuntimeException MUST continue to map to HTTP 422; response body unchanged_
    - _Requirements: 1.2, 1.3, 1.4, 1.5, 2.2, 2.3, 2.4, 2.5_

  - [x] 3.4 Add early config validation in GeminiClient constructor
    - In `GeminiClient::__construct()`, after assigning `$this->apiKey`, `$this->endpoint`, and `$this->model`, add a guard that throws `\RuntimeException` if any value is empty
    - Exception message: `'OpenRouter configuration is incomplete: key, endpoint, and model are required.'`
    - No HTTP call should be made when config is missing
    - _Bug_Condition: isBugCondition(input) where input.apiKey OR input.endpoint OR input.model is null/empty AND no descriptive exception is thrown at construction_
    - _Expected_Behavior: new GeminiClient() throws RuntimeException with config-specific message before any HTTP request_
    - _Preservation: Constructor behavior unchanged when all three config values are present_
    - _Requirements: 1.7, 2.7_

  - [x] 3.5 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Missing Structured Logs on Failure Paths
    - **IMPORTANT**: Re-run the SAME tests from task 1 — do NOT write new tests
    - The tests from task 1 encode the expected behavior for all seven failure paths
    - When these tests pass, it confirms structured logging and early config validation are in place
    - Run bug condition exploration tests from step 1
    - **EXPECTED OUTCOME**: Tests PASS (confirms all bugs are fixed)
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7_

  - [x] 3.6 Verify preservation tests still pass
    - **Property 2: Preservation** - Happy Path and Existing Behaviors Unchanged
    - **IMPORTANT**: Re-run the SAME tests from task 2 — do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm happy path, sanitization, normalization, strand URL routing, and 503 vs 422 mapping are all unchanged

- [x] 4. Checkpoint — Ensure all tests pass
  - Run the full test suite; ensure all tests pass
  - Confirm no regressions in unrelated controller or service tests
  - Ask the user if any questions arise
