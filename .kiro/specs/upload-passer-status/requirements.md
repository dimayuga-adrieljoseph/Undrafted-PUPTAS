# Requirements Document

## Introduction

This feature adds a bulk status assignment capability to the Upload Passers page and simplifies the Excel upload to only 7 required columns. Currently, the passer status is read from a "status" column in the uploaded Excel file on a per-row basis, and the import processes many columns including date_of_birth, address, school_address, school, year_graduated, and pupcet_total_score. This enhancement allows the admin user to select a single status (qualified, waitlisted, or unqualified) from a dropdown on the upload form, which will then be applied uniformly to all records imported from the Excel file. Additionally, the Excel upload is reduced to only the 7 columns that are permitted to be collected from applicants: surname, firstname, middle_name, strand, email, reference_number, and pupcet_score.

## Glossary

- **Upload_Form**: The Vue 3 page component at `/test-passers/form` where admins upload Excel files containing test passer data.
- **Status_Selector**: A dropdown UI element on the Upload_Form that allows the admin to choose a passer status to apply to all imported records.
- **TestPasserController**: The Laravel controller responsible for handling the upload request at the `/test-passers/upload` endpoint.
- **TestPassersImport**: The Laravel Excel import class that processes each row of the uploaded Excel file and creates or updates TestPasser records.
- **Passer_Status**: A classification assigned to a test passer record, with possible values: qualified (id=1), waitlisted (id=2), or unqualified (id=3).
- **Bulk_Status**: The status value selected by the admin on the Upload_Form, intended to override per-row status values for all records in the uploaded file.
- **Permitted_Columns**: The 7 Excel columns allowed for upload: surname, firstname, middle_name, strand, email, reference_number, and pupcet_score.

## Requirements

### Requirement 1: Status Selection UI

**User Story:** As an admin, I want to select a passer status from a dropdown on the upload form, so that I can assign the same status to all applicants in the uploaded Excel file.

#### Acceptance Criteria

1. THE Upload_Form SHALL display a Status_Selector dropdown with a disabled placeholder option "Select Passer Status" (selected by default) followed by the options: "Qualified", "Waitlisted", and "Unqualified".
2. THE Status_Selector SHALL be positioned between the Batch/School Year selectors and the file upload area on the Upload_Form.
3. THE Upload_Form SHALL require the admin to select a Passer_Status before submitting the upload.
4. IF no Passer_Status is selected when the admin attempts to submit the form, THEN THE Upload_Form SHALL prevent form submission and display a validation message adjacent to the Status_Selector indicating that a status selection is required.

### Requirement 2: Status Transmission

**User Story:** As an admin, I want the selected status to be sent along with the file upload request, so that the backend can apply it to all imported records.

#### Acceptance Criteria

1. WHEN the admin submits the upload form, THE Upload_Form SHALL include the selected Passer_Status value in the POST request payload as a `passer_status_id` field with an integer value (1, 2, or 3).
2. WHEN the TestPasserController receives an upload request, THE TestPasserController SHALL validate that the `passer_status_id` field is present, is an integer, and contains a valid value (1, 2, or 3) before processing the file import.
3. IF the `passer_status_id` field is missing or contains an invalid value, THEN THE TestPasserController SHALL return a 422 validation error response with a message indicating which validation rule failed, and SHALL NOT process the uploaded file.

### Requirement 3: Bulk Status Application

**User Story:** As an admin, I want all imported records to receive the status I selected, so that I do not have to manually set the status for each applicant or maintain a status column in the Excel file.

#### Acceptance Criteria

1. WHEN the upload request includes a `passer_status_id` value that exists in the `passer_statuses` table (valid values: 1, 2, or 3), THE TestPassersImport SHALL set the `passer_status_id` field of every imported TestPasser record to the value provided in the request.
2. WHEN a `passer_status_id` is provided in the upload request, THE TestPassersImport SHALL disregard any per-row "status" column value present in the Excel file and use only the request-level `passer_status_id` for all records.
3. WHEN a TestPasser record is updated via the `updateOrCreate` operation during import, THE TestPassersImport SHALL overwrite the record's existing `passer_status_id` with the value provided in the upload request.
4. IF the upload request contains a `passer_status_id` value that does not exist in the `passer_statuses` table, THEN THE System SHALL reject the upload request with a validation error indicating the provided status is invalid, and SHALL not import any records.

### Requirement 4: Permitted Excel Column Set

**User Story:** As an admin, I want the Excel upload to only require the 7 permitted columns (surname, firstname, middle_name, strand, email, reference_number, pupcet_score), so that we only collect the information we are allowed to take from applicants.

#### Acceptance Criteria

1. THE TestPassersImport SHALL read only the following 7 columns from the uploaded Excel file: surname, firstname, middle_name, strand, email, reference_number, and pupcet_score.
2. THE TestPassersImport SHALL map the 7 Excel columns to TestPasser record fields as follows: surname → surname, firstname → first_name, middle_name → middle_name, strand → strand, email → email, reference_number → reference_number, pupcet_score → pupcet_total_score.
3. THE TestPassersImport SHALL read the pupcet_score column directly from the Excel file and store its numeric value in the TestPasser record's pupcet_total_score field.
4. IF the pupcet_score column contains a non-numeric or empty value, THEN THE TestPassersImport SHALL store null for the pupcet_total_score field.
5. THE TestPassersImport SHALL NOT use multiple column name variants for score resolution (no pupcet_total_score, total_score, or other aliases) and SHALL only read the column named "pupcet_score".
6. IF the firstname column is empty or missing for a row, THEN THE TestPassersImport SHALL skip that row and not create a TestPasser record.
7. THE TestPassersImport SHALL NOT read or process the date_of_birth column from the Excel file.
8. THE TestPassersImport SHALL NOT read or process the address column from the Excel file.
9. THE TestPassersImport SHALL NOT read or process the school_address column from the Excel file.
10. THE TestPassersImport SHALL NOT read or process the school column (previously mapped to shs_school) from the Excel file.
11. THE TestPassersImport SHALL NOT read or process the year_graduated column from the Excel file.
12. THE TestPassersImport SHALL NOT include a resolveScore method or any multi-variant PUPCET score resolution logic.
13. THE TestPassersImport SHALL NOT include date_of_birth parsing logic (Excel serial date conversion or string date parsing).

### Requirement 5: Removed Fields Handling

**User Story:** As a developer, I want fields that no longer have source data from the Excel upload to be excluded from the import operations, so that the system does not store stale or null data for those fields.

#### Acceptance Criteria

1. THE TestPassersImport SHALL NOT set the `date_of_birth` field when creating or updating TestPasser records.
2. THE TestPassersImport SHALL NOT set the `address` field when creating or updating TestPasser records.
3. THE TestPassersImport SHALL NOT set the `school_address` field when creating or updating TestPasser records.
4. THE TestPassersImport SHALL NOT set the `shs_school` field when creating or updating TestPasser records.
5. THE TestPassersImport SHALL NOT set the `year_graduated` field when creating or updating TestPasser records.
6. WHEN creating a new TestPasser record where the row has no email value, THE TestPassersImport SHALL only populate: surname, first_name, middle_name, strand, reference_number, pupcet_total_score, batch_number, school_year, user_id (set to null), status (set to "pending"), and passer_status_id.
7. WHEN creating or updating a TestPasser record via updateOrCreate with `email` as the unique match key, THE TestPassersImport SHALL only set: surname, first_name, middle_name, strand, reference_number, pupcet_total_score, batch_number, school_year, user_id, status, and passer_status_id.
8. IF a row in the Excel upload has an empty or whitespace-only `firstname` value, THEN THE TestPassersImport SHALL skip that row and not create or update any TestPasser record.

### Requirement 6: PUPCET Score Storage

**User Story:** As an admin, I want the pupcet_score from the Excel file to be stored in the TestPasser record, so that the applicant's test score is captured during import.

#### Acceptance Criteria

1. WHEN a row contains a numeric pupcet_score value in the range 0.00 to 9999.99, THE TestPassersImport SHALL store that value in the `pupcet_total_score` field of the TestPasser record, rounded to 2 decimal places.
2. WHEN a row contains an empty, null, or whitespace-only pupcet_score value, THE TestPassersImport SHALL set the `pupcet_total_score` field to null for that TestPasser record.
3. IF a row contains a non-numeric pupcet_score value (e.g., text, special characters), THEN THE TestPassersImport SHALL set the `pupcet_total_score` field to null for that TestPasser record.
4. IF a row contains a numeric pupcet_score value outside the range 0.00 to 9999.99, THEN THE TestPassersImport SHALL set the `pupcet_total_score` field to null for that TestPasser record.
5. THE TestPassersImport SHALL read the score directly from the "pupcet_score" column without attempting to resolve from alternative column names.

### Requirement 7: Backward Compatibility

**User Story:** As a developer, I want the system to remain compatible with the existing upload flow for non-removed functionality, so that no existing functionality outside the column reduction is broken.

#### Acceptance Criteria

1. THE TestPasserController SHALL validate the upload request requiring `batch_number` as a non-empty string, `school_year` as a non-empty string, and `file` as an uploaded file with mime type xlsx, xls, or csv.
2. THE Upload_Form SHALL retain the batch number selector, school year selector, and file upload input with the same selection behavior and field constraints as before the column reduction change.
3. IF a row's email matches an existing User record, THEN THE TestPassersImport SHALL set the `user_id` field of the TestPasser record to that User's id and set the `status` field to "registered".
4. IF a row's email matches an existing User record that has an applicantProfile and the row contains a reference_number value, THEN THE TestPassersImport SHALL update that applicantProfile's `student_number` field to the row's reference_number.
5. IF a row's email does not match any existing User record, THEN THE TestPassersImport SHALL set the `user_id` field to null and set the `status` field to "pending".
6. IF a row's firstname column is empty or whitespace-only after trimming, THEN THE TestPassersImport SHALL skip that row and not create or update any TestPasser record.
7. IF a row contains a non-empty email value, THEN THE TestPassersImport SHALL use the `updateOrCreate` pattern with email as the unique key.
8. IF a row contains no email value (null or empty), THEN THE TestPassersImport SHALL use `create` to insert a new TestPasser record without an email-based uniqueness check.
