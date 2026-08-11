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
     * Search for TestPassers whose pupcet_total_score falls in a given range.
     */
    public function search(Request $request)
    {
        $request->validate([
            'score_from' => 'required|numeric|min:1|max:150',
            'score_to'   => 'required|numeric|min:1|max:150|gte:score_from',
        ]);

        $from = (float) $request->input('score_from');
        $to   = (float) $request->input('score_to');

        $applicants = TestPasser::whereBetween('pupcet_total_score', [$from, $to])
            ->with(['passerStatus'])
            ->get(['test_passer_id', 'reference_number', 'first_name', 'surname', 'middle_name', 'status', 'passer_status_id', 'pupcet_total_score']);

        return response()->json([
            'applicants' => $applicants
        ]);
    }

    /**
     * Add a new score range override.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'score_from' => 'required|numeric|min:1|max:150',
            'score_to'   => 'required|numeric|min:1|max:150|gte:score_from',
            'expires_at' => 'required|date|after_or_equal:now',
        ]);

        $scoreFrom = (float) $request->input('score_from');
        $scoreTo   = (float) $request->input('score_to');
        $expiresAt = $request->input('expires_at');

        $this->cutoffService->addAllowedRegistrationScore($scoreFrom, $scoreTo, $expiresAt);

        $this->auditLogService->logActivity(
            AuditLog::ACTION_CREATE,
            'Score Overrides',
            "Allowed scores {$scoreFrom}–{$scoreTo} to bypass registration cutoff until {$expiresAt}."
        );

        return redirect()->back()->with('success', "Scores {$scoreFrom}–{$scoreTo} have been allowed for registration until {$expiresAt}.");
    }

    /**
     * Update an existing score range override.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'id'         => 'required|string',
            'score_from' => 'required|numeric|min:1|max:150',
            'score_to'   => 'required|numeric|min:1|max:150|gte:score_from',
            'expires_at' => 'required|date|after_or_equal:now',
        ]);

        $id        = $request->input('id');
        $scoreFrom = (float) $request->input('score_from');
        $scoreTo   = (float) $request->input('score_to');
        $expiresAt = $request->input('expires_at');

        $updated = $this->cutoffService->updateAllowedRegistrationScore($id, $scoreFrom, $scoreTo, $expiresAt);

        if (!$updated) {
            return redirect()->back()->with('error', 'Score range entry not found.');
        }

        $this->auditLogService->logActivity(
            AuditLog::ACTION_UPDATE,
            'Score Overrides',
            "Updated score range override (ID: {$id}) to {$scoreFrom}–{$scoreTo} until {$expiresAt}."
        );

        return redirect()->back()->with('success', "Score range {$scoreFrom}–{$scoreTo} has been updated.");
    }

    /**
     * Remove a score range override by ID.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'id' => 'required|string',
        ]);

        $id = $request->input('id');
        $this->cutoffService->removeAllowedRegistrationScore($id);

        $this->auditLogService->logActivity(
            AuditLog::ACTION_DELETE,
            'Score Overrides',
            "Removed score range override (ID: {$id}) from allowed registration overrides."
        );

        return redirect()->back()->with('success', 'Score range override has been removed.');
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
     * Add one or more emails to the allowed list.
     */
    public function storeEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'emails'          => 'required|array|min:1',
            'emails.*.email'  => 'required|email',
            'emails.*.name'   => 'nullable|string|max:255',
            'expires_at'      => 'required|date|after_or_equal:now',
        ]);

        $entries   = $request->input('emails');
        $expiresAt = $request->input('expires_at');

        foreach ($entries as $entry) {
            $email = strtolower(trim($entry['email']));
            $name  = isset($entry['name']) ? trim($entry['name']) : null;
            $this->cutoffService->addAllowedRegistrationEmail($email, $name, $expiresAt);
        }

        $emailList = implode(', ', array_column($entries, 'email'));
        $this->auditLogService->logActivity(
            AuditLog::ACTION_CREATE,
            'Registration Overrides',
            "Allowed emails to bypass registration cutoff until $expiresAt: $emailList"
        );

        $count = count($entries);
        return redirect()->back()->with('success', "{$count} email(s) have been allowed for registration until {$expiresAt}.");
    }

    /**
     * Update the expiry date of an existing email override.
     */
    public function updateEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'email'      => 'required|email',
            'expires_at' => 'required|date|after_or_equal:now',
        ]);

        $email     = strtolower(trim($request->input('email')));
        $expiresAt = $request->input('expires_at');

        $updated = $this->cutoffService->updateAllowedRegistrationEmail($email, $expiresAt);

        if (!$updated) {
            return redirect()->back()->with('error', 'Email override entry not found.');
        }

        $this->auditLogService->logActivity(
            AuditLog::ACTION_UPDATE,
            'Registration Overrides',
            "Updated email override for {$email} — new expiry: {$expiresAt}."
        );

        return redirect()->back()->with('success', "Email override for {$email} has been updated.");
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

    /**
     * Fetch all applicants with on_probation status.
     */
    public function getProbationApplicants()
    {
        $applicants = TestPasser::whereHas('passerStatus', function ($query) {
            $query->where('status', 'on_probation');
        })
        ->with(['passerStatus'])
        ->get(['test_passer_id', 'reference_number', 'email', 'first_name', 'surname', 'middle_name', 'status', 'passer_status_id']);

        return response()->json([
            'applicants' => $applicants
        ]);
    }
}
