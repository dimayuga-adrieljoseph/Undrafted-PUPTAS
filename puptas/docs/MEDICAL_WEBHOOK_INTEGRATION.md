# Medical System Webhook Integration Guide

> [!WARNING]
> **This is a simplified quick-reference guide.** For the complete, authoritative integration documentation including full authentication setup, see [MEDICAL_SYSTEM_DEVELOPER_HANDOFF.md](MEDICAL_SYSTEM_DEVELOPER_HANDOFF.md).

## Overview
This document describes how the medical system should send webhook notifications to PUPTAS when a student completes their medical examination.

---

## Webhook Endpoint

**URL**: `POST /api/v1/webhooks/medical-result`

**Authentication**: Bearer token (OAuth 2.0 Client Credentials with `medical-write` scope)

**Content-Type**: `application/json`

**Required Header**: `X-Medical-Signature` — HMAC-SHA256 signature of the raw request body (see Security section below)

---

## Request Format

### Required Fields

The webhook accepts the following payload:

```json
{
  "student_id": "ade67dc4-50f0-4e32-bd80-84308c0f4e10",
  "reference_number": "2024-12345",
  "is_health_profile_completed": 1,
  "timestamp": 1718464200,
  "nonce": "a1b2c3d4e5f6g7h8"
}
```

### Field Descriptions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `student_id` | string (UUID) | Conditional* | The student's IDP user ID (UUID format). Can also be sent as `idp_user_id`. |
| `reference_number` | string | Conditional* | The student's official reference number |
| `is_health_profile_completed` | integer | **Required** | Medical clearance status: `1` = cleared/passed, `0` = failed |
| `timestamp` | integer | **Required** | Unix timestamp in seconds. Must be within **5 minutes** of the server's current time. |
| `nonce` | string | **Required** | A unique, cryptographically random string to prevent replay attacks. |

**Note**: At least ONE of `student_id` or `reference_number` must be provided. Providing both is recommended for better matching.

> [!IMPORTANT]
> **Anti-Replay Protection:** The `timestamp` must be within 5 minutes of the server time (rejected with `403 Request expired` otherwise). The `nonce` must be unique per request — duplicate nonces within the 10-minute cache window are rejected with `403 Duplicate request`.

---

## Field Mapping

### Medical System → PUPTAS Mapping

| Your Field | PUPTAS Field | Notes |
|------------|--------------|-------|
| `student_id` | `idp_user_id` | UUID format, primary identifier |
| `reference_number` | `reference_number` | Same field name, secondary identifier |
| `is_health_profile_completed` | `medical_status` | `1` = cleared, `0` = failed |

---

## Webhook Behavior

### When to Send Webhook

**Send webhook ONLY when**: `is_health_profile_completed = 1` (student is cleared)

**Do NOT send webhook when**: `is_health_profile_completed = 0` (student not cleared/incomplete)

### What Happens When You Send

1. PUPTAS receives the webhook
2. System looks up the student by `reference_number` (priority) or `student_id` (fallback)
3. Validates the student is at the medical stage
4. Updates application status:
   - `is_health_profile_completed = 1` → Application status: `cleared_for_enrollment`
   - `is_health_profile_completed = 0` → Application status: `rejected`
5. Marks medical process as completed
6. Returns success response

---

## Response Codes

### Success Response (200 OK)

```json
{
  "message": "Medical result recorded successfully"
}
```

### Error Responses

#### 422 Validation Error - Missing Required Fields

```json
{
  "message": "Validation failed",
  "errors": {
    "is_health_profile_completed": ["The is health profile completed field is required."]
  }
}
```

#### 422 Validation Error - Missing Identifier

```json
{
  "message": "Either reference_number or student_id (idp_user_id) must be provided"
}
```

#### 404 Not Found - Student Not Eligible

```json
{
  "message": "Applicant not found or not eligible for medical stage"
}
```

**Reasons for 404**:
- Student doesn't exist in PUPTAS
- Student hasn't reached medical stage yet
- Student already completed medical stage

---

## Example Webhook Calls

### Example (Node.js — Recommended)

```javascript
const crypto = require('crypto');
const axios = require('axios');

const payload = JSON.stringify({
    student_id: "ade67dc4-50f0-4e32-bd80-84308c0f4e10",
    reference_number: "2024-12345",
    is_health_profile_completed: 1,
    timestamp: Math.floor(Date.now() / 1000),
    nonce: crypto.randomBytes(16).toString('hex')
});

const secret = "YOUR_WEBHOOK_SECRET";
const signature = crypto.createHmac('sha256', secret).update(payload).digest('hex');

await axios.post('https://puptas.undraftedbsit2027.com/api/v1/webhooks/medical-result', payload, {
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Bearer YOUR_OAUTH_TOKEN',
        'X-Medical-Signature': signature
    }
});
```

### Example (cURL)

```bash
# 1. Prepare the JSON payload (must include timestamp and nonce)
PAYLOAD='{"student_id":"ade67dc4-50f0-4e32-bd80-84308c0f4e10","reference_number":"2024-12345","is_health_profile_completed":1,"timestamp":'$(date +%s)',"nonce":"'$(openssl rand -hex 16)'"}'

# 2. Compute the HMAC-SHA256 signature
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "YOUR_WEBHOOK_SECRET" | awk '{print $2}')

# 3. Send the request
curl -X POST https://puptas.undraftedbsit2027.com/api/v1/webhooks/medical-result \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -H "X-Medical-Signature: $SIGNATURE" \
  -d "$PAYLOAD"
```

---

## Important Notes

### 1. Security — HMAC Signature (Mandatory)
- You **must** compute an HMAC-SHA256 hash of the raw request body using the shared webhook secret
- Send the hash in the `X-Medical-Signature` header
- The webhook secret is provided by the PUPTAS Admin (separate from OAuth credentials)
- Requests without a valid signature are rejected with `403 Invalid Signature`
- The HMAC must be computed on the **exact raw bytes** sent in the request body

### 2. Anti-Replay Protection (Mandatory)
- Every request **must** include a `timestamp` (Unix seconds, within 5 minutes of server time)
- Every request **must** include a unique `nonce` string
- Expired timestamps → `403 Request expired`
- Duplicate nonces within the 10-minute window → `403 Duplicate request`

### 3. Retry Logic
- If webhook fails (network error, timeout), retry with exponential backoff
- **Generate a new `nonce` and `timestamp` for each retry** — reusing the same values will be rejected as a duplicate

### 4. General
- Always use HTTPS
- Include valid Bearer token (OAuth 2.0 with `medical-write` scope) in Authorization header
- PUPTAS processes webhooks in real-time

---

## Testing

### Test Endpoint
Use the same endpoint for testing: `/api/v1/webhooks/medical-result`

For the staging environment, see [MEDICAL_SYSTEM_DEVELOPER_HANDOFF_STAGING.md](MEDICAL_SYSTEM_DEVELOPER_HANDOFF_STAGING.md).

### Test Payload
```json
{
  "student_id": "test-uuid-12345",
  "reference_number": "TEST-2024-001",
  "is_health_profile_completed": 1,
  "timestamp": 1718464200,
  "nonce": "unique-test-string-001"
}
```

> [!WARNING]
> Remember: the test payload still requires a valid `X-Medical-Signature` HMAC header and the `timestamp` must be within 5 minutes of the server time.

---

## Support

For integration issues or questions, contact the PUPTAS Core Development team.

**Full Integration Guide:** [MEDICAL_SYSTEM_DEVELOPER_HANDOFF.md](MEDICAL_SYSTEM_DEVELOPER_HANDOFF.md)

---

## Changelog

| Date | Version | Changes |
|------|---------|---------|
| 2026-09-30 | 1.1 | Added mandatory `timestamp`, `nonce`, HMAC-SHA256 signature requirements. Added anti-replay protection docs. Updated examples with proper security headers. |
| 2026-04-16 | 1.0 | Initial webhook integration with `is_health_profile_completed` field |
