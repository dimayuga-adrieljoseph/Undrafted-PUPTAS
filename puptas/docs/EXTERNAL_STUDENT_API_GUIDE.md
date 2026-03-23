# External Student API Guide

## Overview
This guide documents the implemented External Student API feature for sharing officially enrolled student records with external systems (for example, Guidance).

## Implemented Scope
- Endpoint: `GET /api/v1/students`
- Authentication: Shared bearer token from environment variable
- Data scope: Only students whose application `enrollment_status` is `officially_enrolled`
- Pagination: Supported (`page`, `per_page`)
- Incremental sync: Supported (`updated_since`)
- Audit visibility: API access events are written to existing `audit_logs` and visible in SuperAdmin Audit Logs

## Files Involved
- Route registration: `routes/api.php`
- Endpoint controller: `app/Http/Controllers/ExternalStudentApiController.php`
- Token middleware: `app/Http/Middleware/ExternalApiTokenMiddleware.php`
- Rate limiter config: `app/Providers/AppServiceProvider.php`
- Service config values: `config/services.php`
- User model `student_number` mass-assignable: `app/Models/User.php`
- Student number migration: `database/migrations/2026_03_17_000010_add_student_number_to_users_table.php`
- Test data seeder: `database/seeders/ApiTestStudentsSeeder.php`
- Feature tests: `tests/Feature/ExternalStudentApiTest.php`

## Environment Variables
Add these in `.env` and deployment variables (Railway):

- `EXTERNAL_API_TOKEN=<long-random-secret>`
- `EXTERNAL_API_DAILY_LIMIT=200`
- `EXTERNAL_API_MINUTE_LIMIT=20`

After updating env values:

```bash
php artisan config:clear
```

## Security Controls Implemented
1. Bearer token verification for every request.
2. Constant-time token comparison (`hash_equals`).
3. Minute and daily rate limits.
4. Field-limited payload (no password, remember token, or 2FA secrets).
5. Audit entries for both denied and successful API access.

## API Request

### URL
- Local: `http://127.0.0.1:8000/api/v1/students`
- Deployed: `https://<your-domain>/api/v1/students`

### Headers
- `Authorization: Bearer <EXTERNAL_API_TOKEN>`
- `Accept: application/json`

### Query Parameters
- `per_page` (optional, integer, min 1, max 200, default 100)
- `page` (optional, integer, min 1)
- `updated_since` (optional, date)

Example:

```http
GET /api/v1/students?per_page=10&page=1&updated_since=2026-03-01
Authorization: Bearer <EXTERNAL_API_TOKEN>
```

## Response Format

```json
{
  "data": [
    {
      "id": 1,
      "student_number": "2026-TST-001",
      "firstname": "APITest001",
      "middlename": null,
      "extension_name": null,
      "lastname": "Student",
      "email": "apitest.student001@example.com",
      "contactnumber": "09170000000",
      "birthday": null,
      "sex": null,
      "street_address": null,
      "barangay": null,
      "city": null,
      "province": null,
      "postal_code": null,
      "application": {
        "application_id": 10,
        "status": "accepted",
        "enrollment_status": "officially_enrolled",
        "enrollment_position": null,
        "submitted_at": "2026-03-17T10:30:00.000000Z"
      },
      "program": {
        "program_id": 1,
        "program_code": "API-TST",
        "program_name": "API Testing Program"
      },
      "created_at": "2026-03-17T10:30:00.000000Z",
      "updated_at": "2026-03-17T10:30:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 20,
    "last_page": 2
  }
}
```

## Error Responses
- `401 Unauthorized`

```json
{
  "message": "Unauthorized"
}
```

- `429 Too Many Requests` when rate limit is exceeded.

## Audit Log Behavior
Audit records are written to the existing `audit_logs` table and shown in SuperAdmin Audit Logs (`/admin/audit-logs`):

- Denied requests:
  - `action_type`: `AUTH_FAILED`
  - `module_name`: `External API`
  - `log_category`: `AUTHENTICATION`

- Successful requests:
  - `action_type`: `READ`
  - `module_name`: `External API`
  - `log_category`: `ADMISSION_DATA`

## Local Testing Steps
1. Run migrations:

```bash
php artisan migrate
```

2. Seed test students (20 records):

```bash
php artisan db:seed --class=ApiTestStudentsSeeder
```

3. Run feature tests:

```bash
php artisan test tests/Feature/ExternalStudentApiTest.php
```

4. Manual checks with Postman:
- No token: expect `401`
- Valid token: expect `200` with `data` and `meta`
- Verify all returned records have `application.enrollment_status = officially_enrolled`

## Deployment Checklist
1. Set env vars on Railway:
- `EXTERNAL_API_TOKEN`
- `EXTERNAL_API_DAILY_LIMIT`
- `EXTERNAL_API_MINUTE_LIMIT`

2. Deploy and run migrations.
3. Validate endpoint with and without token.
4. Verify API audit entries appear in SuperAdmin Audit Logs.

## Notes
- The current implementation supports one shared token at a time (`EXTERNAL_API_TOKEN`).
- If multi-token support is needed later, add a token table and token management workflow.
