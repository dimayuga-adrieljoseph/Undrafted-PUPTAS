# Grade Extraction Autofill Fix — Tasks

## Task List

- [x] 1. Fix Bug 1 — Remove Grade 11 exclusion from AI prompt
  - [x] 1.1 Write exploratory test: assert `buildPrompt()` does NOT contain `"Ignore Grade 11"` or `"Only consider Grade 12"` (run on unfixed code to confirm failure)
    - _Bug_Condition: isBugCondition_1 — prompt contains Grade 11 exclusion instruction_
    - _Expected_Behavior: prompt does not exclude Grade 11 subjects_
    - _Requirements: 2.1_
  - [x] 1.2 Write exploratory test: assert `buildPrompt()` contains `"Business Mathematics"` in the predefined subject mapping (run on unfixed code to confirm failure)
    - _Bug_Condition: isBugCondition_1 — Business Mathematics absent from prompt mapping_
    - _Expected_Behavior: Business Mathematics is listed under Math in the predefined mapping_
    - _Requirements: 2.1_
  - [x] 1.3 Remove the line `"Only consider Grade 12 subjects and grades. Ignore Grade 11 or other grade levels if present."` from `buildPrompt()` in `app/Services/GradeExtractionService.php`
  - [x] 1.4 Add `Business Mathematics` to the Math section of the predefined subject mapping in `buildPrompt()`
  - [x] 1.5 Replace the Grade 12-only scope instruction with one that covers both Grade 11 and Grade 12 subjects
  - [x] 1.6 Run the exploratory tests from 1.1 and 1.2 on the fixed code to confirm they now pass

- [x] 2. Fix Bug 2 — Exclude Grade 10 images from `loadImages()`
  - [x] 2.1 Write exploratory test: create a `UserFile` with `type='file10Front'` and a valid MIME type; assert it is absent from `loadImages()` output (run on unfixed code to confirm failure)
    - _Bug_Condition: isBugCondition_2 — file10Front included in loadImages() output_
    - _Expected_Behavior: file10Front record is excluded from images array_
    - _Requirements: 2.3_
  - [x] 2.2 Write exploratory test: create a `UserFile` with `type='file10'` and a valid MIME type; assert it is absent from `loadImages()` output (run on unfixed code to confirm failure)
    - _Bug_Condition: isBugCondition_2 — file10 included in loadImages() output_
    - _Expected_Behavior: file10 record is excluded from images array_
    - _Requirements: 2.3_
  - [x] 2.3 Add type-key exclusion guard in `loadImages()` in `app/Services/GradeExtractionService.php`:
    - At the top of the `foreach ($files as $file)` loop, add: `if (in_array($file->type, ['file10Front', 'file10'], true)) { continue; }`
  - [x] 2.4 Run the exploratory tests from 2.1 and 2.2 on the fixed code to confirm they now pass

- [x] 3. Preservation verification — `loadImages()` still includes Grade 11/12 images
  - [x] 3.1 Write preservation test: assert `loadImages()` includes `file11Front` and `file11` records with valid MIME types
    - _Preservation: Grade 11 images must continue to be included (Requirement 3.4)_
  - [x] 3.2 Write preservation test: assert `loadImages()` includes `file12Front` and `file12` records with valid MIME types
    - _Preservation: Grade 12 images must continue to be included (Requirement 3.4)_
  - [x] 3.3 Write preservation test: assert `loadImages()` continues to exclude records with non-image MIME types regardless of type key
    - _Preservation: MIME-type filter must remain unchanged (Requirement 3.5)_

- [x] 4. Preservation verification — Grade 12 autofill and confidence UI unchanged
  - [x] 4.1 Verify that Grade 12 form fields (`g12_math_subject_*`, `g12_math_grade_*`, `g12_science_subject_*`, `g12_science_grade_*`, `g12_english_subject_*`, `g12_english_grade_*`) are still populated by `applyAutofill()` when the extraction result contains Grade 12 subjects
    - _Preservation: Grade 12 autofill must remain unchanged (Requirement 3.1)_
  - [x] 4.2 Verify that fields with confidence < 0.80 still receive red border highlighting and "Low confidence result. Please verify." helper text after the prompt change
    - _Preservation: confidence highlighting must remain unchanged (Requirement 3.2)_
  - [x] 4.3 Verify that autofilled fields still display the AI confidence percentage label
    - _Preservation: confidence label must remain unchanged (Requirement 3.3)_
