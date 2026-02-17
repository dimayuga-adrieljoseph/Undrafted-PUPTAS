<?php

namespace App\Services;

use setasign\Fpdi\Tcpdf\Fpdi;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * SAR Form Service - FPDI Overlay Implementation
 * 
 * Responsibilities:
 * - Overlay student data onto SAR-FORM_TEMPLATE.pdf using FPDI/TCPDF
 * - Insert 2x2 photo and checkbox marks
 * - Generate accurate 8-page PDF matching official template
 * 
 * Rules:
 * - Field names must match template mapping exactly
 * - Required fields: reference_number, full_name, graduation_year
 * - Coordinates are in millimeters (mm) from top-left
 * - Do NOT log PII
 * 
 * Source: docs/SAR-FORM_TEMPLATE.pdf
 */
class SarFormService
{
    protected string $tmpDirectory = '';
    protected string $templatePath;
    protected array $fieldPositions;
    protected bool $debugMode = false;
    protected string $disk = 'sar_tmp';
    
    public function __construct()
    {
        $this->templatePath = storage_path('app/templates/SAR-FORM_TEMPLATE-2.pdf');
        $this->fieldPositions = config('sar_fields', []);
    }
    
    /**
     * Enable debug mode for calibration
     */
    public function setDebugMode(bool $debug): self
    {
        $this->debugMode = $debug;
        return $this;
    }
    
    /**
     * Generate SAR PDF for a single row
     * 
     * @param array $rowData Student data from Excel
     * @return array ['success' => bool, 'pdf_path' => string, 'pdf_url' => string, 'error' => string]
     */
    public function generateSarPdf(array $rowData): array
    {
        try {
            // Validate required fields
            $validated = $this->validateRowData($rowData);
            
            // Generate PDF filename
            $filename = $this->generateSafeFilename($validated['reference_number']);
            
            // Generate PDF with FPDI overlay
            $pdfContent = $this->generatePdfWithOverlay($validated);
            
            // Save to sar_tmp disk (storage/app/tmp)
            $disk = Storage::disk($this->disk);
            $disk->put($filename, $pdfContent);
            
            // Get file size
            $fileSize = $disk->size($filename);
            
            // Generate download URL (public download route)
            $downloadUrl = route('sar.passer-download', [
                'filename' => $filename,
                'reference' => $validated['reference_number']
            ]);
            
            return [
                'success' => true,
                'id' => $rowData['id'] ?? null,
                'pdf_path' => 'tmp/' . $filename,
                'pdf_url' => $downloadUrl,
                'filename' => $filename,
                'size_bytes' => $fileSize,
            ];
            
        } catch (ValidationException $e) {
            return [
                'success' => false,
                'id' => $rowData['id'] ?? null,
                'error' => 'Validation failed: ' . json_encode($e->errors()),
            ];
        } catch (\Exception $e) {
            // Do NOT log PII, only log error type and row ID
            \Log::error('SAR PDF generation failed', [
                'row_id' => $rowData['id'] ?? 'unknown',
                'error_type' => get_class($e),
                'message' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'id' => $rowData['id'] ?? null,
                'error' => 'PDF generation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generate PDF using FPDI overlay on template
     * 
     * This method:
     * 1. Imports each page from SAR-FORM_TEMPLATE.pdf
     * 2. Blanks out placeholder tokens (<<SURNAME, FIRST NAME MIDDLE NAME>>, <<DATE>>, etc.)
     * 3. Overlays actual student data at precise coordinates from config/sar_fields.php
     * 4. Maintains the original 8-page template structure
     * 5. Supports debug mode for visual calibration
     * 
     * To adjust field positions:
     * - Edit config/sar_fields.php
     * - Run with debug=true to see field boxes
     * - Adjust coordinates and regenerate
     */
    protected function generatePdfWithOverlay(array $data): string
    {
        if (!file_exists($this->templatePath)) {
            throw new \Exception('SAR form template PDF not found at: ' . $this->templatePath);
        }

        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        
        // Import all pages from template (should be 8 pages)
        $pageCount = $pdf->setSourceFile($this->templatePath);
        
        // Create debug PDF if in debug mode
        $debugPdf = null;
        if ($this->debugMode) {
            $debugPdf = new Fpdi();
            $debugPdf->SetAutoPageBreak(false);
            $debugPdf->SetPrintHeader(false);
            $debugPdf->SetPrintFooter(false);
            $debugPdf->setSourceFile($this->templatePath);
        }
        
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            // Import page for main PDF
            $tplId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tplId);
            
            // Add page with template dimensions
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height'], true);
            
            // Overlay data for this page
            $this->overlayPageData($pdf, $pageNo, $data);
            
            // Generate debug overlay if enabled
            if ($debugPdf) {
                $debugTplId = $debugPdf->importPage($pageNo);
                $debugSize = $debugPdf->getTemplateSize($debugTplId);
                $debugPdf->AddPage($debugSize['orientation'], [$debugSize['width'], $debugSize['height']]);
                $debugPdf->useTemplate($debugTplId, 0, 0, $debugSize['width'], $debugSize['height'], true);
                $this->drawDebugOverlay($debugPdf, $pageNo, $data);
            }
        }
        
        // Save debug files if in debug mode
        if ($debugPdf && $this->debugMode) {
            $this->saveDebugFiles($debugPdf, $data);
        }
        
        return $pdf->Output('', 'S');
    }
    
    /**
     * Overlay data on specific page using config-driven field positions
     */
    protected function overlayPageData(Fpdi $pdf, int $pageNo, array $data): void
    {
        if (!isset($this->fieldPositions[$pageNo])) {
            return; // No fields defined for this page
        }
        
        $fields = $this->fieldPositions[$pageNo];
        
        foreach ($fields as $fieldName => $fieldConfig) {
            // Skip photo fields (handled separately)
            if ($fieldName === 'photo') {
                $this->insertPhoto($pdf, $fieldConfig, $data);
                continue;
            }
            
            // Skip checkbox fields (handled separately)
            if (str_ends_with($fieldName, '_chk')) {
                $this->drawCheckbox($pdf, $fieldConfig, $fieldName, $data);
                continue;
            }
            
            // Map field name to data key
            $dataKey = $this->getDataKey($fieldName, $pageNo);
            if (!isset($data[$dataKey])) {
                continue; // No data for this field
            }
            
            $value = $data[$dataKey];
            
            // Apply uppercase if configured
            if ($fieldConfig['uppercase'] ?? false) {
                $value = strtoupper($value);
            }
            
            // Render the field
            $this->renderField($pdf, $fieldConfig, $value);
        }
    }
    
    /**
     * Map field name to data array key
     */
    protected function getDataKey(string $fieldName, int $pageNo): string
    {
        // Strip _duplicate suffix for duplicate copy fields on page 1
        if (str_ends_with($fieldName, '_duplicate')) {
            $fieldName = str_replace('_duplicate', '', $fieldName);
        }
        
        // Handle special mappings
        $mappings = [
            'name_of_applicant' => 'full_name',
            'name' => 'full_name',
            'previous_school' => 'school_attended',
            'shs_track' => 'shs_strand',
            'date' => 'enrollment_date',
            'date_of_enrollment' => 'enrollment_date',
            'time' => 'enrollment_time',
        ];
        
        return $mappings[$fieldName] ?? $fieldName;
    }
    
    /**
     * Render a field with auto-fitting and proper alignment
     * NO white background blanking - overlays directly on template
     */
    protected function renderField(Fpdi $pdf, array $config, string $value): void
    {
        // Determine font size with auto-fitting
        $fontSize = $this->fitTextToWidth(
            $pdf,
            $value,
            $config['w'],
            $config['font'],
            $config['font_size'],
            max(6, $config['font_size'] - 4) // min font size
        );
        
        // Set font (bold)
        $pdf->SetFont($config['font'], 'B', $fontSize);
        $pdf->SetTextColor(0, 0, 0);
        
        // Handle multi-line fields (wrap mode)
        if (($config['fit_mode'] ?? 'shrink') === 'wrap') {
            $pdf->SetXY($config['x'], $config['y']);
            $pdf->MultiCell($config['w'], 4.5, $value, 0, $config['align']);
            return;
        }
        
        // Calculate vertical centering
        $textHeight = $fontSize * 0.352777778; // 1pt = 0.352777778mm
        $yOffset = max(0, ($config['h'] - $textHeight) / 2);
        
        // Calculate horizontal position based on alignment
        $xPos = $config['x'];
        if ($config['align'] === 'C') {
            $textWidth = $pdf->GetStringWidth($value);
            $xPos = $config['x'] + ($config['w'] - $textWidth) / 2;
            $xPos = $config['x'] + ($config['w'] - $textWidth) / 2;
        } elseif ($config['align'] === 'R') {
            $textWidth = $pdf->GetStringWidth($value);
            $xPos = $config['x'] + $config['w'] - $textWidth - 0.5;
        }
        
        // Write text
        $pdf->SetXY($xPos, $config['y'] + $yOffset);
        $pdf->Write(0, $value);
    }
    
    /**
     * Fit text to width by adjusting font size
     */
    protected function fitTextToWidth(Fpdi $pdf, string $text, float $maxWidth, string $font, int $maxFontSize, int $minFontSize): int
    {
        $fontSize = $maxFontSize;
        
        while ($fontSize >= $minFontSize) {
            $pdf->SetFont($font, '', $fontSize);
            $textWidth = $pdf->GetStringWidth($text);
            
            if ($textWidth <= $maxWidth) {
                return $fontSize;
            }
            
            $fontSize--;
        }
        
        return $minFontSize;
    }
    
    /**
     * Insert photo into designated box
     */
    protected function insertPhoto(Fpdi $pdf, array $config, array $data): void
    {
        if (!isset($data['photo_path']) || !file_exists($data['photo_path'])) {
            return; // No photo to insert
        }
        
        $photoPath = $data['photo_path'];
        
        // Get image dimensions
        $imageSize = getimagesize($photoPath);
        if (!$imageSize) {
            return;
        }
        
        list($imgWidth, $imgHeight) = $imageSize;
        $imgAspect = $imgWidth / $imgHeight;
        $boxAspect = $config['w'] / $config['h'];
        
        // Calculate scaled dimensions to fit box while preserving aspect ratio
        if ($imgAspect > $boxAspect) {
            // Image is wider - fit to width
            $scaledW = $config['w'];
            $scaledH = $config['w'] / $imgAspect;
        } else {
            // Image is taller - fit to height
            $scaledH = $config['h'];
            $scaledW = $config['h'] * $imgAspect;
        }
        
        // Center in box
        $xCentered = $config['x'] + ($config['w'] - $scaledW) / 2;
        $yCentered = $config['y'] + ($config['h'] - $scaledH) / 2;
        
        // Insert image
        $pdf->Image($photoPath, $xCentered, $yCentered, $scaledW, $scaledH, '', '', '', false, 300, '', false, false, 0, false, false, false);
    }
    
    /**
     * Draw checkbox mark if condition is met
     */
    protected function drawCheckbox(Fpdi $pdf, array $config, string $fieldName, array $data): void
    {
        // Determine if checkbox should be checked
        $checked = false;
        
        if ($fieldName === 'admission_status_chk') {
            $checked = isset($data['admission_status']) && 
                      strtolower($data['admission_status']) === 'admitted';
        }
        
        if (!$checked) {
            return;
        }
        
        // Draw checkmark using ZapfDingbats
        $pdf->SetFont('ZapfDingbats', '', 14);
        $pdf->SetXY($config['x'], $config['y']);
        $pdf->Write(0, '4'); // Checkmark glyph
    }
    
    /**
     * Draw debug overlay with field boxes and labels
     */
    protected function drawDebugOverlay(Fpdi $pdf, int $pageNo, array $data): void
    {
        if (!isset($this->fieldPositions[$pageNo])) {
            return;
        }
        
        $fields = $this->fieldPositions[$pageNo];
        
        // Draw field boxes and labels
        foreach ($fields as $fieldName => $config) {
            // Draw red rectangle outline
            $pdf->SetDrawColor(255, 0, 0);
            $pdf->SetLineWidth(0.3);
            $pdf->Rect($config['x'], $config['y'], $config['w'], $config['h']);
            
            // Draw label
            $pdf->SetFont('helvetica', '', 6);
            $pdf->SetTextColor(0, 0, 255);
            $label = "$fieldName: x={$config['x']} y={$config['y']} w={$config['w']} h={$config['h']}";
            $pdf->SetXY($config['x'], $config['y'] - 2);
            $pdf->Write(0, $label);
            
            // Draw actual value in contrasting color if available
            if ($fieldName !== 'photo' && !str_ends_with($fieldName, '_chk')) {
                $dataKey = $this->getDataKey($fieldName, $pageNo);
                if (isset($data[$dataKey])) {
                    $value = $data[$dataKey];
                    if ($config['uppercase'] ?? false) {
                        $value = strtoupper($value);
                    }
                    
                    $pdf->SetFont($config['font'], 'B', $config['font_size']);
                    $pdf->SetTextColor(255, 0, 0);
                    $pdf->SetXY($config['x'] + 0.5, $config['y'] + 0.5);
                    $pdf->Write(0, $value);
                }
            }
        }
    }
    
    /**
     * Save debug files for calibration
     */
    protected function saveDebugFiles(Fpdi $debugPdf, array $data): void
    {
        $debugDir = storage_path('app/sar_debug');
        
        // Create debug directory if it doesn't exist
        if (!is_dir($debugDir)) {
            mkdir($debugDir, 0755, true);
        }
        
        // Save debug PDF
        $timestamp = now()->format('YmdHis');
        $debugPdfPath = $debugDir . '/debug_overlay_' . $timestamp . '.pdf';
        file_put_contents($debugPdfPath, $debugPdf->Output('', 'S'));
        
        // Save positions JSON
        $positionsPath = $debugDir . '/last_positions.json';
        file_put_contents($positionsPath, json_encode($this->fieldPositions, JSON_PRETTY_PRINT));
        
        \Log::info('SAR Debug files saved', [
            'debug_pdf' => $debugPdfPath,
            'positions_json' => $positionsPath
        ]);
    }
    
    /**
     * Validate row data
     */
    protected function validateRowData(array $data): array
    {
        $validator = Validator::make($data, [
            'reference_number' => 'required|string',
            'full_name' => 'required|string',
            'graduation_year' => 'required|digits:4',
            'school_attended' => 'required|string',
            'shs_strand' => 'required|string',
            'enrollment_date' => 'nullable|string',
            'enrollment_time' => 'nullable|string',
            'student_number' => 'nullable|string',
            'admission_status' => 'nullable|in:Admitted,Pending,Rejected',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        
        // Format dates and times
        $validated['enrollment_date'] = $validated['enrollment_date'] ?? date('Y-m-d');
        $validated['enrollment_time'] = $validated['enrollment_time'] ?? date('H:i');
        
        return $validated;
    }

    /**
     * Generate safe filename with timestamp
     */
    protected function generateSafeFilename(string $referenceNumber): string
    {
        // Sanitize reference number
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $referenceNumber);
        $timestamp = now()->format('YmdHis');
        
        return "SAR_{$safe}_{$timestamp}.pdf";
    }

    /**
     * Generate multiple SAR PDFs from array of rows
     * 
     * @param array $rows Array of student data
     * @return array ['generated' => array, 'failed' => array, 'stats' => array]
     */
    public function generateBatch(array $rows): array
    {
        $generated = [];
        $failed = [];
        
        foreach ($rows as $row) {
            $result = $this->generateSarPdf($row);
            
            if ($result['success']) {
                $generated[] = $result;
            } else {
                $failed[] = $result;
            }
        }
        
        return [
            'generated' => $generated,
            'failed' => $failed,
            'stats' => [
                'total' => count($rows),
                'success' => count($generated),
                'failed' => count($failed),
            ],
        ];
    }

    /**
     * Clean up old temporary PDFs (optional - can be called by scheduled job)
     * 
     * @param int $olderThanHours Delete files older than this many hours
     */
    public function cleanupTempFiles(int $olderThanHours = 24): int
    {
        $deletedCount = 0;
        $disk = Storage::disk($this->disk);
        $files = $disk->files();
        $cutoffTime = now()->subHours($olderThanHours);
        
        foreach ($files as $file) {
            // Only process SAR PDF files
            if (!str_starts_with($file, 'SAR_') || !str_ends_with($file, '.pdf')) {
                continue;
            }
            
            $lastModified = $disk->lastModified($file);
            
            if ($lastModified < $cutoffTime->timestamp) {
                $disk->delete($file);
                $deletedCount++;
            }
        }
        
        \Log::info("SAR temp cleanup: deleted {$deletedCount} files older than {$olderThanHours} hours");
        
        return $deletedCount;
    }
}
