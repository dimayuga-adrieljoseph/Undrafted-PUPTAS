# SAR Form Coordinate Calibration Guide

## Overview

This guide explains how to measure and calibrate field coordinates for the SAR form using only PHP/Laravel tools (no Python required).

## Tools Available

### 1. Calibration Grid Generator
Generates a PDF with 10mm grid overlay on the template for precise measurement.

```bash
php artisan sar:measure --generate
```

Output: `storage/app/sar_debug/calibration_grid.pdf`

### 2. Test SAR Generator
Generates a test SAR with sample data and debug overlay showing field positions.

```bash
php artisan sar:measure --test
```

Outputs:
- `storage/app/tmp/SAR_2026-TEST-001_*.pdf` - Actual SAR
- `storage/app/sar_debug/debug_overlay_*.pdf` - Debug version with red boxes

## Measurement Workflow

### Step 1: Generate Calibration Grid

```bash
php artisan sar:measure --generate
```

Open `storage/app/sar_debug/calibration_grid.pdf` in a PDF reader with ruler/measurement tools (Adobe Acrobat, Foxit, etc.).

The grid shows:
- **10mm intervals** - vertical and horizontal lines
- **20mm labels** - numbers showing millimeters from top-left corner
- **Page numbers** - blue text in top-right corner
- **Origin marker** - red lines at (0,0) top-left

### Step 2: Measure Field Positions

For each field you want to position:

1. Locate the field box on the template
2. Use PDF ruler tool to measure from **top-left corner** (0,0):
   - **X** = horizontal distance from left edge to field's left edge (mm)
   - **Y** = vertical distance from top edge to field's top edge (mm)
   - **W** = width of the field box (mm)
   - **H** = height of the field box (mm)

**Important Coordinate Rules:**
- Origin (0, 0) is at **top-left corner**
- X increases **rightward**
- Y increases **downward**
- All measurements in **millimeters (mm)**

### Step 3: Update config/sar_fields.php

Edit [config/sar_fields.php](../config/sar_fields.php) with measured coordinates:

```php
return [
    1 => [ // Page 1
        'reference_number' => [
            'x' => 35.0,      // mm from left
            'y' => 28.0,      // mm from top
            'w' => 60.0,      // width
            'h' => 6.0,       // height
            'font' => 'helvetica',
            'font_size' => 10,
            'align' => 'L',   // L=Left, C=Center, R=Right
            'uppercase' => true,
            'fit_mode' => 'shrink',
        ],
        // ... more fields
    ],
];
```

**Field Configuration Options:**
- `x`, `y`, `w`, `h` - Position and size in mm (required)
- `font` - Font family: `helvetica`, `courier`, `times` (default: `helvetica`)
- `font_size` - Font size in points (default: 10)
- `align` - Alignment: `L` (left), `C` (center), `R` (right) (default: `L`)
- `uppercase` - Convert text to uppercase (default: false)
- `fit_mode` - Text fitting: `shrink` (auto-shrink font), `wrap` (multi-line) (default: `shrink`)

### Step 4: Test and Verify

Generate test SAR with debug overlay:

```bash
php artisan sar:measure --test
```

Open both PDFs:
- `storage/app/tmp/SAR_2026-TEST-001_*.pdf` - final output
- `storage/app/sar_debug/debug_overlay_*.pdf` - debug with red boxes

**Verification Checklist:**
- ✅ Red boxes align with template field boxes
- ✅ Text is centered within boxes
- ✅ Text doesn't overflow boxes
- ✅ No text overlapping with borders
- ✅ Checkboxes appear in correct positions
- ✅ Photo appears in correct position with proper size

### Step 5: Iterate

If fields are misaligned:
1. Remeasure coordinates using calibration grid
2. Update `config/sar_fields.php`
3. Run test again: `php artisan sar:measure --test`
4. Repeat until perfect alignment

## Tips for Accurate Measurement

### PDF Reader Tools

**Adobe Acrobat:**
- Tools → Measure → Distance Tool
- Set units to Millimeters
- Measure from edges

**Foxit Reader:**
- Comment → Measure → Distance
- Right-click measurement → Properties → Set units to mm

### Common Issues

**Issue: Text overflows box**
- Solution: Reduce `font_size` or increase `w` (width)

**Issue: Text not centered vertically**
- Solution: Adjust `h` (height) to match box height exactly

**Issue: Multiple lines needed**
- Solution: Change `fit_mode` to `'wrap'` instead of `'shrink'`

**Issue: Fields scattered/misaligned**
- Solution: Remeasure X and Y coordinates carefully using grid

**Issue: Photo distorted**
- Note: Photos maintain aspect ratio automatically (centered in box)

## Field Mapping Reference

Fields are mapped in [config/sar_fields.php](../config/sar_fields.php) by page:

- **Page 1**: Confirmation (top copy) - reference_number, full_name, photo, etc.
- **Page 2**: Confirmation duplicate (carbon copy) - same fields as page 1
- **Page 3**: Routing/approval - reference_number, approval dates, signatures
- **Page 6**: Medical examination - student_number, date, medical checkboxes

See [TestPasserController::prepareSarDataFromPasser()](../app/Http/Controllers/TestPasserController.php#L150) for data field names.

## No White Rectangles

**Important:** The system **does NOT draw white rectangles** to blank placeholders.

Text is overlaid **directly** on the template at specified coordinates. This maintains template background colors, borders, and graphics.

## Debug Mode

Debug mode is automatically enabled in test command. To enable in production:

```php
$service = new \App\Services\SarFormService();
$service->setDebugMode(true);
$result = $service->generateSarPdf($data);
```

Debug PDFs show:
- **Red boxes** - field boundaries (x, y, w, h)
- **Blue labels** - coordinate values for verification
- **Green checkmarks** - checkbox positions

## Related Files

- [app/Services/SarFormService.php](../app/Services/SarFormService.php) - Main PDF generation service
- [config/sar_fields.php](../config/sar_fields.php) - Field coordinate configuration
- [app/Console/Commands/MeasureSarCoordinates.php](../app/Console/Commands/MeasureSarCoordinates.php) - Calibration command
- [storage/app/templates/SAR-FORM_TEMPLATE-2.pdf](../storage/app/templates/SAR-FORM_TEMPLATE-2.pdf) - Clean template

## Troubleshooting

**Q: Template not found**
A: Copy template to storage:
```bash
Copy-Item "docs\SAR-FORM_TEMPLATE-2.pdf" "storage\app\templates\" -Force
```

**Q: Grid too fine/coarse**
A: Edit [MeasureSarCoordinates.php](../app/Console/Commands/MeasureSarCoordinates.php) line 97 - change `$x += 10` to different interval.

**Q: Debug file not generated**
A: Check `storage/app/sar_debug/` permissions, ensure directory is writable.

**Q: Coordinates from old Python script**
A: Ignore Python script, use this PHP-based calibration workflow instead.
