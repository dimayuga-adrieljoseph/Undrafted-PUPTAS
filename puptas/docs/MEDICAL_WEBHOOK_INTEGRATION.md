# Medical System Webhook Integration Guide

## Overview
This document describes how the medical system should send webhook notifications to PUPTAS when a student completes their medical examination.

---

## Webhook Endpoint

**URL**: `POST /api/v1/webhooks/medical-result`

**Authentication**: Bearer token (medical-write client credentials)

**Content-Type**: `application/json`

---

## Request Format

### Required Fields

The webhook accepts the following payload:

```json
{
  "student_id": "ade67dc4-50f0-4e32-bd80-84308c0f4e10",
  "student_number": "2024-12345",
  "is_health_profile_completed": 1
}
```

### Field Descriptions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `student_id` | string (UUID) | Yes* | The student's IDP user ID (UUID format) |
| `student_number` | string | Yes* | The student's official student number |
| `is_health_profile_completed` | integer | Yes | Medical clearance status: `1` = cleared/passed, `0` = failed |

**Note**: At least ONE of `student_id` or `student_number` must be provided. Providing both is recommended for better matching.

---

## Field Mapping

### Medical System → PUPTAS Mapping

| Your Field | PUPTAS Field | Notes |
|------------|--------------|-------|
| `student_id` | `idp_user_id` | UUID format, primary identifier |
| `student_number` | `student_number` | Same field name, secondary identifier |
| `is_health_profile_completed` | `medical_status` | `1` = cleared, `0` = failed |

---

## Webhook Behavior

### When to Send Webhook

**Send webhook ONLY when**: `is_health_profile_completed = 1` (student is cleared)

**Do NOT send webhook when**: `is_health_profile_completed = 0` (student not cleared/incomplete)

### What Happens When You Send

1. PUPTAS receives the webhook
2. System looks up the student by `student_number` (priority) or `student_id` (fallback)
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
  "message": "Either student_number or student_id (idp_user_id) must be provided"
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

### Example 1: Using Both Identifiers (Recommended)

```bash
curl -X POST https://puptas.example.com/api/v1/webhooks/medical-result \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "student_id": "ade67dc4-50f0-4e32-bd80-84308c0f4e10",
    "student_number": "2024-12345",
    "is_health_profile_completed": 1
  }'
```

### Example 2: Using Only student_id

```bash
curl -X POST https://puptas.example.com/api/v1/webhooks/medical-result \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "student_id": "ade67dc4-50f0-4e32-bd80-84308c0f4e10",
    "is_health_profile_completed": 1
  }'
```

### Example 3: Using Only student_number

```bash
curl -X POST https://puptas.example.com/api/v1/webhooks/medical-result \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "student_number": "2024-12345",
    "is_health_profile_completed": 1
  }'
```

---

## Important Notes

### 1. Idempotency
- Sending the same webhook multiple times is safe
- If medical is already completed, PUPTAS returns success without changes
- No duplicate processing will occur

### 2. Timing
- Send webhook immediately after medical examination is completed
- PUPTAS processes webhooks in real-time

### 3. Retry Logic
- If webhook fails (network error, timeout), retry with exponential backoff
- Safe to retry the same request multiple times

### 4. Security
- Always use HTTPS
- Include valid Bearer token in Authorization header
- Webhook signature verification is enabled (check with PUPTAS admin)

---

## Testing

### Test Endpoint
Use the same endpoint for testing: `/api/v1/webhooks/medical-result`

### Test Payload
```json
{
  "student_id": "test-uuid-12345",
  "student_number": "TEST-2024-001",
  "is_health_profile_completed": 1
}
```

---

## Support

For integration issues or questions, contact:
- **Technical Support**: [support email]
- **API Documentation**: [link to full API docs]
- **Status Page**: [link to status page]

---

## Changelog

| Date | Version | Changes |
|------|---------|---------|
| 2026-04-16 | 1.0 | Initial webhook integration with `is_health_profile_completed` field |
