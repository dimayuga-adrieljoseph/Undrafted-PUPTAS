<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SystemSetting;

class ToggleEmergencyLogin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emergency:login {action? : The action to perform (enable, disable, status)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Toggle or check the status of the IDP Emergency Access (Email OTP) system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        $setting = SystemSetting::firstOrCreate(
            ['key' => 'idp_down_emergency_login_enabled'],
            ['value' => '0']
        );

        if ($action === 'enable') {
            $setting->update(['value' => '1']);
            app(\App\Services\AuditLogService::class)->logActivity(
                'UPDATE',
                'System Settings',
                'Emergency Access (Email OTP) was ENABLED via console command.',
                null,
                \App\Models\AuditLog::CATEGORY_SYSTEM_OPERATION
            );
            $this->info('CRITICAL: Emergency Access (Email OTP) has been ENABLED.');
            $this->warn('Users will now bypass the IDP and login via Email OTP.');
        } elseif ($action === 'disable') {
            $setting->update(['value' => '0']);
            app(\App\Services\AuditLogService::class)->logActivity(
                'UPDATE',
                'System Settings',
                'Emergency Access (Email OTP) was DISABLED via console command.',
                null,
                \App\Models\AuditLog::CATEGORY_SYSTEM_OPERATION
            );
            $this->info('Emergency Access (Email OTP) has been DISABLED.');
            $this->line('Users will log in normally via the external Identity Provider.');
        } elseif ($action === 'status' || !$action) {
            $status = $setting->value === '1' ? 'ENABLED' : 'DISABLED';
            if ($status === 'ENABLED') {
                $this->warn("Emergency Access Status: {$status}");
            } else {
                $this->info("Emergency Access Status: {$status}");
            }
        } else {
            $this->error('Invalid action. Use "enable", "disable", or "status".');
            return 1;
        }

        return 0;
    }
}
