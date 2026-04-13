# Requirements Document

## Introduction

The application details modal (slide-in panel on the Applications/Index page) currently displays only three of the five available academic grades: Mathematics, Science, and English. The Grade model also stores `g12_first_sem` and `g12_second_sem`, but these are not shown. This feature ensures all five grades are displayed with clear, human-readable labels so that evaluators, interviewers, and admins have complete academic information when reviewing an applicant.

## Glossary

- **Modal**: The slide-in details panel rendered on the right side of the Applications/Index page when a user row is selected.
- **Grade**: A record in the `grades` table containing five numeric fields per applicant: `english`, `mathematics`, `science`, `g12_first_sem`, and `g12_second_sem`.
- **Grades_Section**: The "Academic Grades" block inside the Modal that renders grade cards.
- **Applicant**: A user with `role_id = 1` who has submitted an application.
- **Staff_User**: Any authenticated user with `role_id` of 2 (Admin), 3 (Evaluator), 4 (Interviewer), or 7 (Record Staff) who can open the Modal.

## Requirements

### Requirement 1: Display All Five Grades with Labels

**User Story:** As a Staff_User reviewing an application, I want to see all of the applicant's grades with clear labels, so that I can make informed decisions without needing to look up data elsewhere.

#### Acceptance Criteria

1. WHEN the Modal is opened for an Applicant, THE Grades_Section SHALL display all five grade fields: `english`, `mathematics`, `science`, `g12_first_sem`, and `g12_second_sem`.
2. THE Grades_Section SHALL label each grade card with a human-readable name: "English", "Mathematics", "Science", "Grade 12 – 1st Semester", and "Grade 12 – 2nd Semester".
3. WHEN a grade value is `null` or absent for an Applicant, THE Grades_Section SHALL display "—" in place of the numeric value for that grade card.
4. WHEN a grade value is present, THE Grades_Section SHALL display the numeric value formatted to two decimal places.

### Requirement 2: Consistent Layout for All Grade Cards

**User Story:** As a Staff_User, I want the grade cards to be visually consistent and readable, so that I can scan grades quickly without confusion.

#### Acceptance Criteria

1. THE Grades_Section SHALL render all five grade cards using the same visual style (background, padding, typography) as the existing three cards.
2. WHEN the Modal is displayed on a viewport narrower than 768px, THE Grades_Section SHALL wrap grade cards so that no card is clipped or requires horizontal scrolling.
3. THE Grades_Section SHALL display the grade label above the grade value within each card.

### Requirement 3: No Regression on Existing Grade Display

**User Story:** As a Staff_User, I want the existing three grades to continue displaying correctly after the change, so that the update does not break current functionality.

#### Acceptance Criteria

1. WHEN the Modal is opened, THE Grades_Section SHALL continue to display `mathematics`, `science`, and `english` with their existing labels and styling.
2. IF the `grades` relationship returns `null` for an Applicant, THEN THE Grades_Section SHALL render all five grade cards showing "—" without throwing a JavaScript error.
