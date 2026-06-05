<?php

namespace App\Services;

use setasign\Fpdi\Tcpdf\Fpdi;
use App\Models\Grade;
use App\Models\Program;
use App\Models\User;
use App\Services\GradeComputationService;

/**
 * Grade Verification Slip Service — FPDI Overlay on Template
 *
 * Overlays applicant grade data onto the official
 * "GRADE VERIFICATION SLIP TEMPLATE.pdf" using FPDI/TCPDF.
 *
 * The slip is generated on-demand by the applicant (self-service).
 * No SAR, email, or admin action is required.
 *
 * Rules:
 * - Only the authenticated applicant can generate their own slip.
 * - Qualification results are drawn from existing computed data.
 * - Do NOT recalculate qualifications if results already exist.
 * - Do NOT log PII.
 */
class GradeVerificationSlipService
{
    protected string $templatePath;
    protected GradeComputationService $gradeComputation;

    /** Programs listed on the slip in display order */
    protected array $programOrder = [
        'BSME',
        'BSPSYCH',
        'BSECE',
        'BSED-ENGLISH',
        'BSBA-HRM',
        'BSED-MATH',
        'BSBA-MM',
        'DIT',
        'BSIT',
        'BSOA',
        'DOMT',
    ];

    public function __construct(GradeComputationService $gradeComputation)
    {
        $this->gradeComputation = $gradeComputation;

        $filename = 'GRADE VERIFICATION SLIP TEMPLATE.pdf';
        $this->templatePath = base_path('docs/' . $filename);
    }

    /**
     * Generate the Grade Verification Slip PDF for the given user.
     *
     * Returns the raw PDF binary string ready for streaming.
     *
     * @param  User  $user  The authenticated applicant
     * @return string       Raw PDF binary
     * @throws \Exception   If template is missing or data is incomplete
     */
    public function generate(User $user): string
    {
        if (!file_exists($this->templatePath)) {
            throw new \Exception(
                'Grade Verification Slip template not found at: ' . $this->templatePath
            );
        }

        $data = $this->buildData($user);
        return $this->renderPdf($data);
    }

    /**
     * Build the data array needed to populate the slip.
     */
    protected function buildData(User $user): array
    {
        $profile  = $user->applicantProfile;
        $grades   = Grade::where('user_id', (string) $user->id)->first();
        $testPasser = $user->testPasser;

        if (!$grades) {
            throw new \Exception('No grades found for this applicant.');
        }

        // --- Name (Surname, First Name Middle Name) ---
        $surname    = strtoupper(trim($profile?->lastname   ?? $user->lastname   ?? ''));
        $firstName  = strtoupper(trim($profile?->firstname  ?? $user->firstname  ?? ''));
        $middleName = strtoupper(trim($profile?->middlename ?? $user->middlename ?? ''));

        $fullName = $surname;
        if ($firstName) {
            $fullName .= ($fullName ? ', ' : '') . $firstName;
        }
        if ($middleName) {
            $fullName .= ' ' . $middleName;
        }

        // --- Reference number ---
        $referenceNumber = $testPasser?->reference_number ?? 'N/A';

        // --- Strand / Track ---
        $strand = strtoupper(trim($profile?->strand ?? ''));

        // --- GWA ---
        $gwa = null;
        if ($grades->g12_first_sem !== null && $grades->g12_second_sem !== null) {
            $gwa = round(((float) $grades->g12_first_sem + (float) $grades->g12_second_sem) / 2, 2);
        }

        // --- Category averages ---
        $englishAvg  = $this->resolveAverage($grades, 'english');
        $mathAvg     = $this->resolveAverage($grades, 'mathematics');
        $scienceAvg  = $this->resolveAverage($grades, 'science');

        // --- Subject lists per category ---
        $englishSubjects  = $this->collectSubjects($grades, 'english');
        $mathSubjects     = $this->collectSubjects($grades, 'mathematics');
        $scienceSubjects  = $this->collectSubjects($grades, 'science');

        // --- Program qualifications ---
        $programs = Program::with('strands')->get();
        $qualifications = [];

        foreach ($this->programOrder as $code) {
            $program = $programs->firstWhere('code', $code);
            if (!$program) {
                // Program not in DB yet — mark as not qualified
                $qualifications[$code] = false;
                continue;
            }

            $qualifications[$code] = $this->gradeComputation->isQualified(
                $program,
                $strand,
                $mathAvg,
                $englishAvg,
                $scienceAvg,
                $gwa
            );
        }

        return [
            'full_name'        => $fullName,
            'reference_number' => $referenceNumber,
            'strand'           => $strand,
            'gwa'              => $gwa !== null ? number_format($gwa, 2) : 'N/A',
            'english_avg'      => $englishAvg !== null ? number_format($englishAvg, 2) : 'N/A',
            'math_avg'         => $mathAvg    !== null ? number_format($mathAvg, 2)    : 'N/A',
            'science_avg'      => $scienceAvg !== null ? number_format($scienceAvg, 2) : 'N/A',
            'english_subjects' => $englishSubjects,
            'math_subjects'    => $mathSubjects,
            'science_subjects' => $scienceSubjects,
            'qualifications'   => $qualifications,
        ];
    }

    /**
     * Resolve the stored category average from the Grade model.
     * Returns the stored value if present; falls back to computing it.
     */
    protected function resolveAverage(Grade $grades, string $category): ?float
    {
        $stored = $grades->{$category};
        if ($stored !== null && is_numeric($stored)) {
            return round((float) $stored, 2);
        }

        // Fall back: compute from individual + dynamic subjects
        $subjects = $this->collectSubjects($grades, $category);
        $individual = array_column($subjects, 'grade');
        return $this->gradeComputation->computeCategoryAverage($individual, []);
    }

    /**
     * Collect all subjects and grades for a category, merging fixed fields
     * with dynamic_subjects entries.
     *
     * Returns: [['name' => string, 'grade' => float|null], ...]
     */
    protected function collectSubjects(Grade $grades, string $category): array
    {
        $map = $this->getSubjectFieldMap();
        $subjects = [];

        if (isset($map[$category])) {
            foreach ($map[$category] as $label => $field) {
                $grade = $grades->{$field} ?? null;
                if ($grade !== null) {
                    $subjects[] = [
                        'name'  => $label,
                        'grade' => (float) $grade,
                    ];
                }
            }
        }

        // Dynamic subjects
        // The grade input form stores dynamic subjects with categories 'math', 'english', 'science'
        // but our internal category keys are 'mathematics', 'english', 'science'.
        // Map accordingly before querying.
        $dynamicCategory = match($category) {
            'mathematics' => 'math',
            default        => $category,  // 'english' and 'science' match as-is
        };

        $dynamic = $grades->getDynamicSubjectsForCategory($dynamicCategory);
        foreach ($dynamic as $entry) {
            $name  = trim($entry['name'] ?? '');
            $grade = $entry['grade'] ?? null;
            if ($name !== '' && is_numeric($grade)) {
                $subjects[] = [
                    'name'  => $name,
                    'grade' => (float) $grade,
                ];
            }
        }

        return $subjects;
    }

    /**
     * Static field → label map for known Grade model fields, by category.
     */
    protected function getSubjectFieldMap(): array
    {
        return [
            'english' => [
                'Oral Communication in Context'                              => 'g11_oral_communication',
                '21st Century Literature'                                    => 'g11_21st_century_lit',
                'English for Academic and Professional Purposes'             => 'g11_academic_professional',
                'Reading and Writing'                                        => 'g11_reading_writing',
                '21st Century Literature (G12)'                              => 'g12_21st_century_lit',
                'English for Academic and Professional Purposes (G12)'       => 'g12_academic_professional',
            ],
            'mathematics' => [
                'General Mathematics'         => 'g11_general_mathematics',
                'Statistics and Probability'  => 'g11_statistics_probability',
                'Business Mathematics'        => 'g11_business_mathematics',
                'Pre-Calculus'                => 'g11_pre_calculus',
                'Basic Calculus'              => 'g11_basic_calculus',
            ],
            'science' => [
                'Earth and Life Science'      => 'g11_earth_life_science',
                'Physical Science'            => 'g11_physical_science',
                'Earth Science'               => 'g11_earth_science',
                'General Chemistry 1'         => 'g11_general_chemistry_1',
                'General Physics 1'           => 'g12_general_physics_1',
                'General Biology 1'           => 'g12_general_biology_1',
                'General Physics 2'           => 'g12_general_physics_2',
                'General Biology 2'           => 'g12_general_biology_2',
                'General Chemistry 2'         => 'g12_general_chemistry_2',
                'Earth and Life Science (G12)' => 'g12_earth_life_science',
                'Physical Science (G12)'       => 'g12_physical_science',
            ],
        ];
    }

    /**
     * Render the PDF by overlaying data onto the template using FPDI.
     */
    protected function renderPdf(array $data): string
    {
        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);

        $pageCount = $pdf->setSourceFile($this->templatePath);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tplId = $pdf->importPage($pageNo);
            $size  = $pdf->getTemplateSize($tplId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height'], false);

            $this->overlayPage($pdf, $pageNo, $data);
        }

        return $pdf->Output('', 'S');
    }

    /**
     * Overlay applicant data onto a specific template page.
     *
     * Coordinate system: mm from top-left corner.
     * Page size: 215.9 mm x 330.2 mm (Legal portrait, 8.5" x 13").
     *
     * Calibration files (generated by probe scripts):
     *   storage/app/sar_debug/gvs_rulers.pdf     -- mm ruler overlay
     *   storage/app/sar_debug/gvs_calibration.pdf -- grid overlay
     */
    protected function overlayPage(Fpdi $pdf, int $page, array $data): void
    {
        if ($page !== 1) {
            return;
        }

        $pdf->SetTextColor(0, 0, 0);

        // ── Applicant Information ─────────────────────────────────────────────
        $this->writeField($pdf, 40, 55, 183, 5, $data['full_name'], 10, 'L');
        $this->writeField($pdf, 54, 64, 62, 5, $data['reference_number'], 10, 'L');
        $this->writeField($pdf, 150, 64, 55, 5, $data['strand'], 10, 'L');

        // ── Academic Information ──────────────────────────────────────────────
        $this->writeField($pdf, 56, 72, 44, 5, $data['gwa'], 10, 'L');
        $this->writeField($pdf, 73,  81, 30, 5, $data['english_avg'],  10, 'C');
        $this->writeField($pdf, 120, 81, 28, 5, $data['math_avg'],     10, 'C');
        $this->writeField($pdf, 157, 81, 27, 5, $data['science_avg'],  10, 'C');

        // ── Subject Sections ──────────────────────────────────────────────────
        //
        // The three subject sections (English, Math, Science) must all fit
        // between the averages row and the program qualification table.
        //
        // Fixed boundaries (mm):
        //   SUBJECTS_TOP    = 90   — just below the averages row
        //   SUBJECTS_BOTTOM = 205  — where the program qualification table starts
        //   Available height = 115 mm
        //
        // We auto-scale row height so content always fits, no matter how many
        // subjects the applicant has.  Minimum font size = 6pt.

        $x              = 30.0;
        $w              = 180.0;
        $subjectsTop    = 90.0;
        $subjectsBottom = 205.0;   // program table starts here — never go below
        $availableH     = $subjectsBottom - $subjectsTop;

        // Count total rows needed:
        //   NO headings (template has them)
        //   3 column-header rows (1 per section)
        //   all subject rows (but in 2 columns, so max 6 rows per section)
        //   2 gaps between sections
        $maxRowsPerSection = [
            max(1, (int) ceil(count($data['english_subjects']) / 2.0)),
            max(1, (int) ceil(count($data['math_subjects']) / 2.0)),
            max(1, (int) ceil(count($data['science_subjects']) / 2.0)),
        ];
        $totalSubjectRows = array_sum($maxRowsPerSection);
        $colHdrRows       = 3;   // one "Subject / Grade" header per section
        $gapRows          = 4;   // larger gaps between sections (2 full row heights)
        $totalRows        = $totalSubjectRows + $colHdrRows + $gapRows;

        // Calculate row height that makes everything fit
        $rowH = $totalRows > 0
            ? min(5.5, max(3.0, $availableH / $totalRows))
            : 5.5;

        // Scale font proportionally with row height (caps at 8pt)
        $fontSize = (int) min(8, max(6, floor($rowH * 1.4)));

        // ── Fixed Y start for each section ────────────────────────────────────
        // These match the printed "English Subjects:", "Mathematics Subjects:",
        // and "Science Subjects:" labels on the template.
        // Increase a value to move that section DOWN, decrease to move UP.
        $englishY  = 94.5;   // ← Y where English rows start
        $mathY     = 129.5;  // ← Y where Math rows start
        $scienceY  = 164.5;  // ← Y where Science rows start

        $this->writeSubjectSection(
            $pdf, $x, $englishY, $w, $rowH, $fontSize,
            $data['english_subjects']
        );

        $this->writeSubjectSection(
            $pdf, $x, $mathY, $w, $rowH, $fontSize,
            $data['math_subjects']
        );

        $this->writeSubjectSection(
            $pdf, $x, $scienceY, $w, $rowH, $fontSize,
            $data['science_subjects']
        );

        // ── Program Qualifications — always at fixed Y ────────────────────────
        $this->writeQualifications($pdf, $data['qualifications']);
    }

    /**
     * Write a text field at exact coordinates.
     */
    protected function writeField(
        Fpdi   $pdf,
        float  $x,
        float  $y,
        float  $w,
        float  $h,
        string $text,
        int    $fontSize = 10,
        string $align    = 'L'
    ): void {
        $pdf->SetFont('helvetica', 'B', $fontSize);

        // Shrink font to fit width
        while ($fontSize > 6 && $pdf->GetStringWidth($text) > $w) {
            $fontSize--;
            $pdf->SetFont('helvetica', 'B', $fontSize);
        }

        $pdf->SetXY($x, $y);
        $pdf->Cell($w, $h, $text, 0, 0, $align);
    }

    /**
     * Write a subject section: two-column subject/grade table (no heading).
     *
     * The template already has "English Subjects:", "Mathematics Subjects:",
     * and "Science Subjects:" labels printed on it, so we don't draw them again.
     * We just draw the data rows in 2 columns.
     *
     * Layout: split subjects into 2 columns of max 6 rows each.
     *   Left column:  subjects[0..5]
     *   Right column: subjects[6..11]
     * If > 12 subjects, auto-shrink font/row height to fit.
     *
     * Returns Y immediately after the section.
     */
    protected function writeSubjectSection(
        Fpdi   $pdf,
        float  $x,
        float  $startY,
        float  $w,
        float  $rowH,
        int    $fontSize,
        array  $subjects
    ): float {
        // Page layout: 2 columns, equal width, small gap
        $colW = ($w - 4.0) / 2.0;  // 4mm gap between columns
        $leftX  = $x;
        $rightX = $x + $colW + 4.0;

        $y = $startY;

        // ── Data rows (split into 2 columns) ───────────────────────────────
        if (empty($subjects)) {
            $pdf->SetFont('helvetica', 'I', $fontSize);
            $pdf->SetTextColor(150, 150, 150);
            $pdf->SetXY($leftX, $y);
            $pdf->Cell($w, $rowH, 'No subjects recorded', 0, 0, 'L');
            $y += $rowH;
        } else {
            $leftCol  = array_slice($subjects, 0, 6);
            $rightCol = array_slice($subjects, 6, 6);
            $maxRows  = max(count($leftCol), count($rightCol));

            for ($i = 0; $i < $maxRows; $i++) {
                $rowY = $y + ($i * $rowH);

                // Left column row
                if (isset($leftCol[$i])) {
                    $this->writeSubjectRow(
                        $pdf, $leftX, $rowY, $colW, $rowH, $fontSize,
                        $leftCol[$i]['name'],
                        $leftCol[$i]['grade']
                    );
                }

                // Right column row
                if (isset($rightCol[$i])) {
                    $this->writeSubjectRow(
                        $pdf, $rightX, $rowY, $colW, $rowH, $fontSize,
                        $rightCol[$i]['name'],
                        $rightCol[$i]['grade']
                    );
                }
            }

            $y += $maxRows * $rowH;
        }

        $pdf->SetTextColor(0, 0, 0);
        return $y;
    }

    /**
     * Write a single subject row: "Subject Name  |  Grade"
     */
    protected function writeSubjectRow(
        Fpdi  $pdf,
        float $x,
        float $y,
        float $colW,
        float $rowH,
        int   $fontSize,
        string $name,
        float $grade
    ): void {
        $nameW  = $colW * 0.78;
        $gradeW = $colW * 0.22;

        $pdf->SetFont('helvetica', '', $fontSize);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x, $y);
        $pdf->Cell($nameW, $rowH, $name, 0, 0, 'L');

        $pdf->SetFont('helvetica', 'B', $fontSize);
        $pdf->SetXY($x + $nameW, $y);
        $pdf->Cell($gradeW, $rowH, number_format($grade, 2), 0, 0, 'C');
    }

    protected function writeQualifications(Fpdi $pdf, array $qualifications): void
    {
        // [program_code, x, y]
        // Adjust x/y to align the mark inside the template's □ checkbox.
        // Left col:  decrease y to move up, increase x to move right.
        // Right col: decrease x to move left, decrease y to move up.
        $programBoxes = [
            // ── Left column ───────────────────────────────────────────────────
            ['BSME',     27, 204.0],
            ['BSECE',    27, 213.0],
            ['BSBA-HRM', 27, 220.0],
            ['BSBA-MM',  27, 229.0],
            ['BSOA',     27, 237.0],
            ['BSIT',     27, 246.0],
            // ── Right column ──────────────────────────────────────────────────
            ['BSPSYCH',      109, 205.0],
            ['BSED-ENGLISH', 109, 213.0],
            ['BSED-MATH',    109, 221.0],
            ['DIT',          109, 229.0],
            ['DOMT',         109, 237.0],
        ];

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('ZapfDingbats', '', 11);

        foreach ($programBoxes as [$code, $x, $y]) {
            if (!array_key_exists($code, $qualifications)) {
                continue;
            }

            $pdf->SetXY($x, $y);
            $pdf->Write(0, $qualifications[$code] ? chr(52) : chr(56));
        }

        $pdf->SetTextColor(0, 0, 0);
    }
}
