# Implementation Plan

- [x] 1. Write bug condition exploration tests
  - **Property 1: Bug Condition** - Applicant Login Redirect, Unknown Graduate Type, Missing Extract Route
  - **CRITICAL**: These tests MUST FAIL on unfixed code — failure confirms the bugs exist
  - **DO NOT attempt to fix the tests or the code when they fail**
  - **NOTE**: These tests encode the expected behavior — they will validate the fix when they pass after implementation
  - **GOAL**: Surface counterexamples that demonstrate each bug exists
  - **Scoped PBT Approach**: Scope each property to the concrete failing cases for reproducibility
  - Bug 1 — Login redirect: log in as applicant with strand="STEM" and no grades record; assert response redirects to `/applicant-dashboard` (will fail — currently redirects to `/grades/stem`)
  - Bug 2 — Unknown graduate type: call `FileMapper::formatFilesForGraduateType(collect([]), null)` and `formatFilesForGraduateType(collect([]), 'Unknown Type')`; assert result equals `[]` (will fail — currently returns 13-key null array)
  - Bug 3 — Missing extract route: POST to `/api/grades/extract` as authenticated applicant; assert HTTP 200 (will fail — route is not registered, returns 404)
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: All three tests FAIL (this is correct — it proves the bugs exist)
  - Document counterexamples found (e.g., "login redirects to /grades/stem", "formatFilesForGraduateType returns 13 null slots", "POST /api/grades/extract returns 404")
  - Mark task complete when tests are written, run, and failures are documented
  - _Requirements: 1.1, 1.2, 1.4_

- [x] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Non-Applicant Login Redirects, Known Graduate Type File Mapping
  - **IMPORTANT**: Follow observation-first methodology — observe behavior on UNFIXED code first
  - Observe: log in as role_id=2 (admin) → redirects to `/dashboard`
  - Observe: log in as applicant with existing grades → redirects to `/applicant-dashboard`
  - Observe: `formatFilesForGraduateType(files, 'Senior High School of A.Y. 2025-2026')` returns correct 6-key array
  - Observe: `formatFilesForGraduateType(files, 'Alternative Learning System')` returns correct 4-key array
  - Write property-based test: for all role_id values 2–6, login redirects to the same role dashboard as before the fix
  - Write property-based test: for all three recognized graduate types with any combination of uploaded/missing files, `formatFilesForGraduateType` returns the same result as before the fix
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.3, 3.4_

- [x] 3. Fix applicant post-login flow and grade autofill

  - [x] 3.1 Fix login redirect in AuthenticatedSessionController
    - In `toResponse`, remove the `!$hasGrades` branch that checks for strand and redirects to grade input pages
    - For role_id = 1, always `return redirect('/applicant-dashboard')` directly
    - Remove unused `Grade` and `ApplicantProfile` imports if no longer referenced
    - _Bug_Condition: isBugCondition_1(user) — role_id=1, no grades record, strand IN ['ABM','ICT','HUMSS','GAS','STEM','TVL']_
    - _Expected_Behavior: toResponse always returns redirect('/applicant-dashboard') for role_id=1_
    - _Preservation: role_id 2–6 redirect logic (match block) must remain unchanged_
    - _Requirements: 2.1, 3.1, 3.4_

  - [x] 3.2 Fix unknown graduate type fallback in FileMapper
    - In `formatFilesForGraduateType`, change the `$requiredKeys === null` branch to `return []` instead of `return array_fill_keys(array_keys(self::MAPPING), null)`
    - Update the inline comment to reflect the new intent: unknown type means no documents are required yet, return empty array
    - _Bug_Condition: isBugCondition_2(graduateType) — graduateType NOT IN the three recognized values_
    - _Expected_Behavior: formatFilesForGraduateType returns [] for unknown/null graduate types_
    - _Preservation: recognized graduate types ('Senior High School of A.Y. 2025-2026', 'Senior High School of Past School Years', 'Alternative Learning System') must return the same file slot arrays as before_
    - _Requirements: 2.2, 3.3_

  - [x] 3.3 Register POST /api/grades/extract route in routes/api.php
    - Add `Route::post('/grades/extract', [GradeExtractionController::class, 'extract'])` inside the `auth:sanctum` middleware group
    - Verify `GradeExtractionController` is already imported at the top of `api.php` (it is)
    - Confirm the route is accessible to applicants (role_id=1) — no additional role middleware needed beyond `auth:sanctum`
    - _Bug_Condition: isBugCondition_3 — POST /api/grades/extract returns 404 because route is not registered_
    - _Expected_Behavior: POST /api/grades/extract calls GradeExtractionController::extract, stores extraction_result in session, returns JSON with redirect URL_
    - _Preservation: existing /api/extract-grades (Tesseract OCR) route must remain unchanged_
    - _Requirements: 2.4_

  - [x] 3.4 Verify session handoff to grade input page
    - Confirm `GradeExtractionController::extract` stores result via `$request->session()->put('extraction_result', $result)`
    - Confirm each `GradesController` show method passes `'extractionResult' => session()->pull('extraction_result')` as an Inertia prop (already implemented — verify it is wired correctly)
    - Confirm the grade input Vue components (ABMGradeInput, ICTGradeInput, etc.) read the `extractionResult` prop and autofill the editable fields
    - _Bug_Condition: isBugCondition_3 — extractionResult prop is null on grade input page after clicking "Review Grades"_
    - _Expected_Behavior: grade input page receives non-null extractionResult with pre-populated grade values in editable fields_
    - _Requirements: 2.4_

  - [x] 3.5 Verify bug condition exploration tests now pass
    - **Property 1: Expected Behavior** - Applicant Login Redirect, Unknown Graduate Type, Extract Route
    - **IMPORTANT**: Re-run the SAME tests from task 1 — do NOT write new tests
    - Run all three bug condition tests from step 1
    - **EXPECTED OUTCOME**: All three tests PASS (confirms all three bugs are fixed)
    - _Requirements: 2.1, 2.2, 2.4_

  - [x] 3.6 Verify preservation tests still pass
    - **Property 2: Preservation** - Non-Applicant Login Redirects, Known Graduate Type File Mapping
    - **IMPORTANT**: Re-run the SAME tests from task 2 — do NOT write new tests
    - Run all preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm non-applicant login redirects are unchanged
    - Confirm recognized graduate type file mapping is unchanged

- [x] 4. Checkpoint — Ensure all tests pass
  - Run the full test suite and confirm all tests pass
  - Verify the three bug condition tests pass (bugs are fixed)
  - Verify the preservation tests pass (no regressions)
  - Ask the user if any questions arise
