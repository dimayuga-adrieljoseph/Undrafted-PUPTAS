<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\TestPasser;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SisUploadExportService
{
    /**
     * Template filenames stored in storage/app/sis-templates/
     */
    private const PASSERS_TEMPLATE = 'TAGUIG_PUPCET-Passers_for-SIS-Uploading_07.17.2026.xlsx';
    private const RECON_TEMPLATE   = 'TAGUIG_PUPCET-Recon_for-SIS-Uploading_07.17.2026.xlsx';

    /**
     * The sheet name that contains the data table in both templates.
     */
    private const DATA_SHEET = 'PUP TAGUIG';

    /**
     * First row where applicant data is written (below the header row 5).
     */
    private const DATA_START_ROW = 6;

    /**
     * Generate and stream the Passers SIS Upload XLSX.
     *
     * Passers = applicants who have completed the interviewer stage
     * (stage=interviewer, status=completed, action IN (passed, accepted)).
     * Their name / reference_number come from the linked TestPasser record.
     */
    public function generatePassers(?string $schoolYear = null): StreamedResponse
    {
        $rows = $this->fetchPassers($schoolYear);
        return $this->buildResponse(self::PASSERS_TEMPLATE, $rows);
    }

    /**
     * Generate and stream the Recon SIS Upload XLSX.
     *
     * Recon = TestPassers with passer_status_id = 5 (On Probation / Waiver applicants).
     */
    public function generateRecon(?string $schoolYear = null): StreamedResponse
    {
        $rows = $this->fetchRecon($schoolYear);
        return $this->buildResponse(self::RECON_TEMPLATE, $rows);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Query accepted-interview applicants and return rows for the spreadsheet.
     *
     * @return array<int, array{reference_number: string, last_name: string, first_name: string, middle_name: string}>
     */
    private function fetchPassers(?string $schoolYear): array
    {
        // Fetch applications that have a completed interviewer process
        $query = Application::with(['user.testPasser', 'user'])
            ->whereHas('processes', function ($q) {
                $q->where('stage', 'interviewer')
                  ->where('status', 'completed')
                  ->whereIn('action', ['passed', 'accepted']);
            })
            ->whereHas('user'); // must have a linked profile

        // Filter by school year via the linked TestPasser record
        if ($schoolYear) {
            $query->whereHas('user.testPasser', function ($q) use ($schoolYear) {
                $q->where('school_year', $schoolYear);
            });
        }

        $applications = $query->get();

        $rows = [];
        foreach ($applications as $app) {
            $testPasser = $app->user?->testPasser;
            if (!$testPasser) {
                continue;
            }
            $rows[] = [
                'reference_number' => $testPasser->reference_number ?? '',
                'last_name'        => $testPasser->surname     ?? '',
                'first_name'       => $testPasser->first_name  ?? '',
                'middle_name'      => $testPasser->middle_name ?? '',
            ];
        }

        return $rows;
    }

    /**
     * Query on-probation test passers and return rows for the spreadsheet.
     *
     * @return array<int, array{reference_number: string, last_name: string, first_name: string, middle_name: string}>
     */
    private function fetchRecon(?string $schoolYear): array
    {
        $query = TestPasser::where('passer_status_id', 5); // On Probation

        if ($schoolYear) {
            $query->where('school_year', $schoolYear);
        }

        $passers = $query->orderBy('surname')->get();

        $rows = [];
        foreach ($passers as $passer) {
            $rows[] = [
                'reference_number' => $passer->reference_number ?? '',
                'last_name'        => $passer->surname     ?? '',
                'first_name'       => $passer->first_name  ?? '',
                'middle_name'      => $passer->middle_name ?? '',
            ];
        }

        return $rows;
    }

    /**
     * Load the given template, fill in rows, and return a streamed XLSX response.
     *
     * The template already has:
     *  - Col A: sequential row numbers (pre-filled)
     *  - Col F: "TAGUIG"           (pre-filled)
     *
     * We write:
     *  - Col B: iApply Ref. No.
     *  - Col C: Last Name
     *  - Col D: First Name
     *  - Col E: Middle Name
     *
     * @param string $templateFilename  basename of the template in storage/app/sis-templates/
     * @param array  $rows              list of row data arrays
     */
    private function buildResponse(string $templateFilename, array $rows): StreamedResponse
    {
        $templatePath = storage_path('app/sis-templates/' . $templateFilename);

        $spreadsheet = IOFactory::load($templatePath);
        $sheet       = $spreadsheet->getSheetByName(self::DATA_SHEET);

        // Clear existing data cells in columns B–E from DATA_START_ROW downward
        // so stale template placeholder data is removed before we write.
        $highestRow = $sheet->getHighestDataRow();
        for ($r = self::DATA_START_ROW; $r <= $highestRow; $r++) {
            $sheet->getCell('B' . $r)->setValue(null);
            $sheet->getCell('C' . $r)->setValue(null);
            $sheet->getCell('D' . $r)->setValue(null);
            $sheet->getCell('E' . $r)->setValue(null);
        }

        // Write new data rows
        foreach ($rows as $index => $row) {
            $rowNum = self::DATA_START_ROW + $index;
            $sheet->getCell('B' . $rowNum)->setValue($this->sanitize($row['reference_number']));
            $sheet->getCell('C' . $rowNum)->setValue($this->sanitize($row['last_name']));
            $sheet->getCell('D' . $rowNum)->setValue($this->sanitize($row['first_name']));
            $sheet->getCell('E' . $rowNum)->setValue($this->sanitize($row['middle_name']));
        }

        $writer   = IOFactory::createWriter($spreadsheet, 'Xlsx');
        
        // Use the current date for the downloaded filename instead of the template's hardcoded date
        $filename = str_replace('07.17.2026', now()->format('m.d.Y'), $templateFilename);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control'       => 'max-age=0',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Prevent formula injection by prefixing suspicious values.
     */
    private function sanitize(mixed $value): string
    {
        $str = (string) $value;
        if ($str !== '' && in_array($str[0], ['=', '+', '-', '@'], true)) {
            return "'" . $str;
        }
        return $str;
    }
}
