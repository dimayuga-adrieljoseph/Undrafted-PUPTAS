# Design Document

## Overview

This design describes the implementation approach for adding a bulk passer status selector to the Upload Passers page. The feature allows admins to choose a status (qualified, waitlisted, or unqualified) that gets applied to all records imported from an Excel file, overriding any per-row status column.

## Architecture

The implementation touches three layers of the existing upload flow:

1. **Frontend (Vue 3)** — Add a status dropdown to `Form.vue` and include the selected value in the form submission.
2. **Controller (Laravel)** — Add validation for the new `passer_status_id` field and pass it to the import class.
3. **Import Class (Laravel Excel)** — Accept the status parameter and apply it to every record, ignoring the Excel "status" column.

No new database tables, migrations, or routes are needed. The existing `passer_statuses` table already contains the three required status records.

## Components and Interfaces

### 1. Upload Form (Form.vue)

**Changes:**
- Add a reactive `passerStatus` ref initialized to `""` (empty/unselected).
- Add a `<select>` dropdown between the Batch/School Year grid and the File Upload section.
- Options: "Qualified" (value=1), "Waitlisted" (value=2), "Unqualified" (value=3).
- Add client-side validation in `submitForm()` to check that `passerStatus` is selected before submission.
- Append `passer_status_id` to the FormData payload.

**UI Layout:**
```
[Batch Number] [School Year]
[Passer Status ▼]
[File Upload Area]
[Upload Button]
```

### 2. TestPasserController@upload

**Changes:**
- Add `'passer_status_id' => 'required|integer|in:1,2,3'` to the validation rules.
- Pass the validated `passer_status_id` as a third constructor argument to `TestPassersImport`.
- Update the audit log message to include the selected status.

**Updated method signature flow:**
```php
$request->validate([
    'batch_number' => 'required|string',
    'school_year' => 'required|string',
    'file' => 'required|file|mimes:xlsx,xls,csv',
    'passer_status_id' => 'required|integer|in:1,2,3',
]);

$passerStatusId = $request->input('passer_status_id');
Excel::import(new TestPassersImport($batch, $schoolYear, $passerStatusId), $request->file('file'));
```

### 3. TestPassersImport

**Changes:**
- Add a `$passerStatusId` property and accept it as a third constructor parameter.
- In the `model()` method, remove the per-row status parsing logic (the `if (!empty($row['status']))` block).
- Use `$this->passerStatusId` directly when setting `passer_status_id` on both the `create()` and `updateOrCreate()` calls.

**Updated constructor:**
```php
public function __construct($batch, $schoolYear, $passerStatusId)
{
    $this->batch = $batch;
    $this->schoolYear = $schoolYear;
    $this->passerStatusId = $passerStatusId;
}
```

## Data Models

### Existing Models (No Changes Required)

**passer_statuses table:**
| id | status |
|----|--------|
| 1 | qualified |
| 2 | waitlisted |
| 3 | unqualified |

**test_passers table (relevant fields):**
| Column | Type | Description |
|--------|------|-------------|
| passer_status_id | FK (nullable) | References passer_statuses.id — set from bulk selection |

No schema changes are needed. The `passer_status_id` column already exists on `test_passers` and references the `passer_statuses` table.

## Data Flow

```
User selects status → Form.vue sends passer_status_id in FormData
→ TestPasserController validates passer_status_id (must be 1, 2, or 3)
→ TestPassersImport receives passer_status_id via constructor
→ Each row imported gets passer_status_id = user's selection (per-row status column ignored)
```

## Error Handling

| Scenario | Handling |
|----------|----------|
| No status selected on frontend | Client-side alert prevents submission |
| Invalid passer_status_id sent to backend | Laravel validation returns 422 with error message |
| Excel file has no "status" column | No impact — status comes from user selection |
| Excel file has a "status" column | Column is ignored; user selection takes precedence |

## Correctness Properties

### Property 1: Uniform Status Assignment
For every upload request with a valid `passer_status_id`, all TestPasser records created or updated during that import MUST have their `passer_status_id` set to the value provided in the request.
**Validates: Requirements 3.1, 3.3**

### Property 2: Override Guarantee
If the Excel file contains a "status" column, its values MUST NOT influence the `passer_status_id` of any imported record when a bulk status is provided.
**Validates: Requirements 3.2**

### Property 3: Validation Completeness
The system MUST reject any upload request where `passer_status_id` is missing, non-integer, or not in the set {1, 2, 3}.
**Validates: Requirements 2.2, 2.3**

### Property 4: Non-Interference
All other fields (surname, first_name, email, batch_number, school_year, pupcet_total_score, etc.) MUST remain unaffected by the addition of the bulk status feature.
**Validates: Requirements 4.1, 4.2, 4.3**

## Testing Strategy

- **Unit Test**: Verify `TestPassersImport` applies the constructor-provided `passerStatusId` to every record and ignores the Excel "status" column.
- **Integration Test**: Upload an Excel file via the controller with `passer_status_id=2` and assert all resulting `test_passers` records have `passer_status_id = 2`.
- **Frontend Test**: Verify the form prevents submission when no status is selected and includes `passer_status_id` in the FormData when selected.
- **Validation Test**: Send a POST request with invalid `passer_status_id` values (0, 4, "abc", null) and assert 422 responses.
