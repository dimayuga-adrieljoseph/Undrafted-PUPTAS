<?php

namespace App\Services;

use setasign\Fpdi\Tcpdf\Fpdi;
use Illuminate\Support\Collection;

class LogbookService
{
    public function generate(Collection $entries, int $step): string
    {
        $config = config('logbook_fields.steps.' . $step);

        $filename = $config['template'];

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
            throw new \Exception("Logbook template PDF not found at docs/ or storage/app/templates/: {$filename}");
        }

        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);

        // Use the template's actual dimensions — this PDF is 355.6×215.9mm (wider than A4)
        $pdf->setSourceFile($templatePath);
        $templateId = $pdf->importPage($config['page']);
        $size = $pdf->getTemplateSize($templateId);

        // Add page using exact template dimensions so nothing is cropped
        $pdf->AddPage('L', [$size['width'], $size['height']]);
        $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height']);
        $this->writeFooter($pdf);

        $pdf->SetFont(
            config('logbook_fields.font'),
            '',
            config('logbook_fields.font_size')
        );

        // We assume a maximum of 10 rows per page (adjust if the template has more/less drawn rows)
        $maxRowsPerPage = 10;

        foreach ($entries->values() as $index => $entry) {
            // Add a new page with the template if we exceed the max rows for the current page
            if ($index > 0 && $index % $maxRowsPerPage === 0) {
                $pdf->AddPage('L', [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height']);
                $this->writeFooter($pdf);
                $pdf->SetFont(
                    config('logbook_fields.font'),
                    '',
                    config('logbook_fields.font_size')
                );
            }

            // Calculate Y coordinate based on the row's position on the *current* page
            $rowIndex = $index % $maxRowsPerPage;
            $y = $config['row_start_y'] + ($rowIndex * $config['row_height']);
            
            $this->writeRow($pdf, $entry, $y, $config['columns']);
        }

        return $pdf->Output('', 'S');
    }

    private function writeFooter($pdf): void
    {
        $pageWidth = $pdf->GetPageWidth();
        $pageHeight = $pdf->GetPageHeight();
        
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetXY(0, $pageHeight - 10);
        $pdf->Cell($pageWidth, 10, 'This is a system generated report.', 0, 0, 'C');
        $pdf->SetTextColor(0, 0, 0); // reset
    }

    private function writeRow($pdf, $entry, float $y, array $columns): void
    {
        foreach ($columns as $field => $coords) {
            $value = $entry[$field] ?? '';

            $pdf->SetXY($coords['x'], $y);
            $align = $coords['align'] ?? 'L';
            $height = $coords['height'] ?? 5;
            $pdf->Cell($coords['width'], $height, (string) $value, 0, 0, $align);
        }
    }
}
