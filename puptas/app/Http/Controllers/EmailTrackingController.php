<?php

namespace App\Http\Controllers;

use App\Jobs\SendCongratulationsEmail;
use App\Jobs\SendPasserEmail;
use App\Jobs\SendSarFormEmail;
use App\Jobs\SendWaitlistedEmail;
use App\Models\BulkEmailOperation;
use App\Models\EmailLog;
use App\Models\TestPasser;
use App\Services\EmailTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailTrackingController extends Controller
{
    public function __construct(
        private readonly EmailTrackingService $emailTrackingService,
    ) {}

    /**
     * Display paginated list of bulk email operations.
     */
    public function index(): Response
    {
        $operations = BulkEmailOperation::orderBy('started_at', 'desc')->paginate(20);

        return Inertia::render('EmailTracking/Index', [
            'operations' => $operations,
        ]);
    }

    /**
     * Display a single bulk operation with its paginated email logs.
     */
    public function show(int $id): Response
    {
        $operation = BulkEmailOperation::findOrFail($id);

        $query = $operation->emailLogs();

        // Apply status filter if provided and valid
        $status = request('status');
        if ($status && in_array($status, ['sent', 'failed', 'pending'])) {
            $query->where('status', $status);
        }

        // Apply search filter if provided and at least 2 characters
        $search = request('search');
        if ($search && mb_strlen($search) >= 2) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(recipient_email) LIKE ?', ['%' . mb_strtolower($search) . '%'])
                  ->orWhereRaw('LOWER(recipient_name) LIKE ?', ['%' . mb_strtolower($search) . '%']);
            });
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return Inertia::render('EmailTracking/Show', [
            'operation' => $operation,
            'logs' => $logs,
        ]);
    }

    /**
     * Return JSON progress data for a bulk operation (polling endpoint).
     */
    public function progress(int $id): JsonResponse
    {
        return response()->json(
            $this->emailTrackingService->getBulkOperationProgress($id)
        );
    }

    /**
     * Retry selected failed email logs.
     */
    public function retrySelected(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email_log_ids' => 'required|array',
            'email_log_ids.*' => 'integer|exists:email_logs,id',
        ]);

        $retriedCount = $this->emailTrackingService->retryFailedEmails($validated['email_log_ids']);

        // Re-dispatch jobs for the retried logs (now status = 'pending' after retry)
        $retriedLogs = EmailLog::whereIn('id', $validated['email_log_ids'])
            ->where('status', 'pending')
            ->get();

        foreach ($retriedLogs as $log) {
            $this->dispatchRetryJob($log);
        }

        return response()->json([
            'retried_count' => $retriedCount,
        ]);
    }

    /**
     * Retry all failed email logs for a bulk operation.
     */
    public function retryAll(int $id): JsonResponse
    {
        $retriedCount = $this->emailTrackingService->retryAllFailed($id);

        // Re-dispatch jobs for all retried logs
        $retriedLogs = EmailLog::where('bulk_operation_id', $id)
            ->where('status', 'pending')
            ->where('retry_count', '>', 0)
            ->get();

        foreach ($retriedLogs as $log) {
            $this->dispatchRetryJob($log);
        }

        return response()->json([
            'retried_count' => $retriedCount,
        ]);
    }

    /**
     * Dispatch the appropriate job for a retried email log based on its email_type.
     */
    private function dispatchRetryJob(EmailLog $log): void
    {
        $bulkOperationId = $log->bulk_operation_id;

        switch ($log->email_type) {
            case 'congratulations':
                SendCongratulationsEmail::dispatch(
                    $log->recipient_email,
                    $log->id,
                    $bulkOperationId,
                );
                break;

            case 'pupcet_result':
                if ($log->recipient_id) {
                    $passer = TestPasser::find($log->recipient_id);
                    if ($passer) {
                        SendPasserEmail::dispatch(
                            $passer,
                            $log->email_content ?? '',
                            $log->id,
                            $bulkOperationId,
                        );
                    }
                }
                break;

            case 'sar_form':
                if ($log->recipient_id) {
                    $passer = TestPasser::find($log->recipient_id);
                    if ($passer && $log->email_content) {
                        // email_content stores the download URL for SAR emails
                        // We need a sarGenerationId - look it up from the passer's latest SAR generation
                        $sarGeneration = \App\Models\SarGeneration::where('test_passer_id', $passer->test_passer_id)
                            ->latest()
                            ->first();

                        if ($sarGeneration) {
                            SendSarFormEmail::dispatch(
                                $passer,
                                $log->email_content,
                                $sarGeneration->id,
                                $log->id,
                                $bulkOperationId,
                            );
                        }
                    }
                }
                break;

            case 'waitlisted':
                if ($log->recipient_id) {
                    $passer = TestPasser::find($log->recipient_id);
                    if ($passer) {
                        SendWaitlistedEmail::dispatch(
                            $passer,
                            $log->email_content ?? '',
                            $log->id,
                            $bulkOperationId,
                        );
                    }
                }
                break;
        }
    }
}
