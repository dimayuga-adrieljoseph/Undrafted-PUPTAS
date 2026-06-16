<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ApplicationProcess;
use App\Models\Program;
use App\Services\LogbookService;

use App\Models\AuditLog;
use App\Services\AuditLogService;

class AdmissionLogbookController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }
    /**
     * Build an entry array from an ApplicationProcess model.
     * Application::user() returns ApplicantProfile directly.
     */
    private function buildEntry(ApplicationProcess $e, int $step): array
    {
        // FIX 2: Use abs() and cast to int to avoid negative floats
        $minutes = '';
        if ($e->started_at && $e->updated_at) {
            $diff = (int) abs($e->updated_at->diffInMinutes($e->started_at));
            $minutes = (string) $diff; // Print even if 0
        } elseif ($e->created_at && $e->updated_at) {
            $diff = (int) abs($e->updated_at->diffInMinutes($e->created_at));
            $minutes = (string) $diff; // Print even if 0
        }

        // Application::user() returns ApplicantProfile (not User)
        $profile = $e->application->user ?? null;
        
        $programCode = '';
        if ($step === 1 || $step === 2) {
            $programCode = $profile && $profile->firstChoiceProgram ? $profile->firstChoiceProgram->code : '';
        } else {
            $programCode = $e->application->program ? $e->application->program->code : '';
        }

        $fullName = '';
        if ($profile) {
            $fullName = trim(
                ($profile->firstname  ?? '') . ' ' .
                ($profile->middlename ?? '') . ' ' .
                ($profile->lastname   ?? '')
            );
        }

        return [
            'requested_at'      => $e->started_at
                ? $e->started_at->format('m/d/Y h:i A')
                : ($e->created_at ? $e->created_at->format('m/d/Y h:i A') : ''),
            'client_name'       => $fullName,
            'program'           => $programCode,
            'sex'               => $profile ? ($profile->sex ?? '') : '',
            'email'             => $profile ? ($profile->email ?? '') : '',
            'concern'           => $this->getConcernText($step),
            'processed_at'      => $e->updated_at ? $e->updated_at->format('m/d/Y h:i A') : '',
            'minutes_processed' => $minutes,
            'claimed_at'        => today()->format('m/d/Y'), // Always show current date for date claimed/signature
        ];
    }

    private function getStage(int $step): string
    {
        return match ($step) {
            1 => 'document_evaluator',
            2 => 'grade_evaluator',
            3 => 'interviewer',
            default => 'document_evaluator',
        };
    }

    private function getConcernText(int $step): string
    {
        return match ($step) {
            1 => 'CHECKING OF COMPLETENESS AND AUTHENTICITY OF DOCUMENTS',
            2 => 'GRADE COMPUTATION AND VERIFICATION',
            3 => 'INTERVIEW AND SUBMISSION OF ENTRANCE CREDENTIALS',
            default => 'ADMISSION PROCESS',
        };
    }

    private function fetchEntriesQuery(string $stage, string $date)
    {
        // Application::user() -> ApplicantProfile (no further nesting needed)
        // Include firstChoiceProgram for step 1 & 2 program mapping
        return ApplicationProcess::with(['application.user.firstChoiceProgram', 'application.program'])
            ->where('stage', $stage)
            ->where('status', 'completed')
            ->whereIn('action', ['passed', 'accepted', 'course_changed'])
            ->whereDate('updated_at', $date)
            ->orderBy('updated_at', 'asc');
    }

    public function index(Request $request)
    {
        $step = (int) $request->input('step', 1);
        $date = $request->input('date', today()->toDateString());

        // Use pagination for the UI to prevent slow loading
        $paginator = $this->fetchEntriesQuery($this->getStage($step), $date)
            ->paginate(10)
            ->through(fn ($e) => $this->buildEntry($e, $step));

        $programs = Program::orderBy('name')->get();

        return Inertia::render('Reports/Logbook', [
            'entries'     => $paginator,
            'currentStep' => $step,
            'currentDate' => $date,
            'programs'    => $programs,
        ]);
    }

    public function exportPdf(Request $request)
    {
        $step = (int) $request->input('step', 1);
        $date = $request->input('date', today()->toDateString());

        // PDF Export needs all entries for the given date, not paginated
        $entries = $this->fetchEntriesQuery($this->getStage($step), $date)
            ->get()
            ->map(fn ($e) => $this->buildEntry($e, $step));

        $pdf = app(LogbookService::class)->generate($entries, $step);

        $user = auth()->user();
        if ($user) {
            $this->auditLogService->logActivity(
                AuditLog::ACTION_DOWNLOAD,
                'Reports',
                "Exported Admission Logbook PDF for step {$step} on {$date}",
                $user,
                AuditLog::CATEGORY_SYSTEM_OPERATION
            );
        }

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="logbook-step-' . $step . '-' . $date . '.pdf"',
        ]);
    }
}
