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
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\WaiverImport;

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
        // Show all test passers who are On Probation (status=5) OR have is_waivered=true on their application
        $query = TestPasser::with(['user.currentApplication.program', 'passerStatus'])
            ->where(function($q) {
                $q->where('passer_status_id', 5)
                  ->orWhereHas('user.currentApplication', function($q2) {
                      $q2->where('is_waivered', true);
                  });
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
     * Preview the uploaded Excel file and match applicants.
     */
    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,txt|max:5120',
        ]);

        try {
            $data = Excel::toArray(new WaiverImport, $request->file('file'));
            $sheet = $data[0] ?? [];
            
            $matched = [];
            $unmatched = [];

            foreach ($sheet as $row) {
                // Ensure we have a reference number
                if (empty($row['reference_number'])) continue;

                $refNo = trim($row['reference_number']);
                $testPasser = TestPasser::with('user.currentApplication')
                    ->where('reference_number', $refNo)
                    ->first();

                $sheetData = [
                    'rank' => $row['rank'] ?? null,
                    'reference_number' => $refNo,
                    'name' => $row['name'] ?? null,
                    'email' => $row['email'] ?? null,
                    'strand' => $row['strand'] ?? null,
                    'score' => $row['score'] ?? null,
                    'status' => $row['status'] ?? null,
                    'system_status' => $row['system_status'] ?? null,
                    'program_offering' => $row['program_offering'] ?? null,
                ];

                if ($testPasser) {
                    $application = $testPasser->user?->currentApplication;
                    $sheetData['is_already_tagged'] = $application ? (bool) $application->is_waivered : false;
                    $sheetData['test_passer_id'] = $testPasser->test_passer_id;
                    $sheetData['system_name'] = $testPasser->surname . ', ' . $testPasser->first_name;
                    $matched[] = $sheetData;
                } else {
                    $unmatched[] = $sheetData;
                }
            }

            return response()->json([
                'matched' => $matched,
                'unmatched' => $unmatched,
            ]);
        } catch (\Exception $e) {
            Log::error('Waiver Excel Preview Error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to process file: ' . $e->getMessage()], 422);
        }
    }

    /**
     * Confirm the import and tag the matched applicants.
     */
    public function importConfirm(Request $request)
    {
        $request->validate([
            'applicants' => 'required|array',
            'applicants.*.test_passer_id' => 'required|exists:test_passers,test_passer_id',
        ]);

        // Cast test_passer_id to int to prevent type mismatch (frontend sends JSON strings)
        $applicantsData = collect($request->input('applicants'))->map(function ($item) {
            $item['test_passer_id'] = (int) $item['test_passer_id'];
            return $item;
        });
        $testPasserIds = $applicantsData->pluck('test_passer_id')->toArray();

        $testPassers = TestPasser::with('user.currentApplication')
            ->whereIn('test_passer_id', $testPasserIds)
            ->get();

        $taggedCount = 0;
        $updatedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($testPassers as $testPasser) {
                // Match by int comparison
                $sheetData = $applicantsData->first(function ($item) use ($testPasser) {
                    return (int) $item['test_passer_id'] === (int) $testPasser->test_passer_id;
                });

                if (!$sheetData) continue;

                $application = $testPasser->user?->currentApplication;

                // Update sheet columns
                $testPasser->waiver_list_status = $sheetData['status'] ?? null;
                $testPasser->waiver_program_offering = $sheetData['program_offering'] ?? null;
                $testPasser->pupcet_total_score = isset($sheetData['score']) ? floatval($sheetData['score']) : $testPasser->pupcet_total_score;

                $wasTagged = false;

                // Tag test passer as On Probation if not already
                if ((int) $testPasser->passer_status_id !== 5) {
                    $testPasser->previous_passer_status_id = $testPasser->passer_status_id;
                    $testPasser->passer_status_id = 5; // On Probation
                    $wasTagged = true;
                }

                // Tag application as waivered if it exists
                if ($application && !$application->is_waivered) {
                    $application->is_waivered = true;
                    $application->save();
                    $wasTagged = true;
                }

                if ($wasTagged) {
                    $taggedCount++;
                }

                $testPasser->save();
                $updatedCount++;
            }

            $this->auditLogService->logActivity(
                AuditLog::ACTION_UPDATE,
                'Waiver Management',
                "Bulk imported waiver list: Tagged $taggedCount new applicants, updated $updatedCount total."
            );

            DB::commit();

            return redirect()->back()->with('success', "Successfully imported $updatedCount applicants ($taggedCount newly tagged).");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Waiver Excel Import Confirm Error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['message' => 'Failed to import applicants: ' . $e->getMessage()]);
        }
    }

    /**
     * Export the waiver applicants list to PDF.
     */
    public function exportPdf(Request $request)
    {
        $query = TestPasser::with(['user.currentApplication.program', 'passerStatus'])
            ->where(function($q) {
                $q->where('passer_status_id', 5)
                  ->orWhereHas('user.currentApplication', function($q2) {
                      $q2->where('is_waivered', true);
                  });
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
            ->where(function($q) {
                $q->where('passer_status_id', 5)
                  ->orWhereHas('user.currentApplication', function($q2) {
                      $q2->where('is_waivered', true);
                  });
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
            fputcsv($file, ['Reference Number', 'Name', 'Email', 'Strand', 'Score', 'Status', 'System Status', 'Program Offering', 'Tagged Date']);

            foreach ($taggedApplicants as $applicant) {
                fputcsv($file, [
                    $applicant->reference_number,
                    $applicant->surname . ', ' . $applicant->first_name,
                    $applicant->email ?? $applicant->user?->email ?? 'N/A',
                    $applicant->strand ?? 'N/A',
                    $applicant->pupcet_total_score ?? 'N/A',
                    $applicant->waiver_list_status ?? 'N/A',
                    ucwords(str_replace('_', ' ', $applicant->passerStatus?->status ?? 'N/A')),
                    $applicant->waiver_program_offering ?? 'N/A',
                    $applicant->updated_at->format('M d, Y h:i A')
                ]);
            }
            fputcsv($file, []);
            fputcsv($file, ['*** This is a system generated report ***']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
