# Medical System Developer Handoff

Welcome to the PUPTAS Medical API integration guide. This document contains everything you need to connect your Medical Application to the PUPTAS Core System securely using a Zero-Trust M2M (Machine-to-Machine) architecture.

## Overview
- **Authentication**: OAuth 2.0 (Client Credentials Grant)
- **Base URL**: `/api/v1/`
- **Host**: `puptas.undraftedbsit2027.com`
- **Required Scopes**: `medical-read` (for fetching data), `medical-write` (for sending webhooks)
- **Rate Limits**: 5 req/sec, 80 req/min, 100 req/day

---

## 1. Authentication (OAuth 2.0)
Before making any requests, you must obtain a short-lived access token. You will be provided a `client_id` (a UUID) and a `client_secret` by the PUPTAS Admin.

**Requesting a Token**
```http
POST /oauth/token
Host: puptas.undraftedbsit2027.com
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&client_id={YOUR_UUID}&client_secret={YOUR_SECRET}&scope=medical-read medical-write
```

**Response**
```json
{
    "token_type": "Bearer",
    "expires_in": 31536000,
    "access_token": "eyJ0eXAiOi..."
}
```

*Note: All API requests below must include the header: `Authorization: Bearer <access_token>` and `Accept: application/json`.*

---

## 2. Reading Data (`medical-read` scope)

### Fetch Eligible Applicants
Retrieve applicants who have successfully passed the Evaluator and Interviewer stages and are ready for medical evaluation.

- **GET** `/api/v1/medical/applicants` (Deprecated — returns `410 Gone`)
- **GET** `/api/v1/medical/applicants/idp/{idpUserId}` — Lookup by IDP User ID (UUID)
- **GET** `/api/v1/medical/applicants/{referenceNumber}` — Lookup by Reference Number

> [!IMPORTANT]
> Only applicants who meet **all** of these conditions will be returned:
> - Passed or transferred in the **Grade Evaluator** stage
> - Passed or transferred in the **Interviewer** stage
> - Have an active medical process (`in_progress` or `returned`)
> - Have **not** already completed the medical stage
>
> If any condition is not met, the API returns `404`.

**Success Response (200 OK)**
```json
{
    "data": {
        "id": 8,
        "idp_user_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
        "reference_number": "2026-8889-8828",
        "salutation": null,
        "firstname": "Juan",
        "middlename": "D",
        "extension_name": null,
        "lastname": "Dela Cruz",
        "sex": "Male",
        "email": "student@pup.edu.ph",
        "date_graduated": "2026-04-01T00:00:00.000000Z",
        "strand": "STEM",
        "track": "Academic",
        "application": {
            "id": 3,
            "status": "submitted",
            "created_at": "2026-06-14T07:28:29.000000Z"
        },
        "program": {
            "id": 1,
            "code": "BSCS",
            "name": "Bachelor of Science in Computer Science"
        },
        "medical_process_status": "in_progress"
    }
}
```

**Error Response (404 Not Found)**
```json
{
    "message": "Applicant not found or not eligible for medical yet."
}
```

---

## 3. Writing Data: Medical Webhook (`medical-write` scope)

Once a medical evaluation is complete, you will push the result back to PUPTAS.

**Endpoint**: `POST /api/v1/webhooks/medical-result`

### Security Requirement: HMAC-SHA256
In addition to the OAuth Bearer token, you **must** cryptographically sign the JSON body using a shared webhook secret provided by the PUPTAS Admin. 

Calculate an `HMAC-SHA256` hash of the raw request body using the shared webhook secret, and send it in the `X-Medical-Signature` header.

### Payload Structure

```json
{
    "reference_number": "2026-8889-8828",
    "idp_user_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "is_health_profile_completed": 1,
    "timestamp": 1718464200,
    "nonce": "unique_string_123"
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `reference_number` | string | Conditional | The applicant's reference number. At least one of `reference_number` or `idp_user_id` must be provided. |
| `idp_user_id` | string | Conditional | The applicant's IDP UUID. Can also be sent as `student_id`. At least one of `reference_number` or `idp_user_id` must be provided. |
| `is_health_profile_completed` | integer | **Required** | `1` = cleared/passed, `0` = failed. |
| `timestamp` | integer | **Required** | Unix timestamp in seconds. Must be within 5 minutes of the server's current time. |
| `nonce` | string | **Required** | A unique, cryptographically random string to prevent replay attacks. |

> [!IMPORTANT]
> **Anti-Replay Attack Measures**
> To prevent malicious actors from intercepting and re-sending a valid webhook request, the PUPTAS system enforces strict replay protection:
> - **`timestamp`**: Must be a Unix timestamp (in seconds) within 5 minutes of the server's current time. Older requests will be rejected with `403 Request expired`.
> - **`nonce`**: Must be a unique, cryptographically random string for every request. If a request is sent within the 5-minute window with a previously used nonce, it will be rejected as a duplicate.

> [!WARNING]
> **Common Mistake**: Do NOT send `medical_status: "cleared"`. The correct field name is `is_health_profile_completed` with an integer value of `1` (cleared) or `0` (failed). Sending the wrong field will result in a `422 Validation Error`.

**Example Implementation (Node.js)**
```javascript
const crypto = require('crypto');
const axios = require('axios');

const payload = JSON.stringify({
    reference_number: "2026-8889-8828",
    idp_user_id: "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    is_health_profile_completed: 1,
    timestamp: Math.floor(Date.now() / 1000),
    nonce: crypto.randomBytes(16).toString('hex')
});

const secret = "YOUR_WEBHOOK_SECRET";
const signature = crypto.createHmac('sha256', secret).update(payload).digest('hex');

axios.post('https://puptas.undraftedbsit2027.com/api/v1/webhooks/medical-result', payload, {
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Bearer YOUR_OAUTH_TOKEN',
        'X-Medical-Signature': signature
    }
});
```

*Note: The HMAC signature must be computed exactly over the raw request body bytes that are sent to the server. If the payload is modified during transit or serialization (like extra spaces), the signature verification will fail.*

**Response Codes**
- `200 OK`: `{"message": "Medical result recorded successfully"}`
- `400 Bad Request`: Missing `timestamp` or `nonce` in payload.
- `401 Unauthorized`: Missing or invalid OAuth Token.
- `403 Forbidden`: Invalid HMAC Signature, expired timestamp, or duplicate nonce.
- `404 Not Found`: Applicant not found, already passed, or missing prerequisite evaluator/interviewer stages.
- `422 Unprocessable Entity`: Validation error — check that `is_health_profile_completed` is present and is `0` or `1`, and that at least one identifier (`reference_number` or `idp_user_id`) is provided.

---

## Developer Support Flow
If you encounter `404` errors for a student, verify:
1. They have successfully passed the **Grade Evaluator** phase in PUPTAS.
2. They have successfully passed the **Interviewer** phase in PUPTAS.
3. Their medical process is currently **in progress** (not already completed).

Our strict admission pipeline rejects medical records for unverified students.
