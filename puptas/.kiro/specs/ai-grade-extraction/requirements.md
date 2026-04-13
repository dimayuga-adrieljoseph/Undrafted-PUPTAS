# Requirements Document

## Introduction

This feature enhances the existing Laravel + Inertia.js (Vue) applicant admission system to extract grades from uploaded scanned document images using the Gemini AI API. The system will analyze uploaded report card images, extract subject-grade pairs with confidence scores, autofill the Grade Input Page, and highlight low-confidence results for applicant review before final submission. The feature integrates with the existing document upload flow on the Applicant Dashboard and the strand-specific Grade Input Pages (ABM, ICT, HUMSS, GAS, STEM, TVL).

## Glossary

- **AI_Extraction_Service**: The backend Laravel service responsible for sending uploaded images to the Gemini API and processing the response.
- **Gemini_API**: Google's Gemini multimodal AI API used to analyze document images and extract grade data.
- **Grade_Input_Page**: The strand-specific Vue page (e.g., ABMGradeInput.vue) where applicants enter or review their subject grades.
- **Confidence_Score**: A float value between 0 and 1 returned by the Gemini API indicating the AI's certainty in an extracted grade value.
- **Low_Confidence_Threshold**: The value 0.80, below which a confidence score is considered low and requires applicant verification.
- **Extraction_Result**: The structured JSON object returned by the AI_Extraction_Service containing subject groups, grade values, and confidence scores.
- **Applicant_Dashboard**: The Vue page (Dashboard/Applicant.vue) where applicants manage their uploaded documents and track application status.
- **UserFile**: The Eloquent model representing an uploaded document file associated with an applicant.
- **Grade**: The Eloquent model storing computed subject averages (mathematics, english, science, g12_first_sem, g12_second_sem) for an applicant.
- **Subject_Group**: One of four top-level categories in the Extraction_Result: `math`, `science`, `english`, or `others`.
- **Autofill**: The process of populating Grade Input Page fields with grade values from the Extraction_Result.
- **Review_Grades_Button**: A conditionally rendered button on the Applicant_Dashboard that triggers AI grade extraction.

## Requirements

### Requirement 1: Review Grades Trigger

**User Story:** As an applicant, I want a "Review Grades" button to appear once all required documents are uploaded, so that I can trigger AI-based grade extraction without navigating away from the dashboard.

#### Acceptance Criteria

1. WHEN the Applicant_Dashboard loads and all required document slots in `fileStatuses` have a non-null `url`, THE Applicant_Dashboard SHALL render the "Review Grades" button.
2. WHILE one or more required document slots in `fileStatuses` have a null `url`, THE Applicant_Dashboard SHALL hide the "Review Grades" button.
3. WHEN the applicant clicks the "Review Grades" button, THE Applicant_Dashboard SHALL display a loading indicator and disable the button to prevent duplicate submissions.
4. WHEN the applicant clicks the "Review Grades" button, THE Applicant_Dashboard SHALL send a POST request to the backend endpoint `/api/grades/extract` with the authenticated applicant's identity.
5. IF the POST request to `/api/grades/extract` fails, THEN THE Applicant_Dashboard SHALL display an error message and re-enable the "Review Grades" button.

---

### Requirement 2: Backend AI Extraction Endpoint

**User Story:** As a system, I want a secure backend endpoint that retrieves the applicant's uploaded images and sends them to the Gemini API, so that grade extraction is performed server-side without exposing API credentials to the frontend.

#### Acceptance Criteria

1. THE AI_Extraction_Service SHALL read the `GEMINI_API_KEY` exclusively from the server-side `.env` file and SHALL NOT expose it in any HTTP response or frontend asset.
2. WHEN a POST request is received at `/api/grades/extract`, THE AI_Extraction_Service SHALL verify that the authenticated user owns the files being processed before sending them to the Gemini_API.
3. WHEN a POST request is received at `/api/grades/extract`, THE AI_Extraction_Service SHALL retrieve only files of MIME type `image/jpeg` or `image/png` associated with the authenticated applicant.
4. IF a file associated with the authenticated applicant is not of type `image/jpeg` or `image/png`, THEN THE AI_Extraction_Service SHALL exclude that file from the Gemini_API request.
5. THE AI_Extraction_Service SHALL apply rate limiting of no more than 10 extraction requests per applicant per hour.
6. IF the rate limit is exceeded, THEN THE AI_Extraction_Service SHALL return an HTTP 429 response with a descriptive error message.

---

### Requirement 3: Gemini API Integration and Response Parsing

**User Story:** As a system, I want to send uploaded images to the Gemini API and receive a structured JSON extraction result, so that subject grades and confidence scores can be reliably passed to the frontend.

#### Acceptance Criteria

1. WHEN images are sent to the Gemini_API, THE AI_Extraction_Service SHALL include a prompt instructing the Gemini_API to return a JSON object structured with Subject_Groups (`math`, `science`, `english`, `others`), each containing subject name keys mapped to objects with `grade` (integer 0–100) and `confidence` (float 0–1) fields.
2. WHEN the Gemini_API returns a response, THE AI_Extraction_Service SHALL validate that the response is parseable JSON and conforms to the required Extraction_Result structure.
3. WHEN parsing the Extraction_Result, THE AI_Extraction_Service SHALL normalize all subject name keys to lowercase trimmed strings.
4. IF the Gemini_API returns a malformed, unparseable, or structurally non-conforming response, THEN THE AI_Extraction_Service SHALL return an HTTP 422 response with a descriptive error message and SHALL NOT store any partial data.
5. IF the Gemini_API returns a `grade` value outside the range 0–100 or a `confidence` value outside the range 0–1, THEN THE AI_Extraction_Service SHALL reject the entire Extraction_Result and return an HTTP 422 response.
6. WHEN a valid Extraction_Result is produced, THE AI_Extraction_Service SHALL return it as a JSON HTTP response to the frontend.

**Expected Extraction_Result format:**
```json
{
  "math": {
    "algebra": { "grade": 90, "confidence": 0.95 },
    "geometry": { "grade": 88, "confidence": 0.60 }
  },
  "science": {
    "biology": { "grade": 91, "confidence": 0.92 }
  },
  "english": {
    "english": { "grade": 92, "confidence": 0.97 }
  },
  "others": {
    "araling panlipunan": { "grade": 90, "confidence": 0.55 }
  }
}
```

---

### Requirement 4: Grade Input Page Autofill

**User Story:** As an applicant, I want the Grade Input Page to be automatically populated with the AI-extracted grades, so that I don't have to manually transcribe values from my documents.

#### Acceptance Criteria

1. WHEN the Extraction_Result is received by the Grade_Input_Page, THE Grade_Input_Page SHALL match each subject key in the Extraction_Result to input fields using case-insensitive, whitespace-trimmed string comparison.
2. WHEN a subject key in the Extraction_Result matches an input field, THE Grade_Input_Page SHALL populate that field's value with the corresponding `grade` value from the Extraction_Result.
3. WHEN a subject key in the Extraction_Result does not match any input field, THE Grade_Input_Page SHALL leave unmatched fields unchanged and SHALL NOT throw an error.
4. WHEN autofill is applied, THE Grade_Input_Page SHALL store the Extraction_Result (including confidence scores) in component state for use in confidence-based highlighting.
5. THE Grade_Input_Page SHALL complete the autofill of all matched fields within 500ms of receiving the Extraction_Result.

---

### Requirement 5: Confidence-Based Highlighting

**User Story:** As an applicant, I want low-confidence grade fields to be visually highlighted, so that I know which values need my manual verification before submission.

#### Acceptance Criteria

1. WHEN an autofilled input field has a corresponding confidence score less than 0.80, THE Grade_Input_Page SHALL apply a red border style to that input field.
2. WHEN an autofilled input field has a corresponding confidence score less than 0.80, THE Grade_Input_Page SHALL display helper text "Low confidence result. Please verify." directly below that input field.
3. WHEN an autofilled input field has a corresponding confidence score less than 0.80, THE Grade_Input_Page SHALL display the confidence percentage (e.g., "60%") as a tooltip or inline label on that input field.
4. WHEN an autofilled input field has a corresponding confidence score of 0.80 or greater, THE Grade_Input_Page SHALL apply no warning styles and SHALL display no helper text for that field.
5. THE Grade_Input_Page SHALL use Font Awesome icons for any warning indicators and SHALL NOT use inline SVG elements for this purpose.

---

### Requirement 6: User Review and Editing

**User Story:** As an applicant, I want to be able to edit any autofilled grade value before submitting, so that I can correct AI extraction errors and ensure my submission is accurate.

#### Acceptance Criteria

1. THE Grade_Input_Page SHALL keep all grade input fields editable after autofill is applied.
2. WHILE a field is highlighted due to low confidence, THE Grade_Input_Page SHALL allow the applicant to type in that field without restriction.
3. WHEN an applicant edits a field that was autofilled with a low-confidence value, THE Grade_Input_Page SHALL retain the warning highlight until the applicant has modified the value.
4. THE Grade_Input_Page SHALL display a confidence score tooltip or label on each autofilled field regardless of whether the confidence is above or below the Low_Confidence_Threshold.
5. THE Grade_Input_Page SHALL NOT lock, disable, or block interaction with any input field as a result of AI extraction or confidence scoring.

---

### Requirement 7: Submission Flow

**User Story:** As an applicant, I want to submit my reviewed grades after verifying AI-extracted values, so that my application can proceed to the admission process.

#### Acceptance Criteria

1. WHEN the applicant clicks the submit button on the Grade_Input_Page after autofill, THE Grade_Input_Page SHALL submit the current (possibly edited) field values using the existing grade submission flow.
2. THE Grade_Input_Page SHALL NOT require all low-confidence fields to be edited before allowing submission.
3. WHEN grades are successfully submitted, THE Grade_Input_Page SHALL redirect the applicant to the Applicant_Dashboard as per the existing post-submission behavior.

---

### Requirement 8: UI Consistency and Loading States

**User Story:** As an applicant, I want the AI extraction feature to feel consistent with the rest of the application and provide clear feedback during processing, so that I understand what is happening at each step.

#### Acceptance Criteria

1. WHEN the "Review Grades" button is clicked and the extraction request is in progress, THE Applicant_Dashboard SHALL display a loading spinner consistent with the existing loading indicator style used on the dashboard.
2. THE Applicant_Dashboard SHALL render the "Review Grades" button using the existing maroon (`#9E122C`) primary button style used throughout the application.
3. THE Grade_Input_Page SHALL apply confidence highlighting using the existing Tailwind CSS border and text utility classes already present in the design system.
4. IF the AI extraction completes successfully, THEN THE Applicant_Dashboard SHALL navigate the applicant to the appropriate strand-specific Grade_Input_Page with the Extraction_Result passed as page props.
5. THE Grade_Input_Page SHALL display a dismissible banner indicating that grades have been autofilled by AI and require review before submission.

---

### Requirement 9: Security and Input Sanitization

**User Story:** As a system administrator, I want all AI responses and user inputs to be sanitized and validated, so that malformed or malicious data cannot compromise the application.

#### Acceptance Criteria

1. THE AI_Extraction_Service SHALL sanitize the raw Gemini_API response by stripping any content outside the expected JSON structure before parsing.
2. WHEN grade values are submitted via the Grade_Input_Page, THE GradesController SHALL validate that each submitted grade is a numeric value between 0 and 100, consistent with existing validation rules.
3. THE AI_Extraction_Service SHALL verify file ownership by confirming the `user_id` on each UserFile matches the authenticated applicant's ID before processing.
4. IF the Gemini_API is unreachable or returns an HTTP error, THEN THE AI_Extraction_Service SHALL log the error and return an HTTP 503 response with a user-friendly error message.
5. THE AI_Extraction_Service SHALL NOT store raw Gemini_API responses in the database.

---

### Requirement 10: Optional Enhancements

**User Story:** As an applicant, I want additional tools to help me review AI-extracted grades efficiently, so that I can focus my attention on the most uncertain values.

#### Acceptance Criteria

1. WHERE the sort-by-confidence enhancement is enabled, THE Grade_Input_Page SHALL render autofilled subject fields sorted in ascending order of confidence score, placing the lowest-confidence fields first.
2. WHERE the low-confidence filter enhancement is enabled, THE Grade_Input_Page SHALL provide a toggle that, when active, shows only input fields with a confidence score below 0.80.
3. WHERE the admin analytics enhancement is enabled, THE AI_Extraction_Service SHALL log each extraction event including the applicant's user ID, timestamp, and per-subject confidence scores to a dedicated audit log.
4. WHERE the re-run enhancement is enabled, THE Grade_Input_Page SHALL display a "Re-run AI Extraction" button that sends a new POST request to `/api/grades/extract` and replaces the current autofilled values with the new Extraction_Result.
