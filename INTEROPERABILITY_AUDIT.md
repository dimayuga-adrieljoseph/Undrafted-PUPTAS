# PUPTAS — Interoperability & Data Exchange Audit

> **Requirement under audit:** *"Provide evidence supporting the claimed interoperability and data exchange capabilities, including actual interfaces, APIs, data mappings, or successful integration tests where applicable."*

| Audit Metadata | Value |
|---|---|
| **System** | PUPTAS (PUP–Taguig Admission System) |
| **Stack** | Laravel 11 / PHP 8.4 / Inertia + Vue 3 / MySQL / Redis / Passport |
| **Repository** | `Undrafted-PUPTAS` (`git@github.com:dimayuga-adrieljoseph/Undrafted-PUPTAS.git`) |
| **Branch / Commit** | `main` @ `52a32ff6` |
| **Audit Date** | 2026-09-29 |
| **Method** | Full static code audit + local test execution (Pest, in-memory SQLite) |
| **Codebase Modifications** | **NONE** — verified via `git status` / `git diff --stat` |
| **Production Access** | **NONE** — no production environment was contacted |
| **Secrets Exposed** | **NONE** — only environment variable *names* are listed; never values |

---

## ⚠️ HEADLINE FINDING (read this first)

> **There is NO evidence in this repository that PUPTAS has ever successfully exchanged data with any real external system.**
>
> The local log file `puptas/storage/logs/laravel.log` contains **0** occurrences of `IDP token exchange successful` and **0** occurrences of `External programs list requested`. No Postman collection, no captured HTTP transcripts, no production screenshots, and no integration test result artifacts exist anywhere in the repository.
>
> Furthermore, **the three automated External API test suites currently FAIL on `main`** — measured 12 failed / 23 passed. Full breakdown in Part 5 and Part 12.

**Evidence classification summary:**

| Category | Count | Integrations |
|---|---|---|
| A. IMPLEMENTED | 11 | All active integrations |
| B. INTERFACE DOCUMENTED | 11 | All active integrations |
| C. DATA MAPPING DOCUMENTED | 10 | ⚠️ 3 handoff docs contradict the code |
| D. AUTOMATED TESTED | 2 (partial) | Webhook replay protection (12/12 green); IDP (1 of 3 green) |
| E. MOCK TESTED | 1 | IDP — **via `Http::fake`** (the OpenRouter mock tests were deleted with the legacy OCR pipeline) |
| F. REAL INTEGRATION VERIFIED | **0** | 🔴 **No integration qualifies** |
| G. PRODUCTION VERIFICATION REQUIRED | **11** | All active integrations |

---

## TABLE OF CONTENTS

| Part | Title |
|---|---|
| [Part 1](#part-1--identify-all-external-integrations) | Identify All External Integrations |
| [Part 2](#part-2--api-and-interface-inventory) | API and Interface Inventory |
| [Part 3](#part-3--data-mapping) | Data Mapping |
| [Part 4](#part-4--trace-the-complete-data-flow) | Trace the Complete Data Flow |
| [Part 5](#part-5--find-existing-tests) | Find Existing Tests |
| [Part 6](#part-6--determine-production-only-dependencies) | Determine Production-Only Dependencies |
| [Part 7](#part-7--classify-the-available-evidence) | Classify the Available Evidence |
| [Part 8](#part-8--identify-exact-evidence-we-already-have) | Identify Exact Evidence We Already Have |
| [Part 9](#part-9--identify-exact-evidence-still-needed) | Identify Exact Evidence Still Needed |
| [Part 10](#part-10--production-test-plan) | Production Test Plan |
| [Part 11](#part-11--evidence-checklist) | Evidence Checklist |
| [Part 12](#part-12--final-report) | Final Report |

---

## 1.6 Chatwoot Live Chat (INBOUND + OUTBOUND)

| Attribute | Value |
|---|---|
| **External system** | Chatwoot |
| **Purpose** | In-app applicant support chat with an AI (Gemini) auto-reply. |
| **PUPTAS components** | `app/Http/Controllers/ChatwootWebhookController.php`, `app/Helpers/ChatwootHelper.php`, `app/Jobs/ProcessChatwootWebhookJob.php` |
| **Endpoints (in)** | `POST /api/webhooks/chatwoot`; `GET /api/chatwoot/widget-config` (`auth:sanctum`) |
| **Endpoint (out)** | `POST {CHATWOOT_BASE_URL}/api/v1/accounts/{accountId}/conversations/{conversationId}/messages` |
| **Auth mechanism** | Inbound: `X-Chatwoot-Signature` HMAC-SHA256 — **fails closed** if secret unset. Outbound: `api_access_token` header. Widget identity: `hash_hmac('sha256', email, CHATWOOT_HMAC_TOKEN)` |
| **Queue / retry** | `high`, 5 tries, exponential backoff `[30, 60, 120, 300, 600]`s. 5xx → retry; 4xx → log and give up. |
| **Production-only?** | **YES** — all five `CHATWOOT_*` variables required |

---

## 1.7 Resend Email Service (OUTBOUND + INBOUND)

| Attribute | Value |
|---|---|
| **External system** | Resend (via `resend/resend-laravel`); delivery callbacks via **Svix** |
| **Purpose** | All applicant/staff email plus delivery-status tracking. |
| **PUPTAS components** | 8 Mailable classes (`app/Mail/*`), `app/Services/EmailTrackingService.php`, `app/Http/Controllers/ResendWebhookController.php` |
| **Endpoint (in)** | `POST /api/webhooks/resend` |
| **Auth mechanism** | Svix headers `svix-id`, `svix-timestamp`, `svix-signature`; 300s replay tolerance; base64 HMAC-SHA256 over `"{svix-id}.{svix-timestamp}.{body}"`; multi-version signature parsing — **fails closed** if secret unset |
| **Production-only?** | **YES** — `RESEND_API_KEY` + `RESEND_WEBHOOK_SECRET`. (`.env.example` ships `MAIL_MAILER=log` as a safe local default.) |

---

## 1.8 Google Gemini API (OUTBOUND)

| Attribute | Value |
|---|---|
| **External system** | Google Generative Language API |
| **PUPTAS components** | `app/Jobs/ProcessChatwootWebhookJob.php::getGeminiResponse` |
| **Endpoint** | `POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={KEY}` |
| **Auth mechanism** | API key in the **query string** |
| **Request** | `{contents:[{parts:[{inline_data:{mime_type,data}}, {text}]}], generationConfig:{maxOutputTokens:1500}}` |
| **Response** | `candidates.0.content.parts.0.text` |
| **Error handling** | 400/401/403/429/503 mapped to distinct `RuntimeException` messages |
| **Production-only?** | **YES** — the Chatwoot job is the only remaining Gemini caller |

---

## 1.9 ~~OpenRouter API (OUTBOUND)~~ — 🗑️ **REMOVED**

> **This integration no longer exists.** `app/Services/OpenRouterClient.php` and `app/Exceptions/OpenRouterApiException.php` were deleted together with the legacy OCR/AI grade-extraction pipeline that was their only caller. The `openrouter` block in `config/services.php` and the `OPENROUTER_API_KEY` / `OPENROUTER_ENDPOINT` / `OPENROUTER_MODEL` environment variables were removed at the same time. The table below is retained only as a record of the original audit.

| Attribute | Value |
|---|---|
| **External system** | OpenRouter (OpenAI-compatible gateway) — *no longer integrated* |
| **Former PUPTAS components** | ~~`app/Services/OpenRouterClient.php::{send, sendText}`~~, ~~`app/Exceptions/OpenRouterApiException.php`~~ |
| **Endpoint** | `POST {OPENROUTER_ENDPOINT}` — default `https://openrouter.ai/api/v1/chat/completions` |
| **Auth mechanism** | `Authorization: Bearer {OPENROUTER_API_KEY}` + `HTTP-Referer: {app.url}` + `X-Title: {app.name}` |
| **Request** | `{model, max_tokens:1500, messages:[{role:'user', content:[{type:'image_url', image_url:{url:'data:{mime};base64,…'}}, {type:'text', text}]}]}` |
| **Response** | `choices.0.message.content` |
| **Production-only?** | **YES** — constructor **throws** if key/endpoint/model missing (`:20-24`) |

---

## 1.10 Cloud Object Storage — AWS S3 / Railway (INFRASTRUCTURE)

| Attribute | Value |
|---|---|
| **Driver** | `league/flysystem-aws-s3-v3` |
| **Disks** | `s3`, `sar_tmp`, `gvs_tmp`, `f137_tmp`, `local`, `public` — `config/filesystems.php:31-120` |
| **PUPTAS components** | `app/Helpers/FileMapper.php`, `GradeVerificationSlipController`, `TestPasserController`, `F137RequestLetterController`, `DataRetentionService` |
| **Environment switch** | `FileMapper.php:342-350` — the `s3` disk is added as a fallback candidate **only when `!app()->environment('local')`**, confirming S3 is the production path |
| **Production-only?** | **YES** — `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT` |

---

## 1.11 Redis — predis (INFRASTRUCTURE / EXTERNAL SERVICE)

| Attribute | Value |
|---|---|
| **Client** | `predis/predis ^3.4` |
| **Uses** | IDP token store (`idp_tokens:user_{id}`, 30-day TTL); pending-registration tokens (`pending_tokens:{uuid}`, 30-min TTL); webhook replay-nonce cache (`webhook_nonce_{n}`, 600s); queue backend; `Cache::lock()` stampede protection |
| **Degradation** | All Redis reads are wrapped in `try/catch` with DB/`SystemSetting` fallbacks, so a Redis outage degrades rather than crashes |
| **Production-only?** | **YES (effectively)** — required for the IDP token lifecycle and correct replay protection |

---

## 1.13 Additional config-only items (NOT integrations)

| Item | Evidence | Verdict |
|---|---|---|
| **PostgreSQL / PgVector** | `POSTGRES_*` in `.env.example`; only the stock `pgsql` connection stub exists (`config/database.php:85-86`). No `pgvector` reference anywhere in `app/`. | **Not an integration** |
| **Jira commit sync** | `jira-commit-sync/` — a standalone Python script, self-described as a *"Temporary tool. Delete this folder once you've finished importing commits."* Outside the Laravel app. | **Dev tooling, not a PUPTAS integration** |

---

> **Update — legacy OCR / Docling removed.** The previous "1.12 Docling Document AI (configured but disabled)" integration, together with the entire AI grade-extraction pipeline it belonged to (`GradeExtractionController`, `GradeExtractionService`, `GeminiClient`, `OpenRouterClient`, `OpenRouterApiException`, the `POST /api/grades/extract` route, the `grade-extraction` rate limiter, the `docling_json` migrations, and the `extractionResult` Inertia prop) has been **deleted from the codebase**. Docling, Tesseract, and OCR are no longer present in any form. The `DOCLING_*` and `OPENROUTER_*` environment variables and their `config/services.php` entries have been removed. This system is no longer an integration point of any kind.

---

# PART 1 — IDENTIFY ALL EXTERNAL INTEGRATIONS

**12 distinct integration points** identified (9 active, 1 configured-but-disabled, 2 infrastructure/config-only).

Search coverage: `Http::*` facade calls, `guzzle`, `curl_*`, `file_get_contents(http…)`, OAuth/OIDC/SAML/SSO patterns, webhook routes, Scribe OpenAPI spec, `config/services.php`, `.env.example`, Artisan commands, jobs, middleware, and the full test tree.

---

## 1.1 IDP — OAuth2 / OIDC Single Sign-On (OUTBOUND + INBOUND)

| Attribute | Value |
|---|---|
| **External system** | Identity Provider, default base `https://identity-provider.isaxbsit2027.com` |
| **Purpose** | All human login/authentication. The IDP owns identity; PUPTAS owns admission data. |
| **PUPTAS components** | `app/Http/Controllers/IdpAuthController.php`, `app/Auth/IdpUser.php`, `app/Auth/IdpUserProvider.php`, `app/Http/Middleware/RefreshIdpToken.php`, `app/Console/Commands/CheckIdpHealth.php`, `app/Http/Middleware/IdpMaintenanceMode.php`, `app/Http/Middleware/EnsureIdpIsDown.php` |
| **Auth mechanism** | OAuth2 Authorization Code grant; `client_secret` sent in POST **body** (not Basic auth); Bearer token for userinfo; CSRF `state` parameter |
| **Request / Response** | JSON / query-string / form-encoded, depending on step; JSON responses |
| **Data exchanged** | Out: `client_id`, `client_secret`, `code`, `redirect_uri`, `refresh_token`, `grant_type`. In: `access_token`, `refresh_token`, `expires_in`; user claims `id`, `email`, `first_name`, `last_name`, `middle_name`, `username`, `role_name` |
| **Environment dependencies** | `IDP_BASE_URL`, `IDP_CLIENT_ID`, `IDP_CLIENT_SECRET`, `IDP_REDIRECT_URI`, `IDP_SCOPE`, `IDP_HEALTH_CHECK_ENABLED`, Redis |
| **Production-only?** | **YES** — requires a pre-registered client, a public HTTPS `redirect_uri` allow-listed by the IDP, and a reachable Redis instance |

**Notable behaviours**

- Emergency bypass: if `system_settings.idp_down_emergency_login_enabled = '1'`, login diverts to OTP (`IdpAuthController.php:26-29`).
- Health monitor: `php artisan idp:check-health` hits `/.well-known/openid-configuration` and caches `idp_status_down` (`CheckIdpHealth.php:45-56`).
- **CSRF weakening:** `IdpAuthController.php:104-118` documents *"WORKAROUND: The IDP drops `state`"* and falls back to mere session existence.

---

## 1.2 PUPTAS Program Catalog API (INBOUND M2M)

| Attribute | Value |
|---|---|
| **External system** | None — PUPTAS is the **provider**. Consumed by external "Program System" / microsites. |
| **Purpose** | Publish active academic programs to partner systems. |
| **PUPTAS component** | `app/Http/Controllers/ExternalProgramApiController.php::index` |
| **Endpoint** | `GET /api/v1/programs` |
| **Auth mechanism** | OAuth2 Client Credentials (Laravel Passport), scope `program-read` |
| **Request / Response** | No request body → `{"data":[{id, code, name}, …]}` |
| **Rate limits** | 5/sec, 5/min (shared bucket), **50/day** |
| **Production-only?** | **YES** — needs `passport:keys` + a provisioned OAuth client |

---

## 1.3 PUPTAS Student Admission API (INBOUND M2M)

| Attribute | Value |
|---|---|
| **External system** | None — provider. Consumed by the "Guidance System". |
| **Purpose** | Look up officially-enrolled students by email or reference number. |
| **PUPTAS components** | `app/Http/Controllers/ExternalStudentApiController.php::{index, showByEmail, showByReferenceNumber}` |
| **Endpoints** | `GET /api/v1/students` *(deprecated → 410)*, `GET /api/v1/students/email/{email}`, `GET /api/v1/students/{referenceNumber}` |
| **Auth mechanism** | OAuth2 Client Credentials, scope `student-read` |
| **Response** | `{"data":{…}}` + `meta` pagination on the list route; `Cache-Control: no-store, no-cache` on the email route |
| **Production-only?** | **YES** — Passport keys + provisioned client |

---

## 1.4 PUPTAS Medical Read API (INBOUND M2M)

| Attribute | Value |
|---|---|
| **External system** | None — provider. Consumed by the Medical System. |
| **Purpose** | Publish applicants who are eligible for the medical examination. |
| **PUPTAS components** | `app/Http/Controllers/ExternalMedicalApiController.php::{index, showByIdpUserId, showByReferenceNumber}`, private `getEligibleApplicantQuery()` |
| **Endpoints** | `GET /api/v1/medical/applicants` *(deprecated → 410, does NOT consume rate limits)*, `GET /api/v1/medical/applicants/idp/{idpUserId}`, `GET /api/v1/medical/applicants/{referenceNumber}` |
| **Auth mechanism** | OAuth2 Client Credentials, scope `medical-read` |
| **Rate limits** | 10/sec, 200/min, 1500/day |
| **Production-only?** | **YES** |

**⚠️ Highest-risk integration.** This endpoint emits PII (email, full name, date_graduated, strand, track, program). See Risk R1 in Part 12.

---

## 1.5 Medical Result Webhook (INBOUND — the only external WRITE path)

| Attribute | Value |
|---|---|
| **External system** | Medical System → PUPTAS |
| **Purpose** | Push medical clearance results back into the PUPTAS admission pipeline. |
| **PUPTAS components** | `app/Http/Middleware/VerifyMedicalWebhookSignature.php`, `ExternalMedicalApiController::webhookResult`, `app/Jobs/ProcessMedicalWebhookJob.php` |
| **Endpoint** | `POST /api/v1/webhooks/medical-result` |
| **Auth mechanism** | **Dual-layered:** (1) OAuth2 `medical-write` scope, **and** (2) `X-Medical-Signature: HMAC-SHA256(raw body, secret)` + `timestamp` (±5 min) + `nonce` (10-min replay cache) |
| **Request** | `{reference_number?, idp_user_id? \| student_id?, is_health_profile_completed, timestamp, nonce}` |
| **Response** | `200 {"message":"Medical webhook received and queued for processing"}` — **asynchronous** |
| **Queue** | `high` priority; processed by `ProcessMedicalWebhookJob` |
| **Production-only?** | **YES** — requires a shared `MEDICAL_WEBHOOK_SECRET` **and** a running `high`-queue worker (`railway.worker.json`) |
# PART 2 — API AND INTERFACE INVENTORY

**13 inbound endpoints** and **10 outbound endpoints**. Nothing was "not determinable" — every integration's protocol is fully readable from source.

> **No secrets or credentials are reproduced anywhere in this document.**

---

## 2A. Inbound endpoints PUPTAS exposes

| Integration | Endpoint | Method | Authentication | Request | Response | Source Files | Environment |
|---|---|---|---|---|---|---|---|
| Passport token | `/oauth/token` | POST | `client_id`+`client_secret` (form) | `grant_type=client_credentials&scope=…` | `{token_type,expires_in,access_token}` | `AppServiceProvider.php:219-223` | Prod (needs keys) |
| Passport token (alias) | `/medical-auth/token` | POST | same | same | same | `AppServiceProvider.php:227-231` | Prod |
| Program Catalog | `/api/v1/programs` | GET | Bearer, scope `program-read` | none | `{data:[{id,code,name}]}` | `routes/api.php:31`, `ExternalProgramApiController.php:42-62` | Prod |
| Student list (**deprecated, 410**) | `/api/v1/students` | GET | Bearer, `student-read` | none | `410` + `Deprecation: true` | `routes/api.php:23` | Prod |
| Student by email | `/api/v1/students/email/{email}` | GET | Bearer, `student-read` | path param | `{data:{…}}` + `Cache-Control: no-store` | `routes/api.php:24`, `ExternalStudentApiController.php:319-402` | Prod |
| Student by ref no. | `/api/v1/students/{referenceNumber}` | GET | Bearer, `student-read` | path param | `{data:{…}}` | `routes/api.php:25`, `ExternalStudentApiController.php:192-271` | Prod |
| Medical list (**deprecated, 410**) | `/api/v1/medical/applicants` | GET | Bearer, `medical-read` | none | `410`, no rate-limit consumed | `routes/api.php:40` | Prod |
| Medical by IDP UUID | `/api/v1/medical/applicants/idp/{idpUserId}` | GET | Bearer, `medical-read` | path param | `{data:{…}}` | `routes/api.php:46` | Prod |
| Medical by ref no. | `/api/v1/medical/applicants/{referenceNumber}` | GET | Bearer, `medical-read` | path param | `{data:{…}}` | `routes/api.php:47` | Prod |
| **Medical webhook** | `/api/v1/webhooks/medical-result` | POST | Bearer `medical-write` + HMAC + ts + nonce | `{reference_number?, idp_user_id?\|student_id?, is_health_profile_completed, timestamp, nonce}` | `200 {message:"Medical webhook received and queued for processing"}` | `routes/api.php:53`, `ExternalMedicalApiController.php:510-552` | Prod |
| Chatwoot webhook | `/api/webhooks/chatwoot` | POST | `X-Chatwoot-Signature` HMAC | `{content, conversation.id, message_type, account.id}` | `200 {status:"queued"}` | `routes/api.php:68`, `ChatwootWebhookController.php:28-53` | Prod |
| Chatwoot widget cfg | `/api/chatwoot/widget-config` | GET | `auth:sanctum` | none | `{websiteToken, baseUrl, user:{email,name,identifier,identifier_hash}}` | `routes/api.php:69`, `ChatwootHelper.php:31-55` | Prod |
| Resend webhook | `/api/webhooks/resend` | POST | Svix `svix-*` headers | `{type, data:{email_id,to,…}}` | `200 {received,handled}` | `routes/api.php:71`, `ResendWebhookController.php:21-56` | Prod |
| Public status check | `/api/public/admission-results` | POST | none (triple rate-limit) | `{referenceNumber,…}` | status result | `routes/api.php:64-66` | Any |

### Inbound middleware stacks (from `routes/api.php`)

| Route group | Middleware |
|---|---|
| Students (`:20-26`) | `client:student-read`, `throttle:external-api-second`, `throttle:external-api-minute`, `throttle:external-api-daily` |
| Programs (`:28-32`) | `client:program-read`, `throttle:external-api-second`, `throttle:external-api-minute`, `throttle:external-program-api-daily` |
| Medical list (`:37-41`) | `client:medical-read` **only** — deliberately no throttles so a 410 costs nothing |
| Medical lookups (`:43-48`) | `client:medical-read` + 3 medical throttles |
| Medical webhook (`:50-54`) | `client:medical-write`, `medical.webhook`, + 3 medical throttles |

---

## 2B. Outbound endpoints PUPTAS calls

| Integration | Endpoint | Method | Authentication | Request | Response | Source | Env |
|---|---|---|---|---|---|---|---|
| IDP Authorize | `{IDP_BASE_URL}/api/v1/auth/authorize?…` | GET (302) | query `client_id` | `client_id,response_type=code,redirect_uri,state` | redirect w/ `code` | `IdpAuthController.php:59-75` | **Prod-only** |
| IDP Token | `{IDP_BASE_URL}/api/v1/auth/token` | POST | `client_secret` in body | `{client_id,client_secret,code,redirect_uri}` | `{access_token,refresh_token,expires_in}` | `IdpAuthController.php:184-186` | **Prod-only** |
| IDP UserInfo | `{IDP_BASE_URL}/api/v1/me` (config default; code fallback `/api/v1/user`) | GET | `Authorization: Bearer` | – | `{id,email,first_name,last_name,middle_name,username,role_name}` | `IdpAuthController.php:235-238` | **Prod-only** |
| IDP Refresh | `{IDP_BASE_URL}/api/v1/auth/refresh` | POST | `client_secret` in body | `{client_id,client_secret,refresh_token,grant_type:refresh_token}` | new tokens | `RefreshIdpToken.php:104-116` | **Prod-only** |
| IDP Logout | `{IDP_BASE_URL}/api/v1/auth/logout` | POST | `Bearer` | `{client_id,base_url}` | – | `IdpAuthController.php:462-468, 523-529` | **Prod-only** |
| IDP Health | `{IDP_BASE_URL}/.well-known/openid-configuration` | GET | none | – | 200/other → `Cache('idp_status_down')` | `CheckIdpHealth.php:45-47` | **Prod-only** |
| Gemini | `generativelanguage.googleapis.com/v1beta/models/{model}:generateContent` | POST | `?key=` | `{contents,generationConfig}` | `candidates.0.content.parts.0.text` | `ProcessChatwootWebhookJob.php:131-160` | **Prod-only** |
| ~~OpenRouter~~ | — | — | — | — | — | 🗑️ **REMOVED** — was the legacy OCR grade-extraction client | — |
| Chatwoot send | `{CHATWOOT_BASE_URL}/api/v1/accounts/{a}/conversations/{c}/messages` | POST | `api_access_token` | `{content,message_type:"outgoing",private:false}` | 2xx | `ProcessChatwootWebhookJob.php:96-107` | **Prod-only** |
| Resend send | Resend REST API | POST | `RESEND_API_KEY` | via `resend-laravel` SDK | – | `resend/resend-laravel` | **Prod-only** |
| SIS XLSX export | *(no network — streamed download)* | GET | `auth` + `EnsureAdminOrRegistrar` | `?school_year=` | `StreamedResponse` .xlsx | `SisUploadController.php`, `SisUploadExportService.php` | Any |

---

## 2C. OAuth2 scope catalogue

Declared in `app/Providers/AppServiceProvider.php:97-102`:

| Scope | Description |
|---|---|
| `medical-read` | Fetch applicant medical profiles |
| `medical-write` | Submit medical webhook results |
| `student-read` | Fetch enrolled student profiles |
| `program-read` | Fetch active programs list |

Client-credentials clients use **UUIDs** (`Passport::setClientUuids(true)`, `:199`). The `/oauth/token` route is re-registered to bypass Passport's hardcoded 60/min IP throttle, which collapses behind Railway's reverse proxy (`:104-119, 212-232`).

---

## 2D. Named rate limiters

All defined in `app/Providers/AppServiceProvider.php:111-197`:

| Limiter | Key | Limit |
|---|---|---|
| `oauth-token` | `client_id` (falls back to IP) | configurable via `OAUTH_TOKEN_RATE_LIMIT`, default 300/min |
| `external-api-second` | IP | `EXTERNAL_API_SECOND_LIMIT` (default 5) |
| `external-api-minute` | IP | `EXTERNAL_API_MINUTE_LIMIT` (default 1000) |
| `external-api-daily` | IP | `EXTERNAL_API_DAILY_LIMIT` (default 2000) |
| `external-program-api-daily` | IP | `EXTERNAL_PROGRAM_API_DAILY_LIMIT` (default 50) |
| `external-medical-api-second` | bearer token (falls back to IP) | 10/sec |
| `external-medical-api-minute` | bearer token | 200/min |
| `external-medical-api-daily` | bearer token | 1500/day |
| ~~`grade-extraction`~~ | — | 🗑️ **REMOVED** with the legacy OCR pipeline |
| `status-checker` | ref-number × 3 layers | 10/min + 60/day per ref, 60/min per IP |
| `emails` | global | 2/sec |

---

# PART 3 — DATA MAPPING

> Mappings below are taken **only** from code. Nothing is inferred. Where documentation contradicts the code, the contradiction is flagged.

---

## 3.1 IDP → PUPTAS (login-time claim sync)

| External Field (IDP) | PUPTAS Field | Direction | Transformation | Evidence |
|---|---|---|---|---|
| `id` | `users.idp_user_id` | in | direct; written only if changed | `IdpAuthController.php:337-339` |
| `email` | lookup key only (never written) | in | `User::where('email', …)` | `:265` |
| `first_name` | `users.firstname` | in | `substr(trim((string)v), 0, 100)` | `:328` |
| `last_name` | `users.lastname` | in | `substr(trim((string)v), 0, 100)` | `:331` |
| `middle_name` | `users.middlename` | in | `substr(trim((string)v), 0, 100)` | `:334` |
| `username` | `session('pending_registration.username')` | in | direct | `:288` |
| `role_name` | ⚠️ **NOT MAPPED** | — | Role is read from the local `users.role_id` | `:368` |
| `access_token` | Redis `idp_tokens:user_{id}.access_token` | in | 30-day TTL | `:353-361` |
| `refresh_token` | Redis `idp_tokens:user_{id}.refresh_token` | in | 30-day TTL | `:353-361` |
| `expires_in` | `expires_at` (unix ts) | in | `now()->addSeconds(expires_in - 60)` | `:349` |

> ⚠️ **Role is NOT federated from the IDP.** Access control is decided by the local `role_id` column (`:368`). Any claim that roles sync from the IDP is **unsupported by code**.

**Role → redirect mapping** (`IdpAuthController.php:373-391`):

| `role_id` | Destination |
|---|---|
| 1 | `/applicant-dashboard` |
| 2, 7 | `/dashboard` |
| 3, 8 | `/evaluator-dashboard` |
| 4 | `/interviewer-dashboard` |
| 6 | `/record-dashboard` |

---

## 3.2 PUPTAS Program → External Program System

| External Field | PUPTAS Field | Direction | Transformation | Evidence |
|---|---|---|---|---|
| `data[].id` | `programs.id` | out | `select('id','code','name')` | `ExternalProgramApiController.php:44-46` |
| `data[].code` | `programs.code` | out | none | same |
| `data[].name` | `programs.name` | out | none | same |
| *(withheld)* | `strands`, `strand_names` | out | `makeHidden([...])` | `:46` |

> ⚠️ **Documentation drift.** `docs/PROGRAM_SYSTEM_DEVELOPER_HANDOFF.md:60-73` advertises a `department` field and a `meta.last_updated` object. **Neither is returned by the controller.** The endpoint returns only `{data:[{id,code,name}]}` with no `meta` block at all.

---

## 3.3 PUPTAS Student → External Guidance System

| External Field | PUPTAS Field | Direction | Transformation | Evidence |
|---|---|---|---|---|
| `id` | `users.id` (list route) / `Account.id` (email route) | out | ⚠️ **inconsistent source** | `ExternalStudentApiController.php:97, 231, 361` |
| `idp_user_id` | `users.idp_user_id` | out | ⚠️ **emitted on the email route only** | `:362` |
| `reference_number` | `test_passers.reference_number` (list) / `applicant_profiles.reference_number` | out | ⚠️ **inconsistent source** | `:98, 232, 363` |
| `firstname` | `applicant_profiles.firstname` | out | direct | `:233` |
| `middlename` | `applicant_profiles.middlename` | out | direct | `:234` |
| `extension_name` | `applicant_profiles.extension_name` | out | direct | `:235` |
| `lastname` | `applicant_profiles.lastname` | out | direct | `:236` |
| `email` | `applicant_profiles.email` | out | direct | `:237` |
| `sex` | `applicant_profiles.sex` | out | direct | `:238` |
| `g12_gwa` | `grades.g12_first_sem` + `grades.g12_second_sem` | out | **`round((a+b)/2, 2)`**; `null` if either is falsy | `:226-228` |
| `application.application_id` | `applications.id` | out | direct | `:242` |
| `application.status` | `applications.status` | out | direct | `:243` |
| `application.enrollment_status` | `applications.enrollment_status` | out | direct | `:244` |
| `application.enrollment_position` | `applications.enrollment_position` | out | direct | `:245` |
| `application.submitted_at` | `applications.submitted_at` | out | direct | `:246` |
| `program.program_id` | `programs.id` | out | direct | `:249` |
| `program.program_code` | `programs.code` | out | direct | `:250` |
| `program.program_name` | `programs.name` | out | direct | `:251` |
| *(filter)* `?program=` | `programs.code` | in | `whereHas('program', code = X)` | `:79-81` |
| *(gating)* | `applications.enrollment_status = 'officially_enrolled'` | — | hard filter; else 404 | `:323, 196` |

> ⚠️ **Field-name drift.** `docs/GUIDANCE_SYSTEM_DEVELOPER_HANDOFF.md:105-111` shows `first_name`, `last_name`, and `lifecycle_status`. The code emits **`firstname`**, **`lastname`**, and **`application.enrollment_status`**.

---

## 3.4 PUPTAS → External Medical System

| External Field | PUPTAS Field | Transformation | Evidence |
|---|---|---|---|
| `id` | `applicant_profiles.user_id` | direct | `ExternalMedicalApiController.php:220` |
| `idp_user_id` | `users.idp_user_id` | direct | `:221` |
| `reference_number` | `applicant_profiles.reference_number` | direct | `:222` |
| `salutation` | `applicant_profiles.salutation` | direct | `:225` |
| `firstname` | `applicant_profiles.firstname` | direct | `:226` |
| `middlename` | `applicant_profiles.middlename` | direct | `:227` |
| `extension_name` | `applicant_profiles.extension_name` | direct | `:228` |
| `lastname` | `applicant_profiles.lastname` | direct | `:229` |
| `sex` | `applicant_profiles.sex` | direct | `:230` |
| `email` | `applicant_profiles.email` | direct | `:233` |
| `date_graduated` | `applicant_profiles.date_graduated` | direct | `:236` |
| `strand` | `applicant_profiles.strand` | direct | `:237` |
| `track` | `applicant_profiles.track` | direct | `:238` |
| `application.id` | `applications.id` | direct | `:242` |
| `application.status` | `applications.status` | direct | `:243` |
| `application.created_at` | `applications.created_at` | direct | `:244` |
| `program.id` | `programs.id` | direct | `:249` |
| `program.code` | `programs.code` | direct | `:250` |
| `program.name` | `programs.name` | direct | `:251` |
| `medical_process_status` | newest `application_processes.status` where `stage='medical'` | `?? 'in_progress'` | `:255` |

**Eligibility filter** (governs who is visible) — a 4-part `application_processes` self-join at `ExternalMedicalApiController.php:146-207`, duplicated in `ProcessMedicalWebhookJob.php:130-175`:

| # | Condition | `stage` | `status` | `action` |
|---|---|---|---|---|
| 1 | Evaluator passed | `grade_evaluator` | `completed` | IN (`passed`, `transferred`) |
| 2 | Interviewer passed | `interviewer` | `completed` | IN (`passed`, `transferred`) |
| 3 | Medical active (must exist) | `medical` | IN (`in_progress`, `returned`) | — |
| 4 | Medical already cleared (must NOT exist) | `medical` | `completed` | IN (`passed`, `transferred`) |

Failure of any condition → `404 {"message":"Applicant not found or not eligible for medical yet."}`

> ⚠️ Tests `ExternalMedicalApiTest.php:76, 93, 110, 127` assert these conditions yield 404, but **currently return 200**. See Risk R1.

---

## 3.5 External Medical System → PUPTAS (Webhook)

| External Field | PUPTAS Destination | Transformation | Evidence |
|---|---|---|---|
| `reference_number` | lookup → `applicant_profiles` via `test_passers` | `whereHas('testPasser', …)` — **tried first** | `ProcessMedicalWebhookJob.php:45-50` |
| `idp_user_id` **or** `student_id` | lookup → `users.idp_user_id` | **alias: `idp_user_id ?? student_id`** — fallback lookup | `:37, 52-57` |
| `is_health_profile_completed` (int 0/1) | `applications.status` | `1 → 'cleared_for_enrollment'`, `0 → 'rejected'` | `:40, 78, 81` |
| *(derived)* `$actionStr` | `application_processes.action` | `'passed'` / `'failed'` | `:77, 91` |
| *(derived)* | `application_processes.status` | `'completed'` | `:91` |
| *(side-effect)* | new `application_processes` row `stage='records'` | `firstOrCreate` **only when passed** | `:105-114` |
| `timestamp` | replay window | accepts int epoch **or** numeric string **or** ISO8601 via `strtotime`; `>300s` old → 403 | `VerifyMedicalWebhookSignature.php:34-55` |
| `nonce` | cache key `webhook_nonce_{n}` | 600s TTL; duplicate → 403 | `:62-68, 92` |
| `X-Medical-Signature` | — | `hash_hmac('sha256', rawBody, secret)` + `hash_equals` | `:83-88` |
| *(validation)* `is_health_profile_completed` | — | `required\|integer\|in:0,1` → 422 on failure | `ExternalMedicalApiController.php:523-531` |
| *(validation)* identifier presence | — | at least one of the two identifiers, else 422 | `:541-545` |

> ⚠️ **Documentation drift.** `docs/MEDICAL_WEBHOOK_INTEGRATION.md:79-85` documents a `200 {"message":"Medical result recorded successfully"}` and `:108-114` documents a `404`. The code actually returns **`200 {"message":"Medical webhook received and queued for processing"}` asynchronously** and the controller **never returns 404**. The doc also **omits** the mandatory `timestamp`, `nonce`, and `X-Medical-Signature` fields required by the middleware.

---

## 3.6 Chatwoot ↔ PUPTAS

| External Field | PUPTAS | Transformation | Evidence |
|---|---|---|---|
| `X-Chatwoot-Signature` | — | `hash_hmac('sha256', rawBody, CHATWOOT_SECRET_KEY)` + `hash_equals`; **fail-closed** | `ChatwootWebhookController.php:57-74` |
| `conversation.id` | outbound URL segment | cast `(int)` | `ProcessChatwootWebhookJob.php:45, 96` |
| `account.id` | outbound URL segment | cast `(int)`; **required** or message is silently skipped | `:47, 89-94` |
| `message_type` | filter | only `'incoming'` is processed; all others return early | `:50-52` |
| `content` | → Gemini prompt | direct | `:44, 60` |
| `user.email` (PUPTAS) | `identifier_hash` | `hash_hmac('sha256', email, CHATWOOT_HMAC_TOKEN)` | `ChatwootHelper.php:16-21, 42` |

---

## 3.7 Resend → PUPTAS

| External Field | PUPTAS | Transformation | Evidence |
|---|---|---|---|
| `svix-id` + `svix-timestamp` + raw body | signed content | `"{svix-id}.{svix-timestamp}.{rawBody}"` | `ResendWebhookController.php:92` |
| `RESEND_WEBHOOK_SECRET` | signing key | `whsec_` prefix stripped, then `base64_decode` | `:88-89` |
| `svix-signature` | verification | `base64(hmac-sha256, secretBytes, raw=true)`; space-separated multi-version `v1,{sig}`; 300s tolerance; **fail-closed** | `:95-107, 82-86` |
| `type` | event discriminator | passthrough to `EmailTrackingService` | `:30, 46-50` |
| `data.email_id` | Resend message id | passthrough | `:32` |
| *(both required)* | — | else `422 {"error":"Missing event type or email_id"}` | `:42-44` |

---

# PART 4 — TRACE THE COMPLETE DATA FLOW

Each step lists the file responsible.

---

## Trace 1 — IDP SSO Login (Authorization Code flow)

```
[1]  Browser → GET /auth/idp/redirect
       routes/web.php:201-202  →  IdpAuthController::login()      IdpAuthController.php:24-76
[2]  Read SystemSetting 'idp_down_emergency_login_enabled'; if '1' → /emergency/login
                                                                            :26-29
[3]  Guard: missing client_id or base_url → /auth/idp/error                     :36-44
[4]  session['idp_oauth_state'] = Str::random(40)                               :52-55
[5]  302 → {IDP_BASE_URL}/api/v1/auth/authorize
          ?client_id & response_type=code & redirect_uri & state                 :59-75
     ══════════ EXTERNAL NETWORK CALL (Identity Provider) ══════════
[6]  IDP redirects browser → GET /auth/idp/callback?code=…&state=…
       IdpAuthController::callback()                                            :93
[7]  State validation — falls back to session-existence if IDP drops `state`  :104-132
[8]  Extract code → POST {IDP_BASE_URL}/api/v1/auth/token
       payload {client_id, client_secret, code, redirect_uri}                   :176-186
     ══════════ EXTERNAL NETWORK CALL (Identity Provider) ══════════
[9]  Parse access_token / refresh_token / expires_in                          :207-225
[10] GET {IDP_BASE_URL}/api/v1/me  with Authorization: Bearer <access_token>   :235-238
     ══════════ EXTERNAL NETWORK CALL (Identity Provider) ══════════
[11] $idpEmail = $idpUser['email'];  missing → /auth/idp/error                :259-262
[12] Match local DB: App\Models\User::where('email', $idpEmail)                 :265
[13] BRANCH A — no local user:
        Redis put "pending_tokens:{uuid}" (30 min);
        session pending_registration = {uuid,user_id,email,username};
        → redirect /register                                                  :267-291
     BRANCH B — role_id==1 AND passer_status_id ∈ {3,4}:
        apply CutoffSettingsService score/email overrides; else reject        :294-320
[14] Sync firstname/lastname/middlename/idp_user_id (trim + 100-char cap)
        → users table                                                          :325-343
[15] Auth::login($localDbUser)  — Eloquent guard                                :346
[16] Redis put "idp_tokens:user_{id}" (30-day TTL)                            :353-361
[17] Role-based redirect (mapping in Part 3.1)                                :372-391
[18] UI — routes/web.php `auth` group → Inertia pages. Reads MySQL ONLY.
```

**Token refresh path (subsequent requests):** `RefreshIdpToken` middleware (`:17-162`) reads `idp_tokens:user_{id}` from Redis; if `expires_at` is past, POSTs to `/api/v1/auth/refresh` (`:104-116`), updates Redis, and queues HttpOnly+Secure cookies (`:136-137`). On failure it force-logs-out the user (`:148, 156`).

> ⚠️ **Bypassed in local/testing:** `RefreshIdpToken.php:25` short-circuits when `config('app.env') ∈ {local, testing}`. The refresh path is **never exercised locally**.

---

## Trace 2 — Medical Webhook Inbound (the only external WRITE path)

```
[1]  Medical System → POST /api/v1/webhooks/medical-result
[2]  middleware client:medical-write  → Passport scope check      routes/api.php:51
[3]  middleware medical.webhook       → VerifyMedicalWebhookSignature::handle
        (a) timestamp present?        no → 400
        (b) within 300 s?             no → 403 "Request expired"
        (c) nonce present + unseen?   no → 400 / 403 "Duplicate request"
        (d) X-Medical-Signature?      no → 403
        (e) HMAC-SHA256 match?        no → 403 "Invalid Signature"
        (f) secret configured?       no → 500
        (g) ONLY NOW store nonce (600 s) — prevents cache poisoning
                                              VerifyMedicalWebhookSignature.php:27-94
     ══════════ EXTERNAL NETWORK CALL (Medical System) ══════════
[4]  ExternalMedicalApiController::webhookResult                                :510
        in: reference_number | (idp_user_id ?? student_id), is_health_profile_completed
        validate required|integer|in:0,1                          → 422          :523-531
        require ≥1 identifier                                    → 422          :541-545
[5]  ProcessMedicalWebhookJob::dispatch($request->all(), $ip) → queue 'high'    :548
[6]  Return 200 immediately so the caller does not time out                      :551
     ── worker boundary (railway.worker.json: queue:work --queue=high,emails,default) ──
[7]  $status = (is_health_profile_completed == 1) ? 'cleared' : 'failed'        :40
[8]  Locate ApplicantProfile — reference_number first, then idp_user_id
        each constrained by the 4-condition eligibility join                    :45-57
[9]  Not found → audit 'WEBHOOK_MISS' + return (no state change)               :59-74
[10] DB::transaction {
        applications.status ← 'cleared_for_enrollment' | 'rejected'            :80-81
        application_processes[stage=medical].status ← 'completed'              :89-93
        application_processes[stage=medical].action  ← 'passed' | 'failed'      :91-92
        if passed → firstOrCreate processes[stage=records, in_progress]        :105-114
     }                                                                          :115
[11] Audit log 'UPDATE' / 'External Medical API Worker'                         :117-123
[12] PUPTAS UI — Records / Admin dashboards read applications + processes from MySQL
```

---

## Trace 3 — PUPTAS → Guidance System (read path)

```
[1]  Guidance System → POST /oauth/token
        grant_type=client_credentials & scope=student-read
       AppServiceProvider.php:219-223 → Passport AccessTokenController@issueToken
       (alias at /medical-auth/token, :227-231)
[2]  → GET /api/v1/students/email/{email}    Authorization: Bearer <jwt>
       routes/api.php:24 → client:student-read + 3 throttles
[3]  ExternalStudentApiController::showByEmail                                  :319
        Application::where('enrollment_status','officially_enrolled')
          ->whereHas('user.user', email = X)                                   :321-327
[4]  Miss → audit 'READ_MISS' + 404 {"message":"Student not found"}             :332-348
[5]  Hit → build payload, incl. g12_gwa = round((a+b)/2, 2)                    :360-385
[6]  Audit log 'READ' (records requester IP)                                  :387-397
[7]  200 {data:{…}}  with  Cache-Control: no-store, no-cache                   :399-401
```

---

## Trace 4 — Chatwoot AI Chat (inbound → AI → outbound)

```
Chatwoot → POST /api/webhooks/chatwoot
  {content, conversation:{id}, message_type, account:{id}}
[1]  ChatwootWebhookController::handleMessage                                     :28
[2]  HMAC-SHA256 verify vs CHATWOOT_SECRET_KEY — FAIL-CLOSED            :35-38, 55-75
[3]  ProcessChatwootWebhookJob::dispatch  → 200 {"status":"queued"}                :41-43
     ── worker boundary (queue 'high', tries=5, backoff 30/60/120/300/600) ──
[4]  Filter: message_type must === 'incoming', else return                        :50-52
[5]  Empty content → return                                                      :55-57
[6]  getGeminiResponse()  POST generativelanguage.googleapis.com…?key=…  ← EXTERNAL
                                                                    ProcessChatwootWebhookJob.php:129-165
[7]  sendMessageToChatwoot()  POST {base}/api/v1/accounts/{a}/conversations/{c}/messages
        headers: api_access_token, Content-Type
        connectTimeout 10 s, timeout 15 s
        body: {content, message_type:"outgoing", private:false}          ← EXTERNAL
                                                                          :96-107
[8]  2xx → log success | 5xx → throw (retry) | 4xx → log, no retry          :109-126
```

---

## Trace 5 — Program Catalog (shortest read path)

```
External System → POST /oauth/token   (scope=program-read)
                → GET /api/v1/programs
[1]  routes/api.php:28-32 → client:program-read + 3 throttles (5/sec, 5/min, 50/day)
[2]  ExternalProgramApiController::index                                          :42
[3]  Program::select('id','code','name')->get()
        ->makeHidden(['strand_names','strands'])                                 :44-46
[4]  AuditLogService::logActivity('READ','External API',"…requested from IP …")  :48-57
[5]  200 {"data":[{id, code, name}, …]}
```

---

## Trace 6 — Resend Delivery Tracking (outbound send + inbound callback)

```
OUTBOUND (synchronous, inside a queued mail job):
[1]  Job dispatched (SendSarFormEmail / SendWaitlistedEmail / SendCongratulationsEmail /
      SendPasserEmail)  → queue 'emails', rate-limited 2/sec (AppServiceProvider.php:195)
[2]  resend-laravel SDK → Resend REST API  with RESEND_API_KEY          ← EXTERNAL
[3]  Provider message id stored on email_logs
[4]  (on exception) null-guard around tracking calls — BulkEmailTrackingVerificationTest

INBOUND (asynchronous callback from Resend via Svix):
[5]  Svix → POST /api/webhooks/resend
[6]  ResendWebhookController::handle
        verifySignature(): svix-id + svix-timestamp + svix-signature
        tolerance 300 s; base64 HMAC-SHA256; multi-version parse; FAIL-CLOSED
                                                                       :64-110
[7]  require type AND data.email_id, else 422                                        :42-44
[8]  EmailTrackingService::handleResendWebhook(email_id, type, data)                 :46-50
[9]  200 {"received":true, "handled":<bool>}                                         :52-55
[10] PUPTAS UI — Email Tracking page reflects delivery/bounce state
```

---

# PART 5 — FIND EXISTING TESTS

Test frameworks present: **Pest 3** (`pestphp/pest`, `pest-plugin-laravel`) on PHPUnit, plus **Playwright** (E2E) and **Vitest** (frontend).

> ## 🔴 A MOCKED API RESPONSE IS **NOT** A SUCCESSFUL REAL EXTERNAL INTEGRATION.
> Every test in this repository that crosses an external boundary uses `Http::fake()`. **Not one test contacts a real external system.**

---

## 5.1 Integration-related test inventory

| Test File | Test Name | What it tests | External system | Contacts real system? | Type | **Current status (measured)** |
|---|---|---|---|---|---|---|
| `tests/Feature/ExternalProgramApiTest.php:6` | external programs endpoint requires valid token | 401 on missing token | None (PUPTAS itself) | No | Local feature | ❌ **FAIL** — expects `"Unauthorized"`, app returns `"You are not authenticated. Please log in."` |
| `tests/Feature/ExternalProgramApiTest.php:20` | external programs list endpoint returns programs successfully | 200 + JSON + audit | None | No | Local feature | ❌ **FAIL** — `withToken('test-program-token')` no longer works; route now needs `client:program-read`; throws `Passport\AuthenticationException` |
| `tests/Feature/ExternalStudentApiTest.php:13` | external students endpoint requires valid token | 401 | None | No | Local | ✅ PASS |
| `tests/Feature/ExternalStudentApiTest.php:22` | list is gone → 410 | Deprecation contract | None | No | Local | ❌ **FAIL** |
| `tests/Feature/ExternalStudentApiTest.php:38` | lookup by student number | 200 + field mapping | None | No | Local | ❌ **FAIL** — `SQLSTATE[23000] NOT NULL constraint failed: test_passers.email` |
| `tests/Feature/ExternalStudentApiTest.php:83` | 404 when not enrolled/missing | 404 + `READ_MISS` audit | None | No | Local | ✅ PASS |
| `tests/Feature/ExternalStudentApiTest.php:100` | lookup by email | 200 + mapping | None | No | Local | ✅ PASS |
| `tests/Feature/ExternalStudentApiTest.php:139` | 404 by email | 404 | None | No | Local | ✅ PASS |
| `tests/Feature/ExternalMedicalApiTest.php:58` | returns applicant when strictly eligible | 200 + eligibility | None | No | Local | ✅ PASS |
| `tests/Feature/ExternalMedicalApiTest.php:76` | 404 when medical already completed | eligibility rule 4 | None | No | Local | ❌ **FAIL** — got **200** |
| `tests/Feature/ExternalMedicalApiTest.php:93` | 404 when evaluator not completed | eligibility rule 1 | None | No | Local | ❌ **FAIL** — got **200** |
| `tests/Feature/ExternalMedicalApiTest.php:110` | 404 when interviewer failed | eligibility rule 2 | None | No | Local | ❌ **FAIL** — got **200** |
| `tests/Feature/ExternalMedicalApiTest.php:127` | 404 when soft-deleted | `deleted_at` exclusion | None | No | Local | ❌ **FAIL** — got **200** |
| `tests/Feature/IdpStatelessLoginTest.php:7` | IDP login succeeds w/o local user row | token exchange + `/register` redirect | **IDP (mocked)** | **NO — `Http::fake(['*'=>…])`** | 🟨 **MOCKED TEST** | ❌ **FAIL** — mocked IDP returns a user, but state validation rejects → `/auth/idp/error` |
| `tests/Feature/IdpLoginPreservationTest.php:18` | redirect → IDP authorize URL | 302 + Location host | IDP (no HTTP) | No | Local | ✅ PASS |
| `tests/Feature/IdpLoginPreservationTest.php:30` | callback w/o code → error | error handling | None | No | Local | ❌ **FAIL** — redirects to `/auth/idp/error`, test expects `/login` |
| `tests/Feature/IdpLoginPreservationTest.php:40` | GET /login renders Inertia | route-conflict regression | None | No | Local | ❌ **FAIL** — 302 instead of 200 |
| `tests/Feature/IdpLoginBugConditionTest.php` | GET /login returns 200 Inertia | same defect | None | No | Local | ❌ **FAIL** |
| `tests/Feature/WebhookTimestampValidationTest.php` (5 tests) | missing / expired / recent / 5-min boundary / under-5-min | replay window → 400 / 403 | None | No | Local | ✅ **ALL PASS** |
| `tests/Feature/WebhookNonceValidationTest.php` (6 tests) | missing / duplicate / unique nonce, 10-min TTL, concurrent nonces, ordering-before-signature | replay protection | None | No | Local | ✅ **ALL PASS** |
| `tests/Feature/WebhookValidationOrderTest.php` | validation ordering | cost-ordering (cheap checks first) | None | No | Local | ✅ **PASS** |
| `tests/Unit/OpenRouterClientTest.php` (5 tests) — **REMOVED with the legacy OCR pipeline** | OpenRouter 200 / 401 / 429 / 503 / connection failure | error mapping + request shape | **OpenRouter (mocked)** | — | — | 🗑️ **DELETED** |
| `tests/Unit/OpenRouterClientPropertyTest.php` (3 tests) — **REMOVED with the legacy OCR pipeline** | property-based retry/header/body invariants | header & body shape | **OpenRouter (mocked)** | — | — | 🗑️ **DELETED** |
| `tests/Unit/OpenRouterApiExceptionTest.php` — **REMOVED with the legacy OCR pipeline** | exception shape | error surface | – | — | — | 🗑️ **DELETED** |
| `tests/Unit/OpenRouterMigrationSmokeTest.php` — **REMOVED with the legacy OCR pipeline** | Gemini→OpenRouter migration | refactor completeness | – | — | — | 🗑️ **DELETED** |
| `tests/Unit/GradeExtractionServiceTest.php` — **REMOVED with the legacy OCR pipeline** | grade parsing | parsing logic | – | — | — | 🗑️ **DELETED** |
| `tests/Unit/Jobs/ProcessGradeOcrTest.php` — **REMOVED with the legacy OCR pipeline** | OCR job | job behaviour | – | — | — | 🗑️ **DELETED** |
| `tests/E2E/02-authentication-flow.spec.js` (5 tests) | Playwright login/logout | UI auth journey | IDP — **bypassed** via `/dev-login` | No (bypass) | E2E (bypass) | ⚠️ claimed 5/5 in `E2E_TESTING_CHECKLIST.md`; **not independently re-run by this audit** |
| `tests/E2E/01–06` (44 total) | all role workflows | UI | none external | No | E2E | ⚠️ doc claims 40/44 (91%) — **self-reported, dated 2026-08-30** |

---

### Test files with **no** coverage at all

| Integration | Test coverage | Status |
|---|---|---|
| Chatwoot (inbound webhook, outbound send, widget config) | none | ❌ **NOT FOUND** |
| Resend webhook (`ResendWebhookController`) | none | ❌ **NOT FOUND** |
| Medical webhook **happy path** (`is_health_profile_completed=1`) | none | ❌ **NOT FOUND** |
| Medical webhook validation rules (422 paths) | none | ❌ **NOT FOUND** |
| `ProcessMedicalWebhookJob` state transitions | none | ❌ **NOT FOUND** |
| ~~`GeminiClient`~~ | — | 🗑️ **REMOVED** — Gemini is now called only from `ProcessChatwootWebhookJob` |
| S3 / `FileMapper` disk fallback | none | ❌ **NOT FOUND** |
| Redis token storage | none | ❌ **NOT FOUND** |
| `/oauth/token` client-credentials issuance | none | ❌ **NOT FOUND** |
| SuperAdmin `ApiClientController` | none | ❌ **NOT FOUND** |

---

## 5.2 Measured test run

Executed during this audit. Read-only: `phpunit.xml` sets `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `CACHE_STORE=array`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`, `LOG_CHANNEL=null`. **No external system was contacted; no persistent data was written.**

```bash
$ ./vendor/bin/pest --testsuite=Feature --filter='external|medical|IDP|webhook'
   Tests:  12 failed, 23 passed (68 assertions)     Duration: 0.82s

$ ./vendor/bin/pest --testsuite=Unit --filter='OpenRouter'
   Tests:   2 failed, 20 passed (228 assertions)    Duration: 0.36s
```

Environment notes: PHP 8.4.21; **no Passport signing keys present** (`storage/oauth-*.key` absent) — token-issuing flows cannot be exercised without `php artisan passport:keys`.

---

## 5.3 Critical test observations

1. **ZERO real-external-system tests exist.** Every test that crosses an external boundary uses `Http::fake()`. There is no test that contacts the real IDP, Gemini, Chatwoot, Resend, or a real external API client.
2. **No CI configuration** (`.github/workflows`) exists — test results are neither produced automatically nor retained as artifacts.
3. **No Passport keys present locally** — so token-issuing flows cannot be exercised.
4. `tests/Feature/ExternalProgramApiTest.php` still assumes a **static bearer token** (`EXTERNAL_PROGRAM_API_TOKEN`) while `routes/api.php:29` enforces the `client:program-read` Passport scope. The config key `services.external_program_api.token` is **defined but never consumed by any middleware** — dead configuration.
5. The medical "404" failures indicate `showByReferenceNumber` is **less strict** than the documented eligibility contract — a potential **data-exposure** issue, not merely a test bug. **Recommend investigating before any production API evidence is captured.**
6. `E2E_TESTING_CHECKLIST.md` is a **self-authored summary**, not a machine-generated artifact. It explicitly states E2E tests *"bypass IDP correctly for testing"* via `/dev-login` — so E2E evidence does **not** demonstrate the SSO integration.

---

# PART 6 — DETERMINE PRODUCTION-ONLY DEPENDENCIES

> ## 🔒 SECRET VALUES ARE NEVER SHOWN
> Only environment variable **names** and required-vs-optional **status** are reported. No token, key, secret, or credential value appears anywhere in this document.

Format used: `VARIABLE_NAME = REQUIRED` / `= PRODUCTION-SPECIFIC` / `= CONFIG-ONLY (unused)`.

| Variable / Asset | Status | Why it blocks or limits local verification |
|---|---|---|
| `IDP_CLIENT_ID` | **REQUIRED** | Must be pre-registered with the IDP. No self-registration path exists in code. |
| `IDP_CLIENT_SECRET` | **REQUIRED** | Shared secret for token exchange. **Must never be copied locally.** |
| `IDP_REDIRECT_URI` | **PRODUCTION-SPECIFIC** | The IDP validates an exact allow-listed redirect. `http://localhost:8000/auth/callback` will be rejected by a production IDP. |
| `IDP_BASE_URL` | **PRODUCTION-SPECIFIC** | Points at the hosted IDP; the token path is short-circuited when `app.env ∈ {local,testing}` (`RefreshIdpToken.php:25`). |
| `IDP_SCOPE` | REQUIRED | `openid profile email` by default; part of the authorization request. |
| `IDP_HEALTH_CHECK_ENABLED` | Environment-gated | `CheckIdpHealth.php:29-33` disables the check entirely when false. |
| `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` | **REQUIRED** | Signing keys for all M2M tokens. **Absent locally** — no `storage/oauth-*.key` present. |
| `EXTERNAL_PROGRAM_API_TOKEN` | **REQUIRED (legacy/dead)** | Referenced in `config/services.php:61` but **no middleware consumes it**. Cannot authenticate anything. |
| `EXTERNAL_MEDICAL_API_TOKEN` | **REQUIRED (legacy/dead)** | Same: `config/services.php:66`, not consumed by any route middleware. |
| `EXTERNAL_API_SECOND_LIMIT` / `_MINUTE_LIMIT` / `_DAILY_LIMIT` | Required for real throttling | Local tests use array cache, so rate limiting is not meaningfully exercised. |
| `EXTERNAL_PROGRAM_API_DAILY_LIMIT` | Required for real throttling | Default 50/day — must leave headroom for the live partner. |
| `EXTERNAL_MEDICAL_API_*_LIMIT` | Required for real throttling | Defaults 10/sec, 200/min, 1500/day. |
| `MEDICAL_WEBHOOK_SECRET` | **REQUIRED** | Without it the middleware returns **500 "Webhook secret not configured"** (`VerifyMedicalWebhookSignature.php:79-81`). Cannot be guessed or brute-forced. |
| `CHATWOOT_SECRET_KEY` | **REQUIRED** | `ChatwootWebhookController.php:60-63` **fails closed** if unset → all inbound chat webhooks rejected with 403. |
| `CHATWOOT_ACCESS_TOKEN` | **REQUIRED** | Outbound message send (`ProcessChatwootWebhookJob.php:63, 82`). |
| `CHATWOOT_BASE_URL` | **REQUIRED** | Outbound message endpoint host. |
| `CHATWOOT_WEBSITE_TOKEN` | **REQUIRED** | Widget bootstrap served to the browser (`ChatwootHelper.php:34`). |
| `CHATWOOT_HMAC_TOKEN` | **REQUIRED** | Widget identity verification hash (`:16-21`). |
| `RESEND_API_KEY` | **REQUIRED** | Outbound email. `.env.example` ships `MAIL_MAILER=log` (safe local default). |
| `RESEND_WEBHOOK_SECRET` | **REQUIRED** | Svix verification **fails closed** → 401 (`ResendWebhookController.php:69-72`). |
| `GEMINI_API_KEY` | **REQUIRED** | Consumed directly by `ProcessChatwootWebhookJob` via `config('services.gemini.api_key')`. |
| `GEMINI_MODEL` | Required | Defaults `gemini-2.0-flash`. |
| ~~`OPENROUTER_API_KEY`~~ | — | **REMOVED** — the OpenRouter client existed only for the legacy OCR grade-extraction pipeline. |
| ~~`OPENROUTER_ENDPOINT` / `OPENROUTER_MODEL`~~ | — | **REMOVED** — see above. |
| ~~`DOCLING_URL` / `DOCLING_API_KEY` / `DOCLING_TIMEOUT`~~ | — | **REMOVED** — the entire Docling/OCR implementation has been deleted. |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | **PRODUCTION-ONLY** | S3 / Railway Object Storage. |
| `AWS_BUCKET` / `AWS_ENDPOINT` / `AWS_DEFAULT_REGION` / `AWS_USE_PATH_STYLE_ENDPOINT` | **PRODUCTION-ONLY** | `FileMapper.php:342-350` only adds the `s3` disk when **not** `local` env. |
| `REDIS_HOST` / `REDIS_PASSWORD` / `REDIS_PORT` / `REDIS_CLIENT` | **PRODUCTION-ONLY (effectively)** | IDP tokens, pending-registration tokens, and webhook nonces require real Redis. Code has try/catch fallbacks, so local runs degrade rather than fail. |
| `APP_URL` / `FRONTEND_URL` | **PRODUCTION-SPECIFIC** | Used in CORS allow-list (`config/cors.php:22-25`) and in the IDP logout payload. Docs reference `puptas.undraftedbsit2027.com`; partner allow-lists will not include localhost. |
| `OAUTH_TOKEN_RATE_LIMIT` | Optional | Configurable token-bucket size (`AppServiceProvider.php:116`). |
| `POSTGRES_*` | **CONFIG-ONLY (unused)** | Only the stock `pgsql` stub exists (`config/database.php:85`). Not an active integration. |

### Infrastructure / network restrictions

| Restriction | Detail |
|---|---|
| **IDP redirect allow-list** | The IDP must be able to redirect a browser to a **public HTTPS** URL. `localhost` is not reachable by the hosted IDP. |
| **Passport key material** | Token issuance (`/oauth/token`) is cryptographically impossible without keys. |
| **Partner provisioning** | Each external consumer must be provisioned a client via Super Admin → API Clients (`routes/web.php:758-762`) and separately receive the HMAC secret. |
| **Queue worker** | `railway.worker.json` must be running for `ProcessMedicalWebhookJob` and `ProcessChatwootWebhookJob` to drain. |
| **Shared HMAC secret** | Cannot be exercised locally without transmitting a production secret — explicitly out of scope. |
| **Railway edge proxy** | `AppServiceProvider.php:104-119, 212-232` documents that behind Railway's proxy all clients can share one IP, which is why the token limiter is keyed by `client_id` and an alias route `/medical-auth/token` exists to evade edge WAF rules. |

---

# PART 7 — CLASSIFY THE AVAILABLE EVIDENCE

Categories (as defined):
- **A. IMPLEMENTED** — the integration exists in application code
- **B. INTERFACE DOCUMENTED** — endpoint/protocol/request/response can be identified
- **C. DATA MAPPING DOCUMENTED** — exchanged fields and PUPTAS mappings demonstrable from code
- **D. AUTOMATED TESTED** — an automated test verifies the relevant behaviour
- **E. MOCK TESTED** — tested using a mocked/fake external service
- **F. REAL INTEGRATION VERIFIED** — evidence that PUPTAS successfully communicated with the real external system
- **G. PRODUCTION VERIFICATION REQUIRED** — code exists, but real communication still needs production verification

> **Rule applied:** category **F is never assigned merely because implementation code exists.**

| # | Integration | A | B | C | D | E | F | G |
|---|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| 1 | **IDP OAuth2/OIDC SSO** | ✅ | ✅ | ✅ | ⚠️ partial — 1 of 3 IDP tests pass | ✅ `Http::fake` | ❌ **NO** | ✅ **YES** |
| 2 | **Program Catalog API** | ✅ | ✅ | ⚠️ code complete, doc drifted | ❌ both tests fail | ❌ | ❌ **NO** | ✅ **YES** |
| 3 | **Student Admission API** | ✅ | ✅ | ⚠️ code complete, doc drifted | ⚠️ 4 of 6 pass | ❌ | ❌ **NO** | ✅ **YES** |
| 4 | **Medical Read API** | ✅ | ✅ | ⚠️ code complete, doc drifted | ⚠️ 1 of 5 pass | ❌ | ❌ **NO** | ✅ **YES** ⚠️ fix 404 rules first |
| 5 | **Medical Result Webhook** | ✅ | ✅ | ⚠️ code complete, doc drifted | ✅ 12 of 12 pass — **replay protection only** | ❌ | ❌ **NO** | ✅ **YES** |
| 6 | **Chatwoot Live Chat** | ✅ | ✅ | ✅ | ❌ none | ❌ | ❌ **NO** | ✅ **YES** |
| 7 | **Resend Email + tracking** | ✅ | ⚠️ partial — webhook inbound documented; send delegated to SDK | ✅ | ❌ none | ❌ | ❌ **NO** | ✅ **YES** |
| 8 | **Google Gemini** | ✅ | ✅ | ✅ | ❌ indirect only | ⚠️ Chatwoot job untested | ❌ **NO** | ✅ **YES** |
| 9 | ~~**OpenRouter**~~ | 🗑️ **REMOVED** | — | — | — | — | ❌ **NO** | — |
| 10 | **S3 / Object Storage** | ✅ | ⚠️ Flysystem abstraction only | ✅ | ❌ none | ❌ | ❌ **NO** | ✅ **YES** |
| 11 | **Redis** | ✅ | n/a — infrastructure | n/a | ❌ none | ❌ | ❌ **NO** | ✅ **YES** |
| 12 | ~~**Docling**~~ | 🗑️ **REMOVED** | — | — | — | — | ❌ **NO** | — |

### Category F summary

> ## 🔴 **NO INTEGRATION QUALIFIES FOR CATEGORY F.**
> There is zero evidence — no transcript, no log, no screenshot, no partner confirmation — that PUPTAS has ever successfully exchanged data with a real external system.

---

# PART 8 — IDENTIFY EXACT EVIDENCE WE ALREADY HAVE

All paths are relative to `puptas/` unless noted. Line numbers verified against `main` @ `52a32ff6`.

| Integration | Evidence Already Available | Evidence Location | Strength |
|---|---|---|---|
| **IDP SSO** | Login redirect builder | `app/Http/Controllers/IdpAuthController.php:24-76` | **STRONG** (code) |
| **IDP SSO** | Token exchange POST | `IdpAuthController.php:184-186` | **STRONG** |
| **IDP SSO** | Userinfo GET with Bearer | `IdpAuthController.php:235-238` | **STRONG** |
| **IDP SSO** | Redis token persistence (30-day TTL) | `IdpAuthController.php:353-361` | **STRONG** |
| **IDP SSO** | Refresh-token middleware | `app/Http/Middleware/RefreshIdpToken.php:104-139` | **STRONG** |
| **IDP SSO** | Logout propagation (2 call sites) | `IdpAuthController.php:462-468, 523-529` | **STRONG** |
| **IDP SSO** | Health-check command | `app/Console/Commands/CheckIdpHealth.php:45-56` | **STRONG** |
| **IDP SSO** | Session-backed user DTO | `app/Auth/IdpUser.php`, `app/Auth/IdpUserProvider.php` | **STRONG** |
| **IDP SSO** | Claim → column sync map (trim + cap) | `IdpAuthController.php:325-343` | **STRONG** |
| **IDP SSO** | Emergency-down bypass logic | `IdpAuthController.php:26-29`; `RefreshIdpToken.php:32-69` | **STRONG** |
| **IDP SSO** | 1 mock test (failing) | `tests/Feature/IdpStatelessLoginTest.php:16-41` | **WEAK** |
| **IDP SSO** | 1 passing redirect test | `tests/Feature/IdpLoginPreservationTest.php:18` | **MODERATE** |
| **IDP SSO** | Config block | `config/services.php:32-43` | **STRONG** |
| **Program API** | Route + scope + throttles | `routes/api.php:28-32` | **STRONG** |
| **Program API** | Controller + response shape | `ExternalProgramApiController.php:42-62` | **STRONG** |
| **Program API** | Scribe OpenAPI spec | `.scribe/endpoints/01.yaml` | **STRONG** |
| **Program API** | Developer handoff doc | `docs/PROGRAM_SYSTEM_DEVELOPER_HANDOFF.md` | **STRONG** ⚠️ drifted |
| **Program API** | Client-management guide | `docs/API_CLIENT_MANAGEMENT_GUIDE.md:16-19` | **STRONG** |
| **Program API** | 2 tests — both failing | `tests/Feature/ExternalProgramApiTest.php` | **WEAK** |
| **Student API** | 3 routes + scopes | `routes/api.php:20-26` | **STRONG** |
| **Student API** | Full field mapping + GWA calc | `ExternalStudentApiController.php:96-120, 226-254, 360-385` | **STRONG** |
| **Student API** | Enrollment-status gating | `ExternalStudentApiController.php:323, 196` | **STRONG** |
| **Student API** | `READ` / `READ_MISS` audit trail | `ExternalStudentApiController.php:123, 333, 387` | **STRONG** |
| **Student API** | Scribe spec (3 endpoints) | `.scribe/endpoints/00.yaml` | **STRONG** |
| **Student API** | Handoff doc | `docs/GUIDANCE_SYSTEM_DEVELOPER_HANDOFF.md` | **STRONG** ⚠️ drifted |
| **Student API** | 6 tests — 4 pass | `tests/Feature/ExternalStudentApiTest.php` | **MODERATE** |
| **Medical API** | 3 routes + deliberate 410 | `routes/api.php:36-48` | **STRONG** |
| **Medical API** | 4-condition eligibility join | `ExternalMedicalApiController.php:146-207` | **STRONG** |
| **Medical API** | Field mapping | `ExternalMedicalApiController.php:218-256` | **STRONG** |
| **Medical API** | Handoff doc with example payload | `docs/MEDICAL_SYSTEM_DEVELOPER_HANDOFF.md:39-119` | **STRONG** |
| **Medical API** | 5 tests — 1 pass / 4 fail | `tests/Feature/ExternalMedicalApiTest.php` | **WEAK** ⚠️ |
| **Medical Webhook** | Route + dual auth | `routes/api.php:50-54` | **STRONG** |
| **Medical Webhook** | HMAC + ts + nonce middleware | `app/Http/Middleware/VerifyMedicalWebhookSignature.php:17-95` | **STRONG** |
| **Medical Webhook** | Field alias `student_id`→`idp_user_id` | `ExternalMedicalApiController.php:515-516`; `app/Jobs/ProcessMedicalWebhookJob.php:37` | **STRONG** |
| **Medical Webhook** | Status transition map | `ProcessMedicalWebhookJob.php:40, 78, 81, 91, 105-114` | **STRONG** |
| **Medical Webhook** | Async queue dispatch + early 200 | `ExternalMedicalApiController.php:548-551` | **STRONG** |
| **Medical Webhook** | Integration doc | `docs/MEDICAL_WEBHOOK_INTEGRATION.md` | **STRONG** ⚠️ drifted |
| **Medical Webhook** | Scribe spec | `.scribe/endpoints/03.yaml` | **STRONG** |
| **Medical Webhook** | 12 tests — all pass (replay only) | `tests/Feature/WebhookTimestampValidationTest.php`, `WebhookNonceValidationTest.php`, `WebhookValidationOrderTest.php` | **STRONG** |
| **Medical Webhook** | **No happy-path test** | — | ❌ **MISSING** |

| **Chatwoot** | HMAC fail-closed verifier | `app/Http/Controllers/ChatwootWebhookController.php:55-75` | **STRONG** |
| **Chatwoot** | Outbound sender with retry policy | `app/Jobs/ProcessChatwootWebhookJob.php:79-127` | **STRONG** |
| **Chatwoot** | Widget identity hash | `app/Helpers/ChatwootHelper.php:16-21` | **STRONG** |
| **Chatwoot** | `message_type` filter, account-id guard | `ProcessChatwootWebhookJob.php:50-52, 89-94` | **STRONG** |
| **Chatwoot** | **No tests at all** | — | ❌ **MISSING** |
| **Resend** | Svix verifier (tolerance, multi-sig, fail-closed) | `app/Http/Controllers/ResendWebhookController.php:64-110` | **STRONG** |
| **Resend** | Event dispatch to tracking service | `ResendWebhookController.php:29-55` | **STRONG** |
| **Resend** | **No tests** | — | ❌ **MISSING** |
| **Gemini** | Endpoint + payload builder (Chatwoot) | `app/Jobs/ProcessChatwootWebhookJob.php:131-160` | **STRONG** |
| **Gemini** | **No direct tests** | — | ❌ **MISSING** |
| ~~**OpenRouter**~~ | ~~Endpoint + payload + error map~~ | ~~`app/Services/OpenRouterClient.php`~~ | 🗑️ **REMOVED** |
| ~~**OpenRouter**~~ | ~~Custom exception type~~ | ~~`app/Exceptions/OpenRouterApiException.php`~~ | 🗑️ **REMOVED** |
| ~~**OpenRouter**~~ | ~~5 unit tests, all `Http::fake`~~ | ~~`tests/Unit/OpenRouterClientTest.php`~~ | 🗑️ **REMOVED** |
| ~~**OpenRouter**~~ | ~~3 property tests, all `Http::fake`~~ | ~~`tests/Unit/OpenRouterClientPropertyTest.php`~~ | 🗑️ **REMOVED** |
| **S3** | Disk definitions | `config/filesystems.php:31-120` | **STRONG** |
| **S3** | Prod-only disk selection logic | `app/Helpers/FileMapper.php:342-350` | **STRONG** |
| **S3** | **No tests** | — | ❌ **MISSING** |
| **Redis** | IDP token storage | `IdpAuthController.php:353-361`; `RefreshIdpToken.php:79` | **STRONG** |
| **Redis** | Nonce replay cache | `VerifyMedicalWebhookSignature.php:63, 92` | **STRONG** |
| **Redis** | **No tests** | — | ❌ **MISSING** |
| **Cross-cutting** | 4 Passport scopes declared | `app/Providers/AppServiceProvider.php:97-102` | **STRONG** |
| **Cross-cutting** | UUID clients + token-route override | `AppServiceProvider.php:199, 217-232` | **STRONG** |
| **Cross-cutting** | SuperAdmin client UI + audit | `app/Http/Controllers/SuperAdmin/ApiClientController.php:24-70`; `routes/web.php:758-762` | **STRONG** |
| **Cross-cutting** | 11 named rate limiters | `AppServiceProvider.php:111-197` | **STRONG** |
| **Cross-cutting** | Audit trail on every external read/write | `ExternalProgramApiController.php:48`; `ExternalStudentApiController.php:123, 333, 387`; `ExternalMedicalApiController.php:119, 258`; `ProcessMedicalWebhookJob.php:117` | **STRONG** |
| **Cross-cutting** | Scribe OpenAPI spec (4 endpoint files) | `.scribe/endpoints/{00,01,02,03}.yaml`, `.scribe/intro.md` | **STRONG** |
| **Cross-cutting** | 3 partner handoff guides | `docs/{PROGRAM,MEDICAL,GUIDANCE}_SYSTEM_DEVELOPER_HANDOFF.md` | **STRONG** |
| **Cross-cutting** | Env-var reference for all integrations | `puptas/.env.example` | **STRONG** |
| **Cross-cutting** | Known-defect register | `docs/audit-report.md` | **STRONG** |
| **REAL EXCHANGE** | **NONE** | `puptas/storage/logs/laravel.log` — 0 hits for `IDP token exchange successful`, 0 for `External programs list requested` | ❌ **ABSENT** |

---

# PART 9 — IDENTIFY EXACT EVIDENCE STILL NEEDED

Each item states precisely what to capture and what to redact.

---

## 9.1 IDP OAuth2/OIDC SSO — `PT-IDP-01` … `PT-IDP-04`

1. **Browser video or 4-frame screenshot sequence:**
   - (a) PUPTAS login page showing the "Sign in with IDP" control
   - (b) browser address bar showing the 302 to `{IDP_BASE_URL}/api/v1/auth/authorize?…` — **redact `client_id` and `state`**
   - (c) the IDP consent / credential page
   - (d) the final PUPTAS role dashboard
2. **Sanitized production log excerpt** containing these six lines **in order**, proving a real round-trip:
   - `IDP login initiated`
   - `IDP redirecting to authorize URL: …`
   - `IDP callback reached` (with `has_code => true`)
   - `IDP token exchange successful`
   - `Fetching user info from IDP`
   - `User logged in seamlessly via Local DB Match`

   **Redact:** the full authorize URL (contains `client_id`), the `state` value, and the `token_url` host if desired. The app never logs tokens, so the excerpt is safe once the URL is trimmed.
3. **HTTP status evidence:** DevTools → Network screenshot of the `/auth/idp/callback` response showing `HTTP/2 302` with `Location: /applicant-dashboard` (or the role-appropriate destination).
4. **Data-mapping proof:** side-by-side of the IDP `/api/v1/me` response and the resulting `users` row (`firstname`, `lastname`, `middlename`, `idp_user_id`). **Redact** the email and all UUIDs except the first 8 characters.

---

## 9.2 Program Catalog API — `PT-PRG-01` … `PT-PRG-04`

1. **Transcript of `POST /oauth/token`** with `grant_type=client_credentials&scope=program-read` → `HTTP 200` + `{token_type, expires_in, access_token}`. **Redact `client_id`, `client_secret`, and truncate the `access_token` to its first 12 characters.**
2. **Transcript of `GET /api/v1/programs`** with `Authorization: Bearer …` → `HTTP 200` and the full JSON body `{"data":[{"id":…,"code":"BSIT","name":"…"}]}`.
3. **Audit-log screenshot:** Super Admin → Audit Log filtered to `module_name = "External API"`, showing a `READ` entry whose description contains `External programs list requested from IP …` with a **real partner IP**. This proves a *real consumer* called it.
4. **🔴 Consumer-side proof (strongest, currently missing):** a screenshot from the **partner's own system** (Program System / microsite) displaying program cards populated from this API. Without this, only PUPTAS-side evidence exists.

---

## 9.3 Student Admission API — `PT-STU-01` … `PT-STU-05`

1. Token transcript with `scope=student-read` (redact as above).
2. `GET /api/v1/students/email/{email}` → `200` + **sanitized** body (name → `JUAN D.*`, email → `j***@example.edu`, `idp_user_id` → first 8 chars). Note the `Cache-Control: no-store, no-cache` response header.
3. `GET /api/v1/students/{referenceNumber}` → `200` + sanitized body. Use a **test/fixture reference number**, not a real applicant.
4. **Deprecation proof:** `GET /api/v1/students` → `HTTP 410` with the `Deprecation: true` header — demonstrates versioned API governance.
5. **Guidance-System-side screenshot** showing the student record rendered from the response.

---

## 9.4 Medical Read API — `PT-MED-01` … `PT-MED-05`

> ### ⚠️ GATING CONDITION
> 4 of the 5 eligibility tests currently fail. **Do not capture production evidence** until the team confirms whether the 404 rules *should* be enforced on `showByReferenceNumber`. If they are not enforced, the endpoint may return medically-ineligible applicants to the partner — a privacy/compliance problem, and capturing a screenshot could bake the defect into the evidence pack.

1. Token transcript with `scope=medical-read`.
2. `GET /api/v1/medical/applicants/{referenceNumber}` → `200` + body with **`email` masked** (this payload contains PII).
3. `GET /api/v1/medical/applicants/idp/{idpUserId}` → `200` + body, UUID redacted.
4. `GET /api/v1/medical/applicants` → `410` transcript (and confirm no rate-limit cost).
5. **Medical-System-side screenshot** showing the applicant populated in the clinic UI.

---

## 9.5 Medical Result Webhook — `PT-WHK-01` … `PT-WHK-04`

> **Use only a dedicated test applicant. Never use a real applicant.** This is the only integration that writes data.

1. **Signed request transcript (redacted):** `POST /api/v1/webhooks/medical-result` with `Authorization: Bearer …`, `X-Medical-Signature: <hmac>`, body `{"reference_number":"TEST-…","is_health_profile_completed":1,"timestamp":<epoch>,"nonce":"<uuid>"}` → `HTTP 200 {"message":"Medical webhook received and queued for processing"}`. **Redact:** bearer token, the HMAC signature, and the shared secret.
2. **Negative control:** identical request with a deliberately corrupted signature → `HTTP 403 {"message":"Invalid Signature"}`. Proves the security control genuinely works.
3. **Replay control:** re-send the **identical nonce** → `HTTP 403 {"message":"Duplicate request"}`.
4. **State-change proof:** before/after screenshots of the test applicant's PUPTAS record showing `applications.status` moving to `cleared_for_enrollment` and a new `records`-stage row — **plus** the `External Medical API Worker` audit-log entry.

---

## 9.6 Chatwoot — `PT-CWT-01` … `PT-CWT-03`

1. Screenshot of the PUPTAS in-app chat widget rendering a real conversation.
2. Sanitized log: `Chatwoot webhook received` → `Message sent to Chatwoot successfully`.
3. Outbound evidence from the Chatwoot side showing the AI reply arriving in the conversation thread.

---

## 9.7 Resend Email — `PT-RSD-01` … `PT-RSD-02`

1. **Resend dashboard screenshot** (delivery status for a **test recipient**) — this is the *provider-side* proof of delivery.
2. Sanitized log excerpt: `[ResendWebhook] Received event` with `type` and a **masked** `email_id` and `to` address, followed by the `EmailTrackingService` update reflected in the PUPTAS Email Tracking page.

---

## 9.8 Gemini (Chatwoot AI reply) — `PT-AI-01`, `PT-AI-02`

1. ~~Sanitized log showing a grade-extraction request returning parsed grades~~ — **N/A, the AI grade-extraction feature has been removed.**
2. For Chatwoot AI: a transcript of one inbound message → AI reply in the Chatwoot thread.
   > ### 🔴 CRITICAL REDACTION
   > The Gemini API key travels in the URL query string and URLs are logged. **Ensure log lines are trimmed before capture** — the full URL contains the key.

---

## 9.9 S3 / Object Storage — `PT-S3-01` … `PT-S3-02`

1. Screenshot of the Railway/AWS bucket listing showing PUPTAS-prefixed objects.
2. A production SAR PDF opened from an `https://…` external-storage URL, proving external storage is genuinely in use (not local disk).

---

# PART 10 — PRODUCTION TEST PLAN

**Principles applied throughout:** read-only first, test accounts only, no destructive operations, no modification of real applicant records, explicit redaction per test.

---

## `PT-IDP-01` — IDP Authorization Redirect

| Field | Detail |
|---|---|
| **Integration** | IDP OAuth2/OIDC SSO |
| **Objective** | Prove PUPTAS issues a valid OAuth2 authorization request |
| **Preconditions** | Valid non-production test account at the IDP; no applicant record needed |
| **Steps** | 1. Open `https://puptas.online/login` in a private window. 2. Click "Sign in". 3. **Do not complete the IDP login** — stop at the IDP page. |
| **Expected Result** | `302` to `{IDP_BASE_URL}/api/v1/auth/authorize` with `client_id`, `response_type=code`, `redirect_uri`, `state`; the IDP renders its login form |
| **Evidence to Capture** | DevTools Network screenshot of the 302; the IDP page screenshot |
| **Redact** | `client_id`, `state`, and the `redirect_uri` host if sensitive |
| **Production Data Risk** | **NONE** — read-only, no login completed, no record created |

---

## `PT-IDP-02` — IDP Full Login Round-Trip

| Field | Detail |
|---|---|
| **Integration** | IDP OAuth2/OIDC SSO |
| **Objective** | Prove token exchange + userinfo + local account binding |
| **Preconditions** | Dedicated **staff test account** pre-registered in PUPTAS (e.g. an evaluator). **Never** use an applicant's IDP login |
| **Steps** | 1. Log in via IDP as the staff test account. 2. Confirm redirect to the role dashboard. 3. Immediately log out (which triggers `POST /api/v1/auth/logout`). |
| **Expected Result** | Successful dashboard; production log shows `IDP token exchange successful` + `User logged in seamlessly via Local DB Match`; logout clears the session and calls IDP logout |
| **Evidence to Capture** | 4-frame screenshot sequence; sanitized log excerpt (the six lines in §9.1) |
| **Redact** | `client_id`, `state`, any token, email address, UUIDs |
| **Production Data Risk** | **LOW** — a staff session is created and closed; no applicant record is modified |

---

## `PT-PRG-01` — Program Catalog Read (token + list)

| Field | Detail |
|---|---|
| **Integration** | Program Catalog API |
| **Objective** | Prove OAuth2 client-credentials issuance **and** that a real external client consumes the endpoint |
| **Preconditions** | A `program-read` OAuth client provisioned via Super Admin → API Clients. Use a **dedicated test client**, not a live partner's credentials |
| **Steps** | 1. `POST /oauth/token` with `grant_type=client_credentials&scope=program-read`. 2. `GET /api/v1/programs` with the returned bearer. 3. Repeat once to confirm idempotency. |
| **Expected Result** | `200` on token; `200` with `{"data":[{id,code,name},…]}` on the list; audit-log entry `External programs list requested from IP <partner IP>` |
| **Evidence to Capture** | Both HTTP transcripts; audit-log screenshot; **partner-side UI screenshot** |
| **Redact** | `client_id`, `client_secret`, `access_token` (truncate to 12 chars) |
| **Production Data Risk** | **NONE** — read-only; consumes **2 of 50** daily calls. ⚠️ The daily limiter is 50/day — leave headroom for the real partner |

---

## `PT-STU-01` — Student Lookup (email + reference + deprecation)

| Field | Detail |
|---|---|
| **Integration** | Student Admission API |
| **Objective** | Prove field mapping and the `officially_enrolled` gate |
| **Preconditions** | `student-read` test client; a **synthetic** test record with `enrollment_status = officially_enrolled` (seeded in a controlled window, or a designated test student) |
| **Steps** | 1. Token with `scope=student-read`. 2. `GET /api/v1/students/email/{test-email}`. 3. `GET /api/v1/students/{test-reference}`. 4. `GET /api/v1/students/NONEXISTENT-TEST` → expect `404`. 5. `GET /api/v1/students` → expect `410` + `Deprecation` header. |
| **Expected Result** | `200`, `200`, `404`, `410` respectively; `g12_gwa` present; `Cache-Control: no-store` on the email route |
| **Evidence to Capture** | 4 transcripts + audit-log screenshot showing both `READ` and `READ_MISS` |
| **Redact** | Access token; real applicant PII if a real record must be used |
| **Production Data Risk** | **LOW** — read-only. **Prefer a synthetic record.** ~5 of 2000 daily calls |

---

## `PT-MED-01` — Medical Eligibility Read

> ### ⚠️ GATED — do not execute until the 4 failing 404 tests are triaged (see Risk R1)

| Field | Detail |
|---|---|
| **Integration** | Medical Read API |
| **Objective** | Prove the 4-condition eligibility contract in production |
| **Preconditions** | `medical-read` test client; **test applicant** with synthetic `application_processes` rows; dev/ops sign-off on the 404 defect |
| **Steps** | 1. Token `scope=medical-read`. 2. `GET /api/v1/medical/applicants/{test-ref}` → expect `200`. 3. `GET /api/v1/medical/applicants/idp/{test-uuid}` → expect `200`. 4. `GET /api/v1/medical/applicants` → expect `410`. 5. `GET` with a non-eligible test ref → expect `404`. |
| **Expected Result** | `200`, `200`, `410`, **`404`** |
| **Evidence to Capture** | 4 transcripts (PII-masked) + audit log |
| **Redact** | Token; `email`; full UUID; applicant names → initials |
| **Production Data Risk** | **LOW** if a test applicant is used. **MEDIUM** if a real applicant is used — the payload is PII. ~5 of 1500 daily calls |

---

## `PT-WHK-01` — Medical Webhook (signed, test applicant only)

| Field | Detail |
|---|---|
| **Integration** | Medical Result Webhook |
| **Objective** | Prove the full signed write path **and** the negative controls |
| **Preconditions** | `medical-write` test client; `MEDICAL_WEBHOOK_SECRET`; a **dedicated test applicant** parked at the medical stage; **Super Admin present**; a **database snapshot taken first** |
| **Steps** | 1. **Snapshot** the test applicant's `applications` + `application_processes` rows. 2. POST a correctly signed webhook (`is_health_profile_completed:1`, fresh `timestamp`, random `nonce`). 3. Expect `200`. 4. Re-send the **identical nonce** → expect `403 Duplicate request`. 5. Send a **corrupted** signature → expect `403 Invalid Signature`. 6. Send a **stale** timestamp → expect `403 Request expired`. 7. **Verify** the state change in the UI. 8. **Restore** the snapshot. |
| **Expected Result** | `200`, `403`, `403`, `403`; applicant moves to `cleared_for_enrollment`; new `records` row; audit `External Medical API Worker / UPDATE` |
| **Evidence to Capture** | All 4 transcripts; before/after UI screenshots; audit-log entry; restoration confirmation |
| **Redact** | Bearer token; `X-Medical-Signature`; `MEDICAL_WEBHOOK_SECRET`; test applicant identifiers |
| **Production Data Risk** | **🔴 MEDIUM — THE ONLY WRITE TEST.** Mitigations: (a) dedicated test applicant only; (b) DB snapshot before, verified restore after; (c) confirm `idp_down_emergency_login_enabled` is unaffected; (d) run outside peak hours; (e) notify the Medical partner to avoid a duplicate real submission for the same student. **Do not use any real applicant.** |

---

## `PT-WHK-02` — Webhook Queue Processing

| Field | Detail |
|---|---|
| **Integration** | Medical Result Webhook (async half) |
| **Objective** | Prove the async worker actually drains the `high` queue |
| **Preconditions** | Railway worker service running |
| **Steps** | After `PT-WHK-01` step 3, check the **worker** service logs for `ProcessMedicalWebhookJob` completion; confirm the Scheduler service is alive |
| **Expected Result** | Job processed within seconds; no `Medical webhook job error` in the log |
| **Evidence to Capture** | Worker log excerpt |
| **Redact** | Applicant identifiers |
| **Production Data Risk** | **NONE** — observation only |

---

## `PT-CWT-01` — Chatwoot Inbound + AI Reply

| Field | Detail |
|---|---|
| **Integration** | Chatwoot Live Chat |
| **Objective** | Prove inbound webhook → Gemini → outbound Chatwoot reply |
| **Preconditions** | Chatwoot configured; a **test conversation** in a test inbox |
| **Steps** | 1. As a test visitor, send one message in the PUPTAS chat widget. 2. Observe the reply. 3. Send a second identical message to confirm repeatability. |
| **Expected Result** | Widget shows an AI reply; logs show `Chatwoot webhook received` + `Message sent to Chatwoot successfully` |
| **Evidence to Capture** | Before/after widget screenshots; sanitized log excerpt; Chatwoot-side thread screenshot |
| **Redact** | `api_access_token`; visitor identity; all `CHATWOOT_*` secrets |
| **Production Data Risk** | **NONE** — test conversation only. **Do not message real applicants** |

---

## `PT-RSD-01` — Resend Delivery + Webhook Tracking

| Field | Detail |
|---|---|
| **Integration** | Resend Email + Svix tracking |
| **Objective** | Prove outbound send and inbound delivery-status tracking |
| **Preconditions** | `RESEND_API_KEY` + `RESEND_WEBHOOK_SECRET`; a **test recipient** address you control |
| **Steps** | 1. Trigger any PUPTAS email addressed to the test mailbox (e.g. `php artisan tinker` → send to the test address, or use a test applicant's confirmation flow). 2. Observe the Resend dashboard. 3. Confirm the status appears in PUPTAS → Email Tracking. |
| **Expected Result** | Email delivered; Resend dashboard shows `delivered`; PUPTAS Email Tracking reflects it |
| **Evidence to Capture** | Resend dashboard screenshot; PUPTAS Email Tracking screenshot; sanitized `[ResendWebhook]` log |
| **Redact** | `RESEND_API_KEY`; `RESEND_WEBHOOK_SECRET`; the test mailbox address |
| **Production Data Risk** | **NONE** if the recipient is a controlled test mailbox |

---

## `PT-AI-01` — ~~AI Grade Extraction~~ — 🗑️ **REMOVED / NOT APPLICABLE**

> The entire AI grade-extraction feature (Gemini / OpenRouter / Docling / OCR) has been deleted from the codebase. There is no code path to exercise, so this test objective can no longer be performed or evidenced.

| Field | Detail |
|---|---|
| **Integration** | ~~Google Gemini / OpenRouter~~ — 🗑️ **REMOVED** |
| **Objective** | ~~Prove the AI returns usable structured data~~ — **N/A** |
| **Preconditions** | None — the feature no longer exists |
| **Steps** | **N/A** |
| **Expected Result** | **N/A** |
| **Evidence to Capture** | **N/A** |
| **Redact** | **N/A** — the Gemini/OpenRouter keys are no longer used for grade extraction |
| **Production Data Risk** | **NONE** |

---

## `PT-S3-01` — Object Storage Confirmation

| Field | Detail |
|---|---|
| **Integration** | AWS S3 / Railway Object Storage |
| **Objective** | Prove S3/Object Storage is the real production store |
| **Preconditions** | Read access to the bucket listing |
| **Steps** | 1. List the bucket for PUPTAS-prefixed objects. 2. Download one non-PII test object. 3. Open a production SAR PDF in PUPTAS and copy its asset URL. |
| **Expected Result** | Objects present under the PUPTAS prefix; the SAR PDF is served from the external-storage host |
| **Evidence to Capture** | Bucket listing screenshot; browser Network tab showing the external-storage URL |
| **Redact** | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`; any applicant document |
| **Production Data Risk** | **NONE** — read-only. **Do not delete or move objects** |

---

# PART 11 — EVIDENCE CHECKLIST

> **Requirement:** *"Provide evidence supporting the claimed interoperability and data exchange capabilities, including actual interfaces, APIs, data mappings, or successful integration tests where applicable."*

Legend: `[AVAILABLE]` · `[PARTIALLY AVAILABLE]` · `[PRODUCTION VERIFICATION REQUIRED]` · `[NOT FOUND]`

---

## A. Code Evidence

- [x] **API endpoints** — `[AVAILABLE]` — `puptas/routes/api.php:20-71` (13 inbound endpoints), `routes/web.php:201-247` (IDP routes)
- [x] **Controllers / services** — `[AVAILABLE]` — `ExternalProgramApiController`, `ExternalStudentApiController`, `ExternalMedicalApiController`, `IdpAuthController`, `ChatwootWebhookController`, `ResendWebhookController`, `ProcessMedicalWebhookJob`, `ProcessChatwootWebhookJob`, `ChatwootHelper`
- [x] **Authentication** — `[AVAILABLE]` — 4 Passport scopes (`AppServiceProvider.php:97-102`); client-credentials routes (`:219-231`); UUID clients (`:199`); HMAC-SHA256 (`VerifyMedicalWebhookSignature.php:83-88`); Svix (`ResendWebhookController.php:88-107`); Chatwoot HMAC (`ChatwootWebhookController.php:71-74`); OAuth state (`IdpAuthController.php:104-132`); SuperAdmin client provisioning (`ApiClientController.php:62-70`)
- [x] **Request / response structures** — `[AVAILABLE]` — `@response` docblocks on every external controller + `.scribe/endpoints/{00,01,02,03}.yaml` + `.scribe/intro.md`
- [x] **Data mappings** — `[PARTIALLY AVAILABLE]` — code mappings are complete and explicit (Part 3), but the 3 handoff docs **contradict the code** (`department`, `last_updated`, `first_name`/`last_name`, `lifecycle_status`, webhook 200/404 semantics). A doc-reconciliation pass is required.

## B. Test Evidence

- [x] **Automated tests** — `[PARTIALLY AVAILABLE]` — 5 integration test files / 35 tests, but **12 currently FAIL on `main`**
- [x] **Mock tests** — `[AVAILABLE]` — IDP (`IdpStatelessLoginTest.php:16-41`), OpenRouter (8 tests across 2 files). **All external-boundary tests are mocked via `Http::fake()`**
- [x] **Real integration tests** — `[NOT FOUND]` — **zero.** No test contacts a real external system
- [x] **Webhook replay / timestamp tests** — `[AVAILABLE]` — 12 of 12 passing (`WebhookTimestampValidationTest`, `WebhookNonceValidationTest`, `WebhookValidationOrderTest`)
- [ ] **Medical webhook happy-path test** — `[NOT FOUND]` — only rejection paths are tested
- [ ] **Chatwoot tests** — `[NOT FOUND]`
- [ ] **Resend webhook tests** — `[NOT FOUND]`
- [ ] **S3 / FileMapper tests** — `[NOT FOUND]`
- [ ] **Gemini client tests** — `[NOT FOUND]`
- [ ] **`/oauth/token` issuance tests** — `[NOT FOUND]`
- [ ] **CI pipeline producing retained results** — `[NOT FOUND]` — no `.github/workflows`
- [x] **E2E (Playwright)** — `[PARTIALLY AVAILABLE]` — 44 tests exist, but they **bypass IDP** via `/dev-login`; the result is a self-reported Markdown claim, not a machine artifact

## C. Production Evidence

- [ ] **Successful API request (Program / Student / Medical)** — `[PRODUCTION VERIFICATION REQUIRED]`
- [ ] **Successful API response (HTTP 200 + body)** — `[PRODUCTION VERIFICATION REQUIRED]`
- [ ] **Successful SSO / IDP flow** — `[PRODUCTION VERIFICATION REQUIRED]`
- [ ] **Token exchange proof** — `[PRODUCTION VERIFICATION REQUIRED]`
- [ ] **Inbound medical webhook accepted** — `[PRODUCTION VERIFICATION REQUIRED]`
- [ ] **Chatwoot / Resend / AI integration proof** — `[PRODUCTION VERIFICATION REQUIRED]`
- [ ] **Application output (UI showing externally-retrieved data)** — `[PRODUCTION VERIFICATION REQUIRED]`
- [ ] **Relevant sanitized logs** — `[PRODUCTION VERIFICATION REQUIRED]` — local `laravel.log` has **0** matching entries
- [ ] **Partner-side (consumer) confirmation** — `[NOT FOUND]` — no evidence any external partner has ever called the API
- [ ] **Postman collection / captured HTTP transcripts** — `[NOT FOUND]`
- [ ] **Database / queue record of a processed webhook** — `[NOT FOUND]`

## D. Documentation

- [x] **API documentation** — `[AVAILABLE]` — Scribe OpenAPI (4 endpoint YAML files), `config/scribe.php`, `app/Console/Commands/ConvertOpenapiToJson.php`
- [x] **Developer handoff guides** — `[AVAILABLE]` — `docs/PROGRAM_SYSTEM_DEVELOPER_HANDOFF.md`, `docs/MEDICAL_SYSTEM_DEVELOPER_HANDOFF.md`, `docs/MEDICAL_SYSTEM_DEVELOPER_HANDOFF_STAGING.md`, `docs/GUIDANCE_SYSTEM_DEVELOPER_HANDOFF.md`, `docs/API_CLIENT_MANAGEMENT_GUIDE.md`
- [ ] **Data-mapping table (single authoritative source)** — `[NOT FOUND]` — mappings are inferable from code but scattered; existing docs contradict the code
- [ ] **Architecture / data-flow diagram** — `[NOT FOUND]` — no diagram anywhere in the repository
- [ ] **Integration test results (artifact)** — `[NOT FOUND]` — only a self-authored Markdown claim (`E2E_TESTING_CHECKLIST.md`, 2026-08-30)
- [ ] **API versioning / deprecation policy** — `[NOT FOUND]` — 3 endpoints are deprecated (410) but no policy document exists
- [ ] **Webhook end-to-end sample (signed request/response pair)** — `[NOT FOUND]`
- [x] **Env-var reference for integrations** — `[AVAILABLE]` — `puptas/.env.example` includes all integration variables
- [x] **Known-defect register** — `[AVAILABLE]` — `docs/audit-report.md`

---

# PART 12 — FINAL REPORT

## 1. Number of external integrations found

**10 identified** (was 12) — 8 active (IDP, Program API, Student API, Medical Read API, Medical Webhook, Chatwoot, Resend, Gemini), 2 infrastructure (S3/Object Storage, Redis). **OpenRouter and Docling were removed** along with the legacy OCR/AI grade-extraction pipeline. One further config-only item (PostgreSQL/PgVector) is **not** an integration — no code references it.

## 2. List of integrations

1. **IDP OAuth2/OIDC SSO** — outbound authorize / token / userinfo / refresh / logout + inbound callback
2. **PUPTAS Program Catalog API** — `GET /api/v1/programs`
3. **PUPTAS Student Admission API** — 3 endpoints, 1 deprecated
4. **PUPTAS Medical Read API** — 3 endpoints, 1 deprecated
5. **Medical Result Webhook** — `POST /api/v1/webhooks/medical-result` (the only external **write** path)
6. **Chatwoot Live Chat** — inbound webhook + outbound AI reply
7. **Resend Email + Svix delivery tracking** — outbound send + inbound webhook
8. **Google Gemini API** — chat replies (Chatwoot)
9. ~~**OpenRouter API**~~ — 🗑️ **REMOVED** (legacy OCR pipeline)
10. **AWS S3 / Railway Object Storage** — SAR, GVS, F137, user files
11. **Redis (predis)** — token store, replay-nonce cache, queue, locks
12. ~~**Docling**~~ — 🗑️ **REMOVED**; not an integration

## 3. APIs / interfaces identified

**13 inbound endpoints + 10 outbound endpoints**, fully enumerated in Part 2 with methods, auth, request/response shapes, source files and line numbers. Nothing was "not determinable" — every integration's protocol is readable from source.

## 4. Data mappings identified

**6 mapping tables** (IDP→PUPTAS, Program, Student, Medical out, Medical webhook in, Chatwoot, Resend) in Part 3, each field traced to a specific line. Notable derived/computed fields:

- `g12_gwa = round((g12_first_sem + g12_second_sem) / 2, 2)`
- `medical_process_status ?? 'in_progress'`
- webhook `is_health_profile_completed` `0/1` → `applications.status` **and** `application_processes.action` **and** conditional `records`-stage creation

> **Role is NOT mapped from the IDP** — it comes from the local `role_id` column.

## 5. Existing automated tests

**4 integration-relevant test files, 23 tests.** Measured on `main`: **12 FAIL / 23 PASS** (Feature). *(The `Unit/OpenRouter` suite has been removed along with the legacy OCR pipeline.)* Webhook timestamp + nonce suites: **12/12 PASS**. No test suite is currently green across the external-API surface.

## 6. Existing mocked tests

**1 file, 1 test, all `Http::fake`:** `IdpStatelessLoginTest` (1, currently failing). *(The `OpenRouterClientTest` / `OpenRouterClientPropertyTest` mock suites were deleted with the legacy OCR pipeline.)*

> **Every one of these is a MOCKED TEST. None contacts a real external system.**

## 7. Integrations requiring production verification

**All 11 active integrations (1–11).** Docling is excluded because it is disabled. For each, the code exists and the interface is known, but no artifact demonstrates a successful real exchange.

## 8. Exact production evidence needed

Fully specified in **Part 9** (9 integration groups, 31 discrete artifacts) and executed safely in **Part 10** (9 test IDs `PT-*`, with preconditions, redaction lists, and per-test production data risk).

> **The single highest-value artifact is a partner-side screenshot** showing the Program or Guidance System rendering data it pulled from PUPTAS. PUPTAS-side logs alone prove the server responded, **not** that a real partner consumed the data.

## 9. Missing documentation

- **No authoritative data-mapping document** (and the 3 existing handoff guides **contradict the code**)
- **No architecture / data-flow diagram**
- **No Postman collection or committed OpenAPI JSON artifact** (the `scribe:openapi-to-json` command exists but no output is committed)
- **No integration test result artifact** or CI workflow
- **No API versioning / deprecation policy** despite 3 deprecated endpoints
- **No webhook end-to-end sample** (signed request/response pair)

## 10. Risks and limitations

| # | Risk | Severity | Detail |
|---|---|---|---|
| **R1** | **Medical API may over-expose PII** | 🔴 **HIGH** | 4 of 5 `ExternalMedicalApiTest` tests fail — `showByReferenceNumber` returns **200** for applicants the documented contract says must be **404** (evaluator incomplete, interviewer failed, medical already completed, soft-deleted). The endpoint streams `email`, full name, DOB, strand and program to the medical partner. **Verify before any production capture.** |
| **R2** | **Failing CI-equivalent baseline** | 🔴 HIGH | All three External API suites fail on `main`. Presenting them as "automated test evidence" without disclosure would be inaccurate. |
| **R3** | **Zero real-integration evidence** | 🔴 HIGH | No transcript, screenshot, log, or partner confirmation exists. Category **F cannot be claimed for any integration.** |
| **R4** | **Documentation contradicts code** | 🟠 MEDIUM | `department` / `last_updated` (Program), `first_name` / `last_name` / `lifecycle_status` (Guidance), webhook 200/404 semantics (Medical). A partner integrating from the docs would build against the wrong contract. |
| **R5** | **Dead security config** | 🟠 MEDIUM | `EXTERNAL_PROGRAM_API_TOKEN` and `EXTERNAL_MEDICAL_API_TOKEN` are defined in `config/services.php:61,66` but **no middleware consumes them**. `ExternalProgramApiTest` still tests the abandoned static-token model, creating a false impression of a second auth path. |
| **R6** | **OAuth `state` weakened** | 🟠 MEDIUM | `IdpAuthController.php:104-118` documents *"WORKAROUND: The IDP drops `state`"* and falls back to mere session existence. `docs/security-fixes-status.md:42-53` claims this was COMPLETED — the code shows otherwise. |
| **R7** | **IDP role not federated** | 🟡 LOW–MED | If any claim implies role sync from the IDP, it is **unsupported by code** (`:368` uses local `role_id`). |
| **R8** | **AI keys travel in URLs** | 🟡 MEDIUM | The Gemini key is placed in the query string by `ProcessChatwootWebhookJob`; URLs are logged (e.g. `IdpAuthController.php:72` logs the full authorize URL). **Log excerpts must be trimmed before capture.** |
| **R9** | **Chatwoot + Resend fail closed** | 🟡 LOW | If unset, inbound webhooks are hard-rejected (500/403). Good security, but a misconfigured production instance fails **silently and completely** — worth an explicit health check. |
| **R10** | **Test env bypasses IDP** | 🟡 LOW | `RefreshIdpToken.php:25` short-circuits on `app.env ∈ {local, testing}`. Local tests **never exercise the real token-refresh path**. |
| **R11** | **Postgres/PgVector dead config** | 🟢 LOW | Presence in `.env.example` could create a false impression of a vector-search integration. No code references it. *(The sibling Docling dead-config risk is **RESOLVED** — Docling/OCR has been removed entirely.)* |

---

# FINAL EVIDENCE SEPARATION

## ✅ CODE EVIDENCE

> Routes, controllers, services, jobs, middleware, DTOs, scope definitions, rate limiters, audit logging, an OpenAPI spec, and **four detailed partner handoff guides** all exist in the repository. The engineering artifacts for interoperability are substantial, well-structured, and — for the replay-protection and field-mapping layers — demonstrably rigorous.
>
> **This is legitimate, presentable evidence of engineering capability.**

## 🟨 TEST EVIDENCE

> **Mocked only.** 1 test uses `Http::fake`; the remaining integration tests exercise PUPTAS's own controllers in-process. **12 Feature tests currently FAIL on `main`**, including all of `ExternalProgramApiTest`, 4 of 5 `ExternalMedicalApiTest`, and the core IDP callback test. The 12 webhook timestamp/nonce tests are genuinely green and are the **strongest automated evidence** in the repository.
>
> **This must be disclosed honestly, not presented as a passing suite.**

## 🔍 PRODUCTION VERIFICATION

> **None exists.** `storage/logs/laravel.log` contains **zero** occurrences of `IDP token exchange successful` and **zero** of `External programs list requested`. No HTTP transcript, no partner screenshot, no delivery receipt, no queue-completion record, no CI artifact.
>
> **Every one of the 10 active integrations is at status G — PRODUCTION VERIFICATION REQUIRED.**

## ❌ MISSING EVIDENCE

> A data-mapping document; an architecture/data-flow diagram; a Postman collection; a signed medical-webhook sample; retained test-result artifacts; a CI workflow; and — most importantly — **any single artifact proving that a real external system has ever exchanged data with PUPTAS**.

# ⚠️ RECOMMENDED ACTIONS BEFORE CAPTURING PRODUCTION EVIDENCE

1. **R1 — Triage the Medical API 404 defect first.** Four failing tests indicate the eligibility contract is not enforced on the medical lookup path. Capturing a production screenshot of that endpoint could document a PII-disclosure defect as if it were correct behaviour.

2. **R2 — Repair the failing External API tests** so the automated evidence is green. Required fixes:
   - Replace `withToken('test-program-token')` with `Passport::actingAsClient(...)` in `ExternalProgramApiTest`
   - Add an `email` value to the `test_passers` factory in `ExternalStudentApiTest`
   - Update the 401-message assertion to the app's actual response envelope
   - Resolve the `/login` route conflict breaking `IdpLoginPreservationTest:40` and `IdpLoginBugConditionTest`
   - ~~Reconcile `OpenRouterMigrationSmokeTest` with the still-Gemini `GradeExtractionService`~~ — **no longer applicable; the whole pipeline has been removed**

3. **R4 — Reconcile the three handoff guides with the code** so partners are not integrating against a wrong contract.

4. **R5 — Remove or wire up the dead `EXTERNAL_*_API_TOKEN` config keys** to eliminate the false impression of a second auth path.

---

# AUDIT ATTESTATION

| Item | Status |
|---|---|
| **Codebase modified?** | **NO** — verified via `git status` and `git diff --stat`; only pre-existing `.DS_Store` OS metadata differs |
| **Production environment contacted?** | **NO** |
| **Production data or configuration modified?** | **NO** |
| **Destructive operations performed?** | **NO** |
| **Secrets / tokens / credential values exposed?** | **NO** — only environment variable **names** and required/optional status are documented |
| **Commands executed** | Read-only inspection (`ls`, `cat`, `grep`, `git log/status/diff`, `find`) plus Pest runs against **in-memory SQLite** (`phpunit.xml`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `CACHE_STORE=array`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`, `LOG_CHANNEL=null`) — **no external system contacted, no persistent data written** |
| **Integrations claimed as real / verified** | **NONE.** No integration qualifies for evidence category **F**. |

---

*Audit generated 2026-09-29 · Repository `Undrafted-PUPTAS` · Branch `main` @ `52a32ff6` · Auditor: automated codebase review*

---
