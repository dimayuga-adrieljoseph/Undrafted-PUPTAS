# Bugfix Requirements Document

## Introduction

Two bugs affect the AI grade extraction feature:

1. **Incomplete autofill**: Grade 11 subjects (General Mathematics, Business Mathematics, Statistics and Probability, Oral Communication, English for Academic Purposes, Reading and Writing, Earth and Life Science, Physical Science) are never autofilled. The AI prompt explicitly instructs the model to ignore Grade 11 subjects, so the extraction result contains no Grade 11 data, leaving those required fields blank after autofill.

2. **Grade 10 images sent for extraction**: The `GradeExtractionService::loadImages()` method fetches all image files belonging to the user with no filter on document type. This causes Grade 10 report card images (`file10Front`, `file10`) to be included in the AI extraction request, even though Grade 10 grades are not evaluated — those images are uploaded only for identity/checking purposes.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN the AI extraction prompt is built THEN the system instructs the AI to ignore Grade 11 subjects, resulting in no Grade 11 grade data in the extraction result

1.2 WHEN `applyAutofill()` runs with an extraction result that contains no Grade 11 subjects THEN the system leaves all Grade 11 input fields (g11_general_mathematics, g11_business_mathematics, g11_statistics_probability, g11_oral_communication, g11_academic_professional, g11_reading_writing, g11_earth_life_science, g11_physical_science) empty

1.3 WHEN `GradeExtractionService::loadImages()` is called THEN the system fetches all image-type UserFile records for the user regardless of document type key, including Grade 10 report card images (file10Front, file10)

1.4 WHEN Grade 10 images are included in the extraction request THEN the system sends irrelevant Grade 10 grade data to the AI, potentially causing incorrect or confusing extraction results for Grade 11 and Grade 12 subjects

### Expected Behavior (Correct)

2.1 WHEN the AI extraction prompt is built THEN the system SHALL instruct the AI to extract both Grade 11 and Grade 12 subjects and grades from the uploaded images

2.2 WHEN `applyAutofill()` runs with an extraction result that contains Grade 11 subjects THEN the system SHALL populate the corresponding Grade 11 input fields with the extracted grade values

2.3 WHEN `GradeExtractionService::loadImages()` is called THEN the system SHALL exclude UserFile records whose type key is `file10Front` or `file10` (Grade 10 report card images) from the set of images sent to the AI

2.4 WHEN only Grade 11 and Grade 12 images are sent for extraction THEN the system SHALL produce extraction results that contain only Grade 11 and Grade 12 subject grades

### Unchanged Behavior (Regression Prevention)

3.1 WHEN the extraction result contains Grade 12 subjects THEN the system SHALL CONTINUE TO autofill Grade 12 subject name and grade fields (g12_math_subject_*, g12_math_grade_*, g12_science_subject_*, g12_science_grade_*, g12_english_subject_*, g12_english_grade_*)

3.2 WHEN the extraction result contains subjects with confidence scores below 0.80 THEN the system SHALL CONTINUE TO apply red border highlighting and display the "Low confidence result. Please verify." helper text on those fields

3.3 WHEN the extraction result contains subjects with any confidence score THEN the system SHALL CONTINUE TO display the AI confidence percentage label on autofilled fields

3.4 WHEN `GradeExtractionService::loadImages()` is called THEN the system SHALL CONTINUE TO include Grade 11 and Grade 12 report card images (file11Front, file11, file12Front, file12) in the extraction request

3.5 WHEN a UserFile record is not of MIME type image/jpeg, image/png, or image/webp THEN the system SHALL CONTINUE TO exclude that file from the extraction request

3.6 WHEN the extraction result is received THEN the system SHALL CONTINUE TO store it in the session and redirect the applicant to the strand-specific grade input page
