<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Program;
use App\Services\ControlListService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ControlListController extends Controller
{
    protected \App\Services\AuditLogService $auditLogService;

    public function __construct(\App\Services\AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    public function index(Request $request)
    {
        $programs = Program::orderBy('name')->get();
        $applicants = null;

        if ($request->filled('program_id')) {
            $programId = $request->input('program_id');
            
            $applicants = Application::with(['user.grades', 'processes'])
                ->where('program_id', $programId)
                ->whereHas('processes', function ($q) {
                    $q->where('stage', 'interviewer')
                      ->where('status', 'completed')
                      ->whereIn('action', ['passed', 'accepted']);
                })
                ->orderBy('created_at')
                ->paginate(10)
                ->through(fn($app) => [
                    'id'          => $app->id,
                    'full_name'   => strtoupper(
                        trim(($app->user->lastname ?? '') . ', ' .
                        ($app->user->firstname ?? '') . ' ' .
                        ($app->user->middlename ?? ''))
                    ),
                    'strand'      => $app->user->strand ?? '',
                    'gwa'         => $app->user->grades->g12_first_sem ?? '',
                    'math_gwa'    => $app->user->grades->mathematics ?? '',
                    'science_gwa' => $app->user->grades->science ?? '',
                    'english_gwa' => $app->user->grades->english ?? '',
                    'notes'       => explode(' - Notes: ', $app->processes->where('stage', 'interviewer')->where('status', 'completed')->whereIn('action', ['passed', 'accepted'])->last()?->reviewer_notes ?? '')[1] ?? '',
                ])->withQueryString();
        }

        return Inertia::render('Reports/ControlList', [
            'programs'          => $programs,
            'applicants'        => $applicants,
            'selectedProgramId' => $request->input('program_id', ''),
        ]);
    }

    public function export(Request $request)
    {
        $request->validate([
            'program_id'    => 'required|exists:programs,id',
            'academic_year' => 'required|string', // e.g. "2026-2027"
        ]);

        $program = Program::findOrFail($request->program_id);

        // Pull accepted applicants for this program
        // "Accepted" = passed the interview (Step 3 completed)
        $entries = Application::with(['user.grades', 'processes'])
            ->where('program_id', $program->id)
            ->whereHas('processes', function ($q) {
                // Adjust this depending on the exact stage name for Step 3 in DB
                // From LogbookController, step 3 is 'interviewer'
                $q->where('stage', 'interviewer')
                  ->where('status', 'completed')
                  ->whereIn('action', ['passed', 'accepted']);
            })
            ->orderBy('created_at')
            ->get()
            ->map(fn($app) => [
                'full_name'   => strtoupper(
                    trim(($app->user->lastname ?? '') . ', ' .
                    ($app->user->firstname ?? '') . ' ' .
                    ($app->user->middlename ?? ''))
                ),
                'strand'      => $app->user->strand ?? '',
                'gwa'         => $app->user->grades->g12_first_sem ?? '',
                'math_gwa'    => $app->user->grades->mathematics ?? '',
                'science_gwa' => $app->user->grades->science ?? '',
                'english_gwa' => $app->user->grades->english ?? '',
                'notes'       => explode(' - Notes: ', $app->processes->where('stage', 'interviewer')->where('status', 'completed')->whereIn('action', ['passed', 'accepted'])->last()?->reviewer_notes ?? '')[1] ?? '',
            ]);

        $pdf = app(ControlListService::class)->generate(
            $entries,
            $program->code,
            $request->academic_year
        );

        $filename = 'control-list-' . $program->code . '-' . $request->academic_year . '.pdf';

        $user = auth()->user();
        if ($user) {
            $this->auditLogService->logActivity(
                \App\Models\AuditLog::ACTION_DOWNLOAD,
                'Reports',
                "Exported Control List PDF for program: {$program->code}, Academic Year: {$request->academic_year}",
                $user,
                \App\Models\AuditLog::CATEGORY_SYSTEM_OPERATION
            );
        }

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
