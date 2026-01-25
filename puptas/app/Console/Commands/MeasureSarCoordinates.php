<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use setasign\Fpdi\Tcpdf\Fpdi;
use App\Services\SarFormService;

/**
 * Measure SAR Form Coordinates
 * 
 * Generates a calibration PDF with grid overlay and reference points
 * to help determine exact field coordinates for config/sar_fields.php
 */
class MeasureSarCoordinates extends Command
{
    protected $signature = 'sar:measure
                            {--generate : Generate calibration PDF with grid}
                            {--test : Generate test SAR with sample data}';

    protected $description = 'Measure and calibrate SAR form field coordinates';

    public function handle()
    {
        if ($this->option('generate')) {
            return $this->generateCalibrationPdf();
        }
        
        if ($this->option('test')) {
            return $this->generateTestSar();
        }
        
        $this->showUsage();
    }

    /**
     * Generate calibration PDF with measurement grid
     */
    protected function generateCalibrationPdf()
    {
        $this->info('Generating calibration PDF...');
        
        $templatePath = storage_path('app/templates/SAR-FORM_TEMPLATE-2.pdf');
        
        if (!file_exists($templatePath)) {
            $this->error("Template not found: {$templatePath}");
            return 1;
        }
        
        $pdf = new Fpdi();
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        
        // Process each page
        $pageCount = $pdf->setSourceFile($templatePath);
        
        for ($pageNo = 1; $pageNo <= min($pageCount, 8); $pageNo++) {
            $pdf->AddPage();
            $templateId = $pdf->importPage($pageNo);
            $pdf->useTemplate($templateId, 0, 0);
            
            // Draw measurement grid
            $this->drawMeasurementGrid($pdf, $pageNo);
        }
        
        // Save calibration PDF
        $outputPath = storage_path('app/sar_debug/calibration_grid.pdf');
        $outputDir = dirname($outputPath);
        
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        file_put_contents($outputPath, $pdf->Output('S'));
        
        $this->info("✓ Calibration PDF generated: {$outputPath}");
        $this->line('');
        $this->line('Open this PDF to measure coordinates:');
        $this->line('- Grid shows 10mm intervals');
        $this->line('- Numbers on edges show mm from top-left corner');
        $this->line('- Use PDF ruler tool to measure field positions');
        
        return 0;
    }

    /**
     * Draw measurement grid on page
     */
    protected function drawMeasurementGrid(Fpdi $pdf, int $pageNo)
    {
        $size = $pdf->getTemplateSize($pdf->importPage($pageNo));
        $width = $size['width'];
        $height = $size['height'];
        
        // Set grid color (light gray)
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetFont('helvetica', '', 6);
        
        // Draw vertical lines every 10mm with labels
        for ($x = 0; $x <= $width; $x += 10) {
            // Lighter line every 10mm
            $pdf->SetLineWidth(0.1);
            $pdf->Line($x, 0, $x, $height);
            
            // Label every 20mm
            if ($x % 20 === 0 && $x > 0) {
                $pdf->Text($x - 3, 3, (string)$x);
            }
        }
        
        // Draw horizontal lines every 10mm with labels
        for ($y = 0; $y <= $height; $y += 10) {
            $pdf->SetLineWidth(0.1);
            $pdf->Line(0, $y, $width, $y);
            
            // Label every 20mm
            if ($y % 20 === 0 && $y > 0) {
                $pdf->Text(1, $y + 1, (string)$y);
            }
        }
        
        // Draw origin marker
        $pdf->SetDrawColor(255, 0, 0);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(0, 0, 5, 0);
        $pdf->Line(0, 0, 0, 5);
        
        // Page number in corner
        $pdf->SetTextColor(0, 0, 255);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Text($width - 15, 5, "Page {$pageNo}");
    }

    /**
     * Generate test SAR with sample data
     */
    protected function generateTestSar()
    {
        $this->info('Generating test SAR PDF...');
        
        $service = new SarFormService();
        $service->setDebugMode(true);
        
        $testData = [
            'reference_number' => '2026-TEST-001',
            'full_name' => 'DELA CRUZ, JUAN PABLO',
            'graduation_year' => '2026',
            'school_attended' => 'MANILA HIGH SCHOOL',
            'shs_strand' => 'STEM',
            'enrollment_date' => '2026-01-24',
            'enrollment_time' => '10:30',
            'student_number' => '2026-00012',
            'admission_status' => 'Admitted',
        ];
        
        try {
            $result = $service->generateSarPdf($testData);
            
            if (!$result['success']) {
                $this->error("✗ Generation failed: {$result['error']}");
                return 1;
            }
            
            $this->info("✓ Test SAR generated: storage/app/{$result['pdf_path']}");
            
            // Check if debug file was created
            $debugPath = storage_path('app/sar_debug');
            if (is_dir($debugPath)) {
                $debugFiles = glob($debugPath . '/debug_overlay_*.pdf');
                if (!empty($debugFiles)) {
                    $latestDebug = array_pop($debugFiles);
                    $this->info("✓ Debug overlay: {$latestDebug}");
                }
            }
            
            $this->line('');
            $this->line('Compare the debug overlay with template to verify coordinates.');
            $this->line('Red boxes show where fields are being rendered.');
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to generate test SAR: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Show usage instructions
     */
    protected function showUsage()
    {
        $this->line('SAR Coordinate Measurement Tool');
        $this->line('');
        $this->line('Usage:');
        $this->line('  php artisan sar:measure --generate  Generate calibration grid PDF');
        $this->line('  php artisan sar:measure --test      Generate test SAR with debug overlay');
        $this->line('');
        $this->line('Workflow:');
        $this->line('  1. Generate calibration grid: php artisan sar:measure --generate');
        $this->line('  2. Open storage/app/sar_debug/calibration_grid.pdf');
        $this->line('  3. Use PDF ruler to measure field positions (x, y, width, height)');
        $this->line('  4. Update config/sar_fields.php with measured values');
        $this->line('  5. Test with: php artisan sar:measure --test');
        $this->line('  6. Compare debug overlay with template to verify alignment');
    }
}
