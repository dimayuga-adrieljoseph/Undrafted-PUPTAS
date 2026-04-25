# Tasks

## Task List

- [x] 1. Create DoclingParser service
  - [x] 1.1 Create `app/Services/DoclingParser.php` with the `SUBJECT_MAPPING` constant (all categories, canonical names, and aliases as defined in the design)
  - [x] 1.2 Implement `resolveSubject(string $raw): ?array` — lowercases/trims input, iterates `SUBJECT_MAPPING` to return `['category' => ..., 'name' => ...]` or `null`
  - [x] 1.3 Implement `validateGrade(mixed $raw): ?float` — returns `float` if numeric and in `[0, 100]`, otherwise `null`
  - [x] 1.4 Implement `scanTextNode(string $text): array` — applies primary regex (`Subject:\s*(.+?)\s+Grade:\s*(\d+(?:\.\d+)?)`) and secondary alias-proximity scan; returns `[ normalized_subject => float ]`
  - [x] 1.5 Implement `scanTable(array $table): array` — iterates `data.table_cells` in row-major order, pairs subject-alias cells with adjacent numeric cells; returns `[ normalized_subject => float ]`
  - [x] 1.6 Implement `parseJsonContent(array $jsonContent): array` — iterates `texts` nodes (using `text ?? orig`), then `tables`; merges results (last-value-wins); returns flat `[ normalized_subject => float ]`
  - [x] 1.7 Implement `normalizeKey(string $key): string` — `strtolower(trim($key))`
  - [x] 1.8 Implement `buildResult(array $flat): array` — distributes flat map into `{ subjects: { math, science, english, others } }` envelope using `resolveSubject` for category routing; all keys lowercased/trimmed
  - [x] 1.9 Implement `extract(User $user): array` — queries `UserFile` with non-null `docling_json` ordered by `id ASC`, calls `parseJsonContent` per file, merges (last-value-wins), throws `\InvalidArgumentException` if zero valid pairs, returns `buildResult`

- [x] 2. Update GradeExtractionController
  - [x] 2.1 Replace `GradeExtractionService` constructor injection with `DoclingParser` injection
  - [x] 2.2 In `extract()`, add an early-return fallback (with `warning` log) when no `UserFile` records with non-null `docling_json` exist for the user — before calling `DoclingParser`
  - [x] 2.3 Replace the `$this->gradeExtractionService->extract($user)` call with `$this->doclingParser->extract($user)`
  - [x] 2.4 Verify existing `\InvalidArgumentException` and `\RuntimeException` catch blocks remain intact and log at the correct levels (`warning` / `error`)

- [x] 3. Write unit tests for DoclingParser
  - [x] 3.1 Test `resolveSubject` with every alias in `SUBJECT_MAPPING` — verify correct category and canonical name returned
  - [x] 3.2 Test `resolveSubject` with an unknown string — verify `null` returned and subject goes to `others`
  - [x] 3.3 Test `validateGrade` with valid numeric values (0, 50, 100, 75.5) — verify float returned
  - [x] 3.4 Test `validateGrade` with non-numeric strings — verify `null` returned
  - [x] 3.5 Test `validateGrade` with out-of-range values (-1, 101, 200) — verify `null` returned
  - [x] 3.6 Test `scanTextNode` with `"Subject: Mathematics  Grade: 90"` format — verify pair extracted
  - [x] 3.7 Test `scanTextNode` with multiple subject-grade pairs in one text node — verify all pairs extracted
  - [x] 3.8 Test `scanTextNode` with no recognizable pairs — verify empty array returned
  - [x] 3.9 Test `scanTable` with a table containing a subject alias cell adjacent to a numeric cell — verify pair extracted
  - [x] 3.10 Test `scanTable` with a table containing no recognizable pairs — verify empty array returned
  - [x] 3.11 Test `parseJsonContent` with null/absent `texts` and `tables` — verify empty array returned
  - [x] 3.12 Test `extract` with multiple files having overlapping subjects — verify last-value-wins (ascending id order)
  - [x] 3.13 Test `extract` with all inputs yielding zero valid pairs — verify `\InvalidArgumentException` thrown
  - [x] 3.14 Test `extract` with a mix of null and valid `json_content` records — verify null records are skipped
  - [x] 3.15 Test `buildResult` output shape — verify exactly four sub-keys (`math`, `science`, `english`, `others`) present and all subject keys are lowercased/trimmed

- [x] 4. Write unit tests for GradeExtractionController
  - [x] 4.1 Test `extract` with no `UserFile` records having `docling_json` — verify fallback JSON response with `fallback: true` and warning log
  - [x] 4.2 Test `extract` with successful `DoclingParser` result — verify session set and redirect URL returned
  - [x] 4.3 Test `extract` when `DoclingParser` throws `\InvalidArgumentException` — verify fallback response and warning log
  - [x] 4.4 Test `extract` when `DoclingParser` throws `\RuntimeException` — verify fallback response and error log

- [x] 5. Write property-based tests for DoclingParser
  - [x] 5.1 Property 1 — Subject Resolution Correctness: for any alias in `SUBJECT_MAPPING`, `resolveSubject` returns the correct category and canonical name (100+ iterations)
  - [x] 5.2 Property 2 — Grade Output Invariant: for any generated `docling_json` with valid pairs, every grade in the result is a float in `[0, 100]` (100+ iterations)
  - [x] 5.3 Property 3 — Last-Value-Wins Merge: for any sequence of grade values for the same subject, the result equals the last value (100+ iterations)
  - [x] 5.4 Property 4 — Deterministic Multi-File Merge: for any set of files, processing in ascending id order always produces the same result (100+ iterations)
  - [x] 5.5 Property 5 — Empty Input Exception: for any input yielding zero valid pairs, `extract` throws `\InvalidArgumentException` (100+ iterations)
  - [x] 5.6 Property 6 — Table Scanning: for any `docling_json` with subject-grade pairs only in tables, the pairs are extracted (100+ iterations)
  - [x] 5.7 Property 7 — Output Shape Invariant: for any successful extraction, result has exactly `subjects.{math,science,english,others}` with lowercased/trimmed keys (100+ iterations)
  - [x] 5.8 Property 8 — Null json_content Skipped: for any mix of null and valid records, result equals processing only valid records (100+ iterations)
  - [x] 5.9 Property 9 — Round-Trip Text Extraction: for any `json_content`, collecting `text ?? orig` from all `texts` nodes produces the complete original text set (100+ iterations)

- [x] 6. Cleanup
  - [x] 6.1 Remove `GradeExtractionService` from the Laravel service container bindings (if explicitly bound in `AppServiceProvider`)
  - [x] 6.2 Confirm `GeminiClient` and `OpenRouterClient` are no longer referenced by any grade extraction code path (they may remain in the codebase for other uses)
  - [x] 6.3 Update any route service provider or container binding that previously injected `GradeExtractionService` into `GradeExtractionController`
