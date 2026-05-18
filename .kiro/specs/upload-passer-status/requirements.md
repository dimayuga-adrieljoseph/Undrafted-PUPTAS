# Requirements Document

## Introduction

This feature adds a bulk status assignment capability to the Upload Passers page. Currently, the passer status is read from a "status" column in the uploaded Excel file on a per-row basis. This enhancement allows the admin user to select a single status (qualified, waitlisted, or unqualified) from a dropdown on the upload form, which will then be applied uniformly to all records imported from the Excel file, overriding any per-row status column.

## Glossary

- **Upload_Form**: The Vue 3 page component at `/test-passers/form` where admins upload Excel files containing test passer data.
- **Status_Selector**: A dropdown UI element on the Upload_Form that allows the admin to choose a passer status to apply to all imported records.
- **TestPasserController**: The Laravel controller responsible for handling the upload request at the `/test-passers/upload` endpoint.
- **TestPassersImport**: The Laravel Excel import class that processes each row of the uploaded Excel file and creates or updates TestPasser records.
- **Passer_Status**: A classification assigned to a test passer record, with possible values: qualified (id=1), waitlisted (id=2), or unqualified (id=3).
- **Bulk_Status**: The status value selected by the admin on the Upload_Form, intended to override per-row status values for all records in the uploaded file.

## Requirements

### Requirement 1: Status Selection UI

**User Story:** As an admin, I want to select a passer status from a dropdown on the upload form, so that I can assign the same status to all applicants in the uploaded Excel file.

#### Acceptance Criteria

1. THE Upload_Form SHALL display a Status_Selector dropdown with the options: "Qualified", "Waitlisted", and "Unqualified".
2. THE Status_Selector SHALL be positioned between the Batch/School Year selectors and the file upload area on the Upload_Form.
3. THE Upload_Form SHALL require the admin to select a Passer_Status before submitting the upload.
4. WHEN no Passer_Status is selected, THE Upload_Form SHALL prevent form submission and display a validation message indicating that a status selection is required.

### Requirement 2: Status Transmission

**User Story:** As an admin, I want the selected status to be sent along with the file upload request, so that the backend can apply it to all imported records.

#### Acceptance Criteria

1. WHEN the admin submits the upload form, THE Upload_Form SHALL include the selected Passer_Status value in the POST request payload as a `passer_status_id` field.
2. THE TestPasserController SHALL validate that the `passer_status_id` field is present and contains a valid value (1, 2, or 3).
3. IF the `passer_status_id` field is missing or contains an invalid value, THEN THE TestPasserController SHALL return a 422 validation error response with a descriptive message.

### Requirement 3: Bulk Status Application

**User Story:** As an admin, I want all imported records to receive the status I selected, so that I do not have to manually set the status for each applicant or maintain a status column in the Excel file.

#### Acceptance Criteria

1. WHEN the upload request includes a valid `passer_status_id`, THE TestPassersImport SHALL set the `passer_status_id` field of every imported TestPasser record to the value provided in the request.
2. THE TestPassersImport SHALL ignore any per-row "status" column value in the Excel file when a Bulk_Status is provided in the request.
3. THE TestPassersImport SHALL apply the Bulk_Status to both newly created records and records updated via the `updateOrCreate` operation.

### Requirement 4: Backward Compatibility

**User Story:** As a developer, I want the system to remain compatible with the existing upload flow, so that no existing functionality is broken.

#### Acceptance Criteria

1. THE TestPassersImport SHALL continue to process all other Excel columns (surname, first_name, middle_name, date_of_birth, address, school_address, school, strand, year_graduated, email, reference_number, pupcet_total_score) without modification.
2. THE TestPasserController SHALL continue to accept and process the `batch_number`, `school_year`, and `file` fields as before.
3. THE Upload_Form SHALL retain all existing functionality for batch number selection, school year selection, and file upload.
