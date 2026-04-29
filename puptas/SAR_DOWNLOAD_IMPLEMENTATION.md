# SAR Download Implementation - Signed URLs

## Overview

SAR (Student Admission Record) downloads use **signed URLs** for secure, time-limited access without requiring user authentication. This allows test passers to download their SAR forms directly from email links on any device, including mobile.

## Implementation Details

### Route Configuration
```php
// puptas/routes/web.php
Route::get('/sar/download/{reference}/{filename}', [TestPasserController::class, 'downloadSar'])
    ->middleware('signed')
    ->where('filename', '.*')
    ->name('sar.passer-download');
```

### URL Generation
```php
// puptas/app/Http/Controllers/TestPasserController.php
$downloadUrl = \URL::temporarySignedRoute(
    'sar.passer-download',
    now()->addDays(30),  // 30-day expiration
    [
        'reference' => $passer->reference_number,
        'filename' => $result['filename']
    ]
);
```

### Exception Handling
```php
// puptas/app/Exceptions/Handler.php
use Illuminate\Routing\Exceptions\InvalidSignatureException;

// Returns 403 for invalid/expired signatures
if ($e instanceof InvalidSignatureException) {
    return response()->json([
        'success' => false,
        'message' => 'This link is invalid or has expired.',
        'errorCode' => 'INVALID_SIGNATURE',
    ], 403);
}
```

## Security Features

✅ **Cryptographically Signed** - URLs use HMAC-SHA256 with APP_KEY  
✅ **Time-Limited** - Links expire after 30 days  
✅ **Tamper-Proof** - Any modification invalidates the signature  
✅ **No Authentication Required** - Works from Gmail mobile  
✅ **Reference Validation** - Verifies test passer exists  
✅ **Path Traversal Protection** - Blocks malicious filenames  

## URL Structure

**Valid Signed URL:**
```
https://puptas.undraftedbsit2027.com/sar/download/2026-000-104/SAR_2026-000-104.pdf
?expires=1780040443
&signature=6ae1de1bc67586c71632a9bb08de790e7bf3774d1fe2503d82c11316c9b189fb
```

**Components:**
- `reference`: Test passer reference number (2026-000-104)
- `filename`: SAR PDF filename
- `expires`: Unix timestamp (30 days from generation)
- `signature`: HMAC-SHA256 signature

## User Flow

1. Admin sends SAR email to test passer
2. Email contains signed download URL
3. Test passer clicks link from Gmail (mobile or desktop)
4. PDF downloads immediately (no login required)
5. Link expires after 30 days

## Storage

SAR files are stored in **AWS S3-compatible storage**:
- **Disk**: `sar_tmp` (configured in `config/filesystems.php`)
- **Endpoint**: https://t3.storageapi.dev
- **Bucket**: recorded-envelope-baoihwd
- **Auto-regeneration**: Missing files are regenerated on-demand

## Testing

### Generate Test URL:
```bash
php artisan tinker
```

```php
$url = \URL::temporarySignedRoute(
    'sar.passer-download',
    now()->addDays(30),
    [
        'reference' => '2026-000-104',
        'filename' => 'SAR_2026-000-104_20260429055327.pdf'
    ]
);
echo $url;
```

### Test Scenarios:
- ✅ Valid signed URL → Downloads PDF (200 OK)
- ❌ Unsigned URL → 403 Forbidden
- ❌ Tampered URL → 403 Forbidden
- ❌ Expired URL → 403 Forbidden

## Deployment

### Files Modified:
1. `puptas/routes/web.php` - Added signed middleware
2. `puptas/app/Http/Controllers/TestPasserController.php` - Generate signed URLs
3. `puptas/app/Exceptions/Handler.php` - Handle InvalidSignatureException
4. `puptas/config/filesystems.php` - S3 configuration

### Deploy to Production:
```bash
git add routes/web.php app/Http/Controllers/TestPasserController.php app/Exceptions/Handler.php
git commit -m "feat: Implement signed URLs for SAR downloads"
git push origin main
```

## FAQ

**Q: Can users share the download link?**  
A: Yes, but the SAR form is personalized with their information, so it has limited value to others. Links also expire after 30 days.

**Q: What happens when a link expires?**  
A: The user receives a 403 Forbidden error. They must contact admin to resend the email with a fresh link.

**Q: Is this secure enough?**  
A: Yes. Signed URLs provide cryptographic validation and are industry-standard (used by AWS S3, Google Cloud, etc.). SAR forms contain enrollment information, not highly sensitive data like SSN or financial records.

**Q: Can I make links one-time use?**  
A: Yes, but it requires database-backed tokens. Current implementation prioritizes user experience over this additional security layer.

## Monitoring

Check for invalid signature attempts:
```bash
tail -f storage/logs/laravel.log | grep "InvalidSignatureException"
```

Many failed attempts may indicate:
- Users trying expired links
- Attempted URL tampering
- Brute-force attempts

---

**Status:** ✅ Production Ready  
**Security Level:** HIGH  
**User Experience:** EXCELLENT  
**Mobile Compatible:** YES
