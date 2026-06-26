<?php
return [
    'rows_per_page' => 10,
    'max_rows'      => 10000,

    // Position of the dynamic program title (e.g. "BSBA-HRM 1-1")
    // Calibrate x, y after first test render
    'title' => [
        'x'             => 148,
        'y'             => 40,
        'font'          => 'helvetica',
        'font_style'    => 'B',
        'font_size'     => 28, // Increased from 16
        'align'         => 'L',
        'width'         => 0, // full width centered
        'letter_spacing'=> 0.5, // Adjust this value to increase/decrease space between letters
        'mask'          => [
            'x'      => 134, // Center X position for the white box ((355.6 - 150) / 2)
            'y'      => 40,    // Y position for the white box
            'width'  => 115,   // Width of the white box
            'height' => 12,    // Height of the white box
        ],
    ],

    // Academic year position (e.g. "A.Y. 2026-2027")
    'academic_year' => [
        'x'             => 164.7,
        'y'             => 51.5,
        'font'          => 'helvetica',
        'font_style'    => 'B',
        'font_size'     => 16, // Increased from 12
        'align'         => 'L',
        'width'         => 0,
        'letter_spacing'=> 0.5, // Adjust this value to increase/decrease space between letters
        'mask'          => [
            'x'      => 163.5, // Center X position for the white box ((355.6 - 100) / 2)
            'y'      => 51.5,    // Y position for the white box
            'width'  => 50,   // Width of the white box
            'height' => 8,    // Height of the white box
        ],
    ],

    // Data row configuration
    'row_start_y'  => 78.5, // Y of first data row — calibrate
    'row_height'   => 7.8, // height per row — calibrate

    // Column X positions and widths — calibrate all
    'columns' => [
        'number'      => [
            'x' => 21,  
            'width' => 13, 
            'align' => 'C', 
            'font_size' => 14, // Adjust font size here
            'font_style' => 'B', // 'B' for bold, '' for normal
            'mask' => ['x' => 20, 'width' => 16, 'height' => 6]
        ],
        'full_name'   => ['x' => 42,  'width' => 80],
        'strand'      => ['x' => 148, 'width' => 20],
        'gwa'         => ['x' => 190, 'width' => 15],
        'math_gwa'    => ['x' => 220, 'width' => 15],
        'science_gwa' => ['x' => 248, 'width' => 15],
        'english_gwa' => ['x' => 275, 'width' => 15],
        'notes'       => ['x' => 300, 'width' => 40],
    ],

    'font'      => 'helvetica',
    'font_size' => 9.5,

    // Page 6 of the template is the signature page
    'signature_page' => 6,
    'data_page'      => 1, // all data pages use page 1 layout
];
