# Question #14: Server-Side Role-Based Access Control (RBAC)

## 1. Panel Question
> "You have multiple roles—Applicant, Administrator, Document Evaluator, Grade Evaluator, Interviewer, Medical Personnel, Registrar, and Super Administrator. Paano ninyo pinipigilan yung isang user from accessing functions outside their role? Beyond hiding menu items, magandang actual server-side authorization ang maipakita."

## 2. Direct Answer
PUPTAS enforces server-side role-based access control primarily through route-level HTTP middleware, policy gates, and controller authorization checks rather than client-side UI visibility. Every authenticated request resolves the user's single assigned role ID from the `users.role_id` database column and evaluates it against middleware guards (`RoleMiddleware`, `EnsureAdmin`, `EnsureSuperAdmin`, and `EnsureAdminOrRegistrar`). If a user attempts to access an endpoint outside their permitted roles, the middleware terminates execution with an HTTP 403 Forbidden abort before any controller action runs. For record mutations and sensitive file downloads, object-level authorization ensures callers can only manipulate records they own or have explicit stage authority over. Where fine-grained workflow constraints exist, such as program changes, Laravel authorization policies (`ApplicationPolicy::changeCourse`) evaluate both the user's role and the application's current state.

However, detailed code inspection revealed initial scoping and segregation gaps: while admission decision routes (`pass-application`, `reject-application`, `accept`, `reject`) restricted evaluators to their assigned academic programs via the `program_user` pivot table, peripheral review endpoints were initially unscoped, and there was no separation of duties enforcement. On `staging`, targeted authorization controls have been implemented: program scoping is strictly enforced on `user-files` (`ManagesApplicationFiles.php:21`), `user-grades` (`ManagesApplicationFiles`), `start-review` (`EvaluatorDashboardController`), and `lazy-load` endpoints (`LazyLoadController`) for roles 3 and 4; and `RecordStaffDashboardController::tag()` enforces Separation of Duties across `ApplicationProcess::EVALUATION_STAGES` (`document_evaluator`, `grade_evaluator`, and `interviewer`). Several peripheral endpoints remain unscoped as documented limitations: `change-course` is unscoped (cross-role by design allowing interviewers to transfer applicants across programs); `interviewer start/cancel` remains unscoped (future work; transient review locking prior to scoped accept/reject); and `evaluator cancel-review` remains unscoped (future work; transient review lock release).

## 3. How It Works

### Role Mapping
The system maps eight roles to integer IDs defined in `App\Enums\RoleId` (`app/Enums/RoleId.php:15-25`):
- `1` = **Applicant**
- `2` = **Admin**
- `3` = **Document Evaluator**
- `4` = **Interviewer**
- `5` = **Medical**
- `6` = **Registrar** (seeded in migration as `Record Staff`)
- `7` = **SuperAdmin**
- `8` = **Grade Evaluator**

### Architecture Flow
```
Incoming HTTP Request
       │
       ▼
 [auth Guard] ────────► If unauthenticated: redirect('/login')
       │
       ▼
 [Middleware Gate] ────► RoleMiddleware / EnsureAdmin / EnsureAdminOrRegistrar / EnsureSuperAdmin
       │               ├─ Allowed: Continue to Controller
       │               └─ Disallowed: abort(403, 'Unauthorized action.')
       ▼
 [Program Scoping Gate]► Does $user->programs() contain $application->program_id?
       │               ├─ Evaluator pass/reject/flag: SCOPED for Role 3
       │               ├─ Interviewer accept/reject: SCOPED for Role 4
       │               ├─ Start review / user-grades / lazy-load: SCOPED for Roles 3 and 4
       │               └─ Administrative roles (2, 7) / Registrar (6) / Grade Evaluator (8): EXEMPT
       ▼
 [Separation of Duties]► On Enrollment Tagging (POST /record-dashboard/tag/{id}):
       │               Has actor's ID performed document_evaluator, grade_evaluator, or interviewer?
       │               ├─ Yes: abort(403, 'Separation of duties violation...')
       │               └─ No: Proceed with enrollment tag
       ▼
 [Controller Gate] ────► Policy check: $this->authorize('changeCourse', $application)
       │               OR Object ownership check ($user->id === $record->user_id)
       ▼
 Database / Service Layer (Synchronous fail-closed audit log on tag/untag)
```

### Single-Role Storage
A user holds exactly one role stored in `users.role_id`. Role assignment is protected against mass assignment by omitting `role_id` from `$fillable` in `User.php` (`app/Models/User.php:42-45`). Program-level assignments for staff members are maintained in a separate `program_user` pivot table (`database/migrations/2026_03_03_070732_create_program_user_table.php:15-18`), queried via `$user->programs()`.

### Middleware Failure Handling
- `RoleMiddleware`: Throws `abort(403, 'Unauthorized action.')` (`app/Http/Middleware/RoleMiddleware.php:31`).
- `EnsureAdmin`: Throws `abort(403, 'Access denied. Admin privileges required.')` (`app/Http/Middleware/EnsureAdmin.php:30`).
- `EnsureAdminOrRegistrar`: Throws `abort(403, 'Access denied. Admin or Registrar privileges required.')` (`app/Http/Middleware/EnsureAdminOrRegistrar.php:30`).
- `EnsureSuperAdmin`: Throws `abort(403, 'Access denied. Superadmin privileges required.')` for JSON/XHR requests; redirects browser navigation to `dashboard` with an error flash (`app/Http/Middleware/EnsureSuperAdmin.php:34, 38`).

### Webhook Sender Verification
Although `POST /api/webhooks/chatwoot` and `POST /api/webhooks/resend` in `routes/api.php:68-71` do not use route-level middleware, both controllers enforce strict sender verification inside their controller methods:
1. **Chatwoot Webhook (`ChatwootWebhookController.php:34-38, 55-75`):**
   - Extracts the `X-Chatwoot-Signature` request header.
   - Retrieves the shared secret via `config('services.chatwoot.secret_key')`.
   - Computes `hash_hmac('sha256', $request->getContent(), $secretKey)`.
   - Compares signatures using timing-attack-safe `hash_equals()`.
   - **Fail-closed behavior:** If no secret key is configured, the request is immediately rejected with HTTP 403 (`ChatwootWebhookController.php:60-63`).
2. **Resend Webhook (`ResendWebhookController.php:24-27, 64-110`):**
   - Validates Svix delivery headers: `svix-id`, `svix-timestamp`, and `svix-signature`.
   - Enforces a 5-minute replay tolerance window (`abs(time() - (int)$svixTimestamp) > 300`) to prevent replay attacks (`ResendWebhookController.php:83-86`).
   - Strips the `whsec_` prefix and base64-decodes the secret from `config('services.resend.webhook_secret')`.
   - Constructs the signed string `{$svixId}.{$svixTimestamp}.{$body}` and calculates the base64-encoded HMAC-SHA256 digest.
   - Iterates over versioned signatures in `svix-signature` using `hash_equals()`.
   - **Fail-closed behavior:** If secret is missing or signature fails, returns HTTP 401 (`ResendWebhookController.php:26, 71`).
3. **External Medical Webhook (`routes/api.php:50-54`):**
   - Protected at route level by `client:medical-write` (OAuth token scope check) and `VerifyMedicalWebhookSignature` (`medical.webhook` middleware), which validates the `X-Medical-Signature` HMAC-SHA256 header using `config('services.medical_api.webhook_secret')` (`app/Http/Middleware/VerifyMedicalWebhookSignature.php:33-54`).

### Development & Staging Routes Guard
The codebase defines four setup and diagnostic routes in `routes/web.php:57-176, 786-805`:
- `GET /setup-test-applicant`
- `GET /setup-new-applicant`
- `GET /setup-staff`
- `GET /debug-sar-error`

**Exact Environment Guard Implementation:**
```php
// In routes/web.php:58-60, 94-96, 121-123
if (!in_array(config('app.env'), ['local', 'staging'])) {
    abort(404);
}

// In routes/web.php:787
abort_if(!in_array(config('app.env'), ['local', 'staging']), 404);
```
**Production Behavior:** When `APP_ENV=production`, every call to these routes aborts with an HTTP 404 Not Found response, completely disabling them.

**Credentials Created by `/setup-staff` (`routes/web.php:120-176`):**
- Accounts seeded:
  - `evaluator122@gmail.com` (Role 3: Document Evaluator)
  - `interviewer133@gmail.com` (Role 4: Interviewer)
  - `testadmin@example.com` (Role 2: Admin)
  - `garciamhel@example.com` (Role 6: Registrar)
- **Credential Storage:** Passwords are **env-driven**, loaded securely via `config('services.test_setup.staff_passwords')` and `config('services.test_setup.applicant_password')` rather than hardcoded in the codebase.

### Role 5 (Medical Personnel) Integration Model
1. **Reachable Routes:** A user with `role_id = 5` has **no dedicated interactive web dashboard or role-gated web routes** in PUPTAS. Every staff dashboard group in `routes/web.php` explicitly restricts access to roles other than 5:
   - Evaluator: `role:2,3,7,8`
   - Interviewer: `role:2,4,7`
   - Registrar: `role:6`
   - Admin/SuperAdmin: `EnsureAdmin`, `EnsureSuperAdmin`, `role:2,7`
2. **Behavior of `/home` for Role 5 (`routes/web.php:440-484`):**
   The `/home` route inspects `$roleId = Auth::user()->role_id`. It defines redirection branches for Applicant (1), Admin (2), Document/Grade Evaluator (3, 8), Interviewer (4), Registrar (6), and SuperAdmin (7). Because `RoleId::Medical` (5) has **no matching branch**, execution falls through to line 483:
   ```php
   return redirect('/');
   ```
   A logged-in Role 5 user accessing `/home` is redirected back to the root URL `/`.
3. **How Medical Clearance Reaches PUPTAS:**
   Medical clearance does not occur via human browser interaction. Instead, it enters PUPTAS strictly through machine-to-machine (M2M) integration defined in `routes/api.php:45-54`:
   - External hospital/clinic systems authenticate with OAuth 2.0 Client Credentials containing the scope `client:medical-read` to query applicant details (`GET /api/v1/medical/applicants/{referenceNumber}`).
   - External systems post medical assessment results to `POST /api/v1/webhooks/medical-result` with OAuth scope `client:medical-write`.
   - The payload is verified by `VerifyMedicalWebhookSignature` (`app/Http/Middleware/VerifyMedicalWebhookSignature.php:33-54`) using an HMAC-SHA256 signature passed in the `X-Medical-Signature` header.
   - Upon signature verification, `ExternalMedicalApiController::webhookResult` dispatches `ProcessMedicalWebhookJob`, which updates the application process row for `stage = 'medical'` to `status = 'completed'` (`app/Jobs/ProcessMedicalWebhookJob.php:79-114`).

### Separation of Duties Analysis (Sir AJ #15)
1. **Pipeline Actions Accessible to Role 2 (Admin) and Role 7 (SuperAdmin):**
   In the routing and middleware structure, an Admin or SuperAdmin user possesses authorization to execute multiple stages of an application's lifecycle:
   - **Document Evaluation:** Can execute `passApplication`, `rejectApplication`, `flagApplication`, and `returnApplication` (`routes/web.php:565, 577-583`).
   - **Interview:** Can execute `start`, `cancel`, `accept`, and `reject` (`routes/web.php:589, 605-608`).
   - **Course Transfer:** Can execute `changeCourse` via `RecordStaffDashboardController::changeCourse` (`routes/web.php:686-688`, authorized unconditionally by `ApplicationPolicy.php:24-27`).
   - **Enrollment Tagging:** Can execute `tag` and `untag` as officially enrolled via `RecordStaffDashboardController::tag` (`routes/web.php:628-630`).
2. **Cross-Stage `performed_by` Verification and Enforcement:**
   Every controller action records the actor's ID into `application_processes.performed_by` (`auth()->id()`) (`EvaluatorDashboardController.php:178, 268, 333`; `InterviewerDashboardController.php:151, 298`; `RecordStaffDashboardController.php:462, 590, 642`).
   **Current Enforcement on Staging:** Separation of Duties (SoD) is strictly enforced in `RecordStaffDashboardController::tag()` (`app/Http/Controllers/RecordStaffDashboardController.php:567-577`). The system inspects `application_processes` for `ApplicationProcess::EVALUATION_STAGES` (`document_evaluator`, `grade_evaluator`, and `interviewer`) where `performed_by` matches `auth()->id()`. If any match exists, the tag action aborts with HTTP 403:
   > *"Separation of duties violation: You cannot tag an applicant whose evaluation or interview you personally performed."*
   An actor whose only prior stage record was `records` (e.g., from an administrative untag/re-tag cycle) is permitted to tag. This prevents any single user from evaluating and enrolling the same applicant while avoiding administrative deadlocks.
3. **Audit of Alternative Enrollment Status Mutation Paths:**
   A comprehensive code audit was conducted across the codebase (`app/`, `routes/`, `database/seeders`, `app/Console/`, `app/Jobs/`, and `app/Imports/`) to identify all locations that set `enrollment_status = 'officially_enrolled'` or `applications.status = 'accepted'` outside of `RecordStaffDashboardController::tag()`:
   - **Legacy Bulk-Enroll Developer Harness (`POST /test-passers/bulk-enroll`):** A developer test harness in `TestPasserController::bulkEnroll` previously allowed Admin (2), Registrar (6), and SuperAdmin (7) to auto-enroll batches of test passers. It assigned a hardcoded default password (`Password123`), fabricated passing records across all five admission stages (`document_evaluator`, `grade_evaluator`, `interviewer`, `medical`, `records`), checked no prerequisite medical clearance or COR document upload, bypassed Separation of Duties, and logged only a single asynchronous batch audit log without per-applicant entries or reason.
     - **Deployment Database Audit Findings:** `audit_logs` on the deployment database show the endpoint ran 3 times on May 10–11, 2026 (4, 25, and 1 passers; 30 total) from a SuperAdmin account.
     - **Verification Checks Completed:** Forensic checks on the deployment database confirmed:
       1. No `application_processes` rows with `BULK-ENROLL` notes remain.
       2. No accounts were created or updated in that window.
       3. No account tied to a test-passer email still has the default password.
       4. The `ApiTestStudentsSeeder` users do not exist.
     - **Not Verifiable:** Whether any real account's grades were overwritten cannot be verified from available logs.
     - **Status:** Completely removed. The route, controller method, and dead frontend Vue state/modal in `TestPassers/Email.vue` were excised; the route now returns HTTP 404 for all roles (`BulkEnrollDisabledTest`).
   - **`RecordStaffDashboardController::submitRecordsProcess`:** Contains logic updating an application's status to `accepted` and `enrollment_status` to `officially_enrolled`, but has no corresponding route registered in `routes/web.php` or `routes/api.php`. **Status: Unreachable dead code.** It cannot be triggered via HTTP.
   - **`ApplicationService::tagAsOfficiallyEnrolled`:** A service helper method containing logic to update an application to `accepted` and `officially_enrolled`, but has zero callers across any controller, job, or console command. **Status: Unreachable dead code.**
   - **Database Seeders (`database/seeders/ApplicantSeeder.php` and `ApiTestStudentsSeeder.php`):** Set mock applicant records directly in local/testing seed routines invoked only via CLI console (`php artisan db:seed`). No HTTP route other than `RecordStaffDashboardController::tag()` sets official enrollment; the seeders are CLI-only.

### Program Scoping Analysis across Staff Routes
The table below inspects every controller method taking a `{userId}`, `{id}`, or `{applicationProcess}` parameter used by Document Evaluator, Grade Evaluator, Interviewer, or Registrar:

| Route | Controller:Lines | Scoped / NOT Scoped / N/A | Note |
| :--- | :--- | :--- | :--- |
| `POST /evaluator/pass-application/{userId}` | `EvaluatorDashboardController.php:150-159` | **SCOPED** | Restricts Document Evaluators to programs assigned via `$user->programs()` pivot table. Admin (2), SuperAdmin (7), and Grade Evaluator (8) bypass. |
| `POST /evaluator/reject-application/{userId}` | `EvaluatorDashboardController.php:237-246` | **SCOPED** | Restricts Document Evaluators to assigned programs via `$user->programs()`. Admin, SuperAdmin, and Grade Evaluator bypass. |
| `POST /evaluator/flag-application/{userId}` | `EvaluatorDashboardController.php:298-307` | **SCOPED** | Restricts Document Evaluators to assigned programs via `$user->programs()`. Admin, SuperAdmin, and Grade Evaluator bypass. |
| `POST /evaluator/start-review/{applicationProcess}` | `EvaluatorDashboardController.php:347-362` | **SCOPED** | Scoped for roles 3 and 4 via assigned programs (`$user->programs()`). Admin (2), SuperAdmin (7), and Grade Evaluator (8) bypass. |
| `POST /evaluator/cancel-review/{applicationProcess}` | `EvaluatorDashboardController.php:364-382` | **NOT SCOPED** | Resets `reviewed_by = null` without checking reviewer's program assignment. |
| `POST /interviewer-dashboard/accept/{id}` | `InterviewerDashboardController.php:210-221` | **SCOPED** | Validates `$validated['program_id']` against interviewer's assigned programs via `$user->programs()`. Admin/SuperAdmin bypass. |
| `POST /interviewer-dashboard/reject/{id}` | `InterviewerDashboardController.php:334-345` | **SCOPED** | Validates `$validated['program_id']` against interviewer's assigned programs via `$user->programs()`. Admin/SuperAdmin bypass. |
| `POST /interviewer-dashboard/start/{id}` | `InterviewerDashboardController.php:121-159` | **NOT SCOPED** | Checks only that `grade_evaluator` stage is complete; does NOT verify if interviewer is assigned to applicant's program. |
| `POST /interviewer-dashboard/cancel/{id}` | `InterviewerDashboardController.php:161-193` | **NOT SCOPED** | Clears `started_at` and `performed_by` without verifying interviewer's program assignment. |
| `POST /record-dashboard/tag/{id}` | `RecordStaffDashboardController.php:547-564` | **N/A** | Enrollment tagging is institution-wide across all programs. The Registrar role is institution-wide. |
| `POST /record-dashboard/untag/{id}` | `RecordStaffDashboardController.php:614-625` | **N/A** | Enrollment untagging is institution-wide across all programs. |
| `POST /dashboard/return-files/{user}` | `ManagesApplicationFiles.php:395-400` | **SCOPED** | Maps to `returnApplication()`, which checks `$assignedProgramIds = Auth::user()->programs()` and returns 403 if unassigned. (Note: trait also contains an unused `returnFiles()` method at lines 305-373 that lacks program check). |
| `GET /dashboard/user-files/{id}` | `EvaluatorDashboardController.php` (`ManagesApplicationFiles.php:21`) | **SCOPED** | Scoped for roles 3 and 4 via assigned programs (`$user->programs()`) across evaluator (`/dashboard/user-files/{id}`) and interviewer (`/interviewer-dashboard/application/{id}`) routes. Access allowed if ANY applicant application matches; zero applications returns 403. Admin (2), Registrar (6), SuperAdmin (7), and Grade Evaluator (8) bypass. |
| `GET /dashboard/user-grades/{id}` | `ManagesApplicationFiles.php:259-278` | **SCOPED** | Scoped for roles 3 and 4 via assigned programs (`$user->programs()`). Admin, SuperAdmin, and Grade Evaluator bypass. |
| `GET /api/lazy-load/document/{userId}/{fileType}` | `LazyLoadController.php:31-35` | **SCOPED** | Scoped for roles 3 and 4 via assigned programs. Access granted if ANY applicant application matches; returns 403 if unassigned or 0 applications. Roles 2, 6, 7, 8 bypass. |
| `POST /api/lazy-load/documents-batch/{userId}` | `LazyLoadController.php:89-93` | **SCOPED** | Scoped for roles 3 and 4 via assigned programs. Access granted if ANY applicant application matches; returns 403 if unassigned or 0 applications. Roles 2, 6, 7, 8 bypass. |
| `POST /record-dashboard/change-course/{applicantId}` | `RecordStaffDashboardController.php:404-420`, `ApplicationPolicy.php:22-42` | **NOT SCOPED** | Checks `ApplicationPolicy::changeCourse`. Allows Admins unconditionally; allows Interviewers if applicant is not officially enrolled. Does NOT check interviewer's program assignment for either current or destination program. |

---

### Complete Route Inventory
The table below documents every route registered across `routes/web.php` and `routes/api.php`:

| Route | Method | Middleware | Roles Allowed | What It Does |
| :--- | :--- | :--- | :--- | :--- |
| `/api/user` | GET | `auth:sanctum` | All authenticated | Returns current authenticated user record |
| `/api/programs/update/{id}` | PUT | `auth:sanctum`, `EnsureAdmin` | 2, 7 | Updates program data via API |
| `/api/programs/delete/{id}` | DELETE | `auth:sanctum`, `EnsureAdmin` | 2, 7 | Deletes program via API |
| `/api/v1/students` | GET | `client:student-read`, throttle | M2M Client | External API student list |
| `/api/v1/students/email/{email}` | GET | `client:student-read`, throttle | M2M Client | External API student by email |
| `/api/v1/students/{referenceNumber}` | GET | `client:student-read`, throttle | M2M Client | External API student by ref number |
| `/api/v1/programs` | GET | `client:program-read`, throttle | M2M Client | External API program list |
| `/api/v1/medical/applicants` | GET | `client:medical-read` | M2M Client | Deprecated endpoint (returns 410 Gone) |
| `/api/v1/medical/applicants/idp/{idpUserId}` | GET | `client:medical-read`, throttle | M2M Client | Medical applicant by IDP user ID |
| `/api/v1/medical/applicants/{referenceNumber}` | GET | `client:medical-read`, throttle | M2M Client | Medical applicant by ref number |
| `/api/v1/webhooks/medical-result` | POST | `client:medical-write`, `medical.webhook`, throttle | M2M + HMAC | Medical exam result webhook |
| `/api/public/admission-results` | POST | `throttle:status-checker` | Public | Public admission result checker |
| `/api/webhooks/chatwoot` | POST | None (Controller HMAC) | External | Chatwoot webhook receiver |
| `/api/chatwoot/widget-config` | GET | `auth:sanctum` | All authenticated | Chatwoot configuration |
| `/api/webhooks/resend` | POST | None (Controller Svix HMAC) | External | Resend email webhook receiver |
| `/setup-test-applicant` | GET | Environment guard (`local`/`staging`) | Dev / Staging | Seeds test applicant |
| `/setup-new-applicant` | GET | Environment guard (`local`/`staging`) | Dev / Staging | Seeds fresh applicant without profile |
| `/setup-staff` | GET | Environment guard (`local`/`staging`) | Dev / Staging | Seeds staff accounts |
| `/` | GET | None | Public | Landing page |
| `/auth/idp/redirect` | GET | None | Public | IDP OAuth redirection |
| `/auth/idp/callback` | GET | `throttle:10,1` | Public | IDP OAuth callback |
| `/emergency-login` | GET | `ensure.idp.down` | Public (Emergency) | Emergency OTP form |
| `/emergency-login` | POST | `ensure.idp.down`, `throttle:5,1` | Public (Emergency) | Sends emergency OTP |
| `/emergency-login/verify` | GET/POST | `ensure.idp.down`, `throttle:5,1` | Public (Emergency) | Verifies emergency OTP |
| `/auth/callback` | GET | `throttle:10,1` | Public | IDP callback alias |
| `/callback` | GET | `guest`, `throttle:10,1` | Guest | Legacy IDP callback |
| `/api/callback` | GET | `guest`, `throttle:10,1` | Guest | Legacy API IDP callback |
| `/auth/idp/error` | GET | None | Public | IDP error view |
| `/auth/idp/cancel-registration` | GET | None | Public | Cancels IDP registration |
| `/api/v1/auth/logout` | POST | `auth` | All authenticated | Logs out active session |
| `/applications/user/{user}` | GET | `auth`, `role:2,3,4,7,8` | 2, 3, 4, 7, 8 | Renders role-specific applicant view |
| `/check-email` | POST | `auth`, `role:7`, `throttle:30,1` | 7 | Checks email existence in users/passers (SuperAdmin only, rate-limited to 30 req/min) |
| `/check-reference-number` | POST | `guest` | Guest | Validates test passer reference number |
| `/links/admission-criteria` | GET | None | Public | External document redirect |
| `/links/facebook` | GET | None | Public | External social link redirect |
| `/links/register` | GET | None | Public | External IDP registration redirect |
| `/privacy-consent/accept` | POST | `auth` | All authenticated | Records user privacy consent |
| `/privacy-consent/check` | GET | `auth` | All authenticated | Reads user privacy consent status |
| `/programs` | GET | `auth` | All authenticated | Programs view |
| `/addindex` | GET | `auth`, `EnsureAdmin` | 2, 7 | Program creation page |
| `/programs/list` | GET | `auth` | All authenticated | Program catalog data list |
| `/programs/strands` | GET | `auth` | All authenticated | Program strand list |
| `/programs` | POST | `auth`, `EnsureAdmin` | 2, 7 | Creates new academic program |
| `/programs/{program}` | PUT | `auth`, `EnsureAdmin` | 2, 7 | Updates program attributes |
| `/programs/{program}` | DELETE | `auth`, `EnsureAdmin` | 2, 7 | Deletes program |
| `/applicant-dashboard` | GET | `auth`, `role:applicant` | 1 | Applicant dashboard |
| `/applicant-dashboard/qualified-programs` | GET | `auth`, `role:applicant` | 1 | Evaluates eligible programs for applicant |
| `/applicant-dashboard/program-details` | GET | `auth`, `role:applicant` | 1 | Program details modal data |
| `/applicant-dashboard/file-statuses` | GET | `auth`, `role:applicant` | 1 | Uploaded document verification statuses |
| `/user/application` | GET | `auth`, `role:applicant` | 1 | Application confirmation page |
| `/user/application/submit` | POST | `auth`, `role:applicant` | 1 | Final application submission |
| `/user/application/resubmit` | POST | `auth`, `role:applicant` | 1 | Resubmission after evaluator return |
| `/upload-files` | POST | `auth`, `role:applicant` | 1 | Multipart document upload |
| `/api/applicant/files/status` | GET | `auth`, `role:applicant` | 1 | Async polling for file processing status |
| `/grades/stem`, `/grades/abm`, etc. | GET | `auth`, `role:applicant` | 1 | Strand-specific grade entry views |
| `/grades/stem`, `/grades/abm`, etc. | POST | `auth`, `role:applicant` | 1 | Saves applicant high school grades |
| `/home` | GET | `auth` | All authenticated | Role-based portal redirector |
| `/test-passers/form` | GET | `auth` | All authenticated | Legacy test passers upload view (FLAGGED) |
| `/sar/download/{ref}/{file}` | GET | `signed` | Signed URL holder | Tamper-proof SAR PDF download |
| `/applications` | GET | `auth`, `EnsureAdmin` | 2, 7 | Master applications view |
| `/confirmed-applicants` | GET | `auth`, `EnsureAdmin` | 2, 7 | Confirmed applicants page |
| `/confirmed-applicants/list` | GET | `auth`, `EnsureAdmin` | 2, 7 | Lists confirmed applicants |
| `/confirmed-applicants/send-sar` | POST | `auth`, `EnsureAdmin` | 2, 7 | Triggers SAR email delivery |
| `/confirmed-applicants/send-email` | POST | `auth`, `EnsureAdmin` | 2, 7 | Sends custom notification email |
| `/test-passers` | GET | `auth`, `EnsureAdminOrRegistrar` | 2, 6, 7 | Lists imported test passers |
| `/test-passers/select-all-ids` | GET | `auth`, `EnsureAdminOrRegistrar` | 2, 6, 7 | Passer IDs for batch selection |
| `/test-passers/get-by-ids` | POST | `auth`, `EnsureAdminOrRegistrar` | 2, 6, 7 | Fetches passers matching ID array |
| `/test-passers/send-emails` | POST | `auth`, `EnsureAdminOrRegistrar` | 2, 6, 7 | Queues test passer invitation emails |
| `/admin/sar-generations` | GET | `auth`, `EnsureAdminOrRegistrar` | 2, 6, 7 | Lists SAR generation records |
| `/admin/sar/{id}/download` | GET | `auth`, `EnsureAdminOrRegistrar` | 2, 6, 7 | Admin SAR PDF download |
| `/admin/sar/{id}/preview` | GET | `auth`, `EnsureAdminOrRegistrar` | 2, 6, 7 | Admin SAR preview |
| `/admin/sar/preview-email-template` | POST | `auth`, `EnsureAdminOrRegistrar` | 2, 6, 7 | Previews SAR email template |
| `/admin/sar/preview-pdf-template` | POST | `auth`, `EnsureAdminOrRegistrar` | 2, 6, 7 | Previews SAR PDF template |
| `/admin/waitlisted/preview-email-template` | POST | `auth`, `EnsureAdminOrRegistrar` | 2, 6, 7 | Previews waitlisted email template |
| `/admin/email-tracking` | GET | `auth`, `EnsureAdminOrRegistrar` | 2, 6, 7 | Email delivery tracking dashboard |
| `/admin/email-tracking/{id}` | GET | `auth`, `EnsureAdminOrRegistrar` | 2, 6, 7 | Email batch details |
| `/admin/email-tracking/{id}/progress` | GET | `auth`, `EnsureAdminOrRegistrar` | 2, 6, 7 | Polls email batch progress |
| `/admin/email-tracking/retry-selected` | POST | `auth`, `EnsureAdminOrRegistrar` | 2, 6, 7 | Retries selected failed emails |
| `/admin/email-tracking/retry-all-failed`| POST | `auth`, `EnsureAdminOrRegistrar` | 2, 6, 7 | Retries all failed emails in batch |
| `/test-passers/upload` | POST | `auth`, `EnsureAdmin` | 2, 7 | Processes CSV upload of PUPCET passers |
| `/admin/reports` | GET | `auth`, `EnsureAdminOrRegistrar` | 2, 6, 7 | Unified admissions reports view |
| `/admin/reports/data` | GET | `auth`, `EnsureAdminOrRegistrar` | 2, 6, 7 | Master report dataset endpoint |
| `/admin/reports/export/pdf` | GET | `auth`, `EnsureAdminOrRegistrar` | 2, 6, 7 | Generates and downloads official PDF report |
| `/admin/reports/export/excel` | GET | `auth`, `EnsureAdminOrRegistrar` | 2, 6, 7 | Generates and downloads official Excel report |
| `/files/{file}/preview` | GET | `auth`, `signed` | All authenticated | Signed document preview endpoint |
| `/user/eligible-programs` | GET | `auth` | All authenticated | Returns eligible programs for current user |
| `/evaluator-dashboard` | GET | `auth`, `role:2,3,7,8` | 2, 3, 7, 8 | Document evaluator dashboard |
| `/evaluator-applications` | GET | `auth`, `role:2,3,7,8` | 2, 3, 7, 8 | Evaluator applications view |
| `/document-evaluator-dashboard` | GET | `auth`, `role:2,3,7,8` | 2, 3, 7, 8 | Dedicated document evaluator dashboard |
| `/document-evaluator-applications` | GET | `auth`, `role:2,3,7,8` | 2, 3, 7, 8 | Dedicated document applications view |
| `/evaluator-dashboard/applicants` | GET | `auth`, `role:2,3,7,8` | 2, 3, 7, 8 | Paginated applicant queue for evaluators |
| `/evaluator/pass-application/{userId}` | POST | `auth`, `role:2,3,7,8` | 2, 3, 7, 8 | Approves applicant stage |
| `/evaluator/start-review/{proc}` | POST | `auth`, `role:2,3,7,8` | 2, 3, 7, 8 | Acquires review lock on applicant |
| `/evaluator/cancel-review/{proc}` | POST | `auth`, `role:2,3,7,8` | 2, 3, 7, 8 | Releases review lock on applicant |
| `/evaluator/reject-application/{userId}`| POST | `auth`, `role:2,3,7,8` | 2, 3, 7, 8 | Rejects application |
| `/evaluator/flag-application/{userId}` | POST | `auth`, `role:2,3,7,8` | 2, 3, 7, 8 | Flags application for office review |
| `/dashboard/user-files/{id}` | GET | `auth`, `role:2,3,7,8` | 2, 3, 7, 8 | Fetches applicant uploaded documents |
| `/dashboard/return-files/{user}` | POST | `auth`, `role:2,3,7,8` | 2, 3, 7, 8 | Returns application back to applicant |
| `/evaluator-programs` | GET | `auth`, `role:2,3,7,8` | 2, 3, 7, 8 | Lists assigned evaluator programs |
| `/document-evaluator-programs` | GET | `auth`, `role:2,3,7,8` | 2, 3, 7, 8 | Lists document evaluator programs |
| `/interviewer-dashboard` | GET | `auth`, `role:2,4,7` | 2, 4, 7 | Interviewer dashboard view |
| `/interviewer-applications` | GET | `auth`, `role:2,4,7` | 2, 4, 7 | Interviewer applications queue |
| `/interviewer-dashboard/applicants` | GET | `auth`, `role:2,4,7` | 2, 4, 7 | Paginated interviewer queue |
| `/interviewer-dashboard/application/{id}`| GET | `auth`, `role:2,4,7` | 2, 4, 7 | Fetches applicant data for interview |
| `/interviewer-dashboard/start/{id}` | POST | `auth`, `role:2,4,7` | 2, 4, 7 | Starts and locks interview session |
| `/interviewer-dashboard/cancel/{id}` | POST | `auth`, `role:2,4,7` | 2, 4, 7 | Cancels interview session lock |
| `/interviewer-dashboard/accept/{id}` | POST | `auth`, `role:2,4,7` | 2, 4, 7 | Accepts applicant into program |
| `/interviewer-dashboard/reject/{id}` | POST | `auth`, `role:2,4,7` | 2, 4, 7 | Rejects applicant at interview |
| `/interviewer-programs` | GET | `auth`, `role:2,4,7` | 2, 4, 7 | Lists assigned interviewer programs |
| `/record-dashboard` | GET | `auth`, `role:6` | 6 | Registrar dashboard view |
| `/recordstaff-applications` | GET | `auth`, `role:6` | 6 | Registrar applications queue |
| `/record-dashboard/applicants` | GET | `auth`, `role:6` | 6 | Paginated queue for registrar |
| `/record-dashboard/stats` | GET | `auth`, `role:6` | 6 | Enrollment metrics for registrar |
| `/record-dashboard/application/{id}` | GET | `auth`, `role:6` | 6 | Fetches applicant data for registrar |
| `/record-dashboard/tag/{id}` | POST | `auth`, `role:6` | 6 | Tags applicant as officially enrolled |
| `/record-dashboard/untag/{id}` | POST | `auth`, `role:6` | 6 | Reverts applicant enrollment tag |
| `/record-dashboard/return-files/{user}` | POST | `auth`, `role:6` | 6 | Returns files from registrar stage |
| `/record-programs` | GET | `auth`, `role:6` | 6 | Lists programs for registrar view |
| `/admin-dashboard/tag/{id}` | POST | `auth`, `EnsureAdmin` | 2, 7 | Admin enrollment tag endpoint |
| `/admin-dashboard/untag/{id}` | POST | `auth`, `EnsureAdmin` | 2, 7 | Admin enrollment untag endpoint |
| `/admin/records*` (8 routes) | GET/POST | `auth`, `EnsureAdmin` | 2, 7 | Admin records views and mutations |
| `/api/lazy-load/document/{uid}/{type}` | GET | `auth`, `role:2,3,4,6,8` | 2, 3, 4, 6, 8 | Async document lazy loader |
| `/api/lazy-load/documents-batch/{uid}` | POST | `auth`, `role:2,3,4,6,8` | 2, 3, 4, 6, 8 | Async batch document lazy loader |
| `/api/lazy-load/grades/{userId}` | GET | `auth`, `role:2,3,4,6,8` | 2, 3, 4, 6, 8 | Async grades lazy loader |
| `/dashboard/user-grades/{id}` | GET | `auth`, `role:2,3,7,8` | 2, 3, 7, 8 | Evaluator grades fetch endpoint |
| `/api/staff/programs/slots` | GET | `auth`, `role:2,3,4,6,7,8` | 2, 3, 4, 6, 7, 8 | Program capacity and slots summary |
| `/interviewer-dashboard/user-grades/{id}`| GET | `auth`, `role:2,4,7` | 2, 4, 7 | Interviewer grades fetch endpoint |
| `/record-dashboard/user-grades/{id}` | GET | `auth`, `role:6` | 6 | Registrar grades fetch endpoint |
| `/admin-dashboard/user-grades/{id}` | GET | `auth`, `role:2,7` | 2, 7 | Admin grades fetch endpoint |
| `/dashboard/users` | GET | `auth`, `role:2,3,4,7,8` | 2, 3, 4, 7, 8 | Shared applicant listing endpoint |
| `/record-dashboard/programs` | GET | `auth`, `role:2,4,6,7` | 2, 4, 6, 7 | Available programs with slots |
| `/record-dashboard/change-course/{id}` | POST | `auth`, `role:2,4,7` | 2, 4, 7 | Program reassignment / transfer |
| `/admin/users` | GET | `auth`, `EnsureAdmin` | 2, 7 | User management master index |
| `/admin/users/create` | GET | `auth`, `EnsureAdmin` | 2, 7 | User creation form (SuperAdmin only in controller) |
| `/admin/users` | POST | `auth`, `EnsureAdmin` | 2, 7 | Stores new user (SuperAdmin only in controller) |
| `/admin/users/{user}` | PUT | `auth`, `EnsureAdmin` | 2, 7 | Updates user (SuperAdmin only in controller) |
| `/admin/users/{user}` | DELETE | `auth`, `EnsureAdmin` | 2, 7 | Deletes user (SuperAdmin only in controller) |
| `/admin/users/{user}/deactivate` | POST | `auth`, `EnsureAdmin` | 2, 7 | Deactivates user (SuperAdmin only in controller) |
| `/admin/users/{user}/activate` | POST | `auth`, `EnsureAdmin` | 2, 7 | Activates user (SuperAdmin only in controller) |
| `/admin/users/stats` | GET | `auth`, `EnsureAdmin` | 2, 7 | User distribution statistics |
| `/admin/users/programs` | GET | `auth`, `EnsureAdmin` | 2, 7 | Program list for user assignments |
| `/admin/users/{user}/assigned-programs`| GET | `auth`, `EnsureAdmin` | 2, 7 | Fetches programs assigned to staff user |
| `/admin/users/{user}/assign-programs` | POST | `auth`, `EnsureAdmin` | 2, 7 | Updates program assignments for staff |
| `/admin/audit-logs*` (6 routes) | GET/POST/DEL | `auth`, `EnsureSuperAdmin` | 7 | Audit logs viewer and AI analysis |
| `/admin/api-clients*` (4 routes) | GET/POST/DEL | `auth`, `EnsureSuperAdmin` | 7 | Passport M2M client management |
| `/admin/cutoff-settings*` (4 routes) | GET/POST/DEL | `auth`, `EnsureSuperAdmin` | 7 | Admission cutoff configuration |
| `/admin/score-overrides*` (9 routes) | GET/POST/PUT/DEL | `auth`, `EnsureSuperAdmin` | 7 | Score and email registration overrides |
| `/debug-sar-error` | GET | Environment guard (`local`/`staging`) | Dev / Staging | SAR PDF rendering diagnostic endpoint |

---

## 4. Evidence

| Claim | File:Lines | Note |
| :--- | :--- | :--- |
| Role ID Enum definition | `puptas/app/Enums/RoleId.php:15-25` | Defines integer values 1 through 8 |
| Roles database table seeding | `puptas/database/migrations/2025_02_02_073206_create_roles_table.php:18-27` | Seeds IDs 1-8; ID 6 named 'Record Staff' |
| RoleId not mass assignable | `puptas/app/Models/User.php:42-45` | `role_id` intentionally omitted from `$fillable` |
| Default role assignment | `puptas/app/Models/User.php:68-72` | Creating hook sets `RoleId::Applicant` if null |
| Server-side role assignment | `puptas/app/Models/User.php:81-86` | Explicit `assignRole()` via `forceFill()` |
| RoleMiddleware 403 abort | `puptas/app/Http/Middleware/RoleMiddleware.php:28-32` | Resolves tokens and aborts 403 on mismatch |
| EnsureAdmin 403 abort | `puptas/app/Http/Middleware/EnsureAdmin.php:29-31` | Restricts access to roles 2 and 7 |
| EnsureAdminOrRegistrar 403 abort | `puptas/app/Http/Middleware/EnsureAdminOrRegistrar.php:29-31` | Restricts access to roles 2, 6, and 7 |
| EnsureSuperAdmin failure handling | `puptas/app/Http/Middleware/EnsureSuperAdmin.php:31-39` | Aborts 403 on JSON; redirects browser to dashboard |
| Middleware aliases configuration | `puptas/bootstrap/app.php:54-62` | Maps `'role'`, `'guest'`, `'client'` aliases |
| Chatwoot webhook HMAC verification | `puptas/app/Http/Controllers/ChatwootWebhookController.php:34-38, 55-75` | Validates `X-Chatwoot-Signature` via `hash_hmac` |
| Resend webhook Svix HMAC verification | `puptas/app/Http/Controllers/ResendWebhookController.php:24-27, 64-110` | Validates `svix-signature` with 5-minute replay window |
| Medical webhook HMAC middleware | `puptas/app/Http/Middleware/VerifyMedicalWebhookSignature.php:33-54` | Validates `X-Medical-Signature` via HMAC-SHA256 |
| Setup routes environment guard | `puptas/routes/web.php:58-60, 94-96, 121-123` | Aborts 404 unless `APP_ENV` is local or staging |
| Setup staff env-driven credentials | `puptas/routes/web.php:125-159` | Uses `config('services.test_setup.staff_passwords')` |
| Debug SAR environment guard | `puptas/routes/web.php:787` | `abort_if(!in_array(config('app.env'), ['local', 'staging']), 404)` |
| Role 5 absence of web portal routes | `puptas/routes/web.php:440-484` | `/home` falls through to `redirect('/')` on Role 5 |
| Medical M2M OAuth and webhook route | `puptas/routes/api.php:45-54` | `client:medical-write`, `medical.webhook` middleware |
| Evaluator pass program check | `puptas/app/Http/Controllers/EvaluatorDashboardController.php:150-159` | Checks `$application->program_id` in `$user->programs()` |
| Evaluator start review program scoping | `puptas/app/Http/Controllers/EvaluatorDashboardController.php:351-364` | Enforces program scoping for roles 3 and 4; 2, 7, 8 exempt |
| Interviewer accept program check | `puptas/app/Http/Controllers/InterviewerDashboardController.php:210-221` | Checks `$validated['program_id']` against `$user->programs()` |
| Interviewer start unscoped | `puptas/app/Http/Controllers/InterviewerDashboardController.php:121-159` | Verifies stage only, no program assignment check |
| Application files program scoping | `puptas/app/Http/Traits/ManagesApplicationFiles.php:21-65` | Scoped for roles 3 and 4 via `checkProgramScoping()`; exempts roles 2, 5, 6, 7, 8 |
| Lazy load documents program scoping | `puptas/app/Http/Controllers/LazyLoadController.php:26-46` | Scopes documents, batch, and grades to assigned programs for roles 3 and 4 |
| ApplicationPolicy::changeCourse logic | `puptas/app/Policies/ApplicationPolicy.php:22-41` | Allows 2, 7; restricts 4 to pending/unaccepted |
| Policy enforcement on change-course | `puptas/app/Http/Controllers/RecordStaffDashboardController.php:415` | Calls `$this->authorize('changeCourse', $application)` |
| Separation of Duties enforcement in tag() | `puptas/app/Http/Controllers/RecordStaffDashboardController.php:567-577` | Checks `performed_by` against `EVALUATION_STAGES`; aborts with HTTP 403 on conflict |
| Evaluation stages enum constant | `puptas/app/Models/ApplicationProcess.php:23-27` | Defines `EVALUATION_STAGES = ['document_evaluator', 'grade_evaluator', 'interviewer']` |
| Evaluator View All stage preservation | `puptas/resources/js/Pages/Dashboard/Evaluator.vue:88-95` | Computes dynamic `viewAllLink` preserving `document_evaluator` vs `grade_evaluator` stage |
| Synchronous transactional audit logging | `puptas/app/Http/Controllers/RecordStaffDashboardController.php:611-641` | Calls `logActivitySync()` within DB transaction on enrollment tag and untag |

---

## 5. Test Evidence

### Centralized Automated Matrix Test (`tests/Feature/RoleAccessMatrixTest.php`)
To provide irrefutable proof of server-side enforcement across all roles and route groups, an automated matrix test exercises **7 representative route groups** against **Roles 1 through 8 plus unauthenticated guests** (63 distinct authorization test cases):

```php
// In tests/Feature/RoleAccessMatrixTest.php
class RoleAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @dataProvider matrixDataProvider
     */
    public function test_role_access_matrix(string $groupName, string $method, string $uri, array $allowedRoles, ?int $testRole): void
    {
        if ($testRole === null) {
            $response = $method === 'POST' ? $this->post($uri, []) : $this->get($uri);
            $response->assertRedirect('/login');
            return;
        }

        $user = User::factory()->create(['role_id' => $testRole]);
        $response = $method === 'POST'
            ? $this->actingAs($user)->postJson($uri, [])
            : $this->actingAs($user)->getJson($uri);

        if (in_array($testRole, $allowedRoles, true)) {
            $this->assertNotEquals(403, $response->getStatusCode());
        } else {
            $this->assertEquals(403, $response->getStatusCode());
        }
    }
}
```

### Comprehensive Automated Test Suites
All six dedicated security and RBAC feature test suites run clean via Pest SQLite:
1. **`RoleAccessMatrixTest.php`**: **63 passed** (70 assertions) — Verifies route-level middleware across all 8 roles and guests.
2. **`EnrollmentTagAuditTest.php`**: **33 passed** (130 assertions) — Verifies SoD blocks on `document_evaluator`, `grade_evaluator`, and `interviewer`; confirms re-tagging allowed when prior action was only `records`; verifies mandatory reason (5-500 chars); confirms fail-closed synchronous audit logging.
3. **`ProgramScopingReadTest.php`**: **32 passed** (83 assertions) — Verifies program scoping on `user-files`, `user-grades`, `start-review`, and `lazy-load` endpoints for roles 3 and 4, while preserving exemptions for roles 2, 5, 6, 7, and 8.
4. **`CheckEmailSecurityTest.php`**: **12 passed** (45 assertions) — Verifies `/check-email` restricted exclusively to SuperAdmin (role 7) with 30 req/min rate limiting.
5. **`EvaluatorApplicationsStageTest.php`**: **6 passed** (60 assertions) — Verifies stage preservation on Evaluator dashboard for Admin and Document Evaluator, confirming target links and authorization.
6. **`BulkEnrollDisabledTest.php`**: **3 passed** (3 assertions) — Verifies that `POST /test-passers/bulk-enroll` returns HTTP 404 for Admin (2), Registrar (6), and SuperAdmin (7) following complete excision of the legacy developer harness.

**Total suite result:** **149 tests, 391 assertions, 0 failures** (Pest labels them deprecated because of a PHP 8.5 PDO constant notice).

### Live Staging Environment Verification (Conducted Oct 14, 2026)
To confirm operational behavior on live infrastructure (Railway staging with background workers), an end-to-end verification of Separation of Duties and audit logging was executed:
1. **Application Lifecycle Progression:** An applicant was progressed through Document Evaluation (`performed_by` recorded for Admin), Grade Evaluation, and Interview.
2. **Medical Clearance Simulation:** The external Medical System webhook was simulated via M2M OAuth Client Credentials (`medical-write` scope) and raw-body HMAC-SHA256 signature to `POST /api/v1/webhooks/medical-result`. Railway's `high` queue background worker processed the job asynchronously, transitioning the application status to `cleared_for_enrollment` and auto-generating the `records` process row in `in_progress` status.
3. **COR Document Upload:** Applicant's Certificate of Registration (COR Front and Back) was verified to ensure the prerequisite check in `tag()` succeeded before the SoD check.
4. **Separation of Duties Verification (The Security Block):** Logged in as the Admin who performed the document evaluation. Navigated to Records Tagging and clicked "Tag as Officially Enrolled" with reason. The server immediately aborted with **HTTP 403 Forbidden**, displaying the UI alert:
   > *"Separation of duties violation: You cannot tag an applicant whose evaluation or interview you personally performed."*
5. **Legitimate Role Tagging (The Success):** Logged in as the Registrar, who was not an evaluator for this applicant. Submitted the tag request with reason `"officially enrolled"`. The system tagged the applicant as **Accepted / Officially Enrolled** (toast notification displayed; action button updated to *"Untag / Revert"*).
6. **Synchronous Audit Trail Verification:** Queried the staging database `audit_logs` table:
   ```sql
   SELECT id, user_id, username, description, created_at FROM audit_logs WHERE description LIKE 'Tagged applicant%' ORDER BY id DESC LIMIT 1;
   ```
   Confirmed the audit record was inserted synchronously within the same transaction by the Registrar account with description recording official enrollment tagging. The entry immediately appeared in the SuperAdmin Audit Logs UI with masked PII.

---

## 6. Limitations / Open Gaps

### Gap 1: Lack of Separation of Duties (Admin & SuperAdmin Multi-Stage Actions)
- **Severity:** High
- **Description:** Previously, no check existed in any controller or service to prevent the same individual user from acting at multiple stages of the same application. A single Admin or SuperAdmin user could evaluate, interview, change courses, and tag enrollment for the same applicant without oversight.
- **Implemented Fix (Commit B & Fix Branch):** Separation of Duties (SoD) is now strictly enforced in `RecordStaffDashboardController::tag()`. Any staff member whose ID matches `performed_by` on that application's `ApplicationProcess::EVALUATION_STAGES` (`document_evaluator`, `grade_evaluator`, `interviewer`) is blocked with HTTP 403 (`Separation of duties violation: You cannot tag an applicant whose evaluation or interview you personally performed.`).
- **Evaluator Dashboard View All Stage Preservation:** In `Dashboard/Evaluator.vue`, the "View All" link was previously hardcoded to `/evaluator-applications`, which caused an Admin navigating from `/document-evaluator-dashboard` to fall back to the grade evaluator stage. This was fixed by computing `viewAllLink`: pointing to `/document-evaluator-applications` when `currentStage === 'document_evaluator'`, and `/evaluator-applications?stage=` + currentStage otherwise.
- **Deliberate Policy Consequence:** On a small admissions team operating with a single Admin account, an Admin who personally evaluates a document or conducts an interview can **no longer tag that applicant as officially enrolled**. This is a deliberate design rule enforcing the four-eyes principle: final enrollment tagging for that applicant must be executed by another authorized user (e.g., a Registrar or SuperAdmin). Untagging remains available without SoD restrictions to avoid administrative deadlocks during emergency reversions.

### Gap 2: Program Scoping Missing on Review Locks, Grades, Files, and Course Transfers
- **Severity:** Medium
- **Description:** While `passApplication`, `rejectApplication`, `accept`, and `reject` verify `$user->programs()`, peripheral review and document endpoints were initially unscoped.
- **Implemented Fix (Commit A & Scoping Update):** Program scoping is now strictly enforced for roles 3 (Document Evaluator) and 4 (Interviewer) on `user-files` (`ManagesApplicationFiles.php:21`), `user-grades` (`ManagesApplicationFiles`), `start-review` (`EvaluatorDashboardController`), and `lazy-load` document/batch/grade endpoints (`LazyLoadController`). Roles 2 (Admin), 5 (Medical), 6 (Registrar), 7 (SuperAdmin), and 8 (Grade Evaluator) remain deliberately exempt. For `user-files` and `lazy-load`, access is granted if ANY of the applicant's applications belongs to the evaluator's assigned programs; applicants with zero applications are rejected with HTTP 403.
- **Documented Limitations (Remaining Unscoped Endpoints):**
  - `POST /record-dashboard/change-course/{applicantId}`: Unscoped (cross-role by design; allows interviewers to transfer an applicant to an alternate program choice across departmental boundaries).
  - `POST /interviewer-dashboard/start/{id}` and `cancel/{id}`: Unscoped (future work; transient review session locking, while binding admission decision routes `accept`/`reject` remain strictly program-scoped).
  - `POST /evaluator/cancel-review/{applicationProcess}`: Unscoped (future work; transient session lock release resetting reviewer state).

### Gap 3: Information Disclosure on `/check-email` Route
- **Severity:** Medium
- **Description:** `POST /check-email` (`puptas/routes/web.php:298-303`) was previously protected only by `middleware('auth')`. Any authenticated user, including applicants, could submit arbitrary email addresses to check whether an account or test passer record existed in the system.
- **Implemented Fix (Step 2):** Restricted route exclusively to SuperAdmin (role 7) and applied a per-user rate limit of 30 requests/minute (`throttle:30,1`).

### Gap 4: Missing Role Guard on Legacy Upload Form View
- **Severity:** Low
- **Description:** `GET /test-passers/form` (`puptas/routes/web.php:486`) uses only `middleware(['auth'])`. An applicant or interviewer can load the Inertia view `Uploads/Form`. However, the actual processing route `POST /test-passers/upload` is guarded by `EnsureAdmin::class`.
- **Proposed Fix:** Apply `EnsureAdmin::class` directly to `GET /test-passers/form` or remove the obsolete route.

### Gap 5: SuperAdmin Omission in Lazy Load Route Definition
- **Severity:** Low
- **Description:** `puptas/routes/web.php:650` registers lazy-loading document routes under `role:2,3,4,6,8`. SuperAdmin (`role_id = 7`) is omitted from the route middleware parameter even though `LazyLoadController::STAFF_ROLE_IDS` includes ID 7. SuperAdmin accounts navigating the evaluation queue trigger 403 on lazy-loaded file calls.
- **Proposed Fix:** Update the route middleware to `role:2,3,4,6,7,8`.

### Gap 6: UserController Redirects Instead of HTTP 403
- **Severity:** Low
- **Description:** Inside `UserController`, actions restricted to SuperAdmin (create, store, update, destroy, deactivate) redirect with a flash message rather than throwing a standard 403 exception when accessed by an Admin (role ID 2).
- **Proposed Fix:** Replace `return redirect()->route('users.index')->with('error', ...)` with `abort(403, 'Unauthorized action.')` or extract dedicated routes to an `EnsureSuperAdmin` middleware group.

### Gap 7: Asynchronous Audit Logging Everywhere Except Enrollment Tag and Untag
- **Severity:** Medium
- **Description:** Across the application, `AuditLogService::logActivity()` dispatches audit log writes asynchronously via queued job `ProcessAuditLog::dispatch()->afterCommit()` and swallows exceptions. If a queue worker is delayed, halted, or encounters database constraints, audit log writes can fail silently after the HTTP transaction has already committed. Following Commit C, only `RecordStaffDashboardController::tag()` and `untag()` write audit records synchronously inside the active database transaction (`AuditLogService::logActivitySync()`), guaranteeing that an audit write failure immediately rolls back the enrollment status change (`DB::rollBack()`). All other system operations (admissions review, grade changes, authentication events, user role edits) remain asynchronous and vulnerable to silent audit row omissions if workers fail.
- **Architectural Tradeoff (Fail-Closed Enrollment Audit):** Unlike the asynchronous best-effort audit queue used elsewhere, enrollment `tag()` and `untag()` are now strictly **fail-closed**. If the synchronous audit log write fails for any reason (e.g., database constraint violation, disk exhaustion, or connection drop), the entire database transaction rolls back and the tag request is rejected with HTTP 500. This deliberate tradeoff prioritizes absolute regulatory audit integrity over operation availability: no applicant can transition to officially enrolled without an atomic, permanent audit record.
- **Proposed Fix:** For other high-compliance operations (such as grade modifications, applicant rejection, and user role grants), migrate from asynchronous queue dispatch to transactional synchronous writes (`logActivitySync()`).

### Gap 8: Legacy Bulk-Enroll Developer Harness and Dormant Enrollment Setters
- **Severity:** Medium
- **Description:** An endpoint previously existed at `POST /test-passers/bulk-enroll` (`TestPasserController::bulkEnroll`), originally introduced to replace a mock guidance account CLI seeder for developer testing. This endpoint bypassed all pipeline integrity controls: it set a hardcoded default password (`Password123`), fabricated passing records across all five admission stages with notes `[GUIDANCE-BULK-ENROLL]`, performed no checks for medical clearance or COR document uploads, lacked Separation of Duties enforcement, and logged only a single asynchronous batch audit summary without per-applicant records or reasons. Additionally, two dormant methods containing `officially_enrolled` update logic were identified in the codebase: `RecordStaffDashboardController::submitRecordsProcess` (an unrouted controller action) and `ApplicationService::tagAsOfficiallyEnrolled` (an orphaned service helper with zero call sites).
- **Deployment Database Audit Findings:** `audit_logs` on the deployment database show the endpoint ran 3 times on May 10–11, 2026 (4, 25, and 1 passers; 30 total) from a SuperAdmin account. Checks done on the deployment database confirmed:
  - No `application_processes` rows with `BULK-ENROLL` notes remain.
  - No accounts were created or updated in that window.
  - No account tied to a test-passer email still has the default password.
  - The `ApiTestStudentsSeeder` users do not exist.
  - **Not verifiable:** Whether any real account's grades were overwritten cannot be verified from available logs.
- **Implemented Fix:** The `POST /test-passers/bulk-enroll` route, the `TestPasserController::bulkEnroll` method, and the dead frontend state and results modal in `TestPassers/Email.vue` were excised completely. Route constraints (`whereNumber('test_passer')`) were applied to route model binding parameters on sibling routes to ensure that `POST /test-passers/bulk-enroll` returns an unambiguous HTTP 404 Not Found for all roles, verified by `BulkEnrollDisabledTest` (3/3 passing).
- **Documented Limitations (Dormant Dead Code):** `RecordStaffDashboardController::submitRecordsProcess` and `ApplicationService::tagAsOfficiallyEnrolled` remain in the codebase as unreachable dead code. Because they are not exposed to HTTP routing and have no callers, they cannot be triggered by any user role or external request.

---

## 7. Verdict
**VERIFIED WITH GAPS (PANEL FIXES APPLIED & DEPLOYED TO STAGING)**
Server-side RBAC is actively enforced across all major route groups via middleware guards (`RoleMiddleware`, `EnsureAdmin`, `EnsureSuperAdmin`, `EnsureAdminOrRegistrar`), model-level mass-assignment protection, webhook HMAC signature verification, and environment guards on diagnostic routes. All fixes have been merged into `staging` and verified on live infrastructure:
1. `POST /check-email` is restricted to SuperAdmin (role 7) with a 30 req/min rate limit (`CheckEmailSecurityTest` passing 12/12).
2. Enrollment tagging and untagging require mandatory reasons (5-500 chars), with Separation of Duties enforced on `tag()` across `document_evaluator`, `grade_evaluator`, and `interviewer` (`EnrollmentTagAuditTest` passing 33/33).
3. Evaluator dashboard "View All" link preserves evaluation stages, routing Document Evaluators and Admins directly to `/document-evaluator-applications` (`EvaluatorApplicationsStageTest` passing 6/6).
4. Program scoping is enforced for roles 3 and 4 on `user-files`, `user-grades`, `start-review`, and `lazy-load` endpoints while preserving exemptions for administrative roles (`ProgramScopingReadTest` passing 32/32).
5. All 6 dedicated security test suites execute cleanly in a single command: **149 tests, 391 assertions, 0 failures** (Pest labels them deprecated because of a PHP 8.5 PDO constant notice).
6. Live staging demonstration confirmed end-to-end: Admin evaluator was blocked by SoD with HTTP 403, Registrar successfully completed enrollment, and synchronous audit log was atomically recorded.
7. As documented in Section 6 (Gap 7), audit logging remains asynchronous everywhere across the codebase except for enrollment `tag()` and `untag()`.
8. The legacy developer test harness `POST /test-passers/bulk-enroll` has been completely excised along with its dead frontend state. No HTTP route other than `RecordStaffDashboardController::tag()` sets official enrollment (`enrollment_status = 'officially_enrolled'` or `applications.status = 'accepted'`); the seeders (`ApplicantSeeder`, `ApiTestStudentsSeeder`) are CLI-only (`php artisan db:seed`). Dormant dead code in `submitRecordsProcess` and `ApplicationService::tagAsOfficiallyEnrolled` is verified to be unreachable via HTTP routing.


