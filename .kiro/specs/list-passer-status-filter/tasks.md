# Implementation Plan: List Passer Status Filter

## Overview

This implementation plan breaks down the list-passer-status-filter feature into discrete coding tasks. The feature adds a status filter dropdown to the List Passer page, allowing administrators to filter PUPCET test passers by their qualification status (Qualified, Waitlisted, Unqualified). The implementation involves database migration, frontend UI enhancements, and filtering logic updates.

## Tasks

- [x] 1. Create database migration for unqualified status
  - [x] 1.1 Create migration file to add unqualified passer status
    - Generate Laravel migration file using `php artisan make:migration add_unqualified_passer_status`
    - Implement up() method to insert 'unqualified' status record with id=3
    - Implement down() method to remove the unqualified record safely
    - Use insertOrIgnore() to prevent duplicate record errors
    - _Requirements: 1.1, 1.2, 1.4, 1.5_

  - [x] 1.2 Write property test for migration idempotency
    - **Property 1: Migration idempotency**
    - **Validates: Requirements 1.5**

- [ ] 2. Checkpoint - Verify migration works correctly
  - Ensure migration runs successfully, ask the user if questions arise.

- [x] 3. Implement status filter dropdown UI component
  - [x] 3.1 Add status filter dropdown to TestPassers/Email.vue
    - Add filterPasserStatus reactive data property with empty string default
    - Create status filter dropdown in the Filter Grid section
    - Include options: "All Statuses", "Qualified", "Waitlisted", "Unqualified"
    - Apply consistent styling with existing dropdowns (rounded-xl, border, focus ring)
    - Add "Status" label above the select element
    - _Requirements: 2.1, 2.2, 2.3, 2.4_

  - [x] 3.2 Write unit tests for dropdown rendering
    - Test dropdown appears with correct options
    - Test default selection is "All Statuses"
    - Test styling consistency with existing dropdowns
    - _Requirements: 2.1, 2.2, 2.3, 2.4_

- [x] 4. Implement status filtering logic
  - [x] 4.1 Update filteredPassers computed property
    - Extend existing filteredPassers computed property to include status filtering
    - Implement AND logic combining status filter with existing filters
    - Handle "All Statuses" selection to show all passers
    - Handle specific status selections (1=qualified, 2=waitlisted, 3=unqualified)
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.6_

  - [x] 4.2 Write property test for status filtering correctness
    - **Property 1: Status filtering correctness**
    - **Validates: Requirements 2.5, 2.6, 3.1, 3.2, 3.3, 3.4**

  - [x] 4.3 Write property test for combined filter logic
    - **Property 2: Combined filter logic**
    - **Validates: Requirements 3.6**

- [x] 5. Implement pagination reset functionality
  - [x] 5.1 Add pagination reset on status filter change
    - Add watcher for filterPasserStatus changes
    - Reset currentPage to 1 when status filter changes
    - Ensure pagination text updates correctly for filtered results
    - _Requirements: 4.1, 4.2, 4.3_

  - [x] 5.2 Write property test for pagination reset
    - **Property 4: Pagination reset on filter change**
    - **Validates: Requirements 4.1, 4.2**

- [x] 6. Update counter displays and statistics
  - [x] 6.1 Update passer count badge and statistics panel
    - Ensure passers badge reflects filtered count
    - Update Statistics panel "Filtered" metric to show filtered count
    - Verify reactive updates when status filter changes
    - _Requirements: 5.1, 5.2, 5.3, 5.4_

  - [x] 6.2 Write property test for counter consistency
    - **Property 5: Counter consistency**
    - **Validates: Requirements 5.1, 5.2, 5.3**

- [x] 7. Handle empty results and edge cases
  - [x] 7.1 Implement empty result handling
    - Display appropriate messaging when no passers match filters
    - Show "Showing 0 to 0 of 0 results" pagination text for empty results
    - Ensure UI remains stable with null or undefined passer_status_id values
    - _Requirements: 3.7, 4.3_

  - [x] 7.2 Write property test for empty result handling
    - **Property 3: Empty result handling**
    - **Validates: Requirements 3.7, 4.3**

- [ ] 8. Final integration and testing
  - [ ] 8.1 Verify complete feature integration
    - Test all filter combinations work together correctly
    - Verify default state shows all passers with "All Statuses" selected
    - Ensure reactive UI updates work without page reload
    - Test with various datasets including edge cases
    - _Requirements: 2.5, 3.5, 5.4_

  - [ ] 8.2 Write property test for reactive UI updates
    - **Property 6: Reactive UI updates**
    - **Validates: Requirements 5.4**

  - [ ] 8.3 Write integration tests for complete workflow
    - Test end-to-end user workflow from page load to filtered results
    - Test database integration with migration
    - Test component integration with existing filters
    - _Requirements: All requirements_

- [ ] 9. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- The implementation leverages existing Laravel + Vue.js + Inertia.js architecture
- Property tests validate universal correctness properties using Jest with fast-check
- Unit tests validate specific examples and edge cases
- Migration uses insertOrIgnore() to prevent duplicate record errors
- Frontend filtering logic extends existing computed property patterns
- All UI components follow existing design system and styling patterns

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2", "3.1"] },
    { "id": 2, "tasks": ["3.2", "4.1"] },
    { "id": 3, "tasks": ["4.2", "4.3", "5.1"] },
    { "id": 4, "tasks": ["5.2", "6.1"] },
    { "id": 5, "tasks": ["6.2", "7.1"] },
    { "id": 6, "tasks": ["7.2", "8.1"] },
    { "id": 7, "tasks": ["8.2", "8.3"] }
  ]
}
```