<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * SAR Form Service
 * 
 * Responsibilities:
 * - Map Excel columns to template fields
 * - Render Blade view -> PDF
 * - Save PDFs to storage/app/tmp with safe filenames
 * 
 * Rules:
 * - Field names must match template mapping exactly
 * - Required fields: reference_number, full_name, graduation_year
 * - Do NOT log PII
 * 
 * Source: docs/SAR_FORM_TEMPLATE.md
 */
class SarFormService
{
    protected string $tmpDirectory = 'tmp';
    
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
            
            // Prepare template data
            $templateData = $this->prepareTemplateData($validated);
            
            // Generate PDF filename
            $filename = $this->generateSafeFilename($validated['reference_number']);
            
            // Render Blade view to PDF
            $pdf = $this->renderPdf($templateData);
            
            // Save to storage/app/tmp
            $path = $this->savePdf($pdf, $filename);
            
            // Generate download URL (public download route)
            $downloadUrl = route('sar.passer-download', [
                'filename' => basename($path),
                'reference' => $validated['reference_number']
            ]);
            
            return [
                'success' => true,
                'id' => $rowData['id'] ?? null,
                'pdf_path' => $path,
                'pdf_url' => $downloadUrl,
                'filename' => basename($path),
                'size_bytes' => Storage::size($path),
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
            'enrollment_date' => 'nullable|date',
            'enrollment_time' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Prepare data for Blade template
     * Maps to exact placeholders from docs/SAR_FORM_TEMPLATE.md
     */
    protected function prepareTemplateData(array $validated): array
    {
        return [
            // Section 1 & 2: Core student info
            'reference_number' => $validated['reference_number'],
            'full_name' => $validated['full_name'],
            'graduation_year' => $validated['graduation_year'],
            'school_attended' => $validated['school_attended'],
            'shs_strand' => $validated['shs_strand'],
            
            // Editable enrollment details
            'enrollment_date' => $validated['enrollment_date'] ?? '__________',
            'enrollment_time' => $validated['enrollment_time'] ?? '__________',
            
            // Static defaults (can be enhanced later)
            'nstp' => 'CWTS',
            'program_type' => '4 or 5-year Degree',
            'academic_year' => '2025-2026',
            
            // Placeholders for sections not yet filled
            'gwa' => '',
            'interviewer_name' => '',
            'program' => '',
            'section' => '',
        ];
    }

    /**
     * Render Blade view to PDF
     */
    protected function renderPdf(array $data): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('sar.template', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);
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
     * Save PDF to storage/app/tmp
     */
    protected function savePdf(\Barryvdh\DomPDF\PDF $pdf, string $filename): string
    {
        $path = $this->tmpDirectory . '/' . $filename;
        
        // Ensure tmp directory exists
        if (!Storage::exists($this->tmpDirectory)) {
            Storage::makeDirectory($this->tmpDirectory);
        }
        
        // Save PDF content
        Storage::put($path, $pdf->output());
        
        return $path;
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
        $files = Storage::files($this->tmpDirectory);
        $cutoffTime = now()->subHours($olderThanHours);
        
        foreach ($files as $file) {
            $lastModified = Storage::lastModified($file);
            
            if ($lastModified < $cutoffTime->timestamp) {
                Storage::delete($file);
                $deletedCount++;
            }
        }
        
        \Log::info("SAR temp cleanup: deleted {$deletedCount} files older than {$olderThanHours} hours");
        
        return $deletedCount;
    }
}
