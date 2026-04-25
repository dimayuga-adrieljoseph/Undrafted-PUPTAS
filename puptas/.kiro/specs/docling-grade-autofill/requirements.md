# Requirements Document

## Introduction

This feature replaces the existing AI-based grade extraction implementation (Gemini + OpenRouter) with a Docling-only pipeline. When an applicant uploads grade documents, the system will use Docling to convert those documents into structured JSON, then parse that JSON directly — without any LLM call — to autofill the grade input form. The goal is a simpler, more deterministic, and cost-free extraction path.

## Glossary

- **Docling**: An open-source document-conversion library that converts images and PDFs into structured JSON (the `DoclingDocument` schema).
- **Docling_JSON**: The structured JSON object produced by Docling for a single uploaded file, stored in the `docling_json` column of the `user_files` table.
- **DoclingParser**: The new PHP service responsible for parsing Docling_JSON into a normalized grade result. Replaces the AI-based `GradeExtractionService`.
- **ExtractionResult**: A normalized PHP array with the shape `{ subjects: { math: {}, science: {}, english: {}, others: {} } }` where each leaf value is a float grade in [0, 100].
- **Grade_Form**: The strand-specific Inertia page (ABM, ICT, HUMSS, GAS, STEM, TVL) that receives the `extractionResult` prop and pre-fills grade inputs.
- **GradeExtractionController**: The existing Laravel controller that handles the `/grades/extract` endpoint and redirects to the correct Grade_Form.
- **Subject_Mapping**: The canonical mapping from raw subject name strings to one of the four categories: `math`, `science`, `english`, `others`.
- **UserFile**: The Eloquent model representing an uploaded file; has a nullable `docling_json` column.

---

## Requirements

### Requirement 1: Remove AI Dependencies

**User Story:** As a developer, I want to remove the Gemini and OpenRouter AI clients from the grade extraction pipeline, so that the system no longer depends on external AI APIs or incurs their costs.

#### Acceptance Criteria

1. THE DoclingParser SHALL NOT call any external AI API (Gemini, OpenRouter, or any LLM endpoint) during grade extraction.
2. THE DoclingParser SHALL NOT import or instantiate `GeminiClient` or `OpenRouterClient`.
3. WHEN the grade extraction endpoint is invoked, THE GradeExtractionController SHALL delegate to DoclingParser instead of the previous AI-based service.

---

### Requirement 2: Parse Docling JSON into Structured Grades

**User Story:** As a developer, I want to parse the Docling JSON output directly into subject-grade pairs, so that grade extraction is deterministic and does not require an LLM.

#### Acceptance Criteria

1. WHEN a `UserFile` record has a non-null `docling_json` value, THE DoclingParser SHALL extract all text nodes from the `texts` array within the `json_content` field of that Docling_JSON.
2. THE DoclingParser SHALL scan each text node's `text` (or `orig`) field for subject-grade pairs using pattern matching against the Subject_Mapping.
3. THE DoclingParser SHALL map each matched subject name to the correct category (`math`, `science`, `english`, or `others`) according to the Subject_Mapping.
4. WHEN a subject name matches a predefined alias (e.g. "Gen Math", "EAPP", "21st Lit"), THE DoclingParser SHALL normalize it to the canonical predefined subject name.
5. WHEN multiple grade values are found for the same subject across multiple text nodes or files, THE DoclingParser SHALL retain the last encountered value.
6. THE DoclingParser SHALL also scan `tables` arrays within the Docling_JSON for subject-grade pairs when text nodes yield no matches for a given subject.

---

### Requirement 3: Grade Value Validation

**User Story:** As a developer, I want extracted grade values to be validated before they reach the form, so that invalid data does not silently corrupt the autofill.

#### Acceptance Criteria

1. WHEN a parsed grade value is non-numeric, THE DoclingParser SHALL discard that subject-grade pair and continue processing remaining pairs.
2. WHEN a parsed grade value is numeric but outside the range [0, 100], THE DoclingParser SHALL discard that subject-grade pair and continue processing remaining pairs.
3. THE DoclingParser SHALL return an ExtractionResult where every grade value is a float in [0, 100].
4. WHEN all text nodes and tables in all available Docling_JSON records yield zero valid subject-grade pairs, THE DoclingParser SHALL throw an `\InvalidArgumentException` with a descriptive message.

---

### Requirement 4: Multi-File Aggregation

**User Story:** As an applicant, I want grades from all my uploaded documents to be combined, so that subjects spread across multiple files are all captured in a single autofill.

#### Acceptance Criteria

1. WHEN a user has multiple `UserFile` records with non-null `docling_json`, THE DoclingParser SHALL process all of them and merge the results into a single ExtractionResult.
2. WHEN the same subject appears in more than one file, THE DoclingParser SHALL use the value from the last processed file.
3. THE DoclingParser SHALL process files in ascending order of their `id` so that merge order is deterministic.

---

### Requirement 5: Fallback to Manual Input

**User Story:** As an applicant, I want to be redirected to the grade form even when extraction fails, so that I can still fill in my grades manually.

#### Acceptance Criteria

1. WHEN no `UserFile` records with non-null `docling_json` exist for the authenticated user, THE GradeExtractionController SHALL redirect to the strand-appropriate Grade_Form with `fallback: true` and no `extractionResult`.
2. WHEN DoclingParser throws an `\InvalidArgumentException`, THE GradeExtractionController SHALL redirect to the strand-appropriate Grade_Form with `fallback: true` and no `extractionResult`.
3. WHEN DoclingParser throws a `\RuntimeException`, THE GradeExtractionController SHALL redirect to the strand-appropriate Grade_Form with `fallback: true` and no `extractionResult`.
4. IF a fallback occurs, THEN THE GradeExtractionController SHALL log the reason at the `warning` level before redirecting.

---

### Requirement 6: ExtractionResult Shape Compatibility

**User Story:** As a frontend developer, I want the extraction result shape to remain unchanged, so that the existing Grade_Form components continue to work without modification.

#### Acceptance Criteria

1. THE DoclingParser SHALL return an ExtractionResult with the root key `subjects` containing exactly the four sub-keys: `math`, `science`, `english`, `others`.
2. WHEN the Grade_Form receives an `extractionResult` prop, THE Grade_Form SHALL pre-fill grade inputs using the values from `extractionResult.subjects`.
3. THE DoclingParser SHALL lowercase and trim all subject name keys in the returned ExtractionResult, matching the existing `normalizeKeys` behavior.

---

### Requirement 7: Round-Trip JSON Parsing

**User Story:** As a developer, I want the Docling JSON parsing to be verifiably correct, so that I can trust the parser does not silently corrupt or lose data.

#### Acceptance Criteria

1. FOR ALL valid Docling_JSON inputs, parsing the `json_content` field and re-encoding the extracted `texts` array SHALL produce an equivalent set of text strings (round-trip property).
2. THE DoclingParser SHALL accept the `DoclingDocument` schema version `1.10.0` as produced by the Docling library.
3. WHEN the `json_content` field is absent or null in a Docling_JSON record, THE DoclingParser SHALL skip that record and continue with remaining records.
