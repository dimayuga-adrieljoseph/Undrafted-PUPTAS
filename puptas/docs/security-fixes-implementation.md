# Security Fixes Implementation Guide

**Date**: April 28, 2026  
**Branch**: SCRUM-81-Fix-Critical-to-High-Security-Risk  
**Status**: Completed

## Overview

This document details the implementation of critical and high-priority security fixes identified in the PUPTAS security audit. The fixes address authentication vulnerabilities, authorization gaps, replay attack protection, and data integrity issues.

## Table of Contents

1. [OAuth2 State Parameter Validation](#1-oauth2-state-parameter-validation)
2. [Schedule Routes Authentication](#2-schedule-routes-authentication)
3. [SAR Download Authentication and Validation](#3-sar-download-authentication-and-validation)
4. [User Application Data Source Fix](#4-user-application-data-source-fix)
5. [Webhook Replay Protection](#5-webhook-replay-protection)
6. [Testing](#testing)
7. [Deployment Checklist](#deployment-checklist)

---

## 1. OAuth2 State Parameter Validation

### Vulnerability
OAuth2 authentication flow was missing CSRF protection via state parameter validation, allowing potential authorization code interception attacks.

### Fix Implementation

**Files Modified:**
- `puptas/app/Http/Controllers/IdpAuthController.php`
- `puptas/app/Http/Controllers/CallbackController.php`

**Changes:**

1. **State Generation (Login)**
   ```php
   // Generate cryptographic random state
   $state = Str::random(40);
   session(['idp_oauth_state' => $state]);
   
   // Include in authorization URL
   $authUrl .= '&state=' . urlencode($state);
   ```

2. **State Validation (Callback)**
   ```php
   // Extract and validate state
   $receivedState = $request->query('state');
   $sessionState = session('idp_oauth_state');
   
   if (!$receivedState || $receivedState !== $sessionState) {
       return response()->json(['error' => 'Invalid state parameter'], 403);
   }
   
   // Remove state after validation (prevent reuse)
   session()->forget('idp_oauth_state');
   ```

**Security Benefits:**
- Prevents CSRF attacks on OAuth2 flow
- Ensures authorization codes can't be intercepted and replayed
- Implements OAuth2 RFC 6749 best practices

**Test Coverage:**
- State generation and storage
- Successful validation flow
- Rejection of missing state
- Rejection of mismatched state
- Idempotence (state removed after use)

---

## 2. Schedule Routes Authentication

### Vulnerability
Schedule management routes (`/schedules/*`) were publicly accessible without authentication, allowing unauthorized access to schedule data.

### Fix Implementation

**Files Modified:**
- `puptas/routes/web.php`

**Changes:**

```php
// Before: No authentication
Route::resource('schedules', ScheduleController::class);

// After: Authentication + role-based authorization
Route::middleware(['auth'])->group(function () {
    Route::resource('schedules', ScheduleController::class)
        ->middleware('role:2,4'); // Admin (2) and Interviewer (4)
});
```

**Security Benefits:**
- Prevents unauthorized access to schedule data
- Enforces role-based access control (RBAC)
- Protects all CRUD operations (index, create, store, show, edit, update, destroy)

**Authorized Roles:**
- Role 2: Admin
- Role 4: Interviewer

**Test Coverage:**
- Unauthenticated access redirects to login
- Authenticated admin can access routes
- Authenticated interviewer can access routes
- Other roles receive 403 Forbidden

---

## 3. SAR Download Authentication and Validation

### Vulnerability
SAR (Student Admission Record) download endpoint was publicly accessible and vulnerable to path traversal attacks.

### Fix Implementation

**Files Modified:**
- `puptas/routes/web.php`
- `puptas/app/Http/Controllers/TestPasserController.php`

**Changes:**

1. **Route Authentication**
   ```php
   // Before: No authentication
   Route::get('/sar/download/{filename}/{reference}', [TestPasserController::class, 'downloadSar']);
   
   // After: Authentication required
   Route::middleware(['auth'])->group(function () {
       Route::get('/sar/download/{filename}/{reference}', [TestPasserController::class, 'downloadSar']);
   });
   ```

2. **Filename Validation**
   ```php
   public function downloadSar($filename, $reference)
   {
       // Validate filename pattern
       if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
           return response()->json(['error' => 'Invalid filename'], 400);
       }
       
       // Reject path traversal attempts
       if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
           return response()->json(['error' => 'Invalid filename'], 400);
       }
       
       // Continue with file serving...
   }
   ```

**Security Benefits:**
- Prevents unauthorized access to sensitive student records
- Blocks path traversal attacks (../, ..\)
- Validates filename contains only safe characters
- Protects against directory traversal vulnerabilities

**Test Coverage:**
- Unauthenticated access redirects to login
- Authenticated access serves PDF
- Path traversal attempts return 400
- Invalid filename patterns return 400

---

## 4. User Application Data Source Fix

### Vulnerability
User application data was being retrieved from non-existent User model fields, causing null values for school information.

### Fix Implementation

**Files Modified:**
- `puptas/app/Http/Controllers/UserFileController.php`

**Changes:**

```php
// Before: Reading from User model (incorrect)
return response()->json([
    'school' => $user->school,           // NULL - field doesn't exist
    'schoolAdd' => $user->school_address, // NULL - field doesn't exist
    // ...
]);

// After: Reading from ApplicantProfile relationship (correct)
$applicantProfile = $user->applicantProfile;

return response()->json([
    'school' => $applicantProfile?->school,
    'schoolAdd' => $applicantProfile?->school_address,
    'dateGrad' => $applicantProfile?->date_graduated?->format('Y-m-d'),
    'strand' => $applicantProfile?->strand,
    'track' => $applicantProfile?->track,
    // ...
]);
```

**Field Mappings:**
- `school` → `ApplicantProfile.school`
- `schoolAdd` → `ApplicantProfile.school_address`
- `dateGrad` → `ApplicantProfile.date_graduated` (formatted as Y-m-d)
- `strand` → `ApplicantProfile.strand`
- `track` → `ApplicantProfile.track`

**Security Benefits:**
- Ensures data integrity
- Returns accurate applicant information
- Prevents application errors due to missing data

**Test Coverage:**
- School fields retrieved from ApplicantProfile
- Non-null values when profile fields populated
- Correct field mapping
- Graceful handling of missing profile
- Partial profile data handling

---

## 5. Webhook Replay Protection

### Vulnerability
Medical webhook endpoint was vulnerable to replay attacks, allowing duplicate webhook requests to be processed multiple times.

### Fix Implementation

**Files Modified:**
- `puptas/app/Http/Middleware/VerifyMedicalWebhookSignature.php`

**Changes:**

1. **Timestamp Validation**
   ```php
   // Extract timestamp from payload
   $payloadData = $request->json()->all();
   
   if (!isset($payloadData['timestamp'])) {
       return response()->json(['message' => 'Missing timestamp'], 400);
   }
   
   $timestamp = $payloadData['timestamp'];
   $currentTime = time();
   $fiveMinutesInSeconds = 5 * 60;
   
   // Reject requests older than 5 minutes
   if (($currentTime - $timestamp) > $fiveMinutesInSeconds) {
       return response()->json(['message' => 'Request expired'], 403);
   }
   ```

2. **Nonce Validation**
   ```php
   // Extract nonce from payload
   if (!isset($payloadData['nonce'])) {
       return response()->json(['message' => 'Missing nonce'], 400);
   }
   
   $nonce = $payloadData['nonce'];
   $cacheKey = 'webhook_nonce_' . $nonce;
   
   // Check if nonce has been seen before
   if (Cache::has($cacheKey)) {
       return response()->json(['message' => 'Duplicate request'], 403);
   }
   
   // Store nonce with 10-minute expiration
   Cache::put($cacheKey, true, 600); // 600 seconds = 10 minutes
   ```

3. **Validation Order**
   ```
   1. Timestamp validation (fast, prevents old replays)
   2. Nonce validation (fast, prevents recent replays)
   3. HMAC signature validation (expensive, validates authenticity)
   ```

**Security Benefits:**
- Prevents replay attacks within 5-minute window (timestamp)
- Prevents duplicate processing within 10-minute window (nonce)
- Fail-fast approach (cheap checks before expensive HMAC)
- Maintains existing HMAC signature validation

**Webhook Payload Requirements:**
```json
{
  "timestamp": 1714262400,
  "nonce": "unique-identifier-12345",
  "patient_id": "12345",
  "medical_data": "..."
}
```

**Test Coverage:**
- Missing timestamp returns 400
- Expired timestamp returns 403
- Recent timestamp passes validation
- Missing nonce returns 400
- Duplicate nonce returns 403
- Unique nonce passes validation
- Validation order (timestamp → nonce → HMAC)
- Nonce stored in cache with correct TTL

---

## Testing

### Test Suite Summary

**Total Tests Created**: 28 tests (81 assertions)  
**Test Execution Time**: ~1.02s  
**Pass Rate**: 100%

### Test Files

1. **OAuth2 State Validation**
   - Location: `puptas/tests/Unit/OAuth2StateValidationTest.php`
   - Tests: State generation, validation, rejection, idempotence

2. **Schedule Route Authentication**
   - Location: `puptas/tests/Feature/ScheduleRouteAuthenticationTest.php`
   - Tests: Unauthenticated access, role-based authorization

3. **SAR Download Security**
   - Location: `puptas/tests/Feature/SarDownloadSecurityTest.php`
   - Tests: Authentication, path traversal prevention, filename validation

4. **User Application Data Source**
   - Location: `puptas/tests/Feature/UserFileControllerTest.php`
   - Location: `puptas/tests/Unit/UserFileControllerTest.php`
   - Tests: Data retrieval, field mapping, null handling

5. **Webhook Replay Protection**
   - Location: `puptas/tests/Feature/WebhookTimestampValidationTest.php`
   - Location: `puptas/tests/Feature/WebhookNonceValidationTest.php`
   - Location: `puptas/tests/Feature/WebhookValidationOrderTest.php`
   - Tests: Timestamp validation, nonce validation, validation order

### Running Tests

```bash
# Run all security fix tests
php artisan test tests/Feature/UserFileControllerTest.php \
                 tests/Feature/WebhookTimestampValidationTest.php \
                 tests/Feature/WebhookNonceValidationTest.php \
                 tests/Feature/WebhookValidationOrderTest.php \
                 tests/Feature/ScheduleRouteAuthenticationTest.php \
                 tests/Feature/SarDownloadSecurityTest.php \
                 tests/Unit/UserFileControllerTest.php

# Run specific test suite
php artisan test tests/Feature/WebhookTimestampValidationTest.php
```

---

## Deployment Checklist

### Pre-Deployment

- [ ] All tests passing locally
- [ ] Code reviewed and approved
- [ ] Database migrations reviewed (if any)
- [ ] Environment variables documented

### Environment Configuration

**Required Configuration:**

1. **Session Configuration** (`.env`)
   ```env
   SESSION_DRIVER=database  # or redis (NOT file in production)
   SESSION_ENCRYPT=true
   SESSION_SECURE_COOKIE=true
   SESSION_LIFETIME=120
   ```

2. **Cache Configuration** (`.env`)
   ```env
   CACHE_DRIVER=redis  # or database (required for nonce storage)
   ```

3. **Webhook Secret** (`.env`)
   ```env
   MEDICAL_WEBHOOK_SECRET=your-secure-secret-here
   ```

### Deployment Steps

1. **Backup Database**
   ```bash
   php artisan backup:run
   ```

2. **Pull Latest Code**
   ```bash
   git pull origin SCRUM-81-Fix-Critical-to-High-Security-Risk
   ```

3. **Install Dependencies**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

4. **Run Migrations** (if any)
   ```bash
   php artisan migrate --force
   ```

5. **Clear Caches**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   php artisan view:clear
   ```

6. **Optimize for Production**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

7. **Run Tests on Production Environment**
   ```bash
   php artisan test --env=production
   ```

### Post-Deployment Verification

- [ ] OAuth2 login flow works correctly
- [ ] Schedule routes require authentication
- [ ] SAR downloads require authentication
- [ ] User application data displays correctly
- [ ] Webhook endpoint rejects replayed requests
- [ ] No errors in application logs
- [ ] Monitor for 24 hours

### Rollback Plan

If issues are detected:

1. **Revert Code**
   ```bash
   git revert <commit-hash>
   git push origin SCRUM-81-Fix-Critical-to-High-Security-Risk
   ```

2. **Clear Caches**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   ```

3. **Restore Database** (if migrations were run)
   ```bash
   php artisan migrate:rollback
   ```

---

## Security Considerations

### Session Security

- **Session Driver**: Use `database` or `redis` in production (NOT `file`)
- **Session Encryption**: Always enabled (`SESSION_ENCRYPT=true`)
- **Secure Cookies**: Always enabled (`SESSION_SECURE_COOKIE=true`)
- **HTTPS**: Required for secure cookie transmission

### Cache Security

- **Cache Driver**: Use `redis` or `database` for nonce storage
- **Cache Isolation**: Ensure cache is not shared across environments
- **Cache Expiration**: Nonces expire after 10 minutes

### Webhook Security

- **Secret Management**: Store webhook secret in environment variables
- **Secret Rotation**: Rotate webhook secret periodically
- **Timestamp Window**: 5-minute window balances security and clock skew
- **Nonce Storage**: 10-minute TTL prevents long-term storage

### Authentication

- **Route Protection**: All sensitive routes require authentication
- **Role-Based Access**: Enforce least privilege principle
- **Session Timeout**: Configure appropriate session lifetime

---

## Monitoring and Alerts

### Metrics to Monitor

1. **OAuth2 State Validation**
   - Failed state validations (potential attack attempts)
   - State parameter missing (misconfigured clients)

2. **Webhook Replay Protection**
   - Expired timestamp rejections
   - Duplicate nonce rejections
   - HMAC signature failures

3. **Authentication Failures**
   - Unauthorized access attempts to protected routes
   - Path traversal attempts on SAR downloads

### Log Monitoring

Monitor application logs for:
- `Invalid state parameter` (OAuth2 attacks)
- `Request expired` (webhook replay attempts)
- `Duplicate request` (webhook replay attempts)
- `Invalid filename` (path traversal attempts)

### Alerting Thresholds

- **Critical**: >10 failed state validations per minute
- **Warning**: >5 duplicate nonce rejections per minute
- **Info**: Path traversal attempts (log for analysis)

---

## Known Limitations

1. **Clock Skew**: Webhook timestamp validation allows 5-minute window to accommodate clock differences between systems

2. **Nonce Storage**: Nonces are stored in cache for 10 minutes. Ensure cache is persistent and not cleared during deployments

3. **Session Storage**: OAuth2 state is stored in session. Ensure session driver is configured correctly in production

---

## References

- [OAuth 2.0 RFC 6749](https://tools.ietf.org/html/rfc6749)
- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [OWASP Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html)
- [Laravel Security Best Practices](https://laravel.com/docs/11.x/security)

---

## Support

For questions or issues related to these security fixes:

1. Review this documentation
2. Check application logs
3. Review test files for expected behavior
4. Contact the development team

---

**Document Version**: 1.0  
**Last Updated**: April 28, 2026  
**Maintained By**: PUPTAS Development Team
