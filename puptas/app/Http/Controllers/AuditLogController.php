<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AuditLog;
use App\Models\AiAnalyticsHistory;
use Inertia\Inertia;

/**
 * Controller for managing Audit Logs.
 * Only accessible by Superadmin users.
 */
class AuditLogController extends Controller
{
    /**
     * Display a listing of the audit logs.
     *
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'user_id' => ['nullable'], // changed from integer to nullable string support
            'date' => ['nullable', 'date'],
            'log_type' => ['nullable', 'in:' . implode(',', [
                AuditLog::TYPE_SYSTEM,
                AuditLog::TYPE_AUDIT,
                AuditLog::TYPE_SECURITY,
            ])],
            'search' => ['nullable', 'string'],
        ]);

        $baseQuery = AuditLog::query();
        
        // Apply all filters EXCEPT log_type for the counts
        $filtersForCounts = $filters;
        unset($filtersForCounts['log_type']);
        $this->applyFilters($baseQuery, $filtersForCounts);

        $typeCounts = (clone $baseQuery)
            ->selectRaw('log_type, count(*) as total')
            ->groupBy('log_type')
            ->pluck('total', 'log_type')
            ->toArray();

        $logCounts = [
            'SYSTEM' => $typeCounts[AuditLog::TYPE_SYSTEM] ?? 0,
            'AUDIT' => $typeCounts[AuditLog::TYPE_AUDIT] ?? 0,
            'SECURITY' => $typeCounts[AuditLog::TYPE_SECURITY] ?? 0,
        ];

        // Now apply log_type filter for the actual list
        $logsQuery = clone $baseQuery;
        if (!empty($filters['log_type'])) {
            $logsQuery->where('log_type', $filters['log_type']);
        }

        $logs = $logsQuery
            ->latestFirst()
            ->paginate(25)
            ->withQueryString();

        // Extract users from the logs themselves since User model is gone
        $users = AuditLog::query()
            ->select('user_id as id', 'username as email')
            ->whereNotNull('user_id')
            ->groupBy('user_id', 'username')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'firstname' => $log->email, // fallback
                    'lastname' => '',
                    'email' => $log->email,
                ];
            });

        // Add a "System/API" option for logs without a user_id
        $hasSystemLogs = AuditLog::whereNull('user_id')->exists();
        if ($hasSystemLogs) {
            $users->prepend([
                'id' => 'system',
                'firstname' => 'System/API',
                'lastname' => '',
                'email' => 'system',
            ]);
        }

        return Inertia::render('SuperAdmin/Logs', [
            'logs' => $logs,
            'logCounts' => $logCounts,
            'users' => $users,
            'logTypes' => [
                AuditLog::TYPE_SYSTEM,
                AuditLog::TYPE_AUDIT,
                AuditLog::TYPE_SECURITY,
            ],
            'filters' => [
                'user_id' => $filters['user_id'] ?? null,
                'date' => $filters['date'] ?? null,
                'log_type' => $filters['log_type'] ?? null,
                'search' => $filters['search'] ?? null,
            ]
        ]);
    }

    /**
     * Polling endpoint to check for new logs
     */
    public function checkNew(Request $request)
    {
        $sinceId = (int) $request->query('since_id', 0);
        $filters = $request->validate([
            'user_id' => ['nullable'], // changed from integer
            'date' => ['nullable', 'date'],
            'log_type' => ['nullable', 'in:' . implode(',', [
                AuditLog::TYPE_SYSTEM,
                AuditLog::TYPE_AUDIT,
                AuditLog::TYPE_SECURITY,
            ])],
            'search' => ['nullable', 'string'],
        ]);

        $query = AuditLog::query()->where('id', '>', $sinceId);
        $this->applyFilters($query, $filters);

        $newLogIds = $query->pluck('id')->toArray();
        $total = AuditLog::count();

        return response()->json([
            'has_new' => count($newLogIds) > 0,
            'new_log_ids' => $newLogIds,
            'total' => $total,
        ]);
    }

    /**
     * Apply filters to the query.
     */
    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['user_id'])) {
            // Support filtering by 'system' for API/system logs
            if ($filters['user_id'] === 'system') {
                $query->whereNull('user_id');
            } else {
                $query->where('user_id', $filters['user_id']);
            }
        }

        if (!empty($filters['date'])) {
            $query->forDate($filters['date']);
        }

        if (!empty($filters['log_type'])) {
            $query->forType($filters['log_type']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('module_name', 'like', "%{$search}%")
                  ->orWhere('user_role', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Analyze logs using DeepSeek AI.
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = $request->start_date;
        $endDate = $request->end_date;

        // Availability Fix: Restrict to 31 days max to prevent slow queries
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        if ($start->diffInDays($end) > 31) {
            return response()->json(['summary' => 'Date range cannot exceed 31 days for system availability reasons.'], 422);
        }

        // Availability Fix: Aggregate queries instead of loading models into RAM
        $totalLogs = AuditLog::whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->count();

        if ($totalLogs === 0) {
            return response()->json(['summary' => 'No logs found for the selected date range.']);
        }

        // Action Counts
        $actionCounts = AuditLog::whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->selectRaw('action_type, count(*) as count')
            ->groupBy('action_type')
            ->pluck('count', 'action_type');

        // Type Counts
        $typeCounts = AuditLog::whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->selectRaw('log_type, count(*) as count')
            ->groupBy('log_type')
            ->pluck('count', 'log_type');

        // Integrity & Confidentiality Fix: Limit to 10 logs to avoid token exhaustion and Prompt Injection limits
        $criticalLogs = AuditLog::whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->where('log_type', 'SECURITY')
            ->take(10)
            ->pluck('description')
            ->toArray();

        // Confidentiality Fix: Redact PII (Emails, IPs) and sanitize structural characters
        $sanitizedLogs = [];
        foreach ($criticalLogs as $desc) {
            $desc = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[REDACTED_EMAIL]', $desc);
            $desc = preg_replace('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', '[REDACTED_IP]', $desc);
            $desc = str_replace(['{', '}', '[', ']', '<', '>', '`'], '', $desc); // Sanitize injection
            $sanitizedLogs[] = $desc;
        }

        $prompt = "You are an AI assistant for a system administrator. Summarize the following audit logs data from $startDate to $endDate. Focus on important details and security implications. Format the response beautifully in Markdown. IMPORTANT: Do NOT hallucinate. Only use the data strictly provided below.\n\n";
        $prompt .= "Total Logs: " . $totalLogs . "\n\n";
        
        $prompt .= "Action Counts:\n";
        foreach ($actionCounts as $action => $count) {
            $prompt .= "- " . ($action ?: 'UNKNOWN') . ": $count\n";
        }

        $prompt .= "\nLog Type Counts:\n";
        foreach ($typeCounts as $type => $count) {
            $prompt .= "- " . ($type ?: 'UNKNOWN') . ": $count\n";
        }

        if (!empty($sanitizedLogs)) {
            $prompt .= "\nSample Security Logs:\n";
            foreach ($sanitizedLogs as $desc) {
                $prompt .= "- $desc\n";
            }
        }

        try {
            $apiKey = config('services.deepseek.key', env('DEEPSEEK_API_KEY'));
            if (empty($apiKey)) {
                return response()->json(['summary' => 'DeepSeek API Key is not configured in the environment.'], 500);
            }

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.deepseek.com/chat/completions', [
                'model' => 'deepseek-chat',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert security analyst and auditor.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.5,
                'max_tokens' => 1500,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $summary = $result['choices'][0]['message']['content'] ?? 'Failed to generate summary.';
                
                // Save to history
                AiAnalyticsHistory::create([
                    'user_id' => auth()->id(),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'summary' => $summary,
                ]);

                return response()->json(['summary' => $summary]);
            } else {
                \Illuminate\Support\Facades\Log::error('DeepSeek API Error: ' . $response->body());
                return response()->json(['summary' => 'Error communicating with AI service. Details: ' . $response->body()], 500);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('DeepSeek Exception: ' . $e->getMessage());
            return response()->json(['summary' => 'An error occurred while analyzing logs. ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get AI Analytics History
     */
    public function getHistory()
    {
        $history = AiAnalyticsHistory::with('user:id,email,firstname,lastname')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json(['history' => $history]);
    }

    /**
     * Delete AI Analytics History
     */
    public function deleteHistory($id)
    {
        $history = AiAnalyticsHistory::findOrFail($id);
        $history->delete();
        
        return response()->json(['success' => true]);
    }
}
