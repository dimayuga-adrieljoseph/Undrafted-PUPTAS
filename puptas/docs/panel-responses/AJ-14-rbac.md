# Question #14: Server-Side Role-Based Access Control (RBAC)

## 1. Panel Question
> "You have multiple roles—Applicant, Administrator, Document Evaluator, Grade Evaluator, Interviewer, Medical Personnel, Registrar, and Super Administrator. Paano ninyo pinipigilan yung isang user from accessing functions outside their role? Beyond hiding menu items, magandang actual server-side authorization ang maipakita."

## 2. Direct Answer
PUPTAS enforces server-side role-based access control primarily through route-level HTTP middleware, policy gates, and controller authorization checks rather than client-side UI visibility. Every authenticated request resolves the user's single assigned role ID from the `users.role_id` database column and evaluates it against middleware guards (`RoleMiddleware`, `EnsureAdmin`, `EnsureSuperAdmin`, and `EnsureAdminOrRegistrar`). If a user attempts to access an endpoint outside their permitted roles, the middleware terminates execution with an HTTP 403 Forbidden abort before any controller action runs. For record mutations and sensitive file downloads, object-level authorization ensures callers can only manipulate records they own or have explicit stage authority over. Where fine-grained workflow constraints exist, such as program changes, Laravel authorization policies (`ApplicationPolicy::changeCourse`) evaluate both the user's role and the application's current state.

However, detailed code inspection reveals critical scoping and segregation gaps: while admission decision routes (`pass-application`, `reject-application`, `accept`, `reject`) restrict evaluators to their assigned academic programs via the `program_user` pivot table, review locking (`start-review`, `interviewer start`), course transfers (`change-course`), file inspections (`user-files`), grade reads (`user-grades`), and lazy loading are **NOT program-scoped**. Furthermore, there is **no separation of duties enforcement**: an Administrator (Role 2) or SuperAdmin (Role 7) can sequentially evaluate, interview, change courses for, and tag as officially enrolled the exact same applicant without any system-level block.

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
       │               ├─ Evaluator pass/reject/flag: SCOPED (403 if unassigned)
       │               ├─ Interviewer accept/reject: SCOPED (403 if unassigned)
       │               └─ Start review / files / grades / course change: NOT SCOPED
       ▼
 [Controller Gate] ────► Policy check: $this->authorize('changeCourse', $application)
       │               OR Object ownership check ($user->id === $record->user_id)
       ▼
 Database / Service Layer
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
   In the current routing and middleware structure, an Admin or SuperAdmin user possesses simultaneous authorization to execute every single stage of an application's lifecycle:
   - **Document Evaluation:** Can execute `passApplication`, `rejectApplication`, `flagApplication`, and `returnApplication` (`routes/web.php:565, 577-583`).
   - **Interview:** Can execute `start`, `cancel`, `accept`, and `reject` (`routes/web.php:589, 605-608`).
   - **Course Transfer:** Can execute `changeCourse` via `RecordStaffDashboardController::changeCourse` (`routes/web.php:686-688`, authorized unconditionally by `ApplicationPolicy.php:24-27`).
   - **Enrollment Tagging:** Can execute `tag` and `untag` as officially enrolled via `RecordStaffDashboardController::tag` (`routes/web.php:628-630`).
2. **Cross-Stage `performed_by` Verification:**
   Code inspection reveals that every controller action records the actor's ID into `application_processes.performed_by` (`auth()->id()`) solely for audit and presentation purposes (`EvaluatorDashboardController.php:178, 268, 333`; `InterviewerDashboardController.php:151, 298`; `RecordStaffDashboardController.php:462, 590, 642`).
   **Finding:** **There is ZERO enforcement preventing the same individual user from acting across multiple stages.** A single Administrator or SuperAdministrator account can evaluate an applicant's documents, approve their interview, transfer their course, and tag them as officially enrolled from start to finish without any system check or separation of duties constraint.

### Program Scoping Analysis across Staff Routes
The table below inspects every controller method taking a `{userId}`, `{id}`, or `{applicationProcess}` parameter used by Document Evaluator, Grade Evaluator, Interviewer, or Registrar:

| Route | Controller:Lines | Scoped / NOT Scoped / N/A | Note |
| :--- | :--- | :--- | :--- |
| `POST /evaluator/pass-application/{userId}` | `EvaluatorDashboardController.php:150-159` | **SCOPED** | Restricts Document Evaluators to programs assigned via `$user->programs()` pivot table. Admin (2), SuperAdmin (7), and Grade Evaluator (8) bypass. |
| `POST /evaluator/reject-application/{userId}` | `EvaluatorDashboardController.php:237-246` | **SCOPED** | Restricts Document Evaluators to assigned programs via `$user->programs()`. Admin, SuperAdmin, and Grade Evaluator bypass. |
| `POST /evaluator/flag-application/{userId}` | `EvaluatorDashboardController.php:298-307` | **SCOPED** | Restricts Document Evaluators to assigned programs via `$user->programs()`. Admin, SuperAdmin, and Grade Evaluator bypass. |
| `POST /evaluator/start-review/{applicationProcess}` | `EvaluatorDashboardController.php:347-362` | **NOT SCOPED** | Sets `reviewed_by = auth()->id()` without checking if applicant's `program_id` is assigned to the reviewer. Any evaluator can lock any review. |
| `POST /evaluator/cancel-review/{applicationProcess}` | `EvaluatorDashboardController.php:364-382` | **NOT SCOPED** | Resets `reviewed_by = null` without checking reviewer's program assignment. |
| `POST /interviewer-dashboard/accept/{id}` | `InterviewerDashboardController.php:210-221` | **SCOPED** | Validates `$validated['program_id']` against interviewer's assigned programs via `$user->programs()`. Admin/SuperAdmin bypass. |
| `POST /interviewer-dashboard/reject/{id}` | `InterviewerDashboardController.php:334-345` | **SCOPED** | Validates `$validated['program_id']` against interviewer's assigned programs via `$user->programs()`. Admin/SuperAdmin bypass. |
| `POST /interviewer-dashboard/start/{id}` | `InterviewerDashboardController.php:121-159` | **NOT SCOPED** | Checks only that `grade_evaluator` stage is complete; does NOT verify if interviewer is assigned to applicant's program. |
| `POST /interviewer-dashboard/cancel/{id}` | `InterviewerDashboardController.php:161-193` | **NOT SCOPED** | Clears `started_at` and `performed_by` without verifying interviewer's program assignment. |
| `POST /record-dashboard/tag/{id}` | `RecordStaffDashboardController.php:547-564` | **N/A** | Enrollment tagging is institution-wide across all programs. The Registrar role is institution-wide. |
| `POST /record-dashboard/untag/{id}` | `RecordStaffDashboardController.php:614-625` | **N/A** | Enrollment untagging is institution-wide across all programs. |
| `POST /dashboard/return-files/{user}` | `ManagesApplicationFiles.php:395-400` | **SCOPED** | Maps to `returnApplication()`, which checks `$assignedProgramIds = Auth::user()->programs()` and returns 403 if unassigned. (Note: trait also contains an unused `returnFiles()` method at lines 305-373 that lacks program check). |
| `GET /dashboard/user-files/{id}` | `ManagesApplicationFiles.php:60-81` | **NOT SCOPED** | Verifies stage prerequisite only; any evaluator or interviewer can inspect uploaded files of applicants outside their assigned programs. |
| `GET /dashboard/user-grades/{id}` | `ManagesApplicationFiles.php:259-278` | **NOT SCOPED** | Checks staff role only; returns grades for any applicant ID without verifying program assignment or workflow stage. |
| `GET /api/lazy-load/document/{userId}/{fileType}` | `LazyLoadController.php:31-35` | **NOT SCOPED** | Checks `in_array(role_id, STAFF_ROLE_IDS)` only. Does not verify whether applicant belongs to staff user's program. |
| `POST /api/lazy-load/documents-batch/{userId}` | `LazyLoadController.php:89-93` | **NOT SCOPED** | Checks `STAFF_ROLE_IDS` only. Does not verify applicant program assignment. |
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
| `/check-email` | POST | `auth` | All authenticated | Checks email existence in users/passers (FLAGGED) |
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
| Evaluator start review unscoped | `puptas/app/Http/Controllers/EvaluatorDashboardController.php:347-362` | Sets `reviewed_by` without program check |
| Interviewer accept program check | `puptas/app/Http/Controllers/InterviewerDashboardController.php:210-221` | Checks `$validated['program_id']` against `$user->programs()` |
| Interviewer start unscoped | `puptas/app/Http/Controllers/InterviewerDashboardController.php:121-159` | Verifies stage only, no program assignment check |
| Application files preview unscoped | `puptas/app/Http/Traits/ManagesApplicationFiles.php:60-81` | Stage prerequisite check only, no program filter |
| Lazy load documents unscoped | `puptas/app/Http/Controllers/LazyLoadController.php:31-35, 89-93` | Gated by `STAFF_ROLE_IDS` only, no program filter |
| ApplicationPolicy::changeCourse logic | `puptas/app/Policies/ApplicationPolicy.php:22-41` | Allows 2, 7; restricts 4 to pending/unaccepted |
| Policy enforcement on change-course | `puptas/app/Http/Controllers/RecordStaffDashboardController.php:415` | Calls `$this->authorize('changeCourse', $application)` |
| Absence of cross-stage performed_by block | `puptas/app/Http/Controllers/EvaluatorDashboardController.php:178`, `puptas/app/Http/Controllers/InterviewerDashboardController.php:298`, `puptas/app/Http/Controllers/RecordStaffDashboardController.php:590` | Sets `performed_by`; zero checks block multi-stage action by same user |

---

## 5. Test Evidence

### Centralized Automated Matrix Test (`tests/Feature/RoleAccessMatrixTest.php`)
To provide irrefutable proof of server-side enforcement across all roles and route groups, an automated matrix test was implemented on local branch `panel-fixes/aj-14-role-matrix`. The test exercises **7 representative route groups** against **Roles 1 through 8 plus unauthenticated guests** (63 distinct authorization test cases):

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

### Execution Results:
When executed via Pest enabling the local SQLite extension (`php -d extension_dir=C:\php\ext -d extension=pdo_sqlite vendor/pestphp/pest/bin/pest --filter=RoleAccessMatrixTest`):
```text
   DEPR  Tests\Feature\RoleAccessMatrixTest
  ! role access matrix with data set "role:applicant | guest"
  ! role access matrix with data set "role:applicant | role 1 applicant"
  ... [all 63 data-provider test cases]
  ! role access matrix with data set " ensure admin or registrar | role 8 grade eval"

  Tests:    63 passed (70 assertions)
  Duration: 3.36s
```
*(Note: When invoked via `php artisan test --filter=RoleAccessMatrixTest`, the command was `NOT RUN` to completion due to local CLI environment configuration: artisan spawns a child PHP process without inheriting Windows CLI `-d` flags, causing SQLite connection errors in the local shell unless `extension=pdo_sqlite` is permanently uncommented in `C:\php\php.ini`).*

---

## 6. Limitations / Open Gaps

### Gap 1: Lack of Separation of Duties (Admin & SuperAdmin Multi-Stage Actions)
- **Severity:** High
- **Description:** No check exists in any controller or service to prevent the same individual user from acting at multiple stages of the same application. A single Admin or SuperAdmin user can evaluate, interview, change courses, and tag enrollment for the same applicant without oversight.
- **Proposed Fix:** Add a validator rule or domain guard: `abort_if($application->processes()->where('performed_by', auth()->id())->exists(), 403, 'Separation of duties violation: cannot act on an application you previously processed.');`.

### Gap 2: Program Scoping Missing on Review Locks, Grades, Files, and Course Transfers
- **Severity:** Medium
- **Description:** While `passApplication`, `rejectApplication`, `accept`, and `reject` verify `$user->programs()`, peripheral endpoints (`startReview`, `interviewer start`, `getUserFiles`, `getUserGrades`, `LazyLoadController`, and `changeCourse`) do not verify that the target applicant belongs to the caller's assigned academic programs.
- **Proposed Fix:** Create a reusable authorization policy method `$this->authorize('manageProgram', $application->program_id)` and apply it across all staff file, grade, review-lock, and transfer routes.

### Gap 3: Information Disclosure on `/check-email` Route
- **Severity:** Medium
- **Description:** `POST /check-email` (`puptas/routes/web.php:298-303`) is protected only by `middleware('auth')`. Any authenticated user, including applicants, can submit arbitrary email addresses to check whether an account or test passer record exists in the system.
- **Proposed Fix:** Restrict the route to administrative roles (`EnsureAdmin::class`) or throttle heavily and restrict to unauthenticated registration contexts.

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
- **Proposed Fix:** For other high-compliance operations (such as grade modifications, applicant rejection, and user role grants), migrate from asynchronous queue dispatch to transactional synchronous writes (`logActivitySync()`).

---

## 7. Verdict
**VERIFIED WITH GAPS (PANEL FIXES APPLIED)**
Server-side RBAC is actively enforced across all major route groups via middleware guards (`RoleMiddleware`, `EnsureAdmin`, `EnsureSuperAdmin`, `EnsureAdminOrRegistrar`), model-level mass-assignment protection, webhook HMAC signature verification, and environment guards on diagnostic routes. On local branch `panel-fixes/aj-14-fixes`:
1. `POST /check-email` is restricted to SuperAdmin (role 7) with a 30 req/min rate limit (`CheckEmailSecurityTest` passing 12/12).
2. Enrollment tagging and untagging require mandatory reasons (5-500 chars), with separation of duties enforced on `tag()` (evaluators/interviewers cannot tag applicants they personally reviewed) and synchronous transactional audit writes (`EnrollmentTagAuditTest` passing 31/31).
3. Program scoping is enforced for roles 3 and 4 on `user-grades`, `start-review`, and `lazy-load` endpoints while preserving exemptions for roles 2, 5, 6, 7, and 8 (`ProgramScopingReadTest` passing 18/18).
4. As documented in Section 6 (Gap 7), audit logging remains asynchronous everywhere across the codebase except for enrollment `tag()` and `untag()`.
