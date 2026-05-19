<?php

/**
 * SAR Form Field Positions Configuration
 *
 * Coordinates are in millimeters (mm) from top-left corner of each page.
 * Adjust these values to fine-tune field placement on the template.
 *
 * To calibrate:
 * 1. php artisan sar:measure --generate  → open calibration_grid.pdf
 * 2. php artisan sar:measure --test      → open debug_overlay_*.pdf
 * 3. Adjust x/y here, regenerate until aligned.
 *
 * White-blanking:
 *   'blank'       => ['x','y','w','h']  — white rect drawn after template, before text
 *   'redraw_line' => ['x1','y1','x2','y2'] — line redrawn after blanking
 */

return [

    // -------------------------------------------------------------------------
    // Page 1: SAR-FORM 1 — Confirmation Slip (top copy + duplicate copy)
    // -------------------------------------------------------------------------
    1 => [

        // --- Top copy ---
        'reference_number' => [
            'x' => 132, 'y' => 9, 'w' => 60, 'h' => 6,
            'font' => 'helvetica', 'font_size' => 10,
            'align' => 'L', 'uppercase' => true, 'fit_mode' => 'shrink',
            'token' => '«Reference_Number»',
        ],
        'full_name' => [
            'x' => 25, 'y' => 66, 'w' => 120, 'h' => 6,
            'font' => 'helvetica', 'font_size' => 11,
            'align' => 'L', 'uppercase' => true, 'fit_mode' => 'shrink',
            'token' => '<<SURNAME, FIRST NAME MIDDLE NAME>>',
        ],
        'graduation_year' => [
            'x' => 40, 'y' => 77, 'w' => 30, 'h' => 6,
            'font' => 'helvetica', 'font_size' => 10,
            'align' => 'L', 'uppercase' => false, 'fit_mode' => 'shrink',
        ],
        'previous_school' => [
            'x' => 30, 'y' => 85, 'w' => 110, 'h' => 20,
            'font' => 'helvetica', 'font_size' => 9,
            'align' => 'L', 'uppercase' => true, 'fit_mode' => 'wrap',
        ],
        'date_of_enrollment' => [
            'x' => 140, 'y' => 101, 'w' => 30, 'h' => 6,
            'font' => 'helvetica', 'font_size' => 10,
            'align' => 'L', 'uppercase' => false, 'fit_mode' => 'shrink',
            'token' => '<<DATE>>',
        ],
        'time' => [
            'x' => 152, 'y' => 106.5, 'w' => 25, 'h' => 6,
            'font' => 'helvetica', 'font_size' => 10,
            'align' => 'L', 'uppercase' => false, 'fit_mode' => 'shrink',
            'token' => '<<TIME>>',
        ],

        // Printed name above "Signature over Printed Name of Applicant" — top copy
        'printed_name_signature' => [
            'x' => 13, 'y' => 115, 'w' => 80, 'h' => 5,
            'font' => 'helvetica', 'font_size' => 9,
            'align' => 'L', 'uppercase' => true, 'fit_mode' => 'shrink',
        ],

        // --- Duplicate copy (lower half of page 1) ---
        'reference_number_duplicate' => [
            'x' => 132, 'y' => 142, 'w' => 60, 'h' => 6,
            'font' => 'helvetica', 'font_size' => 10,
            'align' => 'L', 'uppercase' => true, 'fit_mode' => 'shrink',
        ],
        'full_name_duplicate' => [
            'x' => 25, 'y' => 209, 'w' => 120, 'h' => 6,
            'font' => 'helvetica', 'font_size' => 11,
            'align' => 'L', 'uppercase' => true, 'fit_mode' => 'shrink',
            // Erase stub lines + (Name) label, redraw clean underline
            'blank'       => ['x' => 19, 'y' => 200, 'w' => 140.8, 'h' => 19],
            'redraw_line' => ['x1' => 22, 'y1' => 215, 'x2' => 158, 'y2' => 215],
        ],
        'graduation_year_duplicate' => [
            'x' => 40, 'y' => 216, 'w' => 30, 'h' => 6,
            'font' => 'helvetica', 'font_size' => 10,
            'align' => 'L', 'uppercase' => false, 'fit_mode' => 'shrink',
        ],
        'previous_school_duplicate' => [
            'x' => 30, 'y' => 225, 'w' => 110, 'h' => 20,
            'font' => 'helvetica', 'font_size' => 9,
            'align' => 'L', 'uppercase' => true, 'fit_mode' => 'wrap',
        ],
        'date_of_enrollment_duplicate' => [
            'x' => 140, 'y' => 244, 'w' => 30, 'h' => 6,
            'font' => 'helvetica', 'font_size' => 10,
            'align' => 'L', 'uppercase' => false, 'fit_mode' => 'shrink',
        ],
        'time_duplicate' => [
            'x' => 152, 'y' => 250, 'w' => 25, 'h' => 6,
            'font' => 'helvetica', 'font_size' => 10,
            'align' => 'L', 'uppercase' => false, 'fit_mode' => 'shrink',
        ],

        // Printed name above "Signature over Printed Name of Applicant" — bottom copy
        'printed_name_signature_duplicate' => [
            'x' => 13, 'y' => 258, 'w' => 80, 'h' => 5,
            'font' => 'helvetica', 'font_size' => 9,
            'align' => 'L', 'uppercase' => true, 'fit_mode' => 'shrink',
        ],
    ],

    // -------------------------------------------------------------------------
    // Page 2: Route & Approval Slip
    // -------------------------------------------------------------------------
    2 => [
        'name_of_applicant' => [
            'x' => 41, 'y' => 26.5, 'w' => 90, 'h' => 6,
            'font' => 'helvetica', 'font_size' => 10,
            'align' => 'L', 'uppercase' => true, 'fit_mode' => 'shrink',
        ],
        'reference_number' => [
            'x' => 41, 'y' => 30.5, 'w' => 60, 'h' => 6,
            'font' => 'helvetica', 'font_size' => 10,
            'align' => 'L', 'uppercase' => true, 'fit_mode' => 'shrink',
        ],
        'date_of_enrollment' => [
            'x' => 135, 'y' => 30.5, 'w' => 40, 'h' => 6,
            'font' => 'helvetica', 'font_size' => 10,
            'align' => 'L', 'uppercase' => false, 'fit_mode' => 'shrink',
        ],
        'time' => [
            'x' => 170, 'y' => 30.5, 'w' => 30, 'h' => 6,
            'font' => 'helvetica', 'font_size' => 10,
            'align' => 'L', 'uppercase' => false, 'fit_mode' => 'shrink',
        ],
        'shs_track' => [
            'x' => 41, 'y' => 37, 'w' => 80, 'h' => 6,
            'font' => 'helvetica', 'font_size' => 10,
            'align' => 'L', 'uppercase' => true, 'fit_mode' => 'shrink',
        ],
    ],

    // -------------------------------------------------------------------------
    // Page 6: Certification/Undertaking
    // "I, ___, of legal age..." → full_name
    // "Campus: ___" → campus (fixed value: "Taguig Campus")
    // NOTE: Calibrate coordinates using: php artisan sar:measure --test
    // -------------------------------------------------------------------------
    6 => [
        'full_name' => [
            'x' => 35, 'y' => 20, 'w' => 80, 'h' => 5,
            'font' => 'helvetica', 'font_size' => 9,
            'align' => 'L', 'uppercase' => true, 'fit_mode' => 'shrink',
        ],
        // Printed name above "Signature over Printed Name of Applicant"
        'printed_name_enrollee' => [
            'x' => 90, 'y' => 246, 'w' => 120, 'h' => 6,
            'font' => 'helvetica', 'font_size' => 9,
            'align' => 'C', 'uppercase' => true, 'fit_mode' => 'shrink',
            'blank' => ['x' => 90, 'y' => 236, 'w' => 140.8, 'h' => 14],
            'redraw_line' => ['x1' => 115, 'y1' => 252, 'x2' => 188, 'y2' => 252],
        ],
        'campus' => [
            'x' => 125, 'y' => 45, 'w' => 50, 'h' => 5,
            'font' => 'helvetica', 'font_size' => 9,
            'align' => 'L', 'uppercase' => false, 'fit_mode' => 'shrink',
        ],
    ],

    // -------------------------------------------------------------------------
    // Page 7: Affidavit of Non-Enrollment
    // "I, ___, of legal age..." → full_name_natural (First Middle Surname)
    // "graduated my high school at ___" → school_attended
    // "in the year ___" → graduation_year
    // NOTE: Calibrate coordinates using: php artisan sar:measure --test
    // -------------------------------------------------------------------------
    7 => [
        'full_name_natural' => [
            'x' => 55, 'y' => 33, 'w' => 80, 'h' => 5,
            'font' => 'helvetica', 'font_size' => 9,
            'align' => 'L', 'uppercase' => true, 'fit_mode' => 'shrink',
        ],
        'affiant_name' => [
            'x' => 115, 'y' => 160, 'w' => 58, 'h' => 7,
            'font' => 'helvetica', 'font_size' => 8,
            'align' => 'R', 'uppercase' => true, 'fit_mode' => 'shrink',
            'blank'       => ['x' => 113, 'y' => 158, 'w' => 75, 'h' => 8],
            'blank2'      => ['x' => 30,  'y' => 240, 'w' => 150.8, 'h' => 20],
            'blank3'      => ['x' => 117,  'y' => 167, 'w' => 150.8, 'h' => 20],
            'redraw_line' => ['x1' => 117, 'y1' => 166, 'x2' => 180, 'y2' => 166],
        ],
        'previous_school' => [
            'x' => 120, 'y' => 58, 'w' => 100, 'h' => 5,
            'font' => 'helvetica', 'font_size' => 9,
            'align' => 'L', 'uppercase' => true, 'fit_mode' => 'shrink',
        ],
        'graduation_year' => [
            'x' => 60, 'y' => 63, 'w' => 20, 'h' => 5,
            'font' => 'helvetica', 'font_size' => 9,
            'align' => 'L', 'uppercase' => false, 'fit_mode' => 'shrink',
        ],
    ],

    // -------------------------------------------------------------------------
    // Page 8: Admission Criteria page (QR code + clickable link)
    // Covers the original static link text and replaces it with a clickable one
    // Template updated: 2026-SAR-FORM 1_TEMPLATE(Latest).pdf (AY 2026-2027)
    // -------------------------------------------------------------------------
    8 => [
        'criteria_link' => [
            'x' => 25, 'y' => 170, 'w' => 165, 'h' => 12,
            'font_size' => 14,
            'label' => 'PUP-Taguig Campus Admission Criteria 2026',
            'link_url' => 'https://drive.google.com/file/d/153oJlLhvU9UDjJ5JzFgA04aWurQ_PBbE/view',
            // Also erase the small original text below
            'blank' => ['x' => 25, 'y' => 170, 'w' => 165, 'h' => 15],
        ],
    ],

    // -------------------------------------------------------------------------
    // Page 9: Consent Form for Access and Use of Student Records
    // -------------------------------------------------------------------------
    9 => [
        // "I, ___________" — the blank after "I," on the first line of the consent body
        'full_name_consent' => [
            'x' => 40, 'y' => 68, 'w' => 120, 'h' => 5,
            'font' => 'helvetica', 'font_size' => 10,
            'align' => 'L', 'uppercase' => true, 'fit_mode' => 'shrink',
        ],

        // Printed name above "Student/Applicant's Signature over Printed Name"
        'printed_name_consent' => [
            'x' => 2, 'y' => 220, 'w' => 120, 'h' => 5,
            'font' => 'helvetica', 'font_size' => 9,
            'align' => 'C', 'uppercase' => true, 'fit_mode' => 'shrink',
        ],

        // "PUP iApply Reference Number: ___"
        'reference_number_consent' => [
            'x' => 82, 'y' => 273, 'w' => 80, 'h' => 5,
            'font' => 'helvetica', 'font_size' => 9,
            'align' => 'L', 'uppercase' => true, 'fit_mode' => 'shrink',
        ],
    ],

    // -------------------------------------------------------------------------
    // Page 10: Declaration of Medical Information and Data Subject Consent Form
    // -------------------------------------------------------------------------
    10 => [
        // Printed name above "Student's Signature Over Printed Name/ Date"
        'printed_name_medical' => [
            'x' => 85, 'y' => 152, 'w' => 100, 'h' => 5,
            'font' => 'helvetica', 'font_size' => 9,
            'align' => 'C', 'uppercase' => true, 'fit_mode' => 'shrink',
        ],
    ],

];
