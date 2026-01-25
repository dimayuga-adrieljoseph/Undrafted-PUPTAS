# SAR Form Generation - Setup Guide

## Quick Setup (2 Steps)

### Step 1: Install Required Packages

Open terminal in the `puptas` folder and run:

```bash
composer require setasign/fpdi tecnickcom/tcpdf barryvdh/laravel-dompdf
```

**If you get a GD extension error:**

```bash
composer require setasign/fpdi tecnickcom/tcpdf barryvdh/laravel-dompdf --ignore-platform-req=ext-gd
```

---

### Step 2: Copy Template File

```bash
Copy-Item "docs\SAR-FORM_TEMPLATE-2.pdf" "storage\app\templates\SAR-FORM_TEMPLATE-2.pdf" -Force
```

---

## Verify Setup

Test SAR generation:

```bash
php artisan sar:measure --test
```

**Expected output:**
```
✓ Test SAR generated: storage/app/tmp/SAR_2026-TEST-001_*.pdf
```

Open the generated PDF in `storage/app/tmp/` to verify all fields are populated.

---

## Done!

The SAR generation feature is now ready. All code is already in place:
- `app/Services/SarFormService.php` - PDF generation
- `config/sar_fields.php` - Field coordinates
- `app/Http/Controllers/TestPasserController.php` - Integration

---

## Troubleshooting

**Problem: "Template not found"**

```bash
Copy-Item "docs\SAR-FORM_TEMPLATE-2.pdf" "storage\app\templates\" -Force
```

**Problem: "Class not found"**

```bash
composer dump-autoload
```

**Problem: "Permission denied"**

```bash
icacls "storage\app" /grant Users:F /T
```

---

## Adjust Field Positions (Optional)

If fields are misaligned, edit coordinates in `config/sar_fields.php`:

```php
'full_name' => [
    'x' => 25,   // mm from left
    'y' => 65,   // mm from top
    'w' => 120,  // width
    'h' => 6,    // height
],
```

Generate calibration grid for precise measurements:

```bash
php artisan sar:measure --generate
```

See [COORDINATE_CALIBRATION.md](COORDINATE_CALIBRATION.md) for details.

