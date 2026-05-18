# Implementation Plan: Upload Passer Status

## Overview

Add a bulk passer status selector to the Upload Passers page so admins can assign a uniform status (qualified, waitlisted, or unqualified) to all records imported from an Excel file.

## Tasks

- [ ] 1. Add a reactive `passerStatus` ref and Status Selector dropdown to Form.vue between the Batch/School Year grid and the File Upload section, with options: "Qualified" (value=1), "Waitlisted" (value=2), "Unqualified" (value=3), and a disabled placeholder.
- [ ] 2. Add client-side validation in `submitForm()` to check that `passerStatus` has been selected before allowing submission, displaying an alert if not selected.
- [ ] 3. Append `passer_status_id` (with the value of `passerStatus`) to the FormData in `submitForm()` before the POST request.
- [ ] 4. Add `'passer_status_id' => 'required|integer|in:1,2,3'` to the validation rules in the TestPasserController `upload()` method.
- [ ] 5. Retrieve the validated `passer_status_id` from the request and pass it as the third argument to the `TestPassersImport` constructor in TestPasserController.
- [ ] 6. Add a `protected $passerStatusId` property to TestPassersImport and accept it as the third parameter in the constructor.
- [ ] 7. Remove the per-row status parsing logic (the `if (!empty($row['status']))` block) from TestPassersImport and use `$this->passerStatusId` for the `passer_status_id` field in both `create()` and `updateOrCreate()` calls.
- [ ] 8. Update the audit log message in TestPasserController to include the selected passer status.
- [ ] 9. Verify that the upload form displays the status dropdown and prevents submission without a selection.
- [ ] 10. Verify that uploading an Excel file with "Qualified" selected sets all imported records' `passer_status_id` to 1.
- [ ] 11. Verify that any per-row "status" column in the Excel file is ignored when a bulk status is provided.
- [ ] 12. Verify that existing fields (batch_number, school_year, other Excel columns) continue to function correctly.

## Task Dependency Graph

```json
{
  "waves": [
    ["1", "4", "6"],
    ["2", "3", "5", "7", "8"],
    ["9", "10", "11", "12"]
  ]
}
```

## Notes

- Tasks 1, 4, and 6 can be done in parallel (frontend dropdown, controller validation, import class property).
- Tasks 2, 3, 5, 7, and 8 depend on their respective wave 1 tasks being complete.
- Tasks 9-12 are verification tasks that depend on all implementation tasks being complete.
- No new database migrations or routes are needed — the existing `passer_statuses` table and `passer_status_id` column are already in place.
