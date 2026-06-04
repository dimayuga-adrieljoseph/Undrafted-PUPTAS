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
        $dynamic = $grades->getDynamicSubjectsForCategory($category);
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
     */
    protected function overlayPage(Fpdi $pdf, int $page, array $data): void
    {
        // All content lives on page 1 of the template
        if ($page !== 1) {
            return;
        }

        $pdf->SetTextColor(0, 0, 0);

        // ── Applicant Information ─────────────────────────────────────────────

        // Name
        $this->writeField($pdf, 35, 45.5, 120, 5, $data['full_name'], 10, 'L');

        // Reference Number
        $this->writeField($pdf, 35, 50.5, 70, 5, $data['reference_number'], 10, 'L');

        // Strand / Track
        $this->writeField($pdf, 131, 50.5, 55, 5, $data['strand'], 10, 'L');

        // ── Academic Information ──────────────────────────────────────────────

        // Grade 12 GWA
        $this->writeField($pdf, 47, 66, 30, 5, $data['gwa'], 10, 'C');

        // English Average
        $this->writeField($pdf, 47, 71, 30, 5, $data['english_avg'], 10, 'C');

        // Mathematics Average
        $this->writeField($pdf, 47, 76, 30, 5, $data['math_avg'], 10, 'C');

        // Science Average
        $this->writeField($pdf, 47, 81, 30, 5, $data['science_avg'], 10, 'C');

        // ── Subject Tables ────────────────────────────────────────────────────

        // English subjects — starts at y≈98, left column
        $this->writeSubjectTable($pdf, 13, 98, $data['english_subjects'], 80);

        // Math subjects — starts at y≈98, right of center
        $this->writeSubjectTable($pdf, 107, 98, $data['math_subjects'], 80);

        // Science subjects — second row, left column
        $startY = 98 + max(
            $this->estimateTableHeight($data['english_subjects']),
            $this->estimateTableHeight($data['math_subjects'])
        ) + 4;
        $this->writeSubjectTable($pdf, 13, $startY, $data['science_subjects'], 175);

        // ── Program Qualifications ────────────────────────────────────────────

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
     * Write a two-column subject table (Subject Name | Grade).
     */
    protected function writeSubjectTable(
        Fpdi  $pdf,
        float $x,
        float $startY,
        array $subjects,
        float $tableWidth
    ): void {
        if (empty($subjects)) {
            return;
        }

        $rowH      = 4.5;
        $nameWidth = $tableWidth * 0.76;
        $gradeWidth = $tableWidth * 0.24;
        $y         = $startY;

        foreach ($subjects as $subject) {
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($x, $y);
            $pdf->Cell($nameWidth, $rowH, $subject['name'], 0, 0, 'L');

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetXY($x + $nameWidth, $y);
            $pdf->Cell($gradeWidth, $rowH, number_format((float) $subject['grade'], 2), 0, 0, 'C');

            $y += $rowH;
        }
    }

    /**
     * Estimate the pixel height of a subject table (used for vertical stacking).
     */
    protected function estimateTableHeight(array $subjects): float
    {
        return count($subjects) * 4.5;
    }

    /**
     * Write qualification markers (✓ / ✗) for each program.
     *
     * The template has a table of programs; we overlay the check/cross symbol
     * in the "Qualified" column next to each program row.
     */
    protected function writeQualifications(Fpdi $pdf, array $qualifications): void
    {
        // Y positions for each program row in the qualification table on the template.
        // These coordinates must match the actual template layout.
        // Adjust if template positions differ after visual inspection.
        $programRows = [
            'BSME'         => 200.0,
            'BSPSYCH'      => 204.5,
            'BSECE'        => 209.0,
            'BSED-ENGLISH' => 213.5,
            'BSBA-HRM'     => 218.0,
            'BSED-MATH'    => 222.5,
            'BSBA-MM'      => 227.0,
            'DIT'          => 231.5,
            'BSIT'         => 236.0,
            'BSOA'         => 240.5,
            'DOMT'         => 245.0,
        ];

        // X position for the qualification mark column
        $xMark = 175.0;

        foreach ($programRows as $code => $y) {
            $isQualified = $qualifications[$code] ?? false;

            if ($isQualified) {
                // Green check mark
                $pdf->SetTextColor(0, 128, 0);
                $pdf->SetFont('ZapfDingbats', '', 10);
                $pdf->SetXY($xMark, $y);
                $pdf->Write(0, chr(52)); // ✓ in ZapfDingbats
            } else {
                // Red X mark
                $pdf->SetTextColor(200, 0, 0);
                $pdf->SetFont('ZapfDingbats', '', 10);
                $pdf->SetXY($xMark, $y);
                $pdf->Write(0, chr(56)); // ✗ in ZapfDingbats
            }
        }

        // Reset color
        $pdf->SetTextColor(0, 0, 0);
    }
}
