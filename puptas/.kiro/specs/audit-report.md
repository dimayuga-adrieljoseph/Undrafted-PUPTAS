# 🧠 System Audit Report — PUPTAS (PUP Taguig Admission System)

**Stack:** Laravel 11 + Vue 3 + Inertia.js + MySQL  
**Purpose:** Multi-stage student admission system (document submission → evaluation → interview → medical → enrollment)  
**Environment:** Production-deployed on Railway.app  
**Audit Date:** April 25, 2026

---

## 1. Executive Summary

The system is functionally complete and demonstrates solid architectural thinking — services layer, audit logging, role-based access, and external API integration are all present. However, several critical security issues exist that must be resolved before the system can be considered production-safe.

**Top 3 Critical Risks:**
1. **Debug routes with hardcoded secrets are live in production** — anyone who knows `debug2026` can manipulate application state for any user.
2. **IDP client secret and OAuth credentials are committed in `.env`** — if the repo is ever exposed, the entire authentication system is compromised.
3. **`CallbackController::handle()` makes an arbitrary HTTP POST to a user-supplied URL** — this is a Server-Side Request Forgery (SSRF) vulnerability.

---

## 2. Findings by Category

### 🔐 Security

| Severity | Issue | Explanation | Recommended Fix |
|----------|-------|-------------|-----------------|
| **Critical** | Debug routes with hardcoded secret in production | `GET /debug-medical/{id}/debug2026`, `POST /debug-medical/assign-student-number/{id}/debug2026`, `POST /debug-medical/complete-medical/{id}/debug2026` are unauthenticated routes that bypass all authorization. The secret `debug2026` is hardcoded in the route file. Anyone who discovers these URLs can assign student numbers, force-complete medical stages, and read PII for any user. | Remove all three debug routes immediately. If needed for ops, move behind `EnsureSuperAdmin` middleware and use a proper env-based secret. |
| **Critical** | SSRF in `CallbackController::handle()` | `CallbackController::handle()` calls `Http::post($request->input('api_url'), $request->all())` — the target URL comes directly from user input with no validation. An attacker can use this to probe internal services, cloud metadata endpoints (e.g., `http://169.254.169.254`), or exfiltrate data. | Remove this method entirely or, if needed, validate `api_url` against a strict allowlist of internal paths only. |
| **Critical** | IDP client secret in `.env` committed to repo | `.env` contains `IDP_CLIENT_SECRET=Rc6mkQF3p2j_DMZ2vCJkvI-bSlNvewoKg2hBzEw87rU` and `IDP_CLIENT_ID`. If `.env` is ever accidentally committed or leaked, the OAuth2 flow is fully compromised. | Rotate the IDP client secret immediately. Ensure `.env` is in `.gitignore` (it is, but verify no historical commits contain it). Use Railway's secret management for production values. |
| **High** | No CSRF protection on `/api/callback` POST route | `Route::post('/api/callback', [CallbackController::class, 'handle'])` is outside any CSRF-protected group and has no `VerifyCsrfToken` exclusion comment — it's just unprotected. | Either add CSRF protection or remove the route (it's unused). |
| **High** | `schedules` resource route has no auth middleware | `Route::resource('schedules', ScheduleController::class)` is declared outside any `auth` middleware group. All schedule CRUD endpoints are publicly accessible. | Wrap the schedules resource in `->middleware(['auth', 'role:2,4'])` or equivalent. |
| **High** | `/sar/download/{filename}/{reference}` is unauthenticated | SAR PDF downloads are accessible without authentication. The `{filename}` parameter could potentially be manipulated for path traversal. | Add `auth` middleware. Validate `{filename}` against a strict pattern (alphanumeric + dash/underscore only). |
| **High** | Session not encrypted | `.env` has `SESSION_ENCRYPT=false`. Session data includes IDP tokens, user roles, and OAuth state. | Set `SESSION_ENCRYPT=true` in production. |
| **High** | `SESSION_SECURE_COOKIE=false` in `.env` | Cookies can be transmitted over HTTP, enabling interception. | Set `SESSION_SECURE_COOKIE=true` in production. The `AppServiceProvider` already forces HTTPS in production, so this should be safe to enable. |
| **Medium** | OAuth2 state parameter not validated in callback | `IdpAuthController::callback()` does not verify the `state` parameter against the session value stored in `idp_oauth_state`. This leaves the OAuth flow vulnerable to CSRF. | Compare `$request->query('state')` against `session('idp_oauth_state')` and reject mismatches. |
| **Medium** | IDP tokens stored in plaintext in `refresh_tokens` table | Refresh tokens are stored as plaintext strings in the database. A database dump exposes all active sessions. | Encrypt refresh tokens at rest using Laravel's `encrypt()`/`decrypt()` helpers, or use a hashed reference. |
| **Medium** | No malware/antivirus scanning on uploaded files | Files are validated by MIME type and extension but not scanned for malicious content. A malicious PDF or image could be uploaded. | Integrate ClamAV or a cloud scanning service (e.g., AWS GuardDuty, VirusTotal API) on upload. |
| **Medium** | File upload path stored as `storage/public/uploads/files` (absolute-style) | Storing paths with `storage/public/` prefix creates coupling to the local disk layout and could cause issues if the disk changes. | Store only the relative path from the disk root (e.g., `uploads/files/filename.jpg`). |
| **Low** | `APP_KEY` visible in `.env` | The application encryption key is in the `.env` file shown in the repo. | Rotate `APP_KEY` if `.env` was ever committed. Confirm via `git log --all -- .env`. |
| **Low** | `APP_DEBUG=true` in `.env` | Debug mode exposes stack traces and environment details in error responses. | Ensure `APP_DEBUG=false` in production Railway config. |

---

### 🧱 Code Quality & Maintainability

| Severity | Issue | Explanation | Recommended Fix |
|----------|-------|-------------|-----------------|
| **High** | Three debug route closures (~150 lines) inline in `web.php` | Business logic (student number generation, medical stage completion) is embedded directly in route closures with no tests, no service layer, and no authorization. | Remove entirely. If the functionality is needed, extract to a `DebugController` behind superadmin auth. |
| **High** | `getUserApplication()` in `UserFileController` returns fields that don't exist on `User` model | The method returns `$user->school`, `$user->schoolAdd`, `$user->schoolyear`, `$user->dateGrad` — none of these are in `User::$fillable` or the model. These fields likely live on `ApplicantProfile`. | Load the `applicantProfile` relationship and return fields from the correct model. |
| **Medium** | Duplicate `files()` and `userFiles()` relationships on `User` model | Both return `$this->hasMany(UserFile::class)`. One is dead code. | Remove `files()` and use `userFiles()` consistently, or vice versa. |
| **Medium** | `Application::user()` relationship points to `ApplicantProfile`, not `User` | `belongsTo(ApplicantProfile::class, 'user_id', 'user_id')` — the relationship is named `user()` but returns an `ApplicantProfile`. This is semantically misleading and will cause confusion. | Rename to `applicantProfile()` or fix the relationship to point to `User`. |
| **Medium** | `Application::files()` uses a cross-table join via `user_id` | `hasMany(UserFile::class, 'user_id', 'user_id')` — files are not actually owned by the application, they're owned by the user. This makes eager loading ambiguous. | Files should be fetched via `$application->user->userFiles()` or add a proper `application_id` FK on `user_files`. |
| **Medium** | `\Log::info()` used directly instead of `Log` facade | Multiple controllers use `\Log::info(...)` with the global namespace accessor instead of the injected `Log` facade. | Use `use Illuminate\Support\Facades\Log;` and call `Log::info(...)`. |
| **Medium** | `ApplicationService::getApplicationByUserId()` ignores soft deletes | `Application::where('user_id', $userId)->firstOrFail()` does not filter `deleted_at`. A deleted application could be returned. | Add `->whereNull('deleted_at')` or use the model's default scope (ensure `SoftDeletes` is applied). |
| **Low** | `CallbackController` imports `Http` facade but only uses it in the SSRF-vulnerable `handle()` method | If `handle()` is removed, the import becomes dead code. | Clean up after removing the method. |
| **Low** | `web.php` has duplicate `use` imports and mixed import ordering | Imports are scattered throughout the file (e.g., `use App\Http\Controllers\ExternalProgramApiController` appears mid-file in `api.php`). | Move all `use` statements to the top of each file. Run `php artisan pint`. |
| **Low** | `GradesController::storeAbmGrades` is reused for ICT strand | `Route::post('/grades/ict', [GradesController::class, 'storeAbmGrades'])` — ICT grades are stored via the ABM handler. | Create a dedicated `storeIctGrades()` method or rename the shared method to reflect its multi-strand purpose. |

---

### 🏗 Architecture & Design

| Severity | Issue | Explanation | Recommended Fix |
|----------|-------|-------------|-----------------|
| **High** | Student number generation has a race condition | In the debug route (and likely in production code), student numbers are generated by reading the last number and incrementing: `$lastNum + 1`. Under concurrent requests, two users could receive the same student number. | Use a database sequence, atomic `INSERT ... SELECT MAX()+1`, or a dedicated `student_number_sequences` table with a DB-level unique constraint. |
| **Medium** | `QUEUE_CONNECTION=sync` in production | All queued jobs (emails, PDF generation, OCR) run synchronously in the HTTP request cycle. This blocks responses and risks timeouts for heavy operations like Tesseract OCR. | Switch to `database` or `redis` queue driver in production. Move email sending and PDF generation to queued jobs. |
| **Medium** | `CACHE_STORE=database` with no Redis | Database-backed cache adds query overhead for every cache read/write. Combined with sync queues, this means the DB handles all caching, queuing, and sessions simultaneously. | Use Redis for cache and queue in production. Railway supports Redis add-ons. |
| **Medium** | No repository pattern — controllers query models directly in some places | Route closures in `web.php` directly instantiate and query `\App\Models\User`, `\App\Models\ApplicantProfile`, etc. This bypasses the service layer. | Move all model queries into services or repositories. Route closures should only call controllers. |
| **Low** | `FileMapper::MAPPING` and `UserFileController::$filesToSave` define the same mapping independently | Two separate arrays map API keys to DB types. They can drift out of sync. | Use `FileMapper::MAPPING` as the single source of truth in `uploadFiles()`. |
| **Low** | `HandleInertiaRequests` shares the full authenticated user object to the frontend | Sharing the entire `Auth::user()` object may expose fields that shouldn't reach the client (e.g., `password` hash, internal IDs). | Explicitly select only the fields the frontend needs: `id`, `firstname`, `lastname`, `email`, `role_id`. |

---

### ⚙️ Business Logic

| Severity | Issue | Explanation | Recommended Fix |
|----------|-------|-------------|-----------------|
| **High** | Medical webhook can be replayed | `VerifyMedicalWebhookSignature` validates the HMAC signature but does not check for replay attacks. The same signed payload can be submitted multiple times. | Add a nonce or timestamp to the webhook payload and reject requests older than 5 minutes or with a previously-seen nonce (store in cache). |
| **High** | `ApplicationService::getApplicationSummary()` counts all applications including soft-deleted | `Application::count()` includes soft-deleted records unless the model's global scope handles it. Dashboard stats may be inflated. | Verify `SoftDeletes` global scope is active on `Application`. Use `Application::withoutTrashed()->count()` explicitly if unsure. |
| **Medium** | No guard against submitting an application twice | `ConfirmationController::submit()` should check whether the user already has a non-deleted, non-rejected application before creating a new one. If this check is missing, a user could submit multiple applications. | Add a uniqueness check: `Application::where('user_id', $userId)->whereNotIn('status', ['rejected'])->exists()`. |
| **Medium** | `changeCourse` is accessible to roles 2, 4, 6, 7 — including Interviewer (role 4) | Interviewers should not be able to change a student's enrolled program after the registrar has tagged them. | Restrict `changeCourse` to roles 2, 6, 7 only. |
| **Low** | `AuditLogService` swallows all exceptions silently | The service wraps writes in try/catch and logs the error but continues. While this prevents audit failures from breaking the main flow, it means audit records can silently disappear. | At minimum, fire a `Log::critical()` alert and consider a dead-letter queue for failed audit writes. |

---

### 🚀 Performance & Scalability

| Severity | Issue | Explanation | Recommended Fix |
|----------|-------|-------------|-----------------|
| **High** | N+1 queries likely in dashboard list views | `getUsers()` endpoints in evaluator/interviewer/record controllers load applicants and then likely access relationships per-row without eager loading. | Audit all `getUsers()` methods. Use `with(['applicantProfile', 'currentApplication.program', 'currentApplication.processes'])` consistently. |
| **High** | Tesseract OCR runs synchronously in HTTP request | `GradeExtractionController` runs OCR in the request cycle. OCR on a multi-page document can take 10–30 seconds, causing gateway timeouts. | Move OCR to a queued job. Return a job ID immediately and poll for results. |
| **Medium** | `ExternalMedicalApiController::getEligibleApplicantQuery()` uses multiple nested `whereHas` | Four nested `whereHas` calls each generate a subquery. On large datasets this becomes slow. | Replace with a single JOIN-based query or add a denormalized `current_stage` column on `applications` that is updated on each stage transition. |
| **Medium** | No database indexes mentioned on `application_processes` | Queries filter by `stage`, `status`, and `action` frequently. Without indexes, these are full table scans. | Add composite indexes: `(application_id, stage, status)` and `(stage, status, action)`. |
| **Low** | `AuditLog` table will grow unbounded | No archival or purge strategy exists. At scale (thousands of logins/day), this table becomes a performance bottleneck. | Add a scheduled command to archive logs older than 1 year to cold storage or a separate `audit_logs_archive` table. |

---

### 🗄 Database Design

| Severity | Issue | Explanation | Recommended Fix |
|----------|-------|-------------|-----------------|
| **High** | `user_files` lacks a proper `application_id` foreign key constraint | `application_id` is nullable and set optionally on upload, but there's no FK constraint enforcing referential integrity. Files can reference non-existent applications. | Add `FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL`. |
| **Medium** | No unique constraint on `(user_id, type)` in `user_files` at the DB level | `UserFile::updateOrCreate(['user_id', 'type'], ...)` relies on application-level uniqueness. A race condition could create duplicate rows. | Add a unique index: `UNIQUE(user_id, type)`. |
| **Medium** | `refresh_tokens` table stores tokens without expiry enforcement at DB level | Expired tokens remain in the table indefinitely. The middleware checks `expires_at` in PHP, but stale rows accumulate. | Add a scheduled command to prune expired refresh tokens. Consider a DB-level `EVENT` or TTL. |
| **Low** | `AuditLog::old_values` and `new_values` stored as JSON in MySQL | For large payloads (e.g., full application snapshots), JSON columns can become large. | Consider storing only changed fields (diff), not full objects. |

---

### 🔌 API Design

| Severity | Issue | Explanation | Recommended Fix |
|----------|-------|-------------|-----------------|
| **High** | Deprecated `GET /api/v1/medical/applicants` returns 410 but is still rate-limited and logged | The endpoint is deprecated and returns 410 Gone, but it still consumes rate limit quota and writes audit logs on every call. External clients hitting it repeatedly will exhaust their quota. | Remove the route entirely or return 410 without consuming rate limits. Update external clients. |
| **Medium** | API versioning is inconsistent | Web API routes (`/api/programs/update/{id}`) use no version prefix, while external APIs use `/api/v1/`. Internal and external APIs are mixed in `api.php`. | Separate internal API routes (Sanctum-protected) from external M2M routes (Passport-protected) into distinct route files or prefixes. |
| **Medium** | `GET /api/v1/students` (index) returns all enrolled students with no pagination | The `ExternalStudentApiController::index()` likely returns all records. At scale this is a memory and bandwidth issue. | Add cursor-based or offset pagination. Return `Link` headers per RFC 5988. |
| **Low** | No API versioning strategy for breaking changes | There is no documented plan for `v2` routes or deprecation timelines beyond the `Sunset` header on the medical endpoint. | Document a versioning policy. Use `Deprecation` and `Sunset` headers consistently on all deprecated endpoints. |

---

### ⚙️ DevOps / Deployment

| Severity | Issue | Explanation | Recommended Fix |
|----------|-------|-------------|-----------------|
| **Critical** | `APP_DEBUG=true` and `LOG_LEVEL=debug` appear to be the committed defaults | If these values are used in production (Railway may inherit from the committed `.env.example` or defaults), stack traces and debug info are exposed publicly. | Explicitly set `APP_DEBUG=false` and `LOG_LEVEL=error` in Railway environment variables. Never rely on `.env` for production secrets. |
| **High** | No health check endpoint | Railway and load balancers need a `/health` or `/up` endpoint to verify the app is running. Laravel 11 ships with `/up` by default — confirm it's not disabled. | Verify `GET /up` returns 200. Add DB connectivity check if needed. |
| **High** | `SESSION_DRIVER=file` in production | File-based sessions don't work correctly in multi-instance deployments (Railway can scale horizontally). Sessions will be lost on instance restarts. | Switch to `SESSION_DRIVER=database` or `redis` in production. |
| **Medium** | No CI/CD pipeline evident | No `.github/workflows` or equivalent pipeline for automated testing, linting, or deployment gating. | Add a GitHub Actions workflow: run `pest`, `pint`, and `php artisan migrate --pretend` on every PR. |
| **Medium** | `php artisan migrate` is not idempotent-safe for production | Running migrations in production without a rollback plan is risky. | Use `migrate --force` only in CI/CD with a tested rollback migration. Tag releases before migrating. |
| **Low** | No `php artisan config:cache` or `route:cache` in deployment | Without caching, every request parses config files and route definitions. | Add `php artisan config:cache && php artisan route:cache && php artisan view:cache` to the Railway deploy command. |

---

### 🧪 Testing

| Severity | Issue | Explanation | Recommended Fix |
|----------|-------|-------------|-----------------|
| **High** | No test coverage evident | `pestphp/pest` is installed but no test files were found beyond the default Laravel stubs. The entire admission workflow — stage transitions, webhook processing, file uploads — is untested. | Write feature tests for: application submission, evaluator pass/return, interviewer accept, medical webhook, and enrollment tagging. |
| **High** | No webhook signature test | The HMAC verification in `VerifyMedicalWebhookSignature` is untested. A regression could silently break webhook security. | Add a test that sends a valid and an invalid signature and asserts 200 vs 403. |
| **Medium** | No factory definitions visible for most models | Without factories, writing tests requires manual DB seeding, which is slow and brittle. | Create factories for `Application`, `ApplicationProcess`, `ApplicantProfile`, `UserFile`, `Program`. |
| **Low** | `APP_ENV=local` and `APP_DEBUG=true` in `.env.example` | New developers cloning the repo will have debug mode on by default. | Add a note in `README.md` to copy `.env.example` and set appropriate values before running. |

---

### 🧹 Cleanup & Refactoring

| Severity | Issue | Explanation | Recommended Fix |
|----------|-------|-------------|-----------------|
| **Medium** | `IdpAuthController::redirect()` is a dead alias | `redirect()` just calls `login()`. It's kept for "backward compatibility" but there's no evidence it's used. | Remove it. |
| **Medium** | Multiple callback route aliases for the same handler | `/auth/idp/callback`, `/auth/callback`, `/callback`, `/api/callback` all route to `IdpAuthController::callback()`. This is confusing and increases attack surface. | Standardize on one callback URL, update the IDP client config, and remove the aliases. |
| **Medium** | `CallbackController` is largely unused | `index()` renders a loading page, `handle()` is the SSRF vector, `handleIdpCallback()` delegates to `IdpAuthController`. The controller adds no value. | Remove `CallbackController` entirely. The IDP callback is handled by `IdpAuthController`. |
| **Low** | Commented-out `application()` relationship on `User` model | Dead code with a deprecation comment. | Remove it. |
| **Low** | `web.php` mixes route declarations with inline business logic | Route closures contain 50–100 lines of business logic. | All route closures with logic should be moved to controllers. |
| **Low** | `nof137a` key in `FileMapper::MAPPING` is inconsistently named | All other keys use camelCase (`file10Front`, `goodMoral`) but `nof137a` is all lowercase. | Rename to `noF137a` for consistency. |

---

## 3. Priority Action Plan

### 🔴 Immediate (Critical)
- [ ] Remove all three `/debug-medical/*` routes from `web.php`
- [ ] Remove or fix `CallbackController::handle()` (SSRF vulnerability)
- [ ] Rotate `IDP_CLIENT_SECRET` and all secrets that may have been exposed in `.env`
- [ ] Set `APP_DEBUG=false` and `SESSION_ENCRYPT=true` in Railway production environment variables

### 🟠 Short-Term (High)
- [ ] Add OAuth2 `state` parameter validation in `IdpAuthController::callback()`
- [ ] Add `auth` middleware to `schedules` resource routes
- [ ] Add `auth` middleware to `/sar/download/{filename}/{reference}`
- [ ] Fix `getUserApplication()` to read from `ApplicantProfile` instead of non-existent `User` fields
- [ ] Switch `SESSION_DRIVER` to `database` or `redis` in production
- [ ] Set `SESSION_SECURE_COOKIE=true` in production
- [ ] Add webhook replay protection (timestamp + nonce check)
- [ ] Move Tesseract OCR to a queued job
- [ ] Switch `QUEUE_CONNECTION` to `database` or `redis` in production

### 🟡 Medium-Term
- [ ] Add unique DB index on `user_files(user_id, type)`
- [ ] Add composite indexes on `application_processes(application_id, stage, status)`
- [ ] Fix `Application::user()` relationship naming/target
- [ ] Remove duplicate `files()` / `userFiles()` on `User` model
- [ ] Add pagination to `GET /api/v1/students`
- [ ] Encrypt refresh tokens at rest
- [ ] Write feature tests for the core admission workflow
- [ ] Add a scheduled command to prune expired refresh tokens and archive old audit logs
- [ ] Add `config:cache`, `route:cache`, `view:cache` to deployment pipeline

### 🟢 Low Priority
- [ ] Remove dead code: `IdpAuthController::redirect()`, commented-out `application()` relationship
- [ ] Consolidate callback route aliases to a single URL
- [ ] Remove `CallbackController` entirely
- [ ] Move all route closure logic to controllers
- [ ] Rename `nof137a` to `noF137a` in `FileMapper::MAPPING`
- [ ] Run `php artisan pint` to normalize code style

---

## 4. Suggested Improvements

- **Soft-delete audit trail**: When an application is soft-deleted, log the deletion in `AuditLog` with `old_values` snapshot.
- **Event sourcing for application stages**: Replace direct `ApplicationProcess` writes with Laravel Events (`ApplicationPassed`, `ApplicationReturned`). This decouples notifications, audit logging, and state transitions.
- **Signed webhook URLs**: In addition to HMAC, consider short-lived signed URLs for the medical webhook endpoint to further limit exposure.
- **Admin impersonation**: Add a superadmin "view as applicant" feature for support purposes, with full audit logging of impersonation sessions.
- **Rate limiting on web routes**: Add `throttle:60,1` to the applicant file upload and application submission routes to prevent abuse.
- **OpenAPI spec**: Document the external APIs (`/api/v1/students`, `/api/v1/medical/*`) with an OpenAPI 3.0 spec. This enables client SDK generation and contract testing.

---

## 5. Missing Best Practices

- No automated test suite (Pest is installed but unused)
- No CI/CD pipeline (GitHub Actions or equivalent)
- No API documentation (OpenAPI/Swagger)
- No database backup strategy documented
- No secrets rotation policy
- No structured logging format (JSON logs for Railway log aggregation)
- No `Content-Security-Policy` headers on Inertia responses
- No `X-Frame-Options` or `Referrer-Policy` headers configured
- No input sanitization audit for OCR-extracted text before it's stored as grades
- No rate limiting on web-facing form submissions (login, file upload, application submit)
