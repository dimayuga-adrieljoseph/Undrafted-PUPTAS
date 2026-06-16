<?php
return [
    'steps' => [
        // 1: CHECKING OF COMPLETENESS AND AUTHENTICITY OF DOCUMENTS
        1 => [
            'page' => 1,
            'template' => 'LOGSHEET-TEMPLATE-2026_PROCESSING-OF-REQUEST-FOR-FIRST-YEAR-STUDENTS-ADMISSION.pdf',
            // Template is 355.6mm wide × 215.9mm tall (custom size, wider than A4)
            // First data row Y — measured from top of page. Adjust if text lands on wrong row.
            'row_start_y' => 78,
            'row_height'  => 9.5,   // height per row in mm
            'columns' => [
                // 'concern' is intentionally omitted — it is pre-printed on every row of the template
                'requested_at'      => ['x' => 18,  'width' => 23],
                'client_name'       => ['x' => 54,  'width' => 55],
                'program'           => ['x' => 106.5, 'width' => 25],
                // 'sex' is intentionally omitted to leave them blank
                'email'             => ['x' => 148, 'width' => 55],
                'processed_at'      => ['x' => 235, 'width' => 30],
                'minutes_processed' => ['x' => 278, 'width' => 15],
                'claimed_at'        => ['x' => 307, 'width' => 30],
            ],
        ],
        // 2: GRADE COMPUTATION AND VERIFICATION
        2 => [
            'page' => 1,
            'template' => 'LOGSHEET-TEMPLATE-2026_PROCESSING-OF-REQUEST-FOR-FIRST-YEAR-STUDENTS-ADMISSION_GRADES.pdf',
            'row_start_y' => 78,
            'row_height'  => 9.5,
            'columns' => [
                // 'concern' is intentionally omitted — it is pre-printed on every row of the template
                'requested_at'      => ['x' => 18,  'width' => 23],
                'client_name'       => ['x' => 54,  'width' => 55],
                'program'           => ['x' => 106.5, 'width' => 25],
                // 'sex' is intentionally omitted to leave them blank
                'email'             => ['x' => 148, 'width' => 55],
                'processed_at'      => ['x' => 235, 'width' => 30],
                'minutes_processed' => ['x' => 278, 'width' => 15],
                'claimed_at'        => ['x' => 307, 'width' => 30],
            ],
        ],
        // 3: INTERVIEW AND SUBMISSION OF ENTRANCE CREDENTIALS
        3 => [
            'page' => 1,
            'template' => 'LOGSHEET-TEMPLATE-2026_PROCESSING-OF-REQUEST-FOR-FIRST-YEAR-STUDENTS-ADMISSION_SUBMISSION.pdf',
            'row_start_y' => 78,
            'row_height'  => 9.5,
            'columns' => [
                // 'concern' is intentionally omitted — it is pre-printed on every row of the template
                'requested_at'      => ['x' => 18,  'width' => 23],
                'client_name'       => ['x' => 54,  'width' => 55],
                'program'           => ['x' => 106.5, 'width' => 25],
                // 'sex' is intentionally omitted to leave them blank
                'email'             => ['x' => 148, 'width' => 55],
                'processed_at'      => ['x' => 235, 'width' => 30],
                'minutes_processed' => ['x' => 278, 'width' => 15],
                'claimed_at'        => ['x' => 307, 'width' => 30],
            ],
        ],
    ],
    'font'      => 'helvetica',
    'font_size' => 8,
];
