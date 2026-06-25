<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use App\Services\AuditLogService;
use App\Models\AuditLog;

class SystemSettingsController extends Controller
{
    private AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }
    public function index()
    {
        // By default, treat it as enabled if not set
        $qualifiedEnabled = SystemSetting::where('key', 'enable_qualified_programs_view')->value('value');
        if ($qualifiedEnabled === null) {
            $qualifiedEnabled = '1';
        }

        return Inertia::render('SuperAdmin/SystemSettings', [
            'settings' => [
                'enable_qualified_programs_view' => $qualifiedEnabled !== '0',
            ]
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'enable_qualified_programs_view' => 'required|boolean',
        ]);

        SystemSetting::updateOrCreate(
            ['key' => 'enable_qualified_programs_view'],
            ['value' => $request->enable_qualified_programs_view ? '1' : '0']
        );

        Cache::forget('setting_qualified_programs_view');

        $this->auditLogService->logActivity(
            AuditLog::ACTION_UPDATE,
            'System Settings',
            "Updated Qualified Programs View to " . ($request->enable_qualified_programs_view ? 'Enabled' : 'Disabled'),
            auth()->user(),
            AuditLog::CATEGORY_SYSTEM_OPERATION
        );

        return redirect()->back()->with('success', 'System settings updated successfully.');
    }
}
