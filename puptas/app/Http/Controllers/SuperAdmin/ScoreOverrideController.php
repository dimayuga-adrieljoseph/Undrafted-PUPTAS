<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TestPasser;
use App\Services\CutoffSettingsService;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScoreOverrideController extends Controller
{
    public function __construct(
        private CutoffSettingsService $cutoffService,
        private AuditLogService $auditLogService
    ) {}

    /**
     * Display the Score Overrides page.
     */
    public function index(): Response
    {
        return Inertia::render('SuperAdmin/ScoreOverrides', [
            'allowed_scores' => $this->cutoffService->getAllowedRegistrationScores(),
            'cutoff_active'  => $this->cutoffService->isCutoffPassed(),
        ]);
    }

    /**
     * Search for TestPassers matching a specific pupcet_total_score.
     */
    public function search(Request $request)
    {
        $request->validate([
            'score' => 'required|numeric',
        ]);

        $score = (float) $request->input('score');

        $applicants = TestPasser::where('pupcet_total_score', $score)
            ->with(['passerStatus'])
            ->get(['test_passer_id', 'reference_number', 'first_name', 'surname', 'middle_name', 'status', 'passer_status_id', 'pupcet_total_score']);

        return response()->json([
            'applicants' => $applicants
        ]);
    }

    /**
     * Add a score to the allowed list.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'score' => 'required|numeric',
            'expires_at' => 'required|date|after_or_equal:now',
        ]);

        $score = (float) $request->input('score');
        $expiresAt = $request->input('expires_at');
        
        $this->cutoffService->addAllowedRegistrationScore($score, $expiresAt);

        $this->auditLogService->logActivity(
            AuditLog::ACTION_CREATE,
            'Score Overrides',
            "Allowed score $score to bypass registration cutoff until $expiresAt."
        );

        return redirect()->back()->with('success', "Score $score has been allowed for registration until $expiresAt.");
    }

    /**
     * Remove a score from the allowed list.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'score' => 'required|numeric',
        ]);

        $score = (float) $request->input('score');
        $this->cutoffService->removeAllowedRegistrationScore($score);

        $this->auditLogService->logActivity(
            AuditLog::ACTION_DELETE,
            'Score Overrides',
            "Removed score $score from allowed registration overrides."
        );

        return redirect()->back()->with('success', "Score $score has been removed from allowed registration.");
    }
}
