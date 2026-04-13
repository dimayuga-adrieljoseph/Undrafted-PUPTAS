# Program System Developer Handoff

Welcome to the PUPTAS Program API integration guide. This document explains how external systems or microsites can securely fetch active academic programs from PUPTAS.

## Overview
- **Authentication**: OAuth 2.0 (Client Credentials Grant)
- **Base URL**: `/api/v1/`
- **Required Scope**: `program-read`
- **Rate Limits**: 5 req/sec, 50 req/day (Aggressively cached endpoint)

---

## 1. Authentication (OAuth 2.0)
You must fetch a bearer token using your unique `client_id` (a UUID format) and `client_secret` assigned by the PUPTAS Admin.

**Request Token**
```http
POST /oauth/token
Host: puptas.undraftedbsit2027.com
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&client_id={YOUR_UUID}&client_secret={YOUR_SECRET}&scope=program-read
```

**Token Response**
```json
{
    "token_type": "Bearer",
    "expires_in": 31536000,
    "access_token": "eyJ0eXAiOi..."
}
```

*Note: You must include `Authorization: Bearer <access_token>` and `Accept: application/json` in all requests.*

---

## 2. Fetching Programs

This endpoint serves a list of valid, active programs open for application in PUPTAS. Due to the strict `50 req/day` limit, we recommend fetching this data once per day and caching it heavily on your system.

**Endpoint**: `GET /api/v1/programs`

### Example Request
```http
GET /api/v1/programs
Host: puptas.domain.com
Accept: application/json
Authorization: Bearer eyJ0eXA...
```

### Example Response (200 OK)
```json
{
    "data": [
        {
            "id": 1,
            "code": "BSCS",
            "name": "Bachelor of Science in Computer Science",
            "department": "College of Computer and Information Sciences"
        },
        {
            "id": 2,
            "code": "BSIT",
            "name": "Bachelor of Science in Information Technology",
            "department": "College of Computer and Information Sciences"
        }
    ],
    "meta": {
        "total": 2,
        "last_updated": "2026-04-03T10:00:00Z"
    }
}
```

## Error Handling
- `401 Unauthorized`: Token is missing, expired, or invalid.
- `403 Forbidden`: Your token lacks the `program-read` scope.
- `429 Too Many Requests`: You have exceeded the 50 calls per day. Please respect caching mechanisms.
