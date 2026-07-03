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
            'allowed_emails' => $this->cutoffService->getAllowedRegistrationEmails(),
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

    /**
     * Search for TestPassers matching a specific email.
     */
    public function searchEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
        ]);

        $searchTerm = trim($request->input('email'));

        $applicants = TestPasser::where('email', 'LIKE', '%' . $searchTerm . '%')
            ->orWhere('first_name', 'LIKE', '%' . $searchTerm . '%')
            ->orWhere('surname', 'LIKE', '%' . $searchTerm . '%')
            ->with(['passerStatus'])
            ->limit(50)
            ->get(['test_passer_id', 'reference_number', 'email', 'first_name', 'surname', 'middle_name', 'status', 'passer_status_id']);

        return response()->json([
            'applicants' => $applicants
        ]);
    }

    /**
     * Add an email to the allowed list.
     */
    public function storeEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'emails' => 'required|array|min:1',
            'emails.*' => 'required|email',
            'expires_at' => 'required|date|after_or_equal:now',
        ]);

        $emails = array_map(function ($email) {
            return strtolower(trim($email));
        }, $request->input('emails'));
        
        $expiresAt = $request->input('expires_at');
        
        foreach ($emails as $email) {
            $this->cutoffService->addAllowedRegistrationEmail($email, $expiresAt);
        }

        $emailList = implode(', ', $emails);
        $this->auditLogService->logActivity(
            AuditLog::ACTION_CREATE,
            'Registration Overrides',
            "Allowed emails to bypass registration cutoff until $expiresAt: $emailList"
        );

        return redirect()->back()->with('success', count($emails) . " email(s) have been allowed for registration until $expiresAt.");
    }

    /**
     * Remove an email from the allowed list.
     */
    public function destroyEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($request->input('email')));
        $this->cutoffService->removeAllowedRegistrationEmail($email);

        $this->auditLogService->logActivity(
            AuditLog::ACTION_DELETE,
            'Registration Overrides',
            "Removed email $email from allowed registration overrides."
        );

        return redirect()->back()->with('success', "Email $email has been removed from allowed registration.");
    }
}
