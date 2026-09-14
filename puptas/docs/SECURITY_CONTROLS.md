# Security Controls Documentation

## PUPT Admission System (PUPTAS) — Security Evidence Portfolio

> **Compliance Target:** Pre-Oral Defense Checklist §3 — *"Evidence of authentication, access control (RBAC), and SSL/HTTPS encryption."*
>
> **Last Updated:** August 23, 2026

---

## 1. Authentication

### 1.1 Identity Provider (IDP) Integration

The system authenticates users through the **PUP IDP (Identity Provider)** via OAuth 2.0. Users do not manage passwords within PUPTAS — all credential handling is delegated to the university's centralized identity platform.

| Component | Implementation |
|---|---|
| **OAuth 2.0 Flow** | IDP redirect via `/auth/idp/redirect` → token exchange → local session |
| **Token Refresh** | Middleware `RefreshIdpToken` automatically refreshes expired IDP tokens |
| **Session Management** | Laravel's encrypted session cookies with configurable lifetime |
| **CSRF Protection** | Active on all state-changing requests via Laravel's `VerifyCsrfToken` middleware |

**Key Files:**
- `app/Http/Middleware/RefreshIdpToken.php` — Automatic IDP token refresh
- `app/Http/Middleware/HandleInertiaRequests.php` — Shares auth state to frontend

### 1.2 API Authentication (OAuth 2.0 — Laravel Passport)

External API consumers authenticate via **Laravel Passport** (OAuth 2.0 server):

| Feature | Detail |
|---|---|
| **Grant Types** | Authorization Code, Client Credentials |
| **Token Storage** | Encrypted, database-backed access/refresh tokens |
| **Scopes** | Fine-grained API scopes per client |
| **Middleware** | `auth:api` guard on all API routes |

**Key Files:**
- `composer.json` → `laravel/passport: ^12.0`
- `config/auth.php` — Guard configuration

---

## 2. Access Control (RBAC)

### 2.1 Role-Based Access Control

The system implements a **multi-layered RBAC** system with the following enforcement points:

| Layer | Mechanism | File |
|---|---|---|
| **Route Middleware** | `RoleMiddleware` — accepts numeric IDs or symbolic names | `app/Http/Middleware/RoleMiddleware.php` |
| **Admin Guard** | `EnsureAdmin` — restricts to Admin + SuperAdmin roles | `app/Http/Middleware/EnsureAdmin.php` |
| **Registrar Guard** | `EnsureAdminOrRegistrar` — restricts to Admin, Registrar, SuperAdmin | `app/Http/Middleware/EnsureAdminOrRegistrar.php` |
| **SuperAdmin Guard** | `EnsureSuperAdmin` — restricts to SuperAdmin only | `app/Http/Middleware/EnsureSuperAdmin.php` |
| **Frontend Route Guard** | Vue Router + Inertia.js shared props restrict UI navigation by `role_id` | `resources/js/Layouts/*Layout.vue` |

### 2.2 Role Definitions

Roles are defined via the `RoleId` enum (`app/Enums/RoleId.php`):

| Role | ID | Access Level |
|---|---|---|
| Applicant | 1 | Applicant dashboard, own records only |
| Admin | 2 | Full system administration |
| DocumentEvaluator | 3 | Document verification and evaluation |
| Interviewer | 4 | Interview scheduling and evaluation |
| ~~Medical~~ | ~~5~~ | *Not an in-system role — medical clearance is handled by an external Medical System via OAuth API and HMAC webhook integration (see §2.4)* |
| Registrar | 6 | Registration and SAR management |
| SuperAdmin | 7 | Unrestricted access to all features |
| GradeEvaluator | 8 | Grade verification and evaluation |

### 2.4 External Medical System Integration

The medical clearance stage is **not handled by an in-system user role**. Instead, an external Medical System communicates with PUPTAS via a secure API:

| Direction | Mechanism | Security |
|---|---|---|
| **Medical reads applicant data** | `GET /api/v1/medical/applicants` | OAuth 2.0 Client Credentials with `medical-read` scope |
| **Medical writes results back** | `POST` webhook endpoint | OAuth 2.0 with `medical-write` scope + `X-Medical-Signature` HMAC-SHA256 verification + timestamp/nonce replay protection |

**Key Files:**
- `app/Http/Controllers/ExternalMedicalApiController.php` — API endpoints for the external Medical System
- `app/Http/Middleware/VerifyMedicalWebhookSignature.php` — HMAC signature + replay protection middleware
- `app/Jobs/ProcessMedicalWebhookJob.php` — Async processing of medical results

### 2.5 Unauthorized Access Handling

All middleware aborts with **HTTP 403 Forbidden** for unauthorized role access. Each middleware returns a context-specific message (e.g., `"Unauthorized action."`, `"Access denied. Admin privileges required."`, `"Access denied. Superadmin privileges required."`). Unauthenticated users are redirected to `/login`.

---

## 3. SSL/HTTPS Encryption

### 3.1 Transport Layer Security

| Control | Implementation |
|---|---|
| **HTTPS Enforcement** | All production traffic served over HTTPS via Railway platform |
| **HSTS Header** | `Strict-Transport-Security: max-age=31536000; includeSubDomains; preload` |
| **Upgrade Insecure Requests** | CSP directive `upgrade-insecure-requests` forces HTTP→HTTPS |

### 3.2 HTTP Security Headers

The `SecurityHeaders` middleware (`app/Http/Middleware/SecurityHeaders.php`) applies comprehensive security headers to all production responses:

| Header | Value | Protection |
|---|---|---|
| **Content-Security-Policy** | Strict allowlists for scripts, styles, fonts, images, connections | XSS, resource injection |
| **X-Frame-Options** | `DENY` (default) / `SAMEORIGIN` (SAR preview only) | Clickjacking |
| **X-Content-Type-Options** | `nosniff` | MIME-sniffing attacks |
| **Strict-Transport-Security** | 1 year, includeSubDomains, preload | Protocol downgrade, cookie hijacking |
| **Referrer-Policy** | `strict-origin-when-cross-origin` | Referrer leakage |
| **Permissions-Policy** | All sensitive APIs denied (camera, geolocation, microphone, etc.) | Browser feature abuse |

### 3.3 Information Leakage Prevention

The following headers are **removed** from all responses:
- `X-Powered-By`
- `Server`

Error messages are sanitized to prevent internal implementation details from reaching the client (see `SECURITY_FIXES.md`).

---

## 4. Data Protection at Rest

| Control | Implementation |
|---|---|
| **Session Storage** | Database-backed sessions (`SESSION_DRIVER=database`) with `secure`, `http_only`, and `same_site=lax` cookie flags |
| **Password Hashing** | Managed by PUP IDP — passwords are never stored in PUPTAS |
| **File Storage** | S3-compatible object storage with `visibility=private` for SAR, GVS, and F137 documents |
| **Environment Secrets** | `.env` file excluded from version control (`.gitignore`), secrets managed per environment |

---

## 5. Audit Trail

| Feature | Evidence |
|---|---|
| **Consent Tracking** | `privacy_consent_at` timestamp recorded per user acceptance |
| **SAR Generation Audit** | `created_by_user_id` + `email_sent_successfully` tracked per SAR |
| **Login Activity** | IDP-managed authentication logs |
| **Error Logging** | Centralized Laravel logging with sanitized error responses |

---

## 6. Verification Instructions for Panel

To verify security controls during the live demo:

### Authentication
1. Visit the system without logging in → redirected to IDP login
2. Attempt to access `/admin/*` routes → redirected to `/login`

### RBAC
1. Log in as **Applicant** → only see applicant dashboard
2. Attempt to navigate to `/admin/sar-generations` → receive 403 Forbidden
3. Log in as **Admin** → full admin panel access

### HTTPS/Security Headers
1. Open browser DevTools → Network tab → inspect response headers
2. Verify presence of: `Content-Security-Policy`, `Strict-Transport-Security`, `X-Frame-Options`
3. Verify absence of: `X-Powered-By`, `Server`

### Consent
1. Reset a user's consent: `php artisan privacy:reset --user-id=1`
2. Log in as that user → Terms & Conditions modal appears (non-dismissible)
3. User must check "I Agree" checkbox before proceeding

---

## 7. OSS Dependencies & Security

All third-party dependencies are licensed under permissive open-source licenses:

| Package | License | Purpose |
|---|---|---|
| Laravel Framework | MIT | Backend framework |
| Vue.js 3 | MIT | Frontend framework |
| Inertia.js | MIT | SPA bridge |
| Laravel Passport | MIT | OAuth 2.0 API authentication |
| Laravel Jetstream | MIT | Auth scaffolding |
| TailwindCSS | MIT | UI styling |
| Chart.js | MIT | Data visualization |

Full dependency list available in `composer.json` and `package.json`.
