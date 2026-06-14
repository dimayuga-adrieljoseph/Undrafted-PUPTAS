<?php

namespace App\Services;

use setasign\Fpdi\Tcpdf\Fpdi;
use Illuminate\Support\Collection;

class ControlListService
{
    public function generate(
        Collection $entries,
        string $programCode,
        string $academicYear
    ): string {
        $config = config('control_list_fields');
        $filename = 'CONTROL-LIST-INTERVIEW-AND-SUBMISSION-OF-ENTRANCE-CREDENTIALS.pdf';
        
        $paths = [
            base_path('docs/' . $filename),
            storage_path('app/templates/' . $filename),
        ];

        $templatePath = null;
        foreach ($paths as $path) {
            if (file_exists($path)) {
                $templatePath = $path;
                break;
            }
        }

        if (!$templatePath) {
            throw new \Exception("Control list template PDF not found at docs/ or storage/app/templates/: {$filename}");
        }

        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);

        // Cap at 50 entries max
        $entries = $entries->take($config['max_rows']);
        $chunks  = $entries->chunk($config['rows_per_page']);

        if ($chunks->isEmpty()) {
            // If empty, generate at least one page so it doesn't crash
            $chunks->push(collect([]));
        }

        foreach ($chunks as $chunk) {
            $pdf->AddPage('L', [$size['width'] ?? 355.6, $size['height'] ?? 215.9]);
            $pdf->setSourceFile($templatePath);

            // All data pages use page 1 of the template
            $templateId = $pdf->importPage($config['data_page']);
            $size = $pdf->getTemplateSize($templateId);
            $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height']);

            // Overlay dynamic title: e.g. "BSBA-HRM 1-1"
            $this->writeTitle($pdf, $programCode . ' 1-1', $config);

            // Overlay academic year: e.g. "A.Y. 2026-2027"
            $this->writeAcademicYear($pdf, 'A.Y. ' . $academicYear, $config);

            // Write data rows
            $pdf->SetFont($config['font'], '', $config['font_size']);
            foreach ($chunk->values() as $index => $entry) {
                // If row_start_y or row_height are not set, don't crash
                if ($config['row_start_y'] === null || $config['row_height'] === null) {
                    continue;
                }
                
                $y = $config['row_start_y'] + ($index * $config['row_height']);
                $this->writeRow($pdf, $entry, $y, $config['columns']);
            }
        }

        // Always append the signature page last
        $pdf->AddPage('L', [$size['width'] ?? 355.6, $size['height'] ?? 215.9]);
        $pdf->setSourceFile($templatePath);
        $signatureId = $pdf->importPage($config['signature_page']);
        $size = $pdf->getTemplateSize($signatureId);
        $pdf->useTemplate($signatureId, 0, 0, $size['width'], $size['height']);

        // Overlay program title and academic year on signature page too
        $this->writeTitle($pdf, $programCode . ' 1-1', $config);
        $this->writeAcademicYear($pdf, 'A.Y. ' . $academicYear, $config);

        // "Prepared by" left blank — written manually
        // "Verified by" and "Noted by" are pre-printed on template

        return $pdf->Output('', 'S');
    }

    private function writeTitle($pdf, string $title, array $config): void
    {
        $t = $config['title'];
        if ($t['x'] === null || $t['y'] === null) return;
        
        $pageWidth = $pdf->GetPageWidth();

        // Hide baked-in template text using a white rectangle (center 150mm area by default or use mask config)
        $pdf->SetFillColor(255, 255, 255);
        if (isset($t['mask'])) {
            $pdf->Rect($t['mask']['x'], $t['mask']['y'], $t['mask']['width'], $t['mask']['height'], 'F');
        } else {
            $pdf->Rect(($pageWidth - 150) / 2, $t['y'] - 2, 150, 12, 'F');
        }

        $pdf->SetFont($t['font'], $t['font_style'], $t['font_size']);
        
        if (isset($t['color']) && is_array($t['color'])) {
            $pdf->SetTextColor($t['color'][0], $t['color'][1], $t['color'][2]);
        } else {
            $pdf->SetTextColor(192, 0, 0); // fallback #c00000
        }

        $pdf->SetXY($t['x'] ?: 0, $t['y']);
        $width = isset($t['width']) && $t['width'] > 0 ? $t['width'] : $pageWidth;
        $align = $t['align'] ?? 'C';
        
        $pdf->Cell($width, 10, $title, 0, 0, $align);
        $pdf->SetTextColor(0, 0, 0); // reset to black
    }

    private function writeAcademicYear($pdf, string $year, array $config): void
    {
        $a = $config['academic_year'];
        if ($a['x'] === null || $a['y'] === null) return;
        
        $pageWidth = $pdf->GetPageWidth();

        // Hide baked-in template text using a white rectangle
        $pdf->SetFillColor(255, 255, 255);
        if (isset($a['mask'])) {
            $pdf->Rect($a['mask']['x'], $a['mask']['y'], $a['mask']['width'], $a['mask']['height'], 'F');
        } else {
            $pdf->Rect(($pageWidth - 100) / 2, $a['y'] - 1, 100, 10, 'F');
        }

        $pdf->SetFont($a['font'], $a['font_style'], $a['font_size']);
        
        if (isset($a['color']) && is_array($a['color'])) {
            $pdf->SetTextColor($a['color'][0], $a['color'][1], $a['color'][2]);
        } else {
            $pdf->SetTextColor(192, 0, 0); // fallback #c00000
        }

        $pdf->SetXY($a['x'] ?: 0, $a['y']);
        $width = isset($a['width']) && $a['width'] > 0 ? $a['width'] : $pageWidth;
        $align = $a['align'] ?? 'C';
        
        $pdf->Cell($width, 8, $year, 0, 0, $align);
        $pdf->SetTextColor(0, 0, 0); // reset to black
    }

    private function writeRow($pdf, array $entry, float $y, array $columns): void
    {
        $pdf->SetFont(config('control_list_fields.font'), '', config('control_list_fields.font_size'));
        foreach ($columns as $field => $coords) {
            if ($coords['x'] === null || $coords['width'] === null) continue;
            
            $pdf->SetXY($coords['x'], $y);
            $align = $coords['align'] ?? 'L';
            $height = $coords['height'] ?? 5;
            $pdf->Cell($coords['width'], $height, $entry[$field] ?? '', 0, 0, $align);
        }
    }
}
