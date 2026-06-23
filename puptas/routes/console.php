<?php

use Illuminate\Support\Facades\Artisan;
use App\Models\SystemSetting;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('emergency:login {action? : enable, disable, or status}', function (string $action = null) {
    $setting = SystemSetting::firstOrCreate(
        ['key' => 'idp_down_emergency_login_enabled'],
        ['value' => '0']
    );

    if ($action === 'enable') {
        $setting->update(['value' => '1']);
        $this->info('CRITICAL: Emergency Access (Email OTP) has been ENABLED.');
        $this->warn('Users will now bypass the IDP and login via Email OTP.');
    } elseif ($action === 'disable') {
        $setting->update(['value' => '0']);
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
})->purpose('Toggle or check the status of the IDP Emergency Access (Email OTP) system');
