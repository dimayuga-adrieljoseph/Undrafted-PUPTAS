# Bugfix Requirements Document

## Introduction

Multiple bugs affect the AI-powered grade extraction and autofill feature. The issues span the full pipeline: the OpenRouter API call (the "Review Grade" button fails with API errors), the AI pre-prompt (Grade 11 subjects are excluded, causing incomplete extraction), subject name normalization mismatches (e.g. "Earth and Life Science" vs "Earth Science"), autofill logic failures (grades retrieved but not filled into form inputs), and subjects categorized as "others" not being autofilled at all.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN the user clicks the "Review Grade" button and the OpenRouter API key, endpoint, or model is misconfigured or the API is unavailable THEN the system returns a generic API error with no actionable feedback to the user

1.2 WHEN the AI extraction prompt is built THEN the system instructs the AI to extract only Grade 11 and Grade 12 subjects, but the prompt's subject mapping and normalization rules cause the AI to return subject names that do not exactly match the predefined names (e.g. "Earth Science" instead of "Earth and Life Science"), resulting in failed lookups

1.3 WHEN `applyAutofill()` attempts to match an extracted subject key to a form field THEN the system uses a fragile string-stripping heuristic that removes common words and checks for substring inclusion, causing mismatches when the AI returns a slightly different subject name variant

1.4 WHEN `normalizeKeys()` in `GradeExtractionService` lowercases all subject name keys before returning the result THEN the system loses the original casing, and the frontend `applyAutofill()` function cannot reliably match lowercased keys to form field names derived from camelCase/underscore keys

1.5 WHEN the extraction result contains subjects in the "others" category THEN the system's `applyAutofill()` function iterates over the "others" group but has no logic to fill any form field or `otherSubjects` reactive array with those subjects, so "others" subjects are silently dropped

1.6 WHEN `applyAutofill()` processes G11 subjects THEN the system only checks form keys that start with `g11_` and do not contain "grade" or "subject", but the matching logic strips the `g11_` prefix and compares against the extracted key using a word-removal heuristic that fails for multi-word subjects like "english for academic purposes" (stored as `g11_academic_professional` in the form)

1.7 WHEN `GradeExtractionService::loadImages()` is called THEN the system fetches all image-type UserFile records for the user with no filter on document type, including Grade 10 report card images (`file10Front`, `file10`), sending irrelevant data to the AI

### Expected Behavior (Correct)

2.1 WHEN the OpenRouter API returns an error (401, 429, 503, or connection failure) THEN the system SHALL return a descriptive error message to the frontend indicating the specific failure reason so the user understands what went wrong

2.2 WHEN the AI extraction prompt is built THEN the system SHALL include an explicit, exhaustive list of accepted subject name variants and their canonical mappings so the AI consistently returns the exact predefined subject names

2.3 WHEN `applyAutofill()` matches an extracted subject key to a G11 form field THEN the system SHALL use an explicit lookup table mapping canonical lowercased subject names to their corresponding form field keys, instead of a fragile heuristic

2.4 WHEN `normalizeKeys()` returns the extraction result THEN the system SHALL preserve the original subject name casing (or use a canonical form agreed upon with the frontend) so the frontend can perform reliable lookups

2.5 WHEN the extraction result contains subjects in the "others" category THEN the system SHALL populate the `otherSubjects` reactive array with those subject name and grade pairs so they are displayed in the "Other Subjects" section of the form

2.6 WHEN `applyAutofill()` processes G11 subjects THEN the system SHALL correctly map "earth and life science" → `g11_earth_life_science`, "physical science" → `g11_physical_science`, "general mathematics" → `g11_general_mathematics`, "business mathematics" → `g11_business_mathematics`, "statistics and probability" → `g11_statistics_probability`, "oral communication" → `g11_oral_communication`, "english for academic purposes" → `g11_academic_professional`, and "reading and writing" → `g11_reading_writing`

2.7 WHEN `GradeExtractionService::loadImages()` is called THEN the system SHALL exclude UserFile records whose type key is `file10Front` or `file10` from the set of images sent to the AI

### Unchanged Behavior (Regression Prevention)

3.1 WHEN the extraction result contains Grade 12 subjects THEN the system SHALL CONTINUE TO autofill Grade 12 subject name and grade fields (g12_math_subject_*, g12_math_grade_*, g12_science_subject_*, g12_science_grade_*, g12_english_subject_*, g12_english_grade_*)

3.2 WHEN the extraction result contains subjects with confidence scores below 0.80 THEN the system SHALL CONTINUE TO apply red border highlighting and display the "Low confidence result. Please verify." helper text on those fields

3.3 WHEN the extraction result contains subjects with any confidence score THEN the system SHALL CONTINUE TO display the AI confidence percentage label on autofilled fields

3.4 WHEN `GradeExtractionService::loadImages()` is called THEN the system SHALL CONTINUE TO include Grade 11 and Grade 12 report card images in the extraction request

3.5 WHEN a UserFile record is not of MIME type image/jpeg, image/png, or image/webp THEN the system SHALL CONTINUE TO exclude that file from the extraction request

3.6 WHEN the extraction result is received THEN the system SHALL CONTINUE TO store it in the session and redirect the applicant to the strand-specific grade input page

3.7 WHEN the extraction result is null or absent THEN the system SHALL CONTINUE TO display the grade form without the AI autofill banner and without any prefilled values
