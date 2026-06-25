<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCutoffRequest;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use App\Services\CutoffSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class CutoffSettingsController extends Controller
{
    public function __construct(
        private CutoffSettingsService $cutoffService,
        private AuditLogService $auditLogService,
    ) {}

    /**
     * Display the cutoff settings page.
     */
    public function index(): Response
    {
        $qualifiedEnabled = SystemSetting::where('key', 'enable_qualified_programs_view')->value('value');
        if ($qualifiedEnabled === null) {
            $qualifiedEnabled = '1';
        }

        return Inertia::render('SuperAdmin/CutoffSettings', [
            'cutoff_display' => $this->cutoffService->formatForDisplay(),
            'cutoff_raw'     => $this->cutoffService->getCutoff()?->toIso8601String(),
            'settings' => [
                'enable_qualified_programs_view' => $qualifiedEnabled !== '0',
            ]
        ]);
    }

    /**
     * Save a new cutoff datetime.
     */
    public function store(StoreCutoffRequest $request): RedirectResponse
    {
        // Read old cutoff before saving so we can log the transition.
        $oldCutoff = $this->cutoffService->getCutoff();

        $this->cutoffService->saveCutoff($request->input('cutoff_at'));

        // Audit log — non-fatal per Requirement 5.4.
        try {
            $this->auditLogService->logActivity(
                actionType: AuditLog::ACTION_UPDATE,
                moduleName: 'Cutoff Settings',
                description: 'Super Admin updated the application submission cutoff.',
                actor: null,
                logCategory: AuditLog::CATEGORY_SYSTEM_OPERATION,
                oldValues: ['cutoff_at' => $oldCutoff?->toIso8601String()],
                newValues: ['cutoff_at' => $request->input('cutoff_at')],
            );
        } catch (\Throwable $e) {
            \Log::error('Cutoff audit log write failed', ['error' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Cutoff saved successfully.');
    }

    /**
     * Clear the current cutoff datetime.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Read old cutoff before clearing so we can log the transition.
        $oldCutoff = $this->cutoffService->getCutoff();

        $this->cutoffService->clearCutoff();

        // Audit log — non-fatal per Requirement 5.4.
        try {
            $this->auditLogService->logActivity(
                actionType: AuditLog::ACTION_UPDATE,
                moduleName: 'Cutoff Settings',
                description: 'Super Admin cleared the application submission cutoff.',
                actor: null,
                logCategory: AuditLog::CATEGORY_SYSTEM_OPERATION,
                oldValues: ['cutoff_at' => $oldCutoff?->toIso8601String()],
                newValues: ['cutoff_at' => null],
            );
        } catch (\Throwable $e) {
            \Log::error('Cutoff audit log write failed', ['error' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Cutoff cleared successfully.');
    }

    /**
     * Update global system settings (like Qualified Programs View).
     */
    public function updateSystemSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'enable_qualified_programs_view' => 'required|boolean',
        ]);

        SystemSetting::updateOrCreate(
            ['key' => 'enable_qualified_programs_view'],
            ['value' => $request->enable_qualified_programs_view ? '1' : '0']
        );

        Cache::forget('setting_qualified_programs_view');

        try {
            $this->auditLogService->logActivity(
                actionType: AuditLog::ACTION_UPDATE,
                moduleName: 'System Settings',
                description: 'Super Admin updated Qualified Programs View to ' . ($request->enable_qualified_programs_view ? 'Enabled' : 'Disabled'),
                actor: auth()->user(),
                logCategory: AuditLog::CATEGORY_SYSTEM_OPERATION
            );
        } catch (\Throwable $e) {
            \Log::error('System Settings audit log write failed', ['error' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', 'System settings updated successfully.');
    }
}
