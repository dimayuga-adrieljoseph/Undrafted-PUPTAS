<?php

namespace App\Models;

use App\Models\UserFile;
use App\Models\Application;
use App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class ApplicationProcess extends Model
{
    use HasFactory, MassPrunable;

    protected $fillable = [
        'application_id',
        'stage',
        'status',
        'action',
        'performed_by',
        'decision_reason',
        'reviewer_notes',
        'files_affected',
        'ip_address',
        'started_at',
        'reviewed_by',
    ];

    protected $casts = [
        'files_affected' => 'array',
        'started_at'     => 'datetime',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    // ─── Pruning ─────────────────────────────────────────────────────────────

    /**
     * Prune completed process records older than the configured retention period.
     *
     * DISABLED BY DEFAULT — set APP_PROCESS_RETENTION_YEARS in .env to enable.
     *
     * These records are the workflow audit trail for every application. Before
     * enabling pruning, confirm:
     *   1. No compliance/legal requirement mandates longer retention.
     *   2. No report or export reads historical process rows.
     *   3. AuditLog alone is sufficient for long-term accountability.
     *
     * To enable: APP_PROCESS_RETENTION_YEARS=2 (or any positive integer)
     *
     * The idx_app_processes_pruning index (status, updated_at) makes this query
     * index-backed when it does run.
     *
     * Run via the Laravel scheduler: $schedule->command('model:prune')->daily()
     * (already scheduled in bootstrap/app.php)
     */
    public function prunable(): \Illuminate\Database\Eloquent\Builder
    {
        $years = (int) config('app.process_retention_years', 0);

        // When retention is not configured (0), return a query that matches
        // nothing — effectively disabling pruning without removing the trait.
        if ($years <= 0) {
            return static::whereRaw('1 = 0');
        }

        return static::where('status', 'completed')
            ->where('updated_at', '<', now()->subYears($years));
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
