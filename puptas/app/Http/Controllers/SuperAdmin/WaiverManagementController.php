<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TestPasser;
use App\Models\Application;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class WaiverManagementController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    /**
     * Display the Waiver Management page.
     */
    public function index(Request $request): Response
    {
        $query = TestPasser::with(['user.currentApplication.program', 'passerStatus'])
            ->whereHas('user.currentApplication', function($q) {
                $q->where('is_waivered', true);
            })
            ->orderBy('updated_at', 'desc');

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('surname', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        $taggedApplicants = $query->paginate(15)->withQueryString();

        return Inertia::render('SuperAdmin/WaiverManagement', [
            'tagged_applicants' => $taggedApplicants,
            'filters' => $request->only('search')
        ]);
    }

    /**
     * Search for eligible applicants to tag (Waitlisted, Unqualified, etc).
     */
    public function searchEligible(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2',
        ]);

        $search = $request->input('query');

        // Relevant statuses: applicant must have an active (non-accepted/enrolled) application
        $relevantStatuses = ['pending', 'submitted', 'under_review', 'returned', 'waitlisted', 'not_qualified'];

        $applicants = TestPasser::with(['user.currentApplication.program', 'passerStatus'])
            ->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('surname', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%");
            })
            // Must not be already waivered
            ->whereDoesntHave('user.currentApplication', function($q) {
                $q->where('is_waivered', true);
            })
            // Only applicants whose application is in a relevant status
            ->whereHas('user.currentApplication', function($q) use ($relevantStatuses) {
                $q->whereIn('status', $relevantStatuses);
            })
            ->limit(20)
            ->get();

        return response()->json([
            'applicants' => $applicants
        ]);
    }

    /**
     * Tag an applicant as Waivered / On Probation.
     */
    public function tag(Request $request): RedirectResponse
    {
        $request->validate([
            'test_passer_id' => 'required|exists:test_passers,test_passer_id',
        ]);

        $testPasser = TestPasser::with('user.currentApplication')->findOrFail($request->test_passer_id);
        $application = $testPasser->user?->currentApplication;

        if (!$application) {
            return redirect()->back()->withErrors(['message' => 'Applicant does not have an active application.']);
        }

        try {
            DB::transaction(function () use ($testPasser, $application) {
                $application->is_waivered = true;
                $application->save();

                // Store previous status so it can be restored on untag
                $testPasser->previous_passer_status_id = $testPasser->passer_status_id;
                $testPasser->passer_status_id = 5; // On Probation
                $testPasser->save();
            });

            $name = $testPasser->first_name . ' ' . $testPasser->surname;
            $this->auditLogService->logActivity(
                AuditLog::ACTION_UPDATE,
                'Waiver Management',
                "Tagged applicant $name (Ref: {$testPasser->reference_number}) as Waiver Applicant (On Probation)."
            );

            return redirect()->back()->with('success', "Applicant $name has been tagged as Waiver Applicant and placed On Probation.");
        } catch (\Throwable $e) {
            Log::error('Failed to tag waiver applicant: ' . $e->getMessage());
            return redirect()->back()->withErrors(['message' => 'Failed to tag applicant. Please try again.']);
        }
    }

    /**
     * Untag an applicant.
     */
    public function untag(Request $request): RedirectResponse
    {
        $request->validate([
            'test_passer_id' => 'required|exists:test_passers,test_passer_id',
            'reason' => 'required|string|max:255',
        ]);

        $testPasser = TestPasser::with('user.currentApplication')->findOrFail($request->test_passer_id);
        $application = $testPasser->user?->currentApplication;

        if (!$application) {
            return redirect()->back()->withErrors(['message' => 'Applicant does not have an active application.']);
        }

        try {
            DB::transaction(function () use ($testPasser, $application) {
                $application->is_waivered = false;
                $application->save();

                // Restore previous status if stored; fallback to 3 (Unqualified)
                $testPasser->passer_status_id = $testPasser->previous_passer_status_id ?? 3;
                $testPasser->previous_passer_status_id = null;
                $testPasser->save();
            });

            $name = $testPasser->first_name . ' ' . $testPasser->surname;
            $reason = $request->input('reason');
            $this->auditLogService->logActivity(
                AuditLog::ACTION_UPDATE,
                'Waiver Management',
                "Untagged applicant $name (Ref: {$testPasser->reference_number}) from Waiver Program. Reason: $reason"
            );

            return redirect()->back()->with('success', "Applicant $name has been removed from the Waiver Program.");
        } catch (\Throwable $e) {
            Log::error('Failed to untag waiver applicant: ' . $e->getMessage());
            return redirect()->back()->withErrors(['message' => 'Failed to untag applicant. Please try again.']);
        }
    }

    /**
     * Export the waiver applicants list to PDF.
     */
    public function exportPdf(Request $request)
    {
        $query = TestPasser::with(['user.currentApplication.program', 'passerStatus'])
            ->whereHas('user.currentApplication', function($q) {
                $q->where('is_waivered', true);
            })
            ->orderBy('updated_at', 'desc');

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('surname', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        $taggedApplicants = $query->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.waiver_report', ['applicants' => $taggedApplicants]);
        $pdf->setPaper('A4', 'landscape');
        
        return $pdf->download('waiver_applicants_report_' . date('Y-m-d') . '.pdf');
    }

    /**
     * Export the waiver applicants list to CSV.
     */
    public function exportCsv(Request $request)
    {
        $query = TestPasser::with(['user.currentApplication.program', 'passerStatus'])
            ->whereHas('user.currentApplication', function($q) {
                $q->where('is_waivered', true);
            })
            ->orderBy('updated_at', 'desc');

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('surname', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        $taggedApplicants = $query->get();

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=waiver_applicants_report_" . date('Y-m-d') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($taggedApplicants) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Reference Number', 'Name', 'Email', 'Program', 'Status', 'Tagged Date']);

            foreach ($taggedApplicants as $applicant) {
                fputcsv($file, [
                    $applicant->reference_number,
                    $applicant->surname . ', ' . $applicant->first_name,
                    $applicant->user?->email,
                    $applicant->user?->currentApplication?->program?->name ?? 'N/A',
                    $applicant->passerStatus?->name ?? 'N/A',
                    $applicant->updated_at->format('Y-m-d h:i A')
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
