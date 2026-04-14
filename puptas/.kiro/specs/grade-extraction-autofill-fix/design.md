# Grade Extraction Autofill Fix — Bugfix Design

## Overview

Two bugs affect the AI grade extraction feature. First, `GradeExtractionService::buildPrompt()` explicitly instructs the AI to ignore Grade 11 subjects, so the extraction result never contains Grade 11 data and `applyAutofill()` leaves all Grade 11 fields blank. Second, `GradeExtractionService::loadImages()` fetches every image-type `UserFile` for the user with no filter on document type, causing Grade 10 report card images (`file10Front`, `file10`) to be sent to the AI even though Grade 10 grades are not evaluated.

The fix is minimal and targeted: remove the Grade 11 exclusion instruction from the prompt (and add Grade 11 subjects to the predefined mapping), and add a type-key exclusion filter in `loadImages()` to skip `file10Front` and `file10` records.

## Glossary

- **Bug_Condition (C)**: The condition that triggers a bug — either the prompt excludes Grade 11 subjects, or `loadImages()` includes Grade 10 images.
- **Property (P)**: The desired correct behavior — Grade 11 subjects are extracted and autofilled; Grade 10 images are excluded from the extraction request.
- **Preservation**: Existing Grade 12 autofill, confidence highlighting, MIME-type filtering, and session/redirect behavior that must remain unchanged.
- **`buildPrompt()`**: Method in `app/Services/GradeExtractionService.php` that returns the structured AI prompt string sent to OpenRouter.
- **`loadImages(User $user)`**: Method in `app/Services/GradeExtractionService.php` that queries `UserFile` records and returns base64-encoded image arrays for the AI request.
- **`applyAutofill(result)`**: Frontend function in each `{Strand}GradeInput.vue` that iterates the `extractionResult` prop and populates form fields by matching normalized subject keys.
- **`type` key**: The `type` column on the `UserFile` model — a string identifier such as `file10Front`, `file10`, `file11Front`, `file11`, `file12Front`, `file12`.
- **Grade 11 subjects**: General Mathematics, Business Mathematics, Statistics and Probability, Oral Communication, English for Academic Purposes, Reading and Writing, Earth and Life Science, Physical Science.
- **Grade 10 images**: `UserFile` records with `type` of `file10Front` or `file10` — uploaded for identity/checking purposes only, not for grade evaluation.

## Bug Details

### Bug Condition

Two independent bug conditions exist. Both must be fixed.

**Bug 1 — Grade 11 Exclusion in Prompt**

The prompt returned by `buildPrompt()` contains the instruction `"Only consider Grade 12 subjects and grades. Ignore Grade 11 or other grade levels if present."` This causes the AI to omit all Grade 11 subject-grade pairs from its response. When `applyAutofill()` runs, it finds no matching keys for Grade 11 form fields and leaves them blank.

```
FUNCTION isBugCondition_1(prompt)
  INPUT: prompt of type string (output of buildPrompt())
  OUTPUT: boolean

  RETURN prompt CONTAINS "Ignore Grade 11"
         OR prompt CONTAINS "Only consider Grade 12"
END FUNCTION
```

**Bug 2 — Grade 10 Images Included in Extraction**

`loadImages()` queries all `UserFile` records for the user filtered only by MIME type. It does not filter by `type` key, so records with `type = 'file10Front'` or `type = 'file10'` are included in the images array sent to the AI.

```
FUNCTION isBugCondition_2(userFiles, images)
  INPUT: userFiles — collection of UserFile records for a user
         images    — array returned by loadImages()
  OUTPUT: boolean

  grade10Files := userFiles WHERE type IN ['file10Front', 'file10']
                                AND mimeType IN ['image/jpeg', 'image/png', 'image/webp']
  RETURN ANY grade10File IN grade10Files
         WHERE grade10File IS PRESENT IN images
END FUNCTION
```

### Examples

**Bug 1:**
- User uploads Grade 11 and Grade 12 report cards. AI extraction runs. Extraction result contains only Grade 12 subjects. `applyAutofill()` populates Grade 12 fields but leaves `g11_general_mathematics`, `g11_oral_communication`, etc. all blank. Expected: Grade 11 fields are populated from the extraction result.

**Bug 2:**
- User has uploaded `file10Front` (Grade 10 front page) and `file12Front` (Grade 12 front page). `loadImages()` returns both images. The AI receives the Grade 10 image and may extract Grade 10 grades, confusing the Grade 11/12 extraction. Expected: only `file12Front` is included.

**Edge case — Bug 2:**
- User has no Grade 10 images uploaded (only Grade 11 and Grade 12 files). `loadImages()` should behave identically to the current implementation — no change in output.

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Grade 12 subject autofill (`g12_math_subject_*`, `g12_math_grade_*`, `g12_science_subject_*`, `g12_science_grade_*`, `g12_english_subject_*`, `g12_english_grade_*`) must continue to work exactly as before.
- Fields with confidence scores below 0.80 must continue to receive red border highlighting and the "Low confidence result. Please verify." helper text.
- All autofilled fields must continue to display the AI confidence percentage label.
- `UserFile` records with non-image MIME types must continue to be excluded from the extraction request.
- Grade 11 and Grade 12 report card images (`file11Front`, `file11`, `file12Front`, `file12`) must continue to be included in the extraction request.
- The controller must continue to store the extraction result in the session and return the strand-specific redirect URL.

**Scope:**
All inputs that do NOT involve the prompt text or the `file10Front`/`file10` type keys are completely unaffected by this fix. This includes:
- The `sanitize()`, `parse()`, `validate()`, and `normalizeKeys()` pipeline steps.
- Mouse-based form interactions on the grade input pages.
- Grade submission and qualification validation logic.

## Hypothesized Root Cause

**Bug 1 — Grade 11 Exclusion:**

1. **Explicit exclusion instruction in prompt**: The line `"Only consider Grade 12 subjects and grades. Ignore Grade 11 or other grade levels if present."` was likely added to reduce noise from Grade 10 data visible on the same report card page. However, it also suppresses Grade 11 data that the system needs.

2. **Missing Grade 11 subjects in predefined mapping**: The `Predefined Subject Mapping` section in the prompt lists only Grade 12 subjects (General Mathematics, Statistics and Probability, Earth and Life Science, Physical Science, Oral Communication, 21st Century Literature, English for Academic Purposes, Reading and Writing). Grade 11-specific subjects like Business Mathematics are absent, so even if the exclusion instruction were removed, the AI might not map them correctly.

**Bug 2 — Grade 10 Images:**

1. **No type-key filter in `loadImages()`**: The method queries `UserFile::where('user_id', $user->id)->get()` with no additional constraint on the `type` column. The MIME-type filter was added to exclude non-image documents, but there was no mechanism to exclude image files that belong to Grade 10 report cards specifically.

## Correctness Properties

Property 1: Bug Condition 1 — Grade 11 Subjects Extracted and Autofilled

_For any_ call to `buildPrompt()`, the returned prompt string SHALL NOT contain any instruction to ignore or exclude Grade 11 subjects, and SHALL include Grade 11 subject names (General Mathematics, Business Mathematics, Statistics and Probability, Oral Communication, English for Academic Purposes, Reading and Writing, Earth and Life Science, Physical Science) in the predefined subject mapping so the AI extracts them. Consequently, for any `extractionResult` that contains Grade 11 subject keys, `applyAutofill()` SHALL populate the corresponding Grade 11 form fields (`g11_general_mathematics`, `g11_business_mathematics`, `g11_statistics_probability`, `g11_oral_communication`, `g11_academic_professional`, `g11_reading_writing`, `g11_earth_life_science`, `g11_physical_science`) with the extracted grade values.

**Validates: Requirements 2.1, 2.2**

Property 2: Bug Condition 2 — Grade 10 Images Excluded from Extraction

_For any_ user whose `UserFile` records include entries with `type` of `file10Front` or `file10`, the fixed `loadImages()` SHALL NOT include those records in the returned images array, regardless of their MIME type.

**Validates: Requirements 2.3, 2.4**

Property 3: Preservation — Grade 11/12 Images Always Included

_For any_ user whose `UserFile` records include entries with `type` of `file11Front`, `file11`, `file12Front`, or `file12` and a valid image MIME type, the fixed `loadImages()` SHALL include those records in the returned images array, identical to the original behavior.

**Validates: Requirements 3.4, 3.5**

Property 4: Preservation — Grade 12 Autofill Unchanged

_For any_ `extractionResult` that contains Grade 12 subject keys, the fixed `applyAutofill()` SHALL populate Grade 12 form fields (`g12_math_subject_*`, `g12_math_grade_*`, `g12_science_subject_*`, `g12_science_grade_*`, `g12_english_subject_*`, `g12_english_grade_*`) identically to the original behavior.

**Validates: Requirements 3.1**

## Fix Implementation

### Changes Required

**File**: `app/Services/GradeExtractionService.php`

**Function**: `buildPrompt()`

**Specific Changes**:

1. **Remove Grade 11 exclusion instruction**: Delete the line `"Only consider Grade 12 subjects and grades. Ignore Grade 11 or other grade levels if present."` from the Instructions section of the prompt.

2. **Add Grade 11 subjects to predefined mapping**: Extend the `Predefined Subject Mapping` section to include Grade 11 subjects under the appropriate categories:
   - Math: add `Business Mathematics`
   - English: (no additions needed — Oral Communication, English for Academic Purposes, Reading and Writing are already listed)
   - Science: (no additions needed — Earth and Life Science, Physical Science are already listed)
   - Note: `General Mathematics` and `Statistics and Probability` are already in the Math mapping.

3. **Update extraction scope instruction**: Replace the Grade 12-only instruction with one that covers both Grade 11 and Grade 12: e.g., `"Consider both Grade 11 and Grade 12 subjects and grades."` and instruct the AI to include grade level context where visible.

4. **Update JSON output structure**: The output format and `others` category remain unchanged. The AI will now populate the same four groups (`math`, `science`, `english`, `others`) with both Grade 11 and Grade 12 subjects.

---

**File**: `app/Services/GradeExtractionService.php`

**Function**: `loadImages(User $user)`

**Specific Changes**:

1. **Add type-key exclusion filter**: After querying `UserFile::where('user_id', $user->id)->get()`, add a filter to skip records where `$file->type` is `'file10Front'` or `'file10'` before the MIME-type check. This is a single `continue` guard at the top of the loop:

```php
$excludedTypes = ['file10Front', 'file10'];
if (in_array($file->type, $excludedTypes, true)) {
    continue;
}
```

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate each bug on unfixed code, then verify the fix works correctly and preserves existing behavior.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate both bugs BEFORE implementing the fix. Confirm or refute the root cause analysis.

**Test Plan**: Write unit tests against the unfixed `GradeExtractionService`. For Bug 1, assert that `buildPrompt()` does not contain the exclusion instruction — this will fail on unfixed code. For Bug 2, create a mock `UserFile` set that includes `file10Front`/`file10` records and assert they are absent from `loadImages()` output — this will fail on unfixed code.

**Test Cases**:
1. **Prompt Grade 11 exclusion test**: Assert `buildPrompt()` does NOT contain `"Ignore Grade 11"` — will fail on unfixed code.
2. **Prompt Grade 11 subjects present test**: Assert `buildPrompt()` contains `"Business Mathematics"` in the predefined mapping — will fail on unfixed code.
3. **loadImages excludes file10Front test**: Create a `UserFile` with `type='file10Front'` and a valid MIME type; assert it is absent from `loadImages()` output — will fail on unfixed code.
4. **loadImages excludes file10 test**: Create a `UserFile` with `type='file10'` and a valid MIME type; assert it is absent from `loadImages()` output — will fail on unfixed code.

**Expected Counterexamples**:
- `buildPrompt()` returns a string containing `"Ignore Grade 11"`.
- `loadImages()` returns an array that includes base64 data from `file10Front`/`file10` records.

### Fix Checking

**Goal**: Verify that for all inputs where each bug condition holds, the fixed functions produce the expected behavior.

**Pseudocode — Bug 1:**
```
FOR ALL prompt WHERE isBugCondition_1(prompt) DO
  fixedPrompt := buildPrompt_fixed()
  ASSERT NOT isBugCondition_1(fixedPrompt)
  ASSERT fixedPrompt CONTAINS "Business Mathematics"
  ASSERT fixedPrompt CONTAINS "Grade 11"
END FOR
```

**Pseudocode — Bug 2:**
```
FOR ALL userFileSet WHERE isBugCondition_2(userFileSet, loadImages_original(user)) DO
  images := loadImages_fixed(user)
  ASSERT NOT isBugCondition_2(userFileSet, images)
  ASSERT NO image IN images CORRESPONDS TO file10Front OR file10 record
END FOR
```

### Preservation Checking

**Goal**: Verify that for all inputs where the bug conditions do NOT hold, the fixed functions produce the same result as the original functions.

**Pseudocode:**
```
FOR ALL userFileSet WHERE NOT isBugCondition_2(userFileSet, ...) DO
  ASSERT loadImages_original(user) = loadImages_fixed(user)
END FOR
```

**Testing Approach**: Property-based testing is recommended for `loadImages()` preservation because it can generate many combinations of `UserFile` sets (varying types and MIME types) and assert that non-Grade-10 files are always included identically.

**Test Cases**:
1. **Grade 12 images preserved**: Verify `file12Front` and `file12` records continue to be included after the fix.
2. **Grade 11 images preserved**: Verify `file11Front` and `file11` records continue to be included after the fix.
3. **Non-image MIME type exclusion preserved**: Verify PDF and other non-image files continue to be excluded.
4. **Grade 12 autofill preserved**: Provide an `extractionResult` with only Grade 12 subjects and verify Grade 12 form fields are populated identically.
5. **Confidence highlighting preserved**: Verify fields with confidence < 0.80 still receive red border and helper text after the prompt change.

### Unit Tests

- Test `buildPrompt()` does not contain Grade 11 exclusion instruction.
- Test `buildPrompt()` contains all Grade 11 subjects in the predefined mapping.
- Test `loadImages()` excludes `file10Front` records even when MIME type is valid.
- Test `loadImages()` excludes `file10` records even when MIME type is valid.
- Test `loadImages()` continues to include `file11Front`, `file11`, `file12Front`, `file12` records.
- Test `loadImages()` continues to exclude non-image MIME types regardless of type key.

### Property-Based Tests

- For any `UserFile` set, if a record has `type` in `['file10Front', 'file10']`, `loadImages()` SHALL never include it (Property 2).
- For any `UserFile` set, if a record has `type` in `['file11Front', 'file11', 'file12Front', 'file12']` and a valid MIME type, `loadImages()` SHALL always include it (Property 3).
- For any `extractionResult` containing Grade 12 subject keys, `applyAutofill()` output is identical before and after the fix (Property 4).

### Integration Tests

- Full extraction flow with a user who has Grade 10, Grade 11, and Grade 12 images: verify only Grade 11 and Grade 12 images are sent, and the extraction result contains both Grade 11 and Grade 12 subjects.
- Grade input page autofill: verify Grade 11 fields are populated after extraction when the fixed prompt is used.
- Verify Grade 12 autofill, confidence highlighting, and session/redirect behavior are unchanged.
