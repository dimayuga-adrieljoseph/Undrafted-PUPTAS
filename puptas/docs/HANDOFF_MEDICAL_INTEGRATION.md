# Medical System Developer Handoff & Integration Guide

## 1. Overview
This document provides the necessary technical details for the **External Medical System** team to integrate with the **PUPTAS (PUP Tertiary Admission System)**. 

The integration allows your system to retrieve applicant profiles for medical clearance once they have successfully passed the Evaluator and Interviewer stages of the admission process.

## 2. Shared Identity Provider (IDP)
PUPTAS and the Medical System MUST use a common Identity Provider. 
- Identification in this API is strictly handled via the **IDP User ID**.
- This ensures a 1-to-1 mapping of applicants across both platforms.

## 3. Applicant Eligibility Rules
An applicant will **ONLY** return a successful response (200 OK) if they meet the following criteria in PUPTAS:
1.  **Evaluator Stage**: Completed (Status: Passed/Transferred).
2.  **Interviewer Stage**: Completed (Status: Passed/Transferred).
3.  **Medical Stage**: Currently In Progress or Returned.

If any of these conditions are not met, the API will return a `404 Not Found`.

## 4. API Reference

### Base URL
- Production: `https://<your-domain>/api/v1`
- Staging/Local: `http://127.0.0.1:8000/api/v1`

### Authentication
A dedicated Bearer token is required for all requests.
- **Header**: `Authorization: Bearer <EXTERNAL_MEDICAL_API_TOKEN>`
- **Header**: `Accept: application/json`

### Endpoint: Lookup by IDP User ID
Retrieves the full profile of a single eligible applicant.

```http
GET /api/v1/medical/applicants/idp/{idpUserId}
```

#### Sample Response (200 OK):
```json
{
  "data": {
    "id": 43,
    "idp_user_id": "idp_med_43",
    "student_number": "2026-MED-1234",
    "firstname": "Juan",
    "middlename": "A",
    "lastname": "dela Cruz",
    "email": "juan.dc@example.com",
    "contact_number": "09123456789",
    "program": {
      "id": 1,
      "code": "BSIT",
      "name": "BS Information Technology"
    },
    "application": {
      "id": 105,
      "status": "submitted",
      "created_at": "2026-04-01T13:25:24.000000Z"
    },
    "medical_process_status": "in_progress"
  }
}
```

## 5. Rate Limiting & Throttling
To protect system stability, the following limits are enforced:
- **5 requests per second**
- **80 requests per minute**
- **1,500 requests per day**

Exceeding these will result in an `HTTP 429 Too Many Requests` error.

## 6. Common Error Codes
| Status | Meaning | Developer Action |
| --- | --- | --- |
| `401` | Unauthorized | Verify your Bearer token in the `Authorization` header. |
| `404` | Not Found | Applicant doesn't exist OR has not yet cleared Evaluator/Interviewer stages. |
| `410` | Gone | Triggered if attempting to use the deprecated bulk list endpoint. |
| `429` | Rate Limited | Reduce the frequency of your API calls. |

## 7. Integration Flow Suggestions
We recommend a "Just-in-Time" lookup:
1. The applicant arrives at the medical clinic.
2. Your system retrieves their **IDP User ID**.
3. Call the PUPTAS API to fetch their profile and verified program choices.
4. Proceed with the medical examination using the retrieved data.

---
*Technical support: Admission System Administrator*
