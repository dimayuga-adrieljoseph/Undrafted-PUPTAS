# Medical System Developer Handoff

Welcome to the PUPTAS Medical API integration guide. This document contains everything you need to connect your Medical Application to the PUPTAS Core System securely using a Zero-Trust M2M (Machine-to-Machine) architecture.

## Overview
- **Authentication**: OAuth 2.0 (Client Credentials Grant)
- **Base URL**: `/api/v1/`
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

- **GET** `/api/v1/medical/applicants` (Deprecated - Avoid bulk polling)
- **GET** `/api/v1/medical/applicants/idp/{idpUserId}`
- **GET** `/api/v1/medical/applicants/{studentNumber}`

**Success Response (200 OK)**
```json
{
    "data": {
        "idp_user_id": "a1b2c3d4...",
        "student_number": "2026-MED-1234",
        "email": "student@pup.edu.ph",
        "first_name": "Juan",
        "last_name": "Dela Cruz",
        "program": {
            "code": "BSCS",
            "name": "Bachelor of Science in Computer Science"
        },
        "lifecycle_status": "Ready for Medical Phase"
    }
}
```

---

## 3. Writing Data: Medical Webhook (`medical-write` scope)

Once a medical evaluation is complete, you will push the result back to PUPTAS.

**Endpoint**: `POST /api/v1/webhooks/medical-result`

### Security Requirement: HMAC-SHA256
In addition to the OAuth Bearer token, you **must** crytographically sign the JSON body using a shared webhook secret provided by the PUPTAS Admin. 

Calculate an `HMAC-SHA256` hash of the raw request body using the shared webhook secret, and send it in the `X-Medical-Signature` header.

**Payload Structure**
```json
{
    "student_number": "2026-MED-1234",
    "medical_status": "cleared" // Can be "cleared" or "failed"
}
```

**Example Implementation (Node.js)**
```javascript
const crypto = require('crypto');
const axios = require('axios');

const payload = JSON.stringify({
    student_number: "2026-MED-1234",
    medical_status: "cleared"
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
- `401 Unauthorized`: Missing or invalid OAuth Token.
- `403 Forbidden`: Invalid HMAC Signature.
- `404 Not Found`: Applicant not found, already passed, or missing prerequisite evaluator/interviewer stages.

---

## Developer Support Flow
If you encounter `404` errors for a student, verify they have successfully passed the Interviewer phase in PUPTAS. Our strict admission pipeline rejects medical records for unverified students.
