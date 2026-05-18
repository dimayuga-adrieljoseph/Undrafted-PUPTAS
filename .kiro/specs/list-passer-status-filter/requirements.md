# Requirements Document

## Introduction

This feature adds a status filter dropdown to the "List Passer" page (TestPassers/Email.vue) in the PUPTAS admin panel. The dropdown allows administrators to filter the displayed list of PUPCET passers by their qualification status: Qualified, Waitlisted, or Unqualified. Currently, the page supports filtering by school year, batch number, and search term, but lacks the ability to filter by passer status.

## Glossary

- **List_Passer_Page**: The admin page rendered at `/test-passers` that displays all PUPCET test passers grouped by school year and batch, with filtering, sorting, and email capabilities (TestPassers/Email.vue).
- **Status_Filter_Dropdown**: A `<select>` UI element placed in the Filters & Controls section of the List_Passer_Page that allows the user to choose a passer status to filter by.
- **Passer_Status**: The qualification status of a test passer, stored via the `passer_status_id` foreign key on the `test_passers` table referencing the `passer_statuses` table. Current database values are `qualified` (id=1) and `waitlisted` (id=2).
- **Unqualified_Status**: A new passer status value representing passers who did not qualify. This requires adding a new record (id=3, status='unqualified') to the `passer_statuses` table.
- **Filtered_Passers_List**: The computed list of passers displayed in the table after all active filters (search, school year, batch, and status) have been applied.

## Requirements

### Requirement 1: Add Unqualified Status to Database

**User Story:** As an administrator, I want an "Unqualified" status option available in the system, so that I can categorize passers who did not meet the qualification criteria.

#### Acceptance Criteria

1. THE System SHALL include a database migration that inserts a new record with status value 'unqualified' into the `passer_statuses` table.
2. WHEN the migration runs, THE System SHALL assign the 'unqualified' status an auto-incremented id (id=3).
3. THE System SHALL preserve existing 'qualified' (id=1) and 'waitlisted' (id=2) records without modification.
4. THE migration SHALL include a rollback (down) method that removes the 'unqualified' record from the `passer_statuses` table.
5. IF the 'unqualified' record already exists in the `passer_statuses` table, THEN THE migration SHALL skip the insert without throwing an error.

### Requirement 2: Render Status Filter Dropdown

**User Story:** As an administrator, I want a status filter dropdown on the List Passer page, so that I can quickly narrow down the passer list by qualification status.

#### Acceptance Criteria

1. THE List_Passer_Page SHALL display a Status_Filter_Dropdown within the existing "Filter Grid" section alongside the School Year, Batch, Sort By, and Order dropdowns.
2. THE Status_Filter_Dropdown SHALL contain the following options: "All Statuses" (default selected on page load, displaying all passers regardless of status), "Qualified", "Waitlisted", and "Unqualified".
3. THE Status_Filter_Dropdown SHALL use the same visual styling (rounded-xl, border, focus ring) as the existing School Year and Batch filter dropdowns.
4. THE Status_Filter_Dropdown SHALL include a label reading "Status" above the select element, consistent with other filter labels on the page.
5. WHEN the administrator selects a status option other than "All Statuses", THE List_Passer_Page SHALL display only passers whose qualification status matches the selected option.
6. WHEN the administrator selects "All Statuses", THE List_Passer_Page SHALL display all passers regardless of their qualification status.

### Requirement 3: Filter Passers by Selected Status

**User Story:** As an administrator, I want the passer list to update when I select a status from the dropdown, so that I only see passers matching the chosen status.

#### Acceptance Criteria

1. WHEN the user selects "Qualified" from the Status_Filter_Dropdown, THE List_Passer_Page SHALL display only passers with `passer_status_id` equal to 1.
2. WHEN the user selects "Waitlisted" from the Status_Filter_Dropdown, THE List_Passer_Page SHALL display only passers with `passer_status_id` equal to 2.
3. WHEN the user selects "Unqualified" from the Status_Filter_Dropdown, THE List_Passer_Page SHALL display only passers with `passer_status_id` equal to 3.
4. WHEN the user selects "All Statuses" from the Status_Filter_Dropdown, THE List_Passer_Page SHALL display passers regardless of their `passer_status_id` value.
5. WHEN the List_Passer_Page loads, THE Status_Filter_Dropdown SHALL default to "All Statuses" so that all passers are visible without requiring user interaction.
6. THE Status_Filter_Dropdown SHALL apply its filter in combination with the existing search, school year, and batch filters using AND logic, such that only passers satisfying all active filter conditions are displayed.
7. IF no passers match the combination of the selected status filter and other active filters, THEN THE List_Passer_Page SHALL display an empty table with a message indicating that no passers match the current filters.

### Requirement 4: Reset Pagination on Status Filter Change

**User Story:** As an administrator, I want the page to reset to page 1 when I change the status filter, so that I always see results from the beginning of the filtered list.

#### Acceptance Criteria

1. WHEN the user changes the selected value in the Status_Filter_Dropdown, THE List_Passer_Page SHALL reset the current page number to 1.
2. WHEN the user changes the selected value in the Status_Filter_Dropdown and the Filtered_Passers_List contains 1 or more results, THE List_Passer_Page SHALL update the "Showing X to Y of Z results" text where X equals 1, Y equals the lesser of the page size and Z, and Z equals the total number of passers matching all active filters.
3. IF the user changes the selected value in the Status_Filter_Dropdown and the Filtered_Passers_List contains 0 results, THEN THE List_Passer_Page SHALL display "Showing 0 to 0 of 0 results" in the pagination text.

### Requirement 5: Update Passer Count Display

**User Story:** As an administrator, I want the passer count badge to reflect the filtered results, so that I know how many passers match my current filter criteria.

#### Acceptance Criteria

1. WHEN a status filter is active, THE List_Passer_Page SHALL display the count of passers matching all active filters (including status) in the "X passers" badge in the Filters & Controls header.
2. WHEN a status filter is active, THE List_Passer_Page SHALL display the filtered count in the Statistics panel "Filtered" metric.
3. WHEN the Status_Filter_Dropdown is set to "All Statuses", THE List_Passer_Page SHALL display the total count of all passers (matching other active filters) in the "X passers" badge.
4. WHEN the user changes the Status_Filter_Dropdown selection, THE passer count badge and Statistics panel "Filtered" metric SHALL update reactively without requiring a page reload.
