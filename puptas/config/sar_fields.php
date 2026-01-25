<?php

/**
 * SAR Form Field Positions Configuration
 * 
 * Coordinates are in millimeters (mm) from top-left corner of each page
 * Adjust these values to fine-tune field placement on the template
 * 
 * To calibrate:
 * 1. Generate SAR with debug=true parameter
 * 2. Open debug PDF to see field boxes and labels
 * 3. Adjust x/y coordinates here
 * 4. Regenerate until alignment is perfect
 */

return [
    // Page 1: Confirmation slip (includes both top copy and duplicate copy)
    1 => [
        // Top copy fields
        'reference_number' => [
            'x' => 132,
            'y' => 9,
            'w' => 60,
            'h' => 6,
            'font' => 'helvetica',
            'font_size' => 10,
            'align' => 'L',
            'uppercase' => true,
            'fit_mode' => 'shrink',
            'token' => '«Reference_Number»'
        ],
        'full_name' => [
            'x' => 25,
            'y' => 65,
            'w' => 120,
            'h' => 6,
            'font' => 'helvetica',
            'font_size' => 11,
            'align' => 'L',
            'uppercase' => true,
            'fit_mode' => 'shrink',
            'token' => '<<SURNAME, FIRST NAME MIDDLE NAME>>'
        ],
        'graduation_year' => [
            'x' => 40,
            'y' => 77,
            'w' => 30,
            'h' => 6,
            'font' => 'helvetica',
            'font_size' => 10,
            'align' => 'L',
            'uppercase' => false,
            'fit_mode' => 'shrink',
        ],
        'previous_school' => [
            'x' => 30,
            'y' => 85,
            'w' => 110,
            'h' => 20,
            'font' => 'helvetica',
            'font_size' => 9,
            'align' => 'L',
            'uppercase' => true,
            'fit_mode' => 'wrap',
        ],
        'date_of_enrollment' => [
            'x' => 140,
            'y' => 101,
            'w' => 30,
            'h' => 6,
            'font' => 'helvetica',
            'font_size' => 10,
            'align' => 'L',
            'uppercase' => false,
            'fit_mode' => 'shrink',
            'token' => '<<DATE>>'
        ],
        'time' => [
            'x' => 152,
            'y' => 106.5,
            'w' => 25,
            'h' => 6,
            'font' => 'helvetica',
            'font_size' => 10,
            'align' => 'L',
            'uppercase' => false,
            'fit_mode' => 'shrink',
            'token' => '<<TIME>>'
        ],
        
        // Duplicate copy fields (adjust coordinates as needed)
        'reference_number_duplicate' => [
            'x' => 132,
            'y' => 142,
            'w' => 60,
            'h' => 6,
            'font' => 'helvetica',
            'font_size' => 10,
            'align' => 'L',
            'uppercase' => true,
            'fit_mode' => 'shrink',
        ],
        'full_name_duplicate' => [
            'x' => 25,
            'y' => 209,
            'w' => 120,
            'h' => 6,
            'font' => 'helvetica',
            'font_size' => 11,
            'align' => 'L',
            'uppercase' => true,
            'fit_mode' => 'shrink',
        ],
        'graduation_year_duplicate' => [
            'x' => 40,
            'y' => 216,
            'w' => 30,
            'h' => 6,
            'font' => 'helvetica',
            'font_size' => 10,
            'align' => 'L',
            'uppercase' => false,
            'fit_mode' => 'shrink',
        ],
        'previous_school_duplicate' => [
            'x' => 30,
            'y' => 226,
            'w' => 110,
            'h' => 20,
            'font' => 'helvetica',
            'font_size' => 9,
            'align' => 'L',
            'uppercase' => true,
            'fit_mode' => 'wrap',
        ],
        'date_of_enrollment_duplicate' => [
            'x' => 142,
            'y' => 244,
            'w' => 30,
            'h' => 6,
            'font' => 'helvetica',
            'font_size' => 10,
            'align' => 'L',
            'uppercase' => false,
            'fit_mode' => 'shrink',
        ],
        'time_duplicate' => [
            'x' => 153,
            'y' => 249,
            'w' => 25,
            'h' => 6,
            'font' => 'helvetica',
            'font_size' => 10,
            'align' => 'L',
            'uppercase' => false,
            'fit_mode' => 'shrink',
        ],
    ],

    // Page 2: Route & Approval slip
    2 => [
        'name_of_applicant' => [
            'x' => 41,
            'y' => 26.5,
            'w' => 90,
            'h' => 6,
            'font' => 'helvetica',
            'font_size' => 10,
            'align' => 'L',
            'uppercase' => true,
            'fit_mode' => 'shrink',
        ],
        'reference_number' => [
            'x' => 41,
            'y' => 30.5,
            'w' => 60,
            'h' => 6,
            'font' => 'helvetica',
            'font_size' => 10,
            'align' => 'L',
            'uppercase' => true,
            'fit_mode' => 'shrink',
        ],
        'date_of_enrollment' => [
            'x' => 135,
            'y' => 30.5,
            'w' => 40,
            'h' => 6,
            'font' => 'helvetica',
            'font_size' => 10,
            'align' => 'L',
            'uppercase' => false,
            'fit_mode' => 'shrink',
        ],
        'time' => [
            'x' => 170,
            'y' => 30.5,
            'w' => 30,
            'h' => 6,
            'font' => 'helvetica',
            'font_size' => 10,
            'align' => 'L',
            'uppercase' => false,
            'fit_mode' => 'shrink',
        ],
        'shs_track' => [
            'x' => 41,
            'y' => 37,
            'w' => 80,
            'h' => 6,
            'font' => 'helvetica',
            'font_size' => 10,
            'align' => 'L',
            'uppercase' => true,
            'fit_mode' => 'shrink',
        ],
    ],
];
