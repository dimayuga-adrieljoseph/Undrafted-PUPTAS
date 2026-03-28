# External Program API Guide

## Overview
This guide documents the implemented External Program API feature. This API provides an internal endpoint specifically designed for querying the `id`, `code`, and `name` of all active programs via secure token authentication. It is entirely decoupled from the Student Info API.

## Implemented Scope
- **Endpoint:** `GET /api/v1/programs`
- **Authentication:** Shared bearer token from environment variable (`EXTERNAL_PROGRAM_API_TOKEN`).
- **Data scope:** Returns strict selection of columns (`id`, `code`, `name`) for all active Programs. Model appends (like `strand_names` and `strands`) are actively hidden to keep payloads clean and secure.
- **Throttling:** Max 50 requests per day (via `external-program-api-daily`), alongside existing minute and second API rate limits.
- **Audit visibility:** All API access events are written to the `audit_logs` table via `AuditLogService` and are visible in the SuperAdmin Audit Logs.

## Files Involved
- Route registration: `routes/api.php`
- Endpoint controller: `app/Http/Controllers/ExternalProgramApiController.php`
- Token middleware: `app/Http/Middleware/ExternalProgramApiTokenMiddleware.php`
- Rate limiter config: `app/Providers/AppServiceProvider.php`
- Service config values: `config/services.php`
- Feature tests: `tests/Feature/ExternalProgramApiTest.php`

## Environment Variables
Add these strictly to `.env` and your deployment variables (Railway):

- `EXTERNAL_PROGRAM_API_TOKEN=<long-random-secret>`
- `EXTERNAL_PROGRAM_API_DAILY_LIMIT=50`

After updating env values, flush the config cache:
```bash
php artisan config:clear
```

## Security Controls Implemented
1. Bearer token verification decoupled strictly to `ExternalProgramApiTokenMiddleware`.
2. Constant-time token comparison via `hash_equals()` to prevent timing attacks.
3. Daily rate limit completely separated from the Student API to prevent Cross-API throttling, isolated to 50 hits/day.
4. Minimal payload exposure using `makeHidden(['strand_names', 'strands'])` and direct column `select()`.
5. Audit `READ` log generated instantly upon successful response, returning 401 and an `AUTH_FAILED` log upon denial.

## Audit Log Behavior
As with all external APIs, requests are tracked directly in `audit_logs`:
- **Denied requests (Invalid Token):**
  - `action_type`: `AUTH_FAILED`
  - `module_name`: `External API`
  - `log_category`: `AUTHENTICATION`
- **Successful requests:**
  - `action_type`: `READ`
  - `module_name`: `External API`
  - `log_category`: `ADMISSION_DATA`

## Local Testing
1. Run feature tests to verify limits, models, and tokens:
```bash
php artisan test --filter ExternalProgramApiTest
```
2. Once running, ping the endpoint using Localhost or Postman:
```http
GET /api/v1/programs
Authorization: Bearer <EXTERNAL_PROGRAM_API_TOKEN>
```
