# Guidance Developer Handoff

## Purpose
Integration guide for Guidance developers consuming PUPTAS student data via API.

## Base URL
- `https://puptas.undraftedbsit2027.com`

## Authentication
Use Bearer token on every request:

```http
Authorization: Bearer <API_TOKEN>
Accept: application/json
```

## Endpoints

### 1. Single Student by IDP User ID (Primary for IDP-integrated systems)
`GET /api/v1/students/idp/{idpUserId}`

Example:

```http
GET /api/v1/students/idp/idp-abc-123
```

Behavior:
- `200` if IDP user exists and is officially enrolled
- `404` if not found or not officially enrolled

### 2. List Endpoint (Disabled)
`GET /api/v1/students`

Deprecation notice:
- This endpoint is legacy and includes these response headers:
  - `Deprecation: true`
  - `Sunset: Tue, 30 Jun 2026 23:59:59 GMT`
  - `Link: </api/v1/students/{studentNumber}>; rel="successor-version"`
- Current behavior: returns `410 Gone`.

Optional query params:
- `per_page` (1-200)
- `page` (>=1)
- `updated_since` (date)

## Data Rules
- Only returns records with `application.enrollment_status = officially_enrolled`.
- Sensitive auth fields are never included.

## Single-Student Success Response

```json
{
  "data": {
    "id": 101,
    "student_number": "2026-00001",
    "firstname": "Juan",
    "middlename": "Santos",
    "extension_name": null,
    "lastname": "Dela Cruz",
    "email": "juan.delacruz@example.com",
    "contactnumber": "09170000000",
    "birthday": "2005-08-17",
    "sex": "male",
    "street_address": "Blk 1 Lot 1",
    "barangay": "Barangay 1",
    "city": "City",
    "province": "Province",
    "postal_code": "1000",
    "application": {
      "application_id": 555,
      "status": "accepted",
      "enrollment_status": "officially_enrolled",
      "enrollment_position": 12,
      "submitted_at": "2026-01-12T08:15:00.000000Z"
    },
    "program": {
      "program_id": 7,
      "program_code": "BSCS",
      "program_name": "BS Computer Science"
    },
    "created_at": "2026-01-10T12:00:00.000000Z",
    "updated_at": "2026-03-17T09:00:00.000000Z"
  }
}
```

## Common Errors
- `401 Unauthorized`

```json
{
  "message": "Unauthorized"
}
```

- `404 Student not found`

```json
{
  "message": "Student not found"
}
```

- `429 Too Many Requests`

```json
{
  "message": "Too Many Attempts."
}
```

## Rate Limits
Current production plan:
- `5` requests per second
- `80` requests per minute
- `1500` requests per day

## Guidance Sync Flow (One Student Per Request)
1. Get IDP user ID from Guidance queue.
2. Call `GET /api/v1/students/idp/{idpUserId}`.
3. If `200`, upsert returned record.
4. If `404`, mark as missing/not officially enrolled.
5. If `429`, retry with backoff.

## cURL Example

By IDP user ID:

```bash
curl -X GET "https://puptas.undraftedbsit2027.com/api/v1/students/idp/idp-abc-123" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <API_TOKEN>"
```

## Notes
- API access is logged in SuperAdmin Audit Logs (`External API` module).
- Invalid token requests are logged as `AUTH_FAILED`.
- Successful reads are logged as `READ`.
