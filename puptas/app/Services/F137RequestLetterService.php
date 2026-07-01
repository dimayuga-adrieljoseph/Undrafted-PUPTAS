<?php

namespace App\Services;

use setasign\Fpdi\Tcpdf\Fpdi;
use App\Models\User;

/**
 * F137 Request Letter Service — FPDI Overlay on Template
 *
 * Overlays applicant data onto the official "F137-2026-TEMPLATE (1).pdf"
 * using FPDI/TCPDF.
 *
 * Page size: A4 portrait — 210.02 × 297.01 mm
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * COORDINATE SYSTEM
 * ─────────────────────────────────────────────────────────────────────────────
 * Origin (0,0) = top-left corner of the page.
 * X increases rightward, Y increases downward.  Units: millimetres.
 *
 * To re-calibrate:
 *   php artisan f137:measure-coordinates
 * Opens storage/app/private/f137_debug/f137_rulers.pdf and f137_probes.pdf.
 * Adjust the constants in overlayPage() until the probes land on the
 * placeholder text.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * PLACEHOLDER MAP
 * ─────────────────────────────────────────────────────────────────────────────
 *   <Date>                          → Right-aligned date line near top of body
 *   <NAME OF PRINCIPAL/REGISTRAR>   → Addressee name block, line 1
 *   <SCHOOL NAME>                   → Addressee name block, line 2
 *   <SCHOOL ADDRESS>                → Addressee name block, line 3
 *   Dear Mr./Ms. <Surname>:         → Salutation line
 *   <FIRST NAME> <MIDDLE NAME>      → Inline in first body paragraph, line 2
 *   <SURNAME>                       → Inline in first body paragraph, line 2
 *                                     (same line, just before <FIRST NAME>
 *                                      based on template order: SURNAME, FIRSTNAME, MI)
 */
class F137RequestLetterService
{
    protected string $templatePath;

    public function __construct()
    {
        $this->templatePath = base_path('docs/F137-2026-TEMPLATE (1).pdf');
    }

    /**
     * Generate the F137 Request Letter PDF for the given user.
     *
     * @param  User  $user  The authenticated applicant
     * @return string       Raw PDF binary
     * @throws \Exception
     */
    public function generate(User $user): string
    {
        if (!file_exists($this->templatePath)) {
            throw new \Exception('F137 Request Letter template not found at: ' . $this->templatePath);
        }

        $data = $this->buildData($user);
        return $this->renderPdf($data);
    }

    /**
     * Build the data array for populating the letter.
     *
     * @throws \Exception If required former school fields are missing
     */
    protected function buildData(User $user): array
    {
        $profile = $user->applicantProfile;

        $formerSchoolName    = trim($profile?->school ?? '');
        $formerSchoolAddress = trim($profile?->former_school_address ?? '');
        $principalName       = trim($profile?->former_school_principal ?? '');

        if ($formerSchoolName === '') {
            throw new \Exception('Former School Name is required to generate the F137 Request Letter.');
        }
        if ($formerSchoolAddress === '') {
            throw new \Exception('Former School Address is required to generate the F137 Request Letter.');
        }

        // Applicant name — use profile values with fallback to user object
        $firstName  = strtoupper(trim($profile?->firstname  ?? $user->firstname  ?? ''));
        $middleName = strtoupper(trim($profile?->middlename ?? $user->middlename ?? ''));
        $lastName   = strtoupper(trim($profile?->lastname   ?? $user->lastname   ?? ''));

        // Current Philippine date (Asia/Manila) — never stored in the database
        $manila     = new \DateTimeZone('Asia/Manila');
        $now        = new \DateTime('now', $manila);
        $dateString = $now->format('F j, Y'); // e.g. "July 30, 2026"

        // Principal/Registrar conditional logic
        if ($principalName !== '') {
            // Extract surname: last word of the full name
            $nameParts       = preg_split('/\s+/', $principalName);
            $principalSurname = ucfirst(strtolower(end($nameParts)));
            $principalLine   = $principalName;
            $salutationLine  = ' Mr./Ms. ' . $principalSurname . ':';
        } else {
            $principalLine  = 'THE PRINCIPAL/REGISTRAR';
            $salutationLine = ' Sir/Madam:';
        }

        return [
            'date'            => $dateString,
            'principal_line'  => strtoupper($principalLine),
            'salutation_line' => $salutationLine,
            'school_name'     => strtoupper($formerSchoolName),
            'school_address'  => strtoupper($formerSchoolAddress),
            'first_name'      => $firstName,
            'middle_name'     => $middleName,
            'surname'         => $lastName,
        ];
    }

    /**
     * Render the PDF by overlaying data onto the template.
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

            if ($pageNo === 1) {
                $this->overlayPage($pdf, $data);
            }
        }

        return $pdf->Output('', 'S');
    }

    /**
     * Overlay applicant data onto the template page.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * COORDINATE REFERENCE (A4 portrait, 210.02 × 297.01 mm)
     * ─────────────────────────────────────────────────────────────────────────
     *
     * Measured from the ruler grid (f137_rulers.pdf) and cross-checked against
     * the actual template layout visible in the screenshot.
     *
     * Layout of the letter body (top → bottom):
     *
     *   ~57 mm   Date line (right-side, approximately centred-right)
     *   ~70 mm   <NAME OF PRINCIPAL/REGISTRAR>  (left margin x≈25)
     *   ~76 mm   <SCHOOL NAME>                  (left margin x≈25)
     *   ~82 mm   <SCHOOL ADDRESS>               (left margin x≈25)
     *   ~91 mm   Dear Mr./Ms. <Surname>:        (left margin x≈25)
     *
     *   Body paragraph 1 — "May I respectfully request for a copy of
     *   F137/Transcript of Records of <FIRST NAME> <MIDDLE NAME> <SURNAME>…"
     *   The names appear on the second line of this paragraph, starting at
     *   approximately y≈113 mm.  The template has them as:
     *     <FIRST NAME>  ~x=52  y=113
     *     <MIDDLE NAME> ~x=85  y=113
     *     <SURNAME>     ~x=118 y=113   (based on template: FIRST MIDDLE SURNAME)
     *
     *   NOTE: The template order shown in the screenshot is
     *   "<FIRST NAME> <MIDDLE NAME> <SURNAME>" on one line.
     *   We overlay each segment at its approximate x position on that line.
     *
     * To fine-tune: run `php artisan f137:measure-coordinates` and check
     * f137_probes.pdf at 100% zoom.
     */
    protected function overlayPage(Fpdi $pdf, array $data): void
    {
        $pdf->SetTextColor(0, 0, 0);

        // ── Date ────────────────────────────────────────────────────────────
        $this->writeText($pdf, 160.0, 46, 77.0, 6.0, $data['date'], 12, 'L');

        // ── Addressee block ─────────────────────────────────────────────────
        $this->writeText($pdf, 24.7, 51, 165.0, 6.0, $data['principal_line'],  12, 'L', true);
        $this->writeText($pdf, 24.7, 56, 165.0, 6.0, $data['school_name'],     12, 'L');
        $this->writeText($pdf, 24.7, 61, 165.0, 6.0, $data['school_address'],  12, 'L');

        // ── Salutation ───────────────────────────────────────────────────────
        $this->writeText($pdf, 35.0, 71, 165.0, 6.0, $data['salutation_line'], 12, 'L');

        // ── Applicant name (inline in body paragraph) ────────────────────────
        $fullName = trim(
            $data['first_name']
            . ($data['middle_name'] ? ' ' . $data['middle_name'] : '')
            . ' ' . $data['surname']
        );

        $this->writeText($pdf, 50.0, 96, 100.0, 5, $fullName, 12, 'L', true);
    }

    /**
     * Write text at exact coordinates.
     * Auto-shrinks font if the string is too wide for the cell.
     */
    protected function writeText(
        Fpdi   $pdf,
        float  $x,
        float  $y,
        float  $w,
        float  $h,
        string $text,
        float  $fontSize = 12,
        string $align    = 'L',
        bool   $bold     = false
    ): void {
        if ($text === '') {
            return;
        }

        $style = $bold ? 'B' : '';
        $pdf->SetFont('freeserif', $style, $fontSize);

        while ($fontSize > 7.5 && $pdf->GetStringWidth($text) > $w) {
            $fontSize -= 0.5;
            $pdf->SetFont('freeserif', $style, $fontSize);
        }

        $pdf->SetXY($x, $y);
        $pdf->Cell($w, $h, $text, 0, 0, $align);
    }
}
