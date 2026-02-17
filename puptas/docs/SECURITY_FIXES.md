# SAR Security & Quality Fixes

## Overview
This document details the security and quality improvements made to the SAR (Student Admission Record) management system based on code review feedback.

## Issues Fixed

### 1. ✅ Missing Audit Trail
**Issue:** `sar_generations` table lacked actor tracking and email outcome status.

**Risk:** Cannot determine who sent a SAR or if the email succeeded, making compliance audits and debugging difficult.

**Fix:**
- Added `created_by_user_id` foreign key to track which user sent the SAR
- Added `email_sent_successfully` boolean field to track email delivery status
- Modified `sendSarEmails()` to:
  - Create SAR record BEFORE sending email (with `email_sent_successfully = false`)
  - Only set `sent_at` timestamp AFTER successful email delivery
  - Update `email_sent_successfully = true` on success

**Files Changed:**
- `database/migrations/2026_02_17_111746_add_audit_fields_to_sar_generations_table.php`
- `app/Models/SarGeneration.php`
- `app/Http/Controllers/TestPasserController.php` (sendSarEmails method)

**Database Schema:**
```sql
ALTER TABLE sar_generations 
ADD COLUMN created_by_user_id BIGINT UNSIGNED NULL,
ADD COLUMN email_sent_successfully BOOLEAN DEFAULT FALSE,
ADD FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL;
```

---

### 2. ✅ Information Disclosure via Error Messages
**Issue:** `previewSarPdfTemplate()` returned raw exception messages to users.

**Risk:** Exposed internal implementation details (file paths, database structure, stack traces) that attackers could exploit.

**Fix:**
- Sanitized error responses to return generic message: "Failed to generate preview"
- Enhanced server-side logging to capture full error details including:
  - Exception message
  - Stack trace
  - Passer ID for debugging
- User sees safe message, admins can check logs for details

**Files Changed:**
- `app/Http/Controllers/TestPasserController.php` (previewSarPdfTemplate method)

**Before:**
```php
return response()->json(['error' => $e->getMessage()], 500);
```

**After:**
```php
\Log::error('SAR PDF preview failed', [
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
    'passer_id' => $passer->test_passer_id
]);
return response()->json(['error' => 'Failed to generate preview'], 500);
```

---

### 3. ✅ Missing Authorization Checks
**Issue:** SAR admin routes only used `auth:sanctum` middleware without role verification.

**Risk:** Any authenticated user (including applicants) could access PII by viewing/downloading SAR PDFs.

**Fix:**
- Applied `role:2,6` middleware to all `/admin/sar*` routes
- Only Admin (role_id = 2) and Registrar (role_id = 6) can access
- Unauthorized access returns 403 Forbidden with "Unauthorized action" message

**Files Changed:**
- `routes/web.php`

**Protected Routes:**
```php
Route::middleware(['role:2,6'])->group(function () {
    Route::get('/admin/sar-generations', ...);
    Route::get('/admin/sar/{id}/download', ...);
    Route::get('/admin/sar/{id}/preview', ...);
    Route::post('/admin/sar/preview-email-template', ...);
    Route::post('/admin/sar/preview-pdf-template', ...);
});
```

---

### 4. ✅ Temporary File Cleanup
**Issue:** Preview PDFs generated in `storage/app/tmp/` were never deleted.

**Risk:** Disk exhaustion over time, especially with frequent previews.

**Fix:**
- Modified `previewSarPdfTemplate()` to:
  1. Read PDF content into memory
  2. Delete temporary file using `unlink()`
  3. Return PDF content from memory
- Preview functionality unchanged, but file cleanup is automatic

**Files Changed:**
- `app/Http/Controllers/TestPasserController.php` (previewSarPdfTemplate method)

**Implementation:**
```php
// Read PDF content before deletion
$pdfContent = file_get_contents($fullPath);

// Delete temporary preview file
unlink($fullPath);

// Return PDF for inline preview
return response($pdfContent, 200, [
    'Content-Type' => 'application/pdf',
    'Content-Disposition' => 'inline; filename="PREVIEW_' . $result['filename'] . '"',
]);
```

---

### 5. ✅ Memory Leak in Vue Component
**Issue:** Blob URLs created for PDF previews were not revoked when generating new previews.

**Risk:** Memory leak in browser causing performance degradation with repeated previews.

**Fix:**
- Added `URL.revokeObjectURL()` call BEFORE creating new blob URL
- Ensures previous blob is freed from memory
- Pattern applied in `previewSarPdfForm()` function

**Files Changed:**
- `resources/js/Pages/TestPassers/Email.vue`

**Implementation:**
```javascript
// Revoke previous blob URL to prevent memory leak
if (sarPdfPreviewUrl.value) {
    URL.revokeObjectURL(sarPdfPreviewUrl.value);
    sarPdfPreviewUrl.value = '';
}

// Create new blob URL
const blob = new Blob([response.data], { type: 'application/pdf' });
sarPdfPreviewUrl.value = URL.createObjectURL(blob);
```

---

## Security Impact Summary

| Issue | Severity | Impact | Status |
|-------|----------|--------|--------|
| Missing Audit Trail | High | Compliance & Debugging | ✅ Fixed |
| Error Message Disclosure | Medium | Information Leakage | ✅ Fixed |
| Missing Authorization | Critical | PII Exposure | ✅ Fixed |
| Temp File Cleanup | Medium | Disk Exhaustion | ✅ Fixed |
| Memory Leak | Low | Performance | ✅ Fixed |

## Testing Recommendations

1. **Audit Trail:**
   - Send SAR emails and verify `created_by_user_id` is set to current user
   - Simulate email failure and verify `email_sent_successfully = false`
   - Verify `sent_at` is NULL when email fails

2. **Authorization:**
   - Log in as Applicant (role_id = 1) and attempt to access `/admin/sar-generations`
   - Should receive 403 Forbidden error
   - Log in as Admin (role_id = 2) and verify access granted

3. **Temp File Cleanup:**
   - Generate SAR preview
   - Check `storage/app/tmp/` directory
   - Preview file should be deleted immediately after response

4. **Memory Leak:**
   - Open browser DevTools → Memory
   - Generate 10 consecutive SAR previews without closing modal
   - Monitor blob object count - should remain constant (old blobs revoked)

## Migration Steps

```bash
# Run the migration
php artisan migrate

# Verify new columns
php artisan tinker
> Schema::hasColumn('sar_generations', 'created_by_user_id')
> Schema::hasColumn('sar_generations', 'email_sent_successfully')

# Clear route cache
php artisan route:clear

# Verify role middleware on routes
php artisan route:list --name=admin.sar
```

## Backwards Compatibility

- Existing SAR records will have `created_by_user_id = NULL` (acceptable)
- Existing SAR records will have `email_sent_successfully = false` (conservative default)
- No breaking changes to API responses
- Frontend continues to work without modifications

## Future Improvements

1. **Enhanced Audit Trail:**
   - Add `email_error_message` text field to capture failure reasons
   - Track retry attempts

2. **Authorization:**
   - Consider implementing Laravel Policies for fine-grained access control
   - Add ability-based permissions (e.g., `view-sar`, `download-sar`)

3. **File Management:**
   - Implement scheduled cleanup of old SAR files (e.g., after 30 days)
   - Consider cloud storage with automatic TTL

4. **Monitoring:**
   - Add alerts for high SAR send failure rates
   - Track disk usage for temp files

---

**Last Updated:** February 17, 2026  
**Reviewed By:** GitHub Copilot  
**Status:** All fixes implemented and deployed
