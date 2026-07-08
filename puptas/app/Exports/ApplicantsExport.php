<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Builder;
use App\Services\ApplicationStatusService;

class ApplicantsExport implements FromQuery, WithMapping, WithHeadings
{
    protected $query;
    protected ApplicationStatusService $statusService;
    protected $reportType;

    public function __construct(Builder $query, ApplicationStatusService $statusService, $reportType = null)
    {
        $this->query = $query;
        $this->statusService = $statusService;
        $this->reportType = $reportType;
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        $headings = [
            'Reference Number',
            'Name',
            'Program',
            'Status',
        ];
        
        if ($this->reportType === 'pulled_out') {
            $headings[] = 'Pull-Out Notes';
        }
        
        $headings[] = 'Date';
        
        return $headings;
    }

    public function map($app): array
    {
        $interviewerProcess = $app->processes->where('stage', 'interviewer')->first();
        $hasMedicalOrRecords = $app->processes->whereIn('stage', ['medical', 'records'])->isNotEmpty();
        $isPulledOut = $interviewerProcess
            && $interviewerProcess->status === 'in_progress'
            && $interviewerProcess->action === null
            && !$hasMedicalOrRecords
            && ($interviewerProcess->decision_reason !== null || $interviewerProcess->reviewer_notes !== null);
            
        $data = [
            $this->sanitizeExcelValue($app->user->testPasser->reference_number ?? 'N/A'),
            $this->sanitizeExcelValue(trim(($app->user->firstname ?? '') . ' ' . ($app->user->lastname ?? ''))),
            $this->sanitizeExcelValue($app->program->code ?? 'N/A'),
            $this->sanitizeExcelValue($isPulledOut ? 'Pulled Out' : $this->statusService->determineStatus($app))
        ];
        
        if ($this->reportType === 'pulled_out') {
            $data[] = $this->sanitizeExcelValue($isPulledOut ? ($interviewerProcess->decision_reason ?? $interviewerProcess->reviewer_notes ?? '—') : '—');
        }
        
        $data[] = $this->sanitizeExcelValue($app->updated_at->format('Y-m-d'));
        
        return $data;
    }

    private function sanitizeExcelValue($value)
    {
        if (is_string($value) && in_array(substr($value, 0, 1), ['=', '+', '-', '@'])) {
            return "'" . $value;
        }
        return $value;
    }
}
