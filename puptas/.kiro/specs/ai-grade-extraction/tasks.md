# Tasks: AI Grade Extraction

## Task List

- [x] 1. Backend: Configuration and Service Infrastructure
  - [x] 1.1 Add `GEMINI_API_KEY` and `GEMINI_ENDPOINT` to `.env.example` and `config/services.php`
  - [x] 1.2 Create `GeminiClient` service (`app/Services/GeminiClient.php`) wrapping Laravel `Http` facade, reading key from config, throwing `GeminiApiException` on HTTP errors
  - [x] 1.3 Register `grade-extraction` rate limiter (10/hour per user) in `AppServiceProvider::boot()`

- [x] 2. Backend: GradeExtractionService
  - [x] 2.1 Create `GradeExtractionService` (`app/Services/GradeExtractionService.php`) with `extract(User $user): array` entry point
  - [x] 2.2 Implement `loadImages(User $user): array` — queries `UserFile` by `user_id`, filters to `image/jpeg`/`image/png` MIME types only
  - [x] 2.3 Implement `buildPrompt(): string` — returns structured Gemini prompt specifying the `ExtractionResult` JSON format with `math`, `science`, `english`, `others` groups
  - [x] 2.4 Implement `sanitize(string $raw): string` — strips non-JSON surrounding content (markdown fences, prose) before parsing
  - [x] 2.5 Implement `parse(string $json): array` — JSON decode + structural validation (four required top-level keys, subject entry shape)
  - [x] 2.6 Implement `validate(array $data): void` — asserts all `grade` values ∈ [0,100] and all `confidence` values ∈ [0.0,1.0], throws on violation
  - [x] 2.7 Implement `normalizeKeys(array $data): array` — lowercases and trims all subject name keys recursively

- [x] 3. Backend: GradeExtractionController and Route
  - [x] 3.1 Create `GradeExtractionController` (`app/Http/Controllers/GradeExtractionController.php`) with `extract(Request $request)` method delegating to `GradeExtractionService`
  - [x] 3.2 Handle error cases: return HTTP 422 for parse/validation failures, HTTP 503 for Gemini connectivity errors, log errors via `Log::error()`
  - [x] 3.3 Register route `POST /api/grades/extract` in `routes/api.php` with `auth:sanctum` and `throttle:grade-extraction` middleware

- [x] 4. Frontend: Applicant Dashboard Updates
  - [x] 4.1 Add `allDocumentsUploaded` computed property to `Applicant.vue` — returns `true` when every `fileStatuses` slot has a non-null `url`
  - [x] 4.2 Add `extracting` ref and `extractionError` ref to `Applicant.vue`
  - [x] 4.3 Implement `triggerExtraction()` method — POST to `/api/grades/extract`, handle loading state, navigate to strand-specific grade page with `extractionResult` as Inertia data prop on success
  - [x] 4.4 Add "Review Grades" button to `Applicant.vue` template — visible only when `allDocumentsUploaded`, disabled when `extracting`, styled with maroon `#9E122C` primary button style, loading spinner during extraction
  - [x] 4.5 Add error display for `extractionError` in `Applicant.vue` template

- [ ] 5. Frontend: Grade Input Page Updates (all 6 strand pages)
  - [x] 5.1 Add `extractionResult` prop (optional Object, default null) to all six `{Strand}GradeInput.vue` pages
  - [x] 5.2 Add `confidenceMap` ref and `bannerDismissed` ref to all six grade input pages
  - [x] 5.3 Implement `applyAutofill(result)` method — iterates `ExtractionResult`, matches subject keys case-insensitively and whitespace-trimmed to `form` fields, populates values, builds `confidenceMap`
  - [x] 5.4 Call `applyAutofill()` in `onMounted` when `extractionResult` prop is non-null
  - [x] 5.5 Implement `getConfidence(fieldKey)` helper and `isLowConfidence(fieldKey)` computed helper
  - [x] 5.6 Apply conditional red border class (`border-red-500`) to grade inputs when `isLowConfidence` is true
  - [x] 5.7 Add helper text "Low confidence result. Please verify." with Font Awesome warning icon below low-confidence fields
  - [x] 5.8 Add confidence percentage label (`AI confidence: X%`) to all autofilled fields regardless of threshold
  - [x] 5.9 Add dismissible AI autofill banner at the top of the form when `extractionResult` is non-null

- [x] 6. Unit Tests (Pest PHP)
  - [x] 6.1 Write unit tests for `GradeExtractionService::sanitize()` — valid JSON in markdown fences, prose, mixed content, plain JSON
  - [x] 6.2 Write unit tests for `GradeExtractionService::parse()` — valid structure, missing keys, wrong value types, empty object
  - [x] 6.3 Write unit tests for `GradeExtractionService::validate()` — boundary values (0, 100, -1, 101, 0.0, 1.0, -0.01, 1.01)
  - [x] 6.4 Write unit tests for `GradeExtractionService::normalizeKeys()` — mixed case, leading/trailing spaces, unicode characters
  - [x] 6.5 Write unit tests for `GradeExtractionService::loadImages()` — mixed MIME types, cross-user files, empty file set
  - [x] 6.6 Write unit tests for `GradeExtractionController` — auth guard, rate limit response (429), Gemini error propagation (503)

- [x] 7. Property-Based Tests (Pest PHP)
  - [x] 7.1 Property 2 — File ownership filter: for any user/file collection, `loadImages()` returns only files with matching `user_id`
  - [x] 7.2 Property 3 — MIME type filter: for any file collection with mixed MIME types, `loadImages()` returns only `image/jpeg`/`image/png` files
  - [x] 7.3 Property 4 — Structural validation: for any JSON string, `parse()` accepts valid structures and rejects invalid ones
  - [x] 7.4 Property 5 — Range validation: for any `ExtractionResult`, `validate()` rejects results with out-of-range grade or confidence values
  - [x] 7.5 Property 6 — Key normalization: for any subject name string, `normalizeKeys()` produces a lowercase trimmed key
  - [x] 7.6 Property 7 — Sanitization: for any string with embedded JSON, `sanitize()` extracts the JSON portion correctly
  - [x] 7.7 Property 12 — Grade submission range: for any numeric value, `GradesController` accepts [0,100] and rejects outside that range

- [x] 8. Vue Component Tests (Vitest)
  - [x] 8.1 Property 1 — Button visibility: for any `fileStatuses`, "Review Grades" button is shown iff all slots have non-null `url`
  - [x] 8.2 Property 8 — Autofill matching: for any `ExtractionResult`, `applyAutofill()` populates matched fields and leaves unmatched fields unchanged
  - [x] 8.3 Property 9 — Confidence highlighting: for any confidence score, red border and helper text appear iff confidence < 0.80
  - [x] 8.4 Property 10 — Confidence percentage: for any confidence score `c`, displayed label shows `Math.round(c * 100)%`
  - [x] 8.5 Property 11 — Fields remain editable: for any `ExtractionResult`, no field has `disabled` or `readonly` after autofill
  - [x] 8.6 Example test — loading state: clicking "Review Grades" shows spinner and disables button
  - [x] 8.7 Example test — error display: failed extraction request shows error message and re-enables button
  - [x] 8.8 Example test — dismissible banner: banner is present after autofill and can be dismissed

- [x] 9. Integration Tests
  - [x] 9.1 Full extraction flow with stubbed Gemini response fixture — assert 200 with valid `ExtractionResult`
  - [x] 9.2 Rate limiting — 11 sequential requests from same user, assert 11th returns 429
  - [x] 9.3 File ownership — request extraction as user A, assert user B's files are not processed
  - [x] 9.4 Gemini unreachable — mock connection failure, assert 503 response and error logged
  - [x] 9.5 No image files — user has only non-image files, assert 422 response
