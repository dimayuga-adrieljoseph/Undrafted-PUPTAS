<?php

use App\Models\AuditLog;
use App\Auth\IdpUser;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Hash;
use App\Models\Auth\VirtualUser;

function makeUser(int $roleId = 2): IdpUser
{
    static $seq = 100;
    $seq++;
    $uid = $seq;
    return new IdpUser([
        'id' => (string)$uid,
        'role_id' => $roleId,
        'email' => 'user'.$seq.'@example.com',
        'firstname' => 'Test',
        'lastname' => 'User',
    ]);
}

test('login and logout are stored as security logs', function () {
    $user = makeUser(2);
    $service = app(AuditLogService::class);

    $service->logLogin($user);
    $service->logLogout($user);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'action_type' => AuditLog::ACTION_LOGIN,
        'log_type' => AuditLog::TYPE_SECURITY,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'action_type' => AuditLog::ACTION_LOGOUT,
        'log_type' => AuditLog::TYPE_SECURITY,
    ]);
});

test('system and audit activities are categorized correctly', function () {     
    $user = makeUser(2);
    $service = app(AuditLogService::class);

    $service->logActivity('UPDATE', 'Programs', 'Updated program settings.', $user, AuditLog::CATEGORY_SYSTEM_OPERATION);
    $service->logActivity('CREATE', 'Applications', 'Created applicant record.', $user, AuditLog::CATEGORY_ADMISSION_DATA);
                                     
    $this->assertDatabaseHas('audit_logs', [
        'module_name' => 'Programs',
        'log_type' => AuditLog::TYPE_SYSTEM,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'module_name' => 'Applications',
        'log_type' => AuditLog::TYPE_AUDIT,
    ]);
});

test('audit log pages are superadmin-only and check-new supports filters', function () {
    $admin = makeUser(2);
    $superadmin = makeUser(7);
    $nonAdmin = makeUser(1);
    $targetUser = makeUser(2);

    AuditLog::create([
        'user_id' => $targetUser->id,
        'username' => $targetUser->email,
        'user_role' => 'Admin',
        'log_type' => AuditLog::TYPE_SECURITY,
        'log_category' => AuditLog::CATEGORY_AUTHENTICATION,
        'action_type' => AuditLog::ACTION_LOGIN,
        'module_name' => 'Authentication',
        'description' => 'Security login event.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    AuditLog::create([
        'user_id' => $admin->id,
        'username' => $admin->email,
        'user_role' => 'Admin',
        'log_type' => AuditLog::TYPE_SYSTEM,
        'log_category' => AuditLog::CATEGORY_SYSTEM_OPERATION,
        'action_type' => AuditLog::ACTION_UPDATE,
        'module_name' => 'Programs',
        'description' => 'System update event.',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $this->actingAs($nonAdmin)
        ->get(route('audit-logs.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('audit-logs.index'))
        ->assertForbidden();

    $response = $this->actingAs($superadmin)
        ->get(route('audit-logs.check-new', [
            'since_id' => 0,
            'user_id' => $targetUser->id,
            'date' => now()->toDateString(),
            'log_type' => AuditLog::TYPE_SECURITY,
        ]))
        ->assertOk()
        ->json();

    expect($response['total'])->toBe(2);
    expect(count($response['new_log_ids']))->toBe(1);
});
