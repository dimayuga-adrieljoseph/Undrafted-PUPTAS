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
                ? $e->started_at->timezone('Asia/Manila')->format('m/d/Y h:i A')
                : ($e->created_at ? $e->created_at->timezone('Asia/Manila')->format('m/d/Y h:i A') : ''),
            'client_name'       => $fullName,
            'program'           => $programCode,
            'sex'               => $profile ? ($profile->sex ?? '') : '',
            'email'             => $profile ? ($profile->email ?? '') : '',
            'concern'           => $this->getConcernText($step),
            'processed_at'      => $e->updated_at ? $e->updated_at->timezone('Asia/Manila')->format('m/d/Y h:i A') : '',
            'minutes_processed' => $minutes,
            'claimed_at'        => today()->timezone('Asia/Manila')->format('m/d/Y'), // Always show current date for date claimed/signature
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

    public function getEntriesPaginated(int $step, string $date)
    {
        return $this->fetchEntriesQuery($this->getStage($step), $date)
            ->paginate(10)
            ->through(fn ($e) => $this->buildEntry($e, $step));
    }

    public function index(Request $request)
    {
        $step = (int) $request->input('step', 1);
        $date = $request->input('date', today()->toDateString());

        $paginator = $this->getEntriesPaginated($step, $date);

        // axios fetch from unified reports page — return JSON
        if ($request->wantsJson()) {
            return response()->json($paginator);
        }

        $programs = Program::orderBy('name')->get();

        return Inertia::render('Reports/Logbook', [
            'entries'     => $paginator,
            'currentStep' => $step,
            'currentDate' => $date,
            'programs'    => $programs,
        ]);
    }

    /**
     * GET /admin/logbook/service-time-kpi
     *
     * Returns average processing time (minutes) per step for a given date,
     * with a pass/fail verdict against a 30-minute target.
     */
    public function serviceTimeKpi(Request $request): \Illuminate\Http\JsonResponse
    {
        // Manual baselines from client-provided logsheet transcription (S.Y. with 503 processed students).
        // These are the pre-PUPTAS averages measured from the physical admission logbook.
        // Source: Admission Office logbook sample — document-checking avg: 16.26 min,
        //         grade-computation avg: 5.50 min, interview avg: 26.90 min.
        // The system target is set to the manual baseline so we can measure improvement.
        $steps = [
            1 => [
                'label'           => 'Step 1 — Document Check',
                'stage'           => 'document_evaluator',
                'target'          => 16.26, // manual baseline from client logsheet
                'manual_baseline' => 16.26,
                'baseline_note'   => 'Client logsheet avg. (manual process)',
            ],
            2 => [
                'label'           => 'Step 2 — Grade Computation',
                'stage'           => 'grade_evaluator',
                'target'          => 5.50,  // manual baseline from client logsheet
                'manual_baseline' => 5.50,
                'baseline_note'   => 'Client logsheet avg. (manual process)',
            ],
            3 => [
                'label'           => 'Step 3 — Interview',
                'stage'           => 'interviewer',
                'target'          => 26.90, // manual baseline from client logsheet
                'manual_baseline' => 26.90,
                'baseline_note'   => 'Client logsheet avg. (manual process)',
            ],
        ];

        $results = [];

        foreach ($steps as $stepNum => $info) {
            // All-time: no date filter — count total distinct applications that completed this stage
            $totalCount = ApplicationProcess::where('stage', $info['stage'])
                ->where('status', 'completed')
                ->whereIn('action', ['passed', 'accepted', 'course_changed'])
                ->count();

            $records = ApplicationProcess::where('stage', $info['stage'])
                ->where('status', 'completed')
                ->whereIn('action', ['passed', 'accepted', 'course_changed'])
                ->get(['started_at', 'created_at', 'updated_at']);

            $minutes = $records->map(function ($e) {
                $from = $e->started_at ?? $e->created_at;
                if (!$from || !$e->updated_at) return null;
                $diff = (int) abs($e->updated_at->diffInMinutes($from));
                // Exclude zero-duration records (seeded/batch-inserted rows where timestamps are identical)
                // and records over 540 minutes (9 working hours) — these indicate an interviewer/evaluator
                // who forgot to tag an applicant and completed the step the next day or later.
                // Such records measure inaction, not processing speed, and would distort the average.
                return ($diff > 0 && $diff <= 540) ? $diff : null;
            })->filter(fn ($v) => $v !== null);

            $timingCount = $minutes->count(); // records with measurable duration
            $avg = $timingCount > 0 ? round($minutes->avg(), 1) : null;

            $results[] = [
                'step'            => $stepNum,
                'label'           => $info['label'],
                'count'           => $totalCount,              // total who completed this stage
                'timing_count'    => $timingCount,             // subset with measurable duration
                'avg_min'         => $avg,
                'target'          => $info['target'],          // step-specific manual baseline target
                'manual_baseline' => $info['manual_baseline'], // pre-PUPTAS manual process average
                'baseline_note'   => $info['baseline_note'],
                'met'             => $avg !== null ? ($avg <= $info['target']) : null,
            ];
        }

        return response()->json([
            'steps' => $results,
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
