# Image Preview Security and Metadata Implementation

## Overview
This document explains how image preview works in the system and what security controls protect file uploads and preview access.

The implementation has three goals:
- Only authenticated users can upload and fetch their files.
- Preview responses use trusted server-derived metadata.
- The frontend renders files based on metadata, not assumptions.

## Upload Flow
1. User uploads a document from the UI.
2. Backend validates type and size:
   - Allowed formats: JPG, JPEG, PNG
   - Max size: 2MB
3. File is stored in `uploads/files` on the configured storage disk.
4. A `user_files` record is created/updated with:
   - `user_id`
   - `type`
   - `file_path`
   - `original_name`
   - `status`
5. Backend returns a metadata payload (see Metadata Contract below).

## Security Controls

### 1. Authenticated Upload Endpoints
Upload and file-fetch endpoints are protected by auth middleware.

What this prevents:
- Anonymous uploads
- Uploading into another user's account by submitting a target email

### 2. Ownership and Role Checks for Preview
Preview access requires:
- Valid signed URL
- Authenticated user
- User is either:
  - File owner, or
  - Authorized staff role

What this prevents:
- ID guessing or direct access to other users' files
- Reuse of unsigned or tampered preview URLs

### 3. Signed, Time-Bound Preview URLs
Preview URLs are generated as temporary signed routes.

What this provides:
- URL integrity checks
- Expiration window limits exposure

### 4. Trusted MIME Handling
The server determines MIME from the stored file data/path and sets response headers using trusted values.

Headers include:
- `Content-Type` (trusted MIME)
- `Content-Disposition: inline` (safe sanitized filename)
- `X-Content-Type-Options: nosniff`
- No-cache headers

What this prevents:
- Browser MIME sniffing risks
- Misleading client-supplied extension behavior

### 5. Sanitized Filename in Response
Before setting `Content-Disposition`, filename characters are sanitized.

What this prevents:
- Header injection or malformed filename response issues

## Metadata Contract
The backend now returns structured metadata per file.

Example payload:

```json
{
  "url": "https://.../files/123/preview?...signature...",
  "mimeType": "image/png",
  "originalName": "school-id.png",
  "isImage": true,
  "status": "pending"
}
```

Field meaning:
- `url`: temporary signed preview URL
- `mimeType`: trusted MIME detected by backend
- `originalName`: sanitized original file name
- `isImage`: true when MIME starts with `image/`
- `status`: file workflow state

## Frontend Behavior
The UI uses metadata instead of raw string URLs.

Important behavior:
- Preview cards use metadata object values.
- Rendering checks `isImage` before opening image modal.
- Label maps explicitly distinguish front/back report files:
  - Grade 11 Report Front
  - Grade 11 Report Back
  - Grade 12 Report Front
  - Grade 12 Report Back
- Upload input accepts only `.jpg,.jpeg,.png`.
- User messaging clearly states allowed formats and size limits.

## File Mapping
`FileMapper` centralizes file key mapping and payload shaping.

It is responsible for:
- Mapping UI keys to DB file `type`
- Building signed preview URLs
- Building standardized metadata payloads
- Detecting MIME and image flags

## Test Coverage
Feature tests validate:
- Guests cannot upload files.
- Authenticated users can upload and receive metadata.
- Non-owners cannot preview another user's file.
- Owners can preview with trusted content headers.

## Maintenance Notes
When adding a new file type:
1. Add mapping in FileMapper.
2. Add validation rule (image + allowed mimes).
3. Ensure metadata payload includes the new type.
4. Add/adjust frontend label mapping.
5. Add/adjust feature tests.

## Summary
Image preview is secured by authentication, signed URLs, authorization checks, trusted server metadata, and strict file validation. Metadata is the central contract that keeps backend security decisions and frontend rendering behavior aligned.
