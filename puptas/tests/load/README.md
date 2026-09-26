# PUPTAS k6 Performance & Load Testing Suite

This directory contains the automated performance and load testing suite for the **Polytechnic University of the Philippines Taguig Admission System (PUPTAS)** using [k6](https://k6.io/).

---

## 1. Environment & Safety Guidelines

> [!CAUTION]
> **Always run load tests against the Railway Staging environment, NEVER against Production.**
> Running load tests against live production risks:
> - Corrupting actual applicant records and application processes.
> - Triggering external transactional email rate limits (e.g., Resend limits).
> - Skewing production audit trails and metrics.

### Staging Configuration
- **Default Staging Base URL:** `https://pup-admission-system-staging.up.railway.app`
- Configurable via environment variable: `BASE_URL`
- Default Test Account: `testapplicant@gmail.com` (created via `/setup-test-applicant` on staging/local)
- Configurable credentials via: `TEST_USER_EMAIL` and `TEST_USER_PASSWORD`

---

## 2. Tested Endpoints & Architectural Rationale

These endpoints correspond to core system claims regarding **concurrent-user performance** and **transaction processing time**:

| Script | Endpoint | Protocol / Method | Panel Defense Focus |
|---|---|---|---|
| [`status-check.js`](file:///c:/Users/Myla/OneDrive/Desktop/Undrafted-PUPTAS/puptas/tests/load/status-check.js) | `/api/public/admission-results` | `POST` (JSON) | **Massive Concurrent Read Throughput**: Simulates the traffic spike when PUPCET admission results are released. Tests DB index performance, SHA-256 name hashing, audit logging, and throttle stability under load. |
| [`auth-login.js`](file:///c:/Users/Myla/OneDrive/Desktop/Undrafted-PUPTAS/puptas/tests/load/auth-login.js) | `/login?local=1` & `/login` | `GET` + `POST` | **Session Concurrency**: Tests session store locking/writing, CSRF token handling, and authentication throughput without session lock contention. |
| [`document-upload.js`](file:///c:/Users/Myla/OneDrive/Desktop/Undrafted-PUPTAS/puptas/tests/load/document-upload.js) | `/upload-files` | `POST` (Multipart) | **I/O & Storage Concurrency**: Tests server throughput during concurrent document uploads, validating multipart streaming, disk/cloud storage operations, and memory consumption. |
| [`application-submission.js`](file:///c:/Users/Myla/OneDrive/Desktop/Undrafted-PUPTAS/puptas/tests/load/application-submission.js) | `/user/application/submit` | `POST` (JSON) | **Transactional Integrity & Row Locking**: Verifies that concurrent applicant submissions do not deadlock or oversell program quotas. |
| [`applicant-workflow.js`](file:///c:/Users/Myla/OneDrive/Desktop/Undrafted-PUPTAS/puptas/tests/load/applicant-workflow.js) | Full User Flow | Composite | **Realistic End-to-End Simulation**: Chains together status check → login → view programs → upload file → submit application. |

---

## 3. Running the Load Tests

You can execute the tests either via `npm run` or directly with the `k6` CLI:

### Running via NPM (from `puptas/` directory)

```bash
# 1. Public Admission Status Check
npm run test:load:status

# 2. Authentication & Login
npm run test:load:login

# 3. Document Upload
npm run test:load:upload

# 4. Application Choices Submission
npm run test:load:submit

# 5. Full End-to-End Composite Workflow
npm run test:load:workflow

# Quick 30-second smoke test with 5 virtual users
npm run test:load:quick
```

### Running via k6 CLI Directly

```bash
# Standard run (uses default stages: 30s ramp to 20 VUs, 1m hold, 20s ramp down)
k6 run tests/load/status-check.js

# Custom VUs and duration
k6 run --vus 50 --duration 1m tests/load/status-check.js

# Pointing to a custom environment or local dev server
k6 run -e BASE_URL=http://localhost:8000 tests/load/status-check.js

# Specifying test account credentials
k6 run -e TEST_USER_EMAIL=testapplicant@gmail.com -e TEST_USER_PASSWORD=your_password tests/load/auth-login.js
```

---

---

## 4. Rate Limiter Architecture & Load Testing Strategy

The PUPTAS staging environment enforces strict security rate limiters to defend against credential harvesting, enumeration attacks, and flood abuse. When running load tests from a single machine/IP, requests will trigger HTTP 429 once the per-IP threshold is reached.

### How to Present This to the Panel (Two-Phase Evidence Strategy)

For your thesis/capstone defense, having **both** test results provides rock-solid evidence:

1. **Phase 1: Security Control Validation (Limiters Enabled - Default)**
   - **Goal:** Proves that the application successfully blocks flood traffic and prevents database resource exhaustion by responding with HTTP 429 (`Too Many Requests`).
   - **Panel takeaway:** Demonstrates compliance with security baselines and active defense mechanisms.

2. **Phase 2: Raw Performance Capacity (Limiters Relaxed - Staging Only)**
   - **Goal:** Tests pure backend processing power, database query latency, and server concurrency (VUs 20 to 50) without artificial throttling caps.
   - **Panel takeaway:** Demonstrates the application's actual throughput (RPS), p95 latency (< 500ms), and concurrency handling under sustained user traffic.

---

### Toggling Rate Limiters via Railway Staging Environment Variables

The rate limiters in [AppServiceProvider.php](file:///c:/Users/Myla/OneDrive/Desktop/Undrafted-PUPTAS/puptas/app/Providers/AppServiceProvider.php) and [FortifyServiceProvider.php](file:///c:/Users/Myla/OneDrive/Desktop/Undrafted-PUPTAS/puptas/app/Providers/FortifyServiceProvider.php) can be adjusted on Railway Staging via the project dashboard:

| Variable | Default Value | Load Testing Value (Phase 2) | Purpose |
|---|---|---|---|
| `STATUS_CHECKER_DISABLE_THROTTLING` | `false` | `true` | Completely disables rate limiting on `POST /api/public/admission-results` |
| `STATUS_CHECKER_IP_MINUTE_LIMIT` | `60` | `5000` | Alternatively, raises the per-IP flood cap from 60 req/min to 5,000 req/min |
| `STATUS_CHECKER_REF_MINUTE_LIMIT` | `10` | `1000` | Raises the per-reference-number limit from 10 req/min to 1,000 req/min |
| `LOGIN_DISABLE_THROTTLING` | `false` | `true` | Disables the 5 req/min per-IP limit on `POST /login` |
| `LOGIN_RATE_LIMIT` | `5` | `1000` | Alternatively, increases allowable login attempts per minute |

> [!TIP]
> After completing Phase 2 load testing, remove these override variables or set them back to `false` in Railway to restore production-parity security controls.

---

## 5. Key Metrics for the Panel Presentation

When presenting the load test results to the thesis/capstone panel, highlight these metrics:

1. **`http_req_duration (p95)` (95th Percentile Latency):**
   - Shows that 95% of requests completed faster than this time (e.g., `< 500ms`). This demonstrates consistent responsiveness without outlier lag.
2. **`http_reqs / s` (Throughput / Requests Per Second - RPS):**
   - Demonstrates the total sustained request volume the server handled per second.
3. **`http_req_failed` (Error Rate):**
   - Confirms stability under load (target: `< 1%`).
4. **`checks_succeeded`:**
   - Verifies functional accuracy (JSON schema valid, HTTP 200/302/422 responses received without internal server crashes or 500 error spikes).

