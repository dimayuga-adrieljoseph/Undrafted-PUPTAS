# Bugfix Requirements Document

## Introduction

Three related bugs affect the applicant post-login flow and grade input feature. First, the login redirect logic in `AuthenticatedSessionController` unconditionally sends any applicant with a strand set and no grades to the grade input page — even on repeat logins — instead of always landing on the dashboard. Second, the "Review Grades" button on the applicant dashboard is permanently hidden because `allDocumentsUploaded` evaluates to `false` when `FileMapper::formatFilesForGraduateType` returns a full set of null-placeholder slots for an unknown graduate type, making every file slot appear empty. Third, when the applicant does reach the grade input page via the "Review Grades" button, the AI grade extraction (GradeExtractionController / OpenRouter) is not triggered automatically, so the grade fields are never autofilled from the uploaded documents.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN an applicant with a strand set and no grades record logs in (on any login, including repeat logins) THEN the system redirects them to the strand-specific grade input page instead of the applicant dashboard.

1.2 WHEN an applicant's graduate type is null or not one of the three recognized values THEN `FileMapper::formatFilesForGraduateType` returns a full set of null-placeholder entries for all 13 file slots, causing `allDocumentsUploaded` on the dashboard to evaluate to `false` and the "Review Grades" button to never appear.

1.3 WHEN an applicant has uploaded all required documents but their graduate type is unrecognized THEN the system hides the "Review Grades" button, making grade extraction permanently inaccessible from the dashboard.

1.4 WHEN an applicant reaches the grade input page by clicking "Review Grades" on the dashboard THEN the system does not automatically trigger AI grade extraction, leaving all grade fields empty and requiring the applicant to fill them in manually without autofill.

### Expected Behavior (Correct)

2.1 WHEN an applicant logs in (regardless of whether they have a strand set or grades on record) THEN the system SHALL always redirect them to the applicant dashboard (`/applicant-dashboard`).

2.2 WHEN an applicant's graduate type is null or not one of the three recognized values THEN the system SHALL return an empty object for `uploadedFiles` so the frontend renders a "no documents required yet" state without falsely blocking the "Review Grades" button.

2.3 WHEN an applicant has uploaded all required documents for their recognized graduate type THEN the system SHALL display the "Review Grades" button on the dashboard (i.e., `allDocumentsUploaded` SHALL evaluate to `true`).

2.4 WHEN an applicant clicks "Review Grades" on the dashboard THEN the system SHALL trigger AI grade extraction via `GradeExtractionController` before navigating to the grade input page, and the grade input page SHALL display the autofilled values in editable fields.

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a non-applicant user (role_id != 1) logs in THEN the system SHALL CONTINUE TO redirect them to their respective role dashboard.

3.2 WHEN an applicant with grades already on record logs in THEN the system SHALL CONTINUE TO redirect them directly to the applicant dashboard.

3.3 WHEN an applicant's graduate type is one of the three recognized values and they have uploaded all required files for that type THEN the system SHALL CONTINUE TO show the "Review Grades" button and `allDocumentsUploaded` SHALL CONTINUE TO evaluate correctly.

3.4 WHEN an applicant logs out THEN the system SHALL CONTINUE TO invalidate the session and redirect to the root path.

3.5 WHEN an applicant submits grades via the grade input form THEN the system SHALL CONTINUE TO validate, save, and redirect to the applicant dashboard as before.

3.6 WHEN an applicant navigates directly to a grade input URL (e.g. `/grades/stem`) without going through "Review Grades" THEN the system SHALL CONTINUE TO render the grade input page (access control for direct URL navigation is unchanged by this fix).
