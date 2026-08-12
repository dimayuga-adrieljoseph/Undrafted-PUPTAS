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
            "Added score range override {$scoreFrom}–{$scoreTo} to bypass registration cutoff until {$expiresAt}.",
            null,
            AuditLog::CATEGORY_ADMISSION_DATA,
            null,
            ['score_from' => $scoreFrom, 'score_to' => $scoreTo, 'expires_at' => $expiresAt]
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

        // Capture old state before mutation
        $existing = collect($this->cutoffService->getAllowedRegistrationScores())
            ->firstWhere('id', $id);

        $updated = $this->cutoffService->updateAllowedRegistrationScore($id, $scoreFrom, $scoreTo, $expiresAt);

        if (!$updated) {
            return redirect()->back()->with('error', 'Score range entry not found.');
        }

        $oldValues = $existing
            ? ['score_from' => $existing['score_from'], 'score_to' => $existing['score_to'], 'expires_at' => $existing['expires_at']]
            : null;

        $this->auditLogService->logActivity(
            AuditLog::ACTION_UPDATE,
            'Score Overrides',
            "Updated score range override to {$scoreFrom}–{$scoreTo} until {$expiresAt}.",
            null,
            AuditLog::CATEGORY_ADMISSION_DATA,
            $oldValues,
            ['score_from' => $scoreFrom, 'score_to' => $scoreTo, 'expires_at' => $expiresAt]
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

        // Capture the entry before it's deleted so the log is human-readable
        $existing = collect($this->cutoffService->getAllowedRegistrationScores())
            ->firstWhere('id', $id);

        $this->cutoffService->removeAllowedRegistrationScore($id);

        $rangeLabel = $existing
            ? "{$existing['score_from']}–{$existing['score_to']}"
            : "ID: {$id}";

        $this->auditLogService->logActivity(
            AuditLog::ACTION_DELETE,
            'Score Overrides',
            "Removed score range override {$rangeLabel} from allowed registration overrides.",
            null,
            AuditLog::CATEGORY_ADMISSION_DATA,
            $existing ? ['score_from' => $existing['score_from'], 'score_to' => $existing['score_to'], 'expires_at' => $existing['expires_at']] : null,
            null
        );

        return redirect()->back()->with('success', "Score range override {$rangeLabel} has been removed.");
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

        $emailList  = implode(', ', array_column($entries, 'email'));
        $count      = count($entries);
        $newValues  = array_map(fn($e) => [
            'email'      => strtolower(trim($e['email'])),
            'name'       => isset($e['name']) ? trim($e['name']) : null,
            'expires_at' => $expiresAt,
        ], $entries);

        $this->auditLogService->logActivity(
            AuditLog::ACTION_CREATE,
            'Registration Overrides',
            "Added {$count} email override(s) to bypass registration cutoff until {$expiresAt}: {$emailList}",
            null,
            AuditLog::CATEGORY_ADMISSION_DATA,
            null,
            $newValues
        );

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

        // Capture old state before mutation
        $existing = collect($this->cutoffService->getAllowedRegistrationEmails())
            ->firstWhere('email', $email);

        $updated = $this->cutoffService->updateAllowedRegistrationEmail($email, $expiresAt);

        if (!$updated) {
            return redirect()->back()->with('error', 'Email override entry not found.');
        }

        $this->auditLogService->logActivity(
            AuditLog::ACTION_UPDATE,
            'Registration Overrides',
            "Updated email override for {$email} — new expiry: {$expiresAt}.",
            null,
            AuditLog::CATEGORY_ADMISSION_DATA,
            $existing ? ['email' => $existing['email'], 'expires_at' => $existing['expires_at']] : null,
            ['email' => $email, 'expires_at' => $expiresAt]
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

        // Capture old state before deletion
        $existing = collect($this->cutoffService->getAllowedRegistrationEmails())
            ->firstWhere('email', $email);

        $this->cutoffService->removeAllowedRegistrationEmail($email);

        $this->auditLogService->logActivity(
            AuditLog::ACTION_DELETE,
            'Registration Overrides',
            "Removed email override for {$email} from allowed registration overrides.",
            null,
            AuditLog::CATEGORY_ADMISSION_DATA,
            $existing ? ['email' => $existing['email'], 'name' => $existing['name'] ?? null, 'expires_at' => $existing['expires_at']] : null,
            null
        );

        return redirect()->back()->with('success', "Email {$email} has been removed from allowed registration.");
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
