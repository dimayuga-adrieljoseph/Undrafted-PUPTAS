# Applicant Flow Fix — Bugfix Design

## Overview

Three bugs break the applicant post-login flow and grade input feature:

1. **Login redirect bug** — `AuthenticatedSessionController::toResponse` sends applicants with a strand and no grades directly to the grade input page on every login, bypassing the dashboard.
2. **Review Grades hidden bug** — `FileMapper::formatFilesForGraduateType` returns 13 null-placeholder slots for unknown/null graduate types, causing `allDocumentsUploaded` on the frontend to always be `false`, permanently hiding the "Review Grades" button.
3. **Grade autofill missing bug** — Clicking "Review Grades" on the dashboard navigates directly to the grade input page without calling `GradeExtractionController::extract`, so grade fields are never autofilled from uploaded documents.

The fix strategy is minimal and targeted: change the login redirect to always go to `/applicant-dashboard`, change the unknown-graduate-type fallback to return `[]` instead of null-filled slots, and wire the "Review Grades" button to call `POST /api/grades/extract` before navigating (this is already partially implemented in the frontend but the backend route/wiring needs to be confirmed).

## Glossary

- **Bug_Condition (C)**: The set of inputs/states that trigger a defective code path
- **Property (P)**: The correct observable behavior that must hold for all C(X) inputs after the fix
- **Preservation**: Behaviors that must remain identical for all ¬C(X) inputs
- **`AuthenticatedSessionController`**: `app/Http/Controllers/AuthenticatedSessionController.php` — implements `LoginResponse`, decides where to redirect after login
- **`FileMapper::formatFilesForGraduateType`**: `app/Helpers/FileMapper.php` — maps uploaded files to API keys for a given graduate type; returns null-placeholder array for unknown types
- **`allDocumentsUploaded`**: Computed property in `Applicant.vue` — `true` when every slot in `fileStatuses` has a non-null `url`
- **`GradeExtractionController::extract`**: `app/Http/Controllers/GradeExtractionController.php` — calls `GradeExtractionService`, stores result in session, returns redirect URL
- **`triggerExtraction`**: Function in `Applicant.vue` — POSTs to `/api/grades/extract` then navigates to the returned URL
- **`extractionResult`**: Inertia prop passed by `GradesController` show methods, populated from `session()->pull('extraction_result')`

## Bug Details

### Bug 1 — Login Redirect

The bug manifests when an applicant (role_id = 1) with a strand set and no grades record logs in. `AuthenticatedSessionController::toResponse` checks `!$hasGrades`, then checks `$strand`, and if a recognized strand is found it redirects to the grade input page instead of the dashboard.

**Formal Specification:**
```
FUNCTION isBugCondition_1(user)
  INPUT: user — authenticated User model
  OUTPUT: boolean

  profile  := ApplicantProfile WHERE user_id = user.id
  strand   := profile.strand
  hasGrades := Grade EXISTS WHERE user_id = user.id

  RETURN user.role_id = 1
         AND hasGrades = false
         AND strand IN ['ABM','ICT','HUMSS','GAS','STEM','TVL']
END FUNCTION
```

**Examples:**
- Applicant with strand = "STEM", no grades → redirected to `/grades/stem` ✗ (should go to `/applicant-dashboard`)
- Applicant with strand = "ABM", no grades, second login → still redirected to `/grades/abm` ✗
- Applicant with strand = null, no grades → correctly goes to `/applicant-dashboard` ✓ (not a bug)
- Applicant with grades already saved → correctly goes to `/applicant-dashboard` ✓ (not a bug)

### Bug 2 — Review Grades Button Hidden

The bug manifests when an applicant's `graduate_type` is `null` or not one of the three recognized values (`'Senior High School of A.Y. 2025-2026'`, `'Senior High School of Past School Years'`, `'Alternative Learning System'`). `FileMapper::formatFilesForGraduateType` falls into the `$requiredKeys === null` branch and returns `array_fill_keys(array_keys(self::MAPPING), null)` — 13 null slots. The frontend `allDocumentsUploaded` computed property sees 13 entries all with `url = null` and evaluates to `false`.

**Formal Specification:**
```
FUNCTION isBugCondition_2(graduateType)
  INPUT: graduateType — string|null from applicant profile
  OUTPUT: boolean

  knownTypes := [
    'Senior High School of A.Y. 2025-2026',
    'Senior High School of Past School Years',
    'Alternative Learning System'
  ]

  RETURN graduateType NOT IN knownTypes
END FUNCTION
```

**Examples:**
- `graduate_type = null` → returns 13 null slots → "Review Grades" hidden ✗
- `graduate_type = 'Other'` → returns 13 null slots → "Review Grades" hidden ✗
- `graduate_type = 'Senior High School of A.Y. 2025-2026'` with all files uploaded → correctly shows button ✓

### Bug 3 — Grade Autofill Not Triggered

The bug manifests when an applicant clicks "Review Grades". The frontend `triggerExtraction` function in `Applicant.vue` already calls `POST /api/grades/extract` and navigates to the returned URL — this code exists. However, the route `POST /api/grades/extract` must be registered and point to `GradeExtractionController::extract`. If the route is missing or the session-based handoff is broken, the grade input page receives `extractionResult = null` and fields are empty.

**Formal Specification:**
```
FUNCTION isBugCondition_3(event)
  INPUT: event — "Review Grades" button click
  OUTPUT: boolean

  RETURN gradeExtractionNotTriggered(event)
         OR extractionResultNotInSession()
         OR gradeInputPageReceivesNullExtractionResult()
END FUNCTION
```

**Examples:**
- Applicant clicks "Review Grades" → grade fields are empty, no autofill ✗
- Applicant clicks "Review Grades" after fix → fields pre-populated from AI extraction, remain editable ✓

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Non-applicant users (role_id 2–6) must continue to be redirected to their respective dashboards on login
- Applicants with grades already saved must continue to go to `/applicant-dashboard` on login
- Applicants with a recognized graduate type and all required files uploaded must continue to see the "Review Grades" button and have `allDocumentsUploaded = true`
- Applicants with a recognized graduate type and missing files must continue to see `allDocumentsUploaded = false`
- Session invalidation and redirect to `/` on logout must remain unchanged
- Grade form submission (validation, save, redirect to dashboard) must remain unchanged
- Direct navigation to grade input URLs (e.g. `/grades/stem`) must remain accessible

**Scope:**
All inputs that do NOT match the three bug conditions above are unaffected by this fix. Specifically:
- Login for non-applicant roles
- Login for applicants who already have grades
- File upload and document management flows
- Grade form submission flows
- All admin/evaluator/interviewer/records flows

## Hypothesized Root Cause

### Bug 1 — Login Redirect
The `toResponse` method was written to fast-track applicants to grade input when they have a strand but no grades. This was likely an early UX shortcut that was never removed. The fix is to delete the strand-check branch entirely and always redirect applicants to `/applicant-dashboard`.

### Bug 2 — Review Grades Hidden
The `formatFilesForGraduateType` method has a defensive fallback for unknown graduate types that returns all 13 MAPPING keys as null. The intent was to prevent the frontend from treating an empty array as "all documents uploaded". However, the frontend `allDocumentsUploaded` check (`values.every(f => f?.url != null)`) treats any non-empty array with all-null values as "not uploaded", which is the opposite of the desired behavior for unknown types. The fix is to return `[]` (empty object) for unknown types so `values.length > 0` is false and the button visibility is controlled by other logic, or alternatively to not include the unknown-type applicant in the document upload flow at all.

### Bug 3 — Grade Autofill Not Triggered
The frontend `triggerExtraction` function is already implemented in `Applicant.vue`. The most likely root cause is that the API route `POST /api/grades/extract` is not registered in the routes file, or the `GradeExtractionController` is not bound to it. A secondary possibility is that the session handoff works but the `extractionResult` prop is not being read correctly by the grade input Vue components.

## Correctness Properties

Property 1: Bug Condition — Applicant Login Always Redirects to Dashboard

_For any_ authenticated user where `isBugCondition_1` holds (role_id = 1, no grades, recognized strand), the fixed `AuthenticatedSessionController::toResponse` SHALL redirect to `/applicant-dashboard` instead of a grade input URL.

**Validates: Requirements 2.1**

Property 2: Bug Condition — Unknown Graduate Type Returns Empty uploadedFiles

_For any_ graduate type where `isBugCondition_2` holds (null or unrecognized value), the fixed `FileMapper::formatFilesForGraduateType` SHALL return an empty array `[]` so that `allDocumentsUploaded` evaluates to `false` due to `values.length === 0`, not due to null-filled slots.

**Validates: Requirements 2.2**

Property 3: Bug Condition — Review Grades Triggers AI Extraction

_For any_ "Review Grades" button click where `isBugCondition_3` holds, the fixed flow SHALL call `GradeExtractionController::extract`, store the result in the session, and navigate to the grade input page where `extractionResult` is passed as a non-null Inertia prop with pre-populated grade values in editable fields.

**Validates: Requirements 2.3, 2.4**

Property 4: Preservation — Non-Applicant Login Redirects Unchanged

_For any_ user where `user.role_id != 1`, the fixed `toResponse` SHALL produce the same redirect as the original function, preserving all role-based routing.

**Validates: Requirements 3.1**

Property 5: Preservation — Known Graduate Type File Mapping Unchanged

_For any_ graduate type where `isBugCondition_2` does NOT hold (recognized value), the fixed `formatFilesForGraduateType` SHALL return the same result as the original function, preserving correct file slot mapping and `allDocumentsUploaded` evaluation.

**Validates: Requirements 3.3**

## Fix Implementation

### Changes Required

**File 1**: `app/Http/Controllers/AuthenticatedSessionController.php`

**Function**: `toResponse`

**Specific Changes**:
1. Remove the `!$hasGrades` branch that checks for strand and redirects to grade input pages
2. For role_id = 1, always `return redirect('/applicant-dashboard')`
3. The `Grade` and `ApplicantProfile` imports can be removed if no longer used

---

**File 2**: `app/Helpers/FileMapper.php`

**Function**: `formatFilesForGraduateType`

**Specific Changes**:
1. Change the unknown-type fallback from `array_fill_keys(array_keys(self::MAPPING), null)` to `[]` (empty array)
2. Update the comment to reflect the new intent: unknown type means no documents are required yet, so return empty

---

**File 3**: Routes file (likely `routes/api.php`)

**Specific Changes**:
1. Verify or add `Route::post('/grades/extract', [GradeExtractionController::class, 'extract'])` inside the authenticated middleware group
2. Ensure the route is accessible to applicants (role_id = 1)

## Testing Strategy

### Validation Approach

Two-phase approach: first surface counterexamples on unfixed code to confirm root causes, then verify the fix and run preservation checks.

### Exploratory Bug Condition Checking

**Goal**: Confirm the three root causes before implementing fixes.

**Test Plan**: Write feature/unit tests that exercise each bug condition on the unfixed code and assert the defective behavior.

**Test Cases**:
1. **Login redirect test**: Log in as applicant with strand="STEM" and no grades → assert response redirects to `/grades/stem` (confirms bug 1 on unfixed code)
2. **FileMapper null-type test**: Call `FileMapper::formatFilesForGraduateType(collect([]), null)` → assert result has 13 keys all null (confirms bug 2 on unfixed code)
3. **FileMapper unknown-type test**: Call `FileMapper::formatFilesForGraduateType(collect([]), 'Unknown Type')` → assert result has 13 keys all null (confirms bug 2 variant)
4. **Grade extraction route test**: POST to `/api/grades/extract` as authenticated applicant → assert 200 or check route exists (confirms bug 3 root cause)

**Expected Counterexamples**:
- Login redirects to `/grades/stem` instead of `/applicant-dashboard`
- `formatFilesForGraduateType` returns 13-key null array for unknown type
- `/api/grades/extract` route may return 404 if not registered

### Fix Checking

**Goal**: Verify all three bug conditions produce correct behavior after the fix.

**Pseudocode:**
```
FOR ALL user WHERE isBugCondition_1(user) DO
  result := toResponse_fixed(user)
  ASSERT result.redirectUrl = '/applicant-dashboard'
END FOR

FOR ALL graduateType WHERE isBugCondition_2(graduateType) DO
  result := formatFilesForGraduateType_fixed(emptyCollection, graduateType)
  ASSERT result = []
END FOR

FOR ALL clickEvent WHERE isBugCondition_3(clickEvent) DO
  result := triggerExtraction_fixed(clickEvent)
  ASSERT extractionWasTriggered = true
  ASSERT gradeInputPage.extractionResult != null
END FOR
```

### Preservation Checking

**Goal**: Verify ¬C(X) inputs produce identical results before and after the fix.

**Pseudocode:**
```
FOR ALL user WHERE NOT isBugCondition_1(user) DO
  ASSERT toResponse_original(user) = toResponse_fixed(user)
END FOR

FOR ALL graduateType WHERE NOT isBugCondition_2(graduateType) DO
  ASSERT formatFilesForGraduateType_original(files, graduateType)
       = formatFilesForGraduateType_fixed(files, graduateType)
END FOR
```

**Testing Approach**: Property-based testing is recommended for `formatFilesForGraduateType` preservation because the three recognized graduate types each have different required file sets, and we want to verify all combinations of uploaded/missing files produce the same output before and after the fix.

**Test Cases**:
1. **Non-applicant login preservation**: Log in as role_id=2 (admin) → assert redirect to `/dashboard` unchanged
2. **Applicant with grades preservation**: Log in as applicant with existing grades → assert redirect to `/applicant-dashboard` unchanged
3. **Known graduate type file mapping preservation**: Call `formatFilesForGraduateType` with each of the three recognized types and various file combinations → assert output matches original
4. **Grade form submission preservation**: Submit grade form → assert validation, save, and redirect to dashboard unchanged

### Unit Tests

- Test `AuthenticatedSessionController::toResponse` for all role_id values and applicant states
- Test `FileMapper::formatFilesForGraduateType` for null, unknown, and all three recognized graduate types
- Test `GradeExtractionController::extract` returns correct JSON structure with redirect URL

### Property-Based Tests

- Generate random applicant states (strand, hasGrades, role_id) and verify login always redirects applicants to `/applicant-dashboard`
- Generate random graduate type strings and verify only the three recognized types return non-empty file maps
- Generate random file collections for recognized graduate types and verify file mapping output is identical before and after the fix

### Integration Tests

- Full login flow: applicant with strand + no grades → lands on dashboard, not grade input page
- Full "Review Grades" flow: all documents uploaded → button visible → click → extraction triggered → grade input page shows autofilled editable fields
- Regression: non-applicant login flows unaffected
- Regression: grade form submission still saves and redirects correctly
