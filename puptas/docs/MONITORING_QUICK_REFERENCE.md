# IDP Monitoring - Quick Reference Card

## 🚀 One-Command Health Check

```bash
php artisan idp:monitor
```

---

## 📊 Essential Queries

### 1. Success Rate (Today)
```sql
SELECT 
    (COUNT(CASE WHEN action = 'UPDATE' THEN 1 END) * 100.0 / COUNT(*)) as success_rate
FROM audit_logs 
WHERE subject = 'IDP Account Type' AND created_at >= CURDATE();
```
**Target**: > 95%

### 2. Recent Failures
```sql
SELECT * FROM audit_logs 
WHERE subject = 'IDP Account Type' 
AND action = 'FAILED' 
AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR);
```
**Target**: 0 rows

### 3. Unsynced Students
```bash
php artisan idp:monitor | grep "Unsynced Students"
```
**Target**: 0 students

---

## 🔍 Real-Time Monitoring

```bash
# Watch live operations
tail -f storage/logs/laravel.log | grep "IDP"

# Count per minute
tail -f storage/logs/laravel.log | grep "IDP" | ts | awk '{print $1}' | uniq -c
```

---

## ⚠️ Alert Thresholds

| Metric | Warning | Critical |
|--------|---------|----------|
| Success Rate | < 95% | < 90% |
| Failures/hour | > 5 | > 10 |
| MFA Required | > 5/day | > 20/day |
| Unsynced Students | > 5 | > 20 |

---

## 🔧 Quick Fixes

### High Failure Rate
```bash
# 1. Check IDP service
curl -I https://identity-provider.isaxbsit2027.com

# 2. Verify token
php artisan tinker
>>> config('services.idp.admin_access_token')
```

### Unsynced Students
```bash
php artisan idp:sync-enrolled-students
```

### MFA Required
```bash
php artisan idp:sync-enrolled-students --mfa-code=123456
```

---

## 📅 Daily Checklist

- [ ] Run: `php artisan idp:monitor`
- [ ] Success rate > 95%?
- [ ] Failures < 5?
- [ ] Any alerts?

---

## 📞 Support

**Logs**: `storage/logs/laravel.log`  
**Queries**: `database/queries/idp_monitoring_queries.sql`  
**Full Guide**: `docs/IDP_MONITORING_GUIDE.md`

---

**Quick Test**:
```bash
php artisan idp:monitor && echo "✅ System Healthy" || echo "❌ Issues Detected"
```
