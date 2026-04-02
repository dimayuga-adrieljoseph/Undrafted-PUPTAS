# External Medical API Guide

## Overview
This guide documents the External Medical API designed to allow an external medical system to retrieve applicant data for medical examinations. This integration is **READ-ONLY** and strictly enforces that applicants must have completed both evaluation and interview stages.

## Implemented Scope
- **Endpoints:**
  - `GET /api/v1/medical/applicants` (Deprecated: returns 410 Gone)
  - `GET /api/v1/medical/applicants/idp/{idpUserId}` (Lookup via IDP User ID)
- **Authentication:** Dedicated Bearer token (`EXTERNAL_MEDICAL_API_TOKEN`)
- **Data Scope:** 
  - Only applicants who have **completed** the `evaluator` stage (Passed/Transferred).
  - Only applicants who have **completed** the `interviewer` stage (Passed/Transferred).
  - Only applicants currently in the `medical` stage (`in_progress` or `returned`).
- **Audit Visibility:** All API access attempts (success and failure) are logged in `audit_logs`.

## Files Involved
- **Route registration:** `routes/api.php`
- **Endpoint controller:** `app/Http/Controllers/ExternalMedicalApiController.php`
- **Token middleware:** `app/Http/Middleware/ExternalMedicalApiTokenMiddleware.php`
- **Rate limiter config:** `app/Providers/AppServiceProvider.php`
- **Service config values:** `config/services.php`
- **Documentation:** `docs/EXTERNAL_MEDICAL_API_GUIDE.md`

## Environment Variables
Ensure these are set in your `.env` and production environment:

- `EXTERNAL_MEDICAL_API_TOKEN=<secure-random-token>`
- `EXTERNAL_MEDICAL_API_DAILY_LIMIT=1500`

Run `php artisan config:clear` after updating these values.

## API Request Details

### Base URL
- Local: `http://127.0.0.1:8000/api/v1`
- Deployed: `https://<your-domain>/api/v1`

### Headers
- `Authorization: Bearer <EXTERNAL_MEDICAL_API_TOKEN>`
- `Accept: application/json`

### Endpoints

#### 1. Lookup by IDP User ID
Retrieves details for a specific applicant using their Identity Provider ID.

```http
GET /api/v1/medical/applicants/idp/{idpUserId}
```

**Example Response (200 OK):**
```json
{
  "data": {
    "id": 43,
    "idp_user_id": "idp_med_43",
    "student_number": "2026-MED-1234",
    "firstname": "Test",
    "middlename": null,
    "lastname": "Medical",
    "email": "medical.test@example.com",
    "contact_number": "09123456789",
    "program": {
      "id": 1,
      "code": "BSIT",
      "name": "BS Information Technology"
    },
    "application": {
      "id": 105,
      "status": "submitted",
      "created_at": "2026-04-01T13:25:24.000000Z"
    },
    "medical_process_status": "in_progress"
  }
}
```

#### 2. Bulk Listing (Deprecated)
Bulk listing is disabled to match the Student API security pattern.

```http
GET /api/v1/medical/applicants
```
**Response (410 Gone):**
```json
{
  "message": "This endpoint is deprecated. Use a specific lookup endpoint."
}
```

## Security & Throttling
- **Throttling:** 
  - 5 requests per second
  - 80 requests per minute
  - 1500 requests per day (matches Student API limit)
- **Validation:** Only returns records if the stage requirements are met. Returns `404` otherwise.
- **Audit Logs:** Logged under category `ADMISSION_DATA` with action `READ` or `READ_MISS`.

## Internal Test Data
For development/testing, you can use the `seed_medical.php` script to create a compliant test applicant.
