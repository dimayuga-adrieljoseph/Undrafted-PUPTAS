# Implementation Plan: Upload Passer Status

## Overview

Simplify the `TestPassersImport` class to read only 7 permitted Excel columns, remove legacy field handling (date parsing, multi-variant score resolution, 5 dropped columns), add range-validated score reading from `pupcet_score` only, and change the `middlename` column read to `middle_name`. The frontend and controller already implement the status dropdown and validation — no changes needed there.

## Tasks

- [x] 1. Refactor TestPassersImport to remove legacy code and reduce to 7 columns
  - [x] 1.1 Remove the `resolveScore()` method and `ExcelDate` import, add `resolvePupcetScore()` method
    - Remove `use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;` import statement
    - Delete the entire `resolveScore()` private method (multi-variant column resolution)
    - Add a new `resolvePupcetScore(array $row): ?float` method that reads only `$row['pupcet_score']`, returns null for empty/whitespace/non-numeric values, rounds valid numeric values to 2 decimal places, and returns null for values outside 0.00–9999.99
    - _Requirements: 4.3, 4.4, 4.5, 4.12, 6.1, 6.2, 6.3, 6.4, 6.5_

  - [x] 1.2 Remove date parsing logic and dropped column references from `model()` method
    - Remove the entire `$dateOfBirth` block (Excel serial date conversion and string date parsing)
    - Remove `date_of_birth`, `address`, `school_address`, `shs_school` (`school`), and `year_graduated` from both the `create()` and `updateOrCreate()` attribute arrays
    - _Requirements: 4.7, 4.8, 4.9, 4.10, 4.11, 4.13, 5.1, 5.2, 5.3, 5.4, 5.5_

  - [x] 1.3 Change `middlename` column read to `middle_name` and wire `resolvePupcetScore()`
    - Change `$row['middlename']` to `$row['middle_name']` in both `create()` and `updateOrCreate()` calls
    - Replace `$pupcetScore = $this->resolveScore($row)` with `$pupcetScore = $this->resolvePupcetScore($row)`
    - Ensure the `create()` path (no email) only sets: surname, first_name, middle_name, strand, reference_number, pupcet_total_score, batch_number, school_year, user_id (null), status ("pending"), passer_status_id
    - Ensure the `updateOrCreate()` path sets: surname, first_name, middle_name, strand, email, reference_number, pupcet_total_score, batch_number, school_year, user_id, status, passer_status_id
    - _Requirements: 4.1, 4.2, 5.6, 5.7_

- [x] 2. Checkpoint - Verify refactored import class
  - Ensure all tests pass, ask the user if questions arise.

- [x] 3. Write property-based tests for TestPassersImport
  - [x] 3.1 Set up test file with randomized data provider helpers
    - Create `tests/Unit/TestPassersImportTest.php` with PHPUnit test class
    - Add helper methods to generate random Excel row data using Faker (random strings for surname, firstname, middle_name, strand, email, reference_number; random numeric/non-numeric values for pupcet_score)
    - Set up database factories/migrations needed for test isolation (RefreshDatabase trait)
    - _Requirements: All_

  - [ ]* 3.2 Write property test for bulk status application (Property 1)
    - **Property 1: Bulk status application**
    - For 100 iterations: generate random valid rows and a random valid passer_status_id (1, 2, or 3), run import, assert every created/updated TestPasser record has the request-level passer_status_id regardless of any "status" column in the row data
    - **Validates: Requirements 3.1, 3.2, 3.3**

  - [ ]* 3.3 Write property test for column mapping correctness (Property 2)
    - **Property 2: Column mapping correctness**
    - For 100 iterations: generate random values for the 7 permitted columns plus extra columns, run import, assert the TestPasser record fields match the expected mapping (surname→surname, firstname→first_name, middle_name→middle_name, strand→strand, email→email, reference_number→reference_number) and extra columns do not affect the record
    - **Validates: Requirements 4.1, 4.2**

  - [ ]* 3.4 Write property test for removed fields exclusion (Property 3)
    - **Property 3: Removed fields exclusion**
    - For 100 iterations: generate rows that include values for date_of_birth, address, school_address, school, year_graduated columns, run import, assert the resulting TestPasser record does not have those fields set by the import (they remain null or unchanged)
    - **Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5**

  - [ ]* 3.5 Write property test for score validation and storage (Property 4)
    - **Property 4: Score validation and storage**
    - For 100 iterations: generate random pupcet_score values (valid numerics in range, out-of-range numerics, non-numeric strings, empty, whitespace, null), run import, assert pupcet_total_score is round(value, 2) for valid in-range numerics and null otherwise
    - **Validates: Requirements 6.1, 6.2, 6.3, 6.4, 4.3, 4.4**

  - [ ]* 3.6 Write property test for empty firstname skips row (Property 5)
    - **Property 5: Empty firstname skips row**
    - For 100 iterations: generate rows with firstname as null, empty string, or random whitespace-only strings, run import, assert no TestPasser record is created for those rows
    - **Validates: Requirements 4.6, 5.8, 7.6**

  - [ ]* 3.7 Write property test for user linking by email (Property 6)
    - **Property 6: User linking by email**
    - For 100 iterations: generate rows with random emails, create User records for some of those emails, run import, assert TestPasser records with matching emails have user_id set and status="registered", and non-matching emails have user_id=null and status="pending"
    - **Validates: Requirements 7.3, 7.5**

  - [ ]* 3.8 Write property test for persistence strategy by email presence (Property 7)
    - **Property 7: Persistence strategy by email presence**
    - For 100 iterations: import rows with the same email multiple times, assert only one TestPasser record exists per email (updateOrCreate). Import rows with empty email multiple times, assert each creates a new record (create behavior)
    - **Validates: Requirements 7.7, 7.8**

  - [ ]* 3.9 Write property test for controller validation rejects invalid passer_status_id (Property 8)
    - **Property 8: Controller validation rejects invalid passer_status_id**
    - For 100 iterations: send upload requests with random invalid passer_status_id values (null, strings, floats, integers outside 1-3, missing field), assert 422 response and no file processing occurs
    - **Validates: Requirements 2.2, 2.3, 3.4**

  - [ ]* 3.10 Write property test for applicant profile student_number update (Property 9)
    - **Property 9: Applicant profile student_number update**
    - For 100 iterations: create Users with applicantProfiles, generate rows with matching emails and random reference_numbers, run import, assert the applicantProfile's student_number is updated to the row's reference_number
    - **Validates: Requirements 7.4**

- [x] 4. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- The frontend (Form.vue) and controller (TestPasserController) already implement the status dropdown and validation — no code changes needed there
- The primary implementation work is entirely in `app/Imports/TestPassersImport.php`
- Property tests use PHPUnit with Faker-based randomized data providers (100 iterations each)
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2"] },
    { "id": 2, "tasks": ["1.3"] },
    { "id": 3, "tasks": ["3.1"] },
    { "id": 4, "tasks": ["3.2", "3.3", "3.4", "3.5", "3.6", "3.7", "3.8", "3.9", "3.10"] }
  ]
}
```
