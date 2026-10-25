# IDP Sync Monitor - Complete Documentation

**Version:** 1.0  
**Last Updated:** January 2025  
**Status:** Production Ready

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Features](#features)
3. [Installation & Setup](#installation--setup)
4. [Usage Guide](#usage-guide)
5. [Security](#security)
6. [Testing](#testing)
7. [Deployment](#deployment)
8. [Troubleshooting](#troubleshooting)
9. [API Reference](#api-reference)
10. [Maintenance](#maintenance)

---

## Overview

The **IDP Sync Monitor** is a dashboard for super administrators to monitor and manage the synchronization of student account types between PUPTAS and the external IDP (Identity Provider) system.

### What It Does

- **Monitors** IDP account type synchronization status
- **Displays** statistics and success/failure rates
- **Triggers** manual sync for all enrolled students
- **Retries** failed sync jobs
- **Logs** all operations for audit compliance

### When Syncing Happens

1. **Automatic:** When a registrar tags a student as "officially enrolled"
2. **Manual:** When a super admin clicks "Trigger Manual Sync"

### What Gets Synced

- **Target:** Students with `enrollment_status = 'officially_enrolled'`
- **Action:** Update IDP account type from "applicant" to "student"
- **Direction:** PUPTAS → IDP (one-way sync)

---

## Features

### Dashboard View
- ✅ Total syncs count
- ✅ Success rate percentage
- ✅ Failed syncs count
- ✅ Unsynced students count
- ✅ Hourly activity chart (last 24 hours)
- ✅ Recent activity log
- ✅ Failure breakdown by type
- ✅ Queue status monitoring

### Security Features
- ✅ Super admin only access (role_id = 7)
- ✅ Rate limiting (5 requests per minute)
- ✅ MFA code masking in logs
- ✅ Comprehensive audit logging
- ✅ Authorization checks on all endpoints

### Operational Features
- ✅ Manual sync trigger with optional MFA
- ✅ Retry failed jobs
- ✅ Time range filtering (24h, 7d, 30d, all)
- ✅ Real-time metrics
- ✅ Automatic audit log cleanup (monthly)

---

## Installation & Setup

### Prerequisites

- Laravel 11+
- MySQL database
- Vue 3 + Inertia.js
- Super admin account (role_id = 7)
- IDP credentials configured

### Step 1: Pull Code

```bash
git pull origin main
```

### Step 2: Install Dependencies

```bash
composer install
npm install
```

### Step 3: Clear Caches

```bash
php artisan optimize:clear
```

### Step 4: Build Frontend (Production)

```bash
npm run build
```

### Step 5: Verify Routes

```bash
php artisan route:list | grep idp-sync-monitor
```

**Expected Output:**
```
GET|HEAD   admin/system-settings/idp-sync-monitor
GET|HEAD   api/super-admin/idp-sync-monitor/metrics
POST       api/super-admin/idp-sync-monitor/trigger-sync
POST       api/super-admin/idp-sync-monitor/retry-failed-jobs
```

### Optional: Configure IDP Admin Token

Add to `.env` (optional, system uses session token by default):

```env
IDP_ADMIN_ACCESS_TOKEN=your_token_here
```

**Note:** This is only needed if you want to test without logging in via IDP. In production, the system automatically uses the logged-in admin's IDP session token.

---

## Usage Guide

### Accessing the Dashboard

1. Log in as **Super Admin** (role_id = 7)
2. Navigate to: **System Settings → IDP Sync Monitor**
3. Dashboard loads with current statistics

### Viewing Statistics

**Stats Displayed:**
- **Total Syncs:** All sync attempts
- **Success Rate:** Percentage of successful syncs
- **Failed Syncs:** Number of failed attempts
- **Unsynced Students:** Students not yet synced to IDP

**Time Range Options:**
- Last 24 Hours
- Last 7 Days
- Last 30 Days
- All Time (limited to 1 year)

**Click any time range** to filter data.

### Triggering Manual Sync

**⚠️ Warning:** This will queue jobs to sync ALL officially enrolled students who haven't been synced yet.

**Steps:**
1. Click **"Trigger Manual Sync"** button
2. Modal appears with:
   - Info about IDP admin token requirement
   - Optional MFA code field (if your IDP account requires it)
3. Enter MFA code if needed (max 8 characters)
4. Click **"Start Sync"**

**What Happens:**
- System queues a job for each enrolled student
- Jobs process in background
- Results appear in Recent Activity log
- Audit logs record the operation

**Common Responses:**

| Response | Meaning | Action |
|----------|---------|--------|
| "Successfully queued X jobs" | Sync started | Monitor dashboard for results |
| "No IDP admin token available" | Token not configured | Log in via IDP or set IDP_ADMIN_ACCESS_TOKEN |
| "Rate limit exceeded" | Too many requests | Wait 1 minute and try again |

### Retrying Failed Jobs

If you see failed syncs in the dashboard:

1. Click **"Retry Failed Jobs"** button
2. Confirm the action
3. Failed jobs are re-queued
4. Monitor Recent Activity for results

### Recent Activity Log

Shows the 20 most recent sync operations:

**Columns:**
- **User:** Student email
- **Role:** Student role
- **Type:** LOGIN/SECURITY/etc.
- **Module:** "IDP Account Type"
- **Action:** UPDATE/FAILED/ATTEMPTED
- **Description:** Details of what happened
- **Timestamp:** When it occurred

**Color Coding:**
- 🟢 Green (UPDATE) = Success
- 🔴 Red (FAILED) = Failed
- 🟡 Yellow (ATTEMPTED) = MFA required or pending

---

## Security

### Access Control

**Who Can Access:**
- ✅ Super Admins only (role_id = 7)
- ❌ All other users get 403 Forbidden

**What's Logged:**
- ✅ Page access
- ✅ Metrics views
- ✅ Sync trigger attempts
- ✅ Failed access attempts
- ✅ All errors

### Rate Limiting

**Limits:**
- 5 requests per minute per user
- Applies to all write operations (trigger sync, retry jobs)
- Read operations (view metrics) not rate limited

**Response:** HTTP 429 Too Many Requests

### Sensitive Data Masking

**Never Logged in Plain Text:**
- MFA codes
- Access tokens
- API keys
- Passwords

**How It's Masked:**
```
❌ "Triggered sync with MFA code: 123456"
✅ "Triggered sync with MFA code"
```

### Audit Logging

**What Gets Logged:**

| Event | Action Type | Module | Details |
|-------|-------------|--------|---------|
| Manual sync triggered | COMMAND | IDP Sync Monitor | Who, when, with/without MFA |
| Manual sync succeeded | UPDATE | IDP Sync Monitor | Jobs queued count |
| Manual sync failed | FAILED | IDP Sync Monitor | Reason for failure |
| Student synced (auto) | UPDATE | IDP Account Type | Student ID, email |
| Student sync failed | FAILED | IDP Account Type | Student ID, error |
| Retry triggered | COMMAND | IDP Sync Monitor | Failed jobs count |
| Unauthorized access | ATTEMPTED | IDP Sync Monitor | User ID, IP |

**Query Audit Logs:**
```sql
-- View all IDP sync activity
SELECT * FROM audit_logs 
WHERE module_name IN ('IDP Sync Monitor', 'IDP Account Type')
ORDER BY created_at DESC 
LIMIT 50;

-- View failures only
SELECT * FROM audit_logs 
WHERE module_name LIKE '%IDP%'
  AND action_type IN ('FAILED', 'ATTEMPTED')
ORDER BY created_at DESC;
```

---

## Testing

### Safe Testing (No Production Impact)

#### Phase 1: View Dashboard (100% Safe)
```bash
# 1. Deploy code
# 2. Log in as super admin
# 3. Navigate to IDP Sync Monitor
# 4. Just look around - don't click buttons yet
# ✅ Zero impact on production data
```

#### Phase 2: Test with One Student (When Ready)
```bash
# 1. Have registrar tag ONE test student as "officially enrolled"
# 2. This triggers automatic IDP sync for that student only
# 3. Check dashboard for results
# 4. Check audit logs
```

#### Phase 3: Manual Bulk Sync (Optional)
```bash
# Only when ready to sync all enrolled students
# Click "Trigger Manual Sync"
# Monitor dashboard
```

### Pre-Sync Verification

Before triggering manual sync, check what will be synced:

```bash
php artisan tinker --execute="
echo 'Students to be synced: ';
echo \App\Models\Application::where('enrollment_status', 'officially_enrolled')
    ->join('users', 'applications.user_id', '=', 'users.id')
    ->whereNotNull('users.idp_user_id')
    ->whereNull('applications.deleted_at')
    ->count();
"
```

### Dry Run (No Actual Syncing)

Test the sync command without updating IDP:

```bash
php artisan idp:sync-enrolled-students --dry-run
```

**Output:** Shows what would be synced without actually doing it.

---

## Deployment

### Pre-Deployment Checklist

- [ ] Production database backup completed
- [ ] Super admin account verified
- [ ] IDP credentials configured (optional)
- [ ] Code reviewed
- [ ] Tests passed (if any)

### Deployment Steps

```bash
# 1. On production server
git pull origin main

# 2. Install dependencies (if any new)
composer install --no-dev --optimize-autoloader

# 3. Build frontend assets
npm run build

# 4. Clear caches
php artisan optimize

# 5. Restart queue workers (if using queues)
php artisan queue:restart
```

### Post-Deployment Verification

```bash
# 1. Check routes
php artisan route:list | grep idp-sync-monitor

# 2. Check scheduled tasks
php artisan schedule:list | grep audit:purge

# 3. Test page access
# Visit: /admin/system-settings/idp-sync-monitor
# Should load without errors

# 4. Check logs
tail -f storage/logs/laravel.log
```

### Rollback Plan

If something goes wrong:

```bash
# 1. Revert code
git revert HEAD
git push

# 2. Clear caches
php artisan optimize:clear

# 3. Restart queue workers
php artisan queue:restart
```

**Note:** Rolling back code won't affect student data or enrollment status. The feature only modifies IDP account types.

---

## Troubleshooting

### Dashboard Not Loading

**Error:** 404 Not Found

**Solution:**
```bash
php artisan route:clear
php artisan route:cache
php artisan optimize:clear
```

---

### Access Denied (403)

**Error:** Forbidden

**Cause:** User is not a super admin (role_id ≠ 7)

**Solution:** Log in with a super admin account

---

### "No IDP admin token available"

**Error:** Cannot trigger sync

**Cause:** No IDP admin access token configured and no IDP session

**Solutions:**

1. **Option A:** Log in via IDP first (recommended)
   - Logout from PUPTAS
   - Click "Login with IDP"
   - Log in with admin IDP account
   - Return to IDP Sync Monitor

2. **Option B:** Configure token in `.env`
   ```env
   IDP_ADMIN_ACCESS_TOKEN=your_token_here
   ```

---

### Rate Limit Exceeded (429)

**Error:** Too Many Requests

**Cause:** More than 5 requests per minute

**Solution:** Wait 60 seconds and try again

---

### Jobs Not Processing

**Problem:** Manual sync triggered but nothing happens

**Diagnosis:**
```bash
# Check queue configuration
php artisan tinker --execute="echo config('queue.default');"

# Check if workers are running
ps aux | grep "queue:work"

# Check failed jobs
php artisan tinker --execute="echo DB::table('failed_jobs')->count();"
```

**Solution:**

If queue is 'sync' (development):
- Jobs run immediately, check audit logs for results

If queue is 'database' or 'redis' (production):
- Start queue worker: `php artisan queue:work`
- Or use supervisor/systemd to manage workers

---

### Failed Syncs

**Problem:** See failed syncs in dashboard

**Check Error Details:**
```sql
SELECT * FROM audit_logs 
WHERE action_type = 'FAILED'
  AND module_name = 'IDP Account Type'
ORDER BY created_at DESC 
LIMIT 10;
```

**Common Failures:**

| Error | Cause | Solution |
|-------|-------|----------|
| UNAUTHORIZED | Invalid or expired token | Refresh IDP token |
| MFA_REQUIRED | IDP account requires MFA | Provide MFA code when syncing |
| USER_NOT_FOUND | Student not in IDP | Verify student exists in IDP |
| RATE_LIMIT_EXCEEDED | Too many IDP API requests | Wait and retry later |

---

## API Reference

### GET /admin/system-settings/idp-sync-monitor

**Description:** Display IDP Sync Monitor page

**Authorization:** Super admin only

**Response:** Inertia Vue page

---

### GET /api/super-admin/idp-sync-monitor/metrics

**Description:** Fetch sync metrics and statistics

**Authorization:** Super admin only

**Parameters:**
- `range` (optional): 24h, 7d, 30d, all (default: 24h)

**Response:**
```json
{
  "success": true,
  "data": {
    "stats": {
      "total_syncs": 150,
      "successful": 145,
      "failed": 3,
      "mfa_required": 2,
      "success_rate": 96.67,
      "synced_students": 145,
      "unsynced_students": 5,
      "hourly_activity": [...]
    },
    "recent_activity": [...],
    "failure_breakdown": {
      "MFA_REQUIRED": 2,
      "UNAUTHORIZED": 1,
      "OTHER": 0
    },
    "queue_status": {
      "pending_jobs": 0,
      "failed_jobs": 3,
      "queue_connection": "redis",
      "is_running": true
    },
    "time_range": "24h"
  }
}
```

---

### POST /api/super-admin/idp-sync-monitor/trigger-sync

**Description:** Manually trigger IDP sync for all enrolled students

**Authorization:** Super admin only

**Rate Limit:** 5 requests per minute

**Request Body:**
```json
{
  "mfa_code": "123456"  // Optional, max 8 characters
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Sync jobs have been queued successfully"
}
```

**Response (No Token):**
```json
{
  "success": false,
  "message": "Cannot sync: No IDP admin access token available..."
}
```

**Response (Rate Limited):**
```
HTTP 429 Too Many Requests
```

---

### POST /api/super-admin/idp-sync-monitor/retry-failed-jobs

**Description:** Retry all failed sync jobs

**Authorization:** Super admin only

**Rate Limit:** 5 requests per minute

**Response:**
```json
{
  "success": true,
  "message": "Failed jobs have been queued for retry",
  "count": 3
}
```

---

## Maintenance

### Audit Log Cleanup

**Automatic:** Runs monthly at 3:00 AM

**Manual:**
```bash
# Preview what will be deleted
php artisan audit:purge --days=365 --dry-run

# Actually purge old logs
php artisan audit:purge --days=365

# Custom retention period (min 90 days)
php artisan audit:purge --days=180
```

**Configuration:**

Edit `bootstrap/app.php`:
```php
$schedule->command('audit:purge --days=365')->monthly()->at('03:00');
```

**Retention Policy:**
- Default: 365 days (1 year)
- Minimum: 90 days (compliance requirement)
- Recommendation: Keep 1-2 years for audit purposes

---

### Monitoring

**Check Sync Health:**
```sql
-- Syncs in last 24 hours
SELECT COUNT(*) FROM audit_logs 
WHERE module_name = 'IDP Account Type'
  AND created_at >= NOW() - INTERVAL 24 HOUR;

-- Success rate today
SELECT 
  SUM(CASE WHEN action_type = 'UPDATE' THEN 1 ELSE 0 END) as successful,
  SUM(CASE WHEN action_type = 'FAILED' THEN 1 ELSE 0 END) as failed,
  COUNT(*) as total
FROM audit_logs 
WHERE module_name = 'IDP Account Type'
  AND DATE(created_at) = CURDATE();
```

**Laravel Logs:**
```bash
# Monitor IDP sync activity
tail -f storage/logs/laravel.log | grep "IDP"

# Check for errors
grep "ERROR" storage/logs/laravel.log | grep "IDP"
```

---

### Scheduled Tasks

**View All Scheduled Tasks:**
```bash
php artisan schedule:list
```

**Expected Tasks:**
- `audit:purge` - Monthly at 03:00 AM
- `idp:check-health` - Every minute (IDP health check)

**Test Schedule (Development):**
```bash
# Run all scheduled tasks now
php artisan schedule:run

# Test specific task
php artisan audit:purge --dry-run
```

**Production Setup:**

Add to crontab:
```cron
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## File Structure

```
puptas/
├── app/
│   ├── Console/Commands/
│   │   └── PurgeOldAuditLogs.php       # Audit log cleanup command
│   ├── Exceptions/
│   │   └── Handler.php                  # MFA masking added
│   ├── Http/Controllers/SuperAdmin/
│   │   └── IdpSyncMonitorController.php # Main controller
│   └── Jobs/
│       └── UpdateIdpAccountType.php     # Sync job
├── bootstrap/
│   └── app.php                          # Scheduled tasks
├── config/
│   └── services.php                     # IDP token config
├── resources/js/
│   ├── Composables/
│   │   └── useSidebarNavigation.js     # Menu item
│   └── Pages/SuperAdmin/
│       └── IdpSyncMonitor.vue           # Dashboard UI
├── routes/
│   └── web.php                          # Routes
└── docs/
    └── IDP_SYNC_MONITOR.md              # This file
```

---

## Support

### Common Questions

**Q: Is it safe to deploy?**  
A: Yes! The feature is read-only by default and requires explicit action to trigger syncs.

**Q: Will it affect my teammates?**  
A: No! The feature is super admin only and backward compatible.

**Q: Do I need to configure anything?**  
A: No! The feature works out of the box. IDP admin token is optional.

**Q: What if something goes wrong?**  
A: Just revert the code. No student data will be deleted or modified.

**Q: Can I test locally?**  
A: Yes! You can view the dashboard locally. Actual syncing requires IDP access.

### Contact

For issues or questions:
1. Check this documentation
2. Check `storage/logs/laravel.log`
3. Check `audit_logs` table in database
4. Contact your team lead or system administrator

---

## Changelog

### Version 1.0 (January 2025)
- ✅ Initial release
- ✅ Dashboard with statistics
- ✅ Manual sync trigger
- ✅ Retry failed jobs
- ✅ Rate limiting
- ✅ Comprehensive audit logging
- ✅ Automatic audit log cleanup
- ✅ Security hardening (MFA masking, authorization)

---

## License

Internal use only - PUP Taguig Admission System

---

**End of Documentation**
