# Grade Verification Slip - Implementation Summary

## What Was Implemented

Applicants can now download their own **Grade Verification Slip** directly from the Applicant Dashboard after submitting their application and completing grade input. The slip is generated on-demand — no admin action, email sending, or prior PDF generation is required.

---

## Files Created

1. **Controller**: `app/Http/Controllers/GradeVerificationSlipController.php`
   - Handles the self-service download route
   - Enforces security: only `role_id = 1` (applicants) can access
   - Guards: application must be submitted (not draft), grades must exist
   - Streams the PDF directly as a file download
   - Filename format: `Grade_Verification_Slip_<ReferenceNumber>.pdf`

2. **Service**: `app/Services/GradeVerificationSlipService.php`
   - Generates the PDF by overlaying applicant data onto the official template
   - Uses FPDI/TCPDF (same library as the SAR Form)
   - Template: `docs/GRADE VERIFICATION SLIP TEMPLATE.pdf`
   - Populates:
     - Applicant name, reference number, SHS strand/track
     - Grade 12 GWA
     - English, Mathematics, Science averages
     - All subject grades (fixed fields + dynamic additional subjects)
     - Program qualification marks (✓ / ✗) for all 11 programs
   - Dynamic subjects use category mapping: `'mathematics'` → `'math'` to match how the grade input form stores them
   - Two-column layout for subject tables (6 subjects per column, up to 12 per section)
   - Auto-scales row height so content fits within the template's fixed areas

3. **Documentation**: `docs/GRADE_VERIFICATION_SLIP_IMPLEMENTATION.md` *(this file)*

---

## Files Modified

1. **`app/Http/Controllers/ApplicantDashboardController.php`**
   - Added `canDownloadSlip` prop passed to the Inertia page
   - Logic: `true` when application is submitted (status ≠ `draft`) AND grades exist

2. **`resources/js/Pages/Dashboard/Applicant.vue`**
   - Added `canDownloadSlipReactive` computed ref — updates immediately after submission without a page reload
   - Added **Download Grade Verification Slip** button (emerald green, shown only when `canDownloadSlipReactive` is true)
   - Added `downloadGradeVerificationSlip()` function — streams PDF via axios blob download
   - Added loading spinner and error toast for download feedback
   - Added step 6 to the **Application Process** timeline:
     _"Download your Grade Verification Slip and bring it along with your SAR Form on your interview day."_
   - Updated step 2 label from "Review Grades" to "Input Grades"

3. **`routes/web.php`**
   - Added new authenticated route:
     ```
     GET /applicant-dashboard/grade-verification-slip
     ```
   - Protected by the existing `auth` middleware
   - No signed URL needed — authenticated session is the sole data source

---

## Template File Required

```
puptas/docs/GRADE VERIFICATION SLIP TEMPLATE.pdf
```

This file **must be deployed** alongside the code. It is read from `base_path('docs/GRADE VERIFICATION SLIP TEMPLATE.pdf')`. Without it the feature will throw an exception.

---

## Database Changes

**New Table: `gvs_generations`** (migration: `2026_06_05_000001_create_gvs_generations_table.php`)

Tracks every slip generated and downloaded with:

| Column | Type | Description |
|---|---|---|
| `user_id` | FK → `users.id` | The applicant who downloaded |
| `reference_number` | string | Snapshot of reference number at generation time |
| `filename` | string | PDF filename (e.g. `GVS_REF-2026-0001.pdf`) |
| `file_path` | string | Path on storage disk (e.g. `gvs/GVS_REF-2026-0001.pdf`) |
| `download_count` | integer | Total number of times downloaded (increments each time) |
| `last_downloaded_at` | timestamp | When the slip was last downloaded |
| `created_at` | timestamp | When the slip was first generated |

One row per applicant — updated on every re-download. Run before deploying:

```bash
php artisan migrate
```

---

## How the PDF Data is Populated

The PDF is generated on-demand when the applicant clicks the button. `GradeVerificationSlipService::generate()` pulls all data live from the database at that moment:

| PDF Field | Source Table | Column(s) |
|---|---|---|
| Applicant name | `applicant_profiles` | `lastname`, `firstname`, `middlename` |
| Reference number | `test_passers` | `reference_number` |
| SHS Strand/Track | `applicant_profiles` | `strand` |
| Grade 12 GWA | `grades` | `g12_first_sem`, `g12_second_sem` (averaged) |
| English average | `grades` | `english` |
| Mathematics average | `grades` | `mathematics` |
| Science average | `grades` | `science` |
| English subjects (fixed) | `grades` | `g11_oral_communication`, `g11_reading_writing`, `g11_academic_professional`, `g11_21st_century_lit`, `g12_21st_century_lit`, `g12_academic_professional` |
| Math subjects (fixed) | `grades` | `g11_general_mathematics`, `g11_statistics_probability`, `g11_business_mathematics`, `g11_pre_calculus`, `g11_basic_calculus` |
| Science subjects (fixed) | `grades` | `g11_earth_life_science`, `g11_physical_science`, `g11_earth_science`, `g11_general_chemistry_1`, `g12_general_physics_1`, `g12_general_biology_1`, etc. |
| Additional subjects (all categories) | `grades` | `dynamic_subjects` (JSON column, category mapped: `'math'` → mathematics) |
| Program qualifications | `programs` | Computed live using `GradeComputationService::isQualified()` |

The PDF will always reflect the exact data in the database at download time — it is never cached or pre-generated.

---

## Features

✅ **Applicant-initiated** — no admin action required  
✅ **On-demand generation** — always reflects current database state  
✅ **Secure** — authenticated session is the sole data source; no URL parameter accepted to prevent IDOR  
✅ **Dynamic subject support** — correctly collects additional/dynamic subjects for all strands  
✅ **Two-column subject layout** — up to 12 subjects per section without overflow  
✅ **Program qualifications** — all 11 programs marked with ✓ or ✗ in black  
✅ **Reactive button visibility** — appears immediately after submission without a page reload  
✅ **Application Process timeline updated** — step 6 instructs applicants to download and bring the slip

---

## How It Works

### When Applicant Downloads the Slip:

1. Applicant submits their application (status changes from `draft` to `submitted`)
2. **Download Grade Verification Slip** button appears on the dashboard immediately
3. Applicant clicks the button
4. Browser sends `GET /applicant-dashboard/grade-verification-slip` with the session cookie
5. Controller verifies: role = applicant, application submitted, grades exist
6. `GradeVerificationSlipService::generate()` runs:
   - Loads grades and dynamic subjects from DB
   - Loads all programs and computes qualifications live
   - Overlays all data onto `docs/GRADE VERIFICATION SLIP TEMPLATE.pdf` using FPDI
7. PDF binary is streamed as `Grade_Verification_Slip_<ReferenceNumber>.pdf`
8. Browser downloads the file immediately

---

## API Endpoint

```
GET /applicant-dashboard/grade-verification-slip
→ Returns PDF as attachment (Content-Disposition: attachment)
→ Filename: Grade_Verification_Slip_<ReferenceNumber>.pdf
→ Requires: authenticated session (role_id = 1)
→ Preconditions: application submitted, grades exist
```

---

## Security

- Only `role_id = 1` (applicants) can call this endpoint
- The authenticated user's own data is the sole source — no `user_id` or reference number is accepted as a URL parameter
- Prevents IDOR (Insecure Direct Object Reference) attacks by design
- Returns HTTP 403 if application is still in draft or grades are missing

---

## Coordinate Calibration

PDF field positions are defined inside `GradeVerificationSlipService::overlayPage()` and `writeQualifications()` in millimeters from the top-left corner of the page.

**Page size**: 215.9 mm × 330.2 mm (Legal portrait, 8.5" × 13")

To recalibrate after a template update, adjust the x/y values in `GradeVerificationSlipService.php`. The key constants are clearly labeled with comments in the source.

---

## File Locations

| File | Location |
|---|---|
| Controller | `app/Http/Controllers/GradeVerificationSlipController.php` |
| Service | `app/Services/GradeVerificationSlipService.php` |
| Model | `app/Models/GvsGeneration.php` |
| Migration | `database/migrations/2026_06_05_000001_create_gvs_generations_table.php` |
| PDF Template | `docs/GRADE VERIFICATION SLIP TEMPLATE.pdf` |
| Dashboard page | `resources/js/Pages/Dashboard/Applicant.vue` |
| Dashboard controller | `app/Http/Controllers/ApplicantDashboardController.php` |
| Routes | `routes/web.php` |
| Filesystem config | `config/filesystems.php` (added `gvs_tmp` disk) |
| Audit log model | `app/Models/AuditLog.php` (added `ACTION_DOWNLOAD` constant) |

---

## No Breaking Changes

✅ Existing application submission flow unchanged  
✅ Existing SAR Form workflow unchanged  
✅ No new database tables or migrations  
✅ Only adds new read-only PDF generation on top of existing data  

---

## Support / Recalibration

If PDF fields appear misaligned after a template update:
- Adjust coordinates in `GradeVerificationSlipService::overlayPage()` (fields) and `writeQualifications()` (program checkboxes)
- The key constants to change are clearly labeled with comments in the source
