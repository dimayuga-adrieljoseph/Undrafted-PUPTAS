<?php

/**
 * Bug Condition Exploration Tests — API Audit Log Visibility
 *
 * **Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5**
 *
 * These tests encode the EXPECTED (correct) behavior for system/API audit logs.
 * On UNFIXED code, these tests FAIL — proving the visibility bug exists.
 * On FIXED code, these tests PASS — confirming the fix works correctly.
 *
 * Bug Condition: System logs (user_id = NULL) created by API calls via OAuth
 * client credentials are not visible in the Audit Logs UI and cannot be filtered.
 *
 * Expected Behavior:
 * - System logs appear in UI table with username "system"
 * - User filter dropdown includes "System/API Calls" option when system logs exist
 * - Filtering by "System/API Calls" returns only logs with user_id = NULL
 * - "All Users" filter includes both user and system logs
 */

use App\Models\AuditLog;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Disable audit log event listeners during tests to prevent infinite loops
    \Illuminate\Support\Facades\Event::fake([
        \Illuminate\Auth\Events\Login::class,
        \Illuminate\Auth\Events\Logout::class,
    ]);
    
    // Create a superadmin user for testing
    $this->superadmin = User::factory()->create([
        'role_id' => 7, // Superadmin role
    ]);
});

test('system logs with user_id NULL are returned by the audit logs index endpoint', function () {
    // Validates: Requirement 2.1 — System logs SHALL appear in the Audit Logs UI table
    
    // Create a system log (API call log with user_id = NULL)
    $systemLog = AuditLog::create([
        'user_id' => null,
        'username' => 'system',
        'user_role' => null,
        'log_type' => AuditLog::TYPE_SYSTEM,
        'log_category' => AuditLog::CATEGORY_SYSTEM_OPERATION,
        'action_type' => 'READ',
        'module_name' => 'External Medical API',
        'description' => 'API call to retrieve applicant data',
        'ip_address' => '192.168.1.100',
        'request_url' => '/api/v1/medical/applicants/12345',
    ]);

    // Create a regular user log for comparison
    $userLog = AuditLog::create([
        'user_id' => $this->superadmin->id,
        'username' => $this->superadmin->email,
        'user_role' => 'superadmin',
        'log_type' => AuditLog::TYPE_AUDIT,
        'log_category' => AuditLog::CATEGORY_AUDIT_ACCESS,
        'action_type' => 'READ',
        'module_name' => 'Audit Logs',
        'description' => 'Viewed audit logs',
        'ip_address' => '192.168.1.50',
    ]);

    // Act as superadmin and request audit logs with no filter (All Users)
    $response = $this->actingAs($this->superadmin)
        ->get(route('audit-logs.index'));

    // Assert: System log should be included in the response
    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('SuperAdmin/Logs')
        ->has('logs.data', 2) // Both logs should be present
        ->where('logs.data.0.id', $userLog->id)  // Latest first
        ->where('logs.data.0.username', $this->superadmin->email)
        ->where('logs.data.1.id', $systemLog->id)
        ->where('logs.data.1.username', 'system')
        ->where('logs.data.1.user_id', null)
    );
});

test('user filter dropdown includes System/API Calls option when system logs exist', function () {
    // Validates: Requirement 2.2 — "System/API Calls" option SHALL be displayed in dropdown
    
    // Create a system log
    AuditLog::create([
        'user_id' => null,
        'username' => 'system',
        'user_role' => null,
        'log_type' => AuditLog::TYPE_SYSTEM,
        'log_category' => AuditLog::CATEGORY_SYSTEM_OPERATION,
        'action_type' => 'READ',
        'module_name' => 'External Medical API',
        'description' => 'API call to retrieve applicant data',
        'ip_address' => '192.168.1.100',
    ]);

    // Act as superadmin and request audit logs
    $response = $this->actingAs($this->superadmin)
        ->get(route('audit-logs.index'));

    // Assert: Users array should include a 'system' option
    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('SuperAdmin/Logs')
        ->has('users', fn (Assert $users) => $users
            ->where('0.id', 'system')
            ->where('0.firstname', 'System/API')
            ->where('0.lastname', '')
            ->where('0.email', 'system')
        )
    );
});

test('user filter dropdown does NOT include System/API Calls option when no system logs exist', function () {
    // Edge case: When no system logs exist, the option should not appear
    
    // Create only a regular user log
    AuditLog::create([
        'user_id' => $this->superadmin->id,
        'username' => $this->superadmin->email,
        'user_role' => 'superadmin',
        'log_type' => AuditLog::TYPE_AUDIT,
        'log_category' => AuditLog::CATEGORY_AUDIT_ACCESS,
        'action_type' => 'READ',
        'module_name' => 'Audit Logs',
        'description' => 'Viewed audit logs',
    ]);

    // Act as superadmin and request audit logs
    $response = $this->actingAs($this->superadmin)
        ->get(route('audit-logs.index'));

    // Assert: Users array should NOT include a 'system' option
    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('SuperAdmin/Logs')
        ->has('users', 1)  // Only the superadmin user
        ->where('users.0.id', $this->superadmin->id)
    );
});

test('filtering by System/API Calls returns only logs with user_id NULL', function () {
    // Validates: Requirement 2.3, 2.5 — Filtering by "System/API Calls" SHALL return only system logs
    
    // Create multiple system logs
    $systemLog1 = AuditLog::create([
        'user_id' => null,
        'username' => 'system',
        'log_type' => AuditLog::TYPE_SYSTEM,
        'action_type' => 'READ',
        'module_name' => 'External Medical API',
        'description' => 'API call 1',
    ]);

    $systemLog2 = AuditLog::create([
        'user_id' => null,
        'username' => 'system',
        'log_type' => AuditLog::TYPE_SYSTEM,
        'action_type' => 'CREATE',
        'module_name' => 'External Student API',
        'description' => 'API call 2',
    ]);

    // Create a regular user log
    $userLog = AuditLog::create([
        'user_id' => $this->superadmin->id,
        'username' => $this->superadmin->email,
        'log_type' => AuditLog::TYPE_AUDIT,
        'action_type' => 'READ',
        'module_name' => 'Audit Logs',
        'description' => 'User action',
    ]);

    // Act as superadmin and filter by 'system'
    $response = $this->actingAs($this->superadmin)
        ->get(route('audit-logs.index', ['user_id' => 'system']));

    // Assert: Only system logs should be returned
    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('SuperAdmin/Logs')
        ->has('logs.data', 2) // Only 2 system logs
        ->where('logs.data.0.id', $systemLog2->id)  // Latest first
        ->where('logs.data.0.user_id', null)
        ->where('logs.data.1.id', $systemLog1->id)
        ->where('logs.data.1.user_id', null)
    );
});

test('filtering by All Users includes both user and system logs', function () {
    // Validates: Requirement 2.4 — "All Users" filter SHALL display both user and system logs
    
    // Create a system log
    $systemLog = AuditLog::create([
        'user_id' => null,
        'username' => 'system',
        'log_type' => AuditLog::TYPE_SYSTEM,
        'action_type' => 'READ',
        'module_name' => 'External Medical API',
        'description' => 'API call',
    ]);

    // Create a user log
    $userLog = AuditLog::create([
        'user_id' => $this->superadmin->id,
        'username' => $this->superadmin->email,
        'log_type' => AuditLog::TYPE_AUDIT,
        'action_type' => 'READ',
        'module_name' => 'Audit Logs',
        'description' => 'User action',
    ]);

    // Act as superadmin and request audit logs with no user_id filter (All Users)
    $response = $this->actingAs($this->superadmin)
        ->get(route('audit-logs.index'));

    // Assert: Both logs should be returned
    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('SuperAdmin/Logs')
        ->has('logs.data', 2)
        ->where('logs.data.0.id', $userLog->id)
        ->where('logs.data.1.id', $systemLog->id)
    );
});

test('filtering by specific numeric user_id returns only that users logs', function () {
    // Preservation check: Ensure numeric user_id filtering still works
    // Validates: Requirement 3.1 — Numeric user_id filtering unchanged
    
    // Create logs for different users
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $user1Log = AuditLog::create([
        'user_id' => $user1->id,
        'username' => $user1->email,
        'log_type' => AuditLog::TYPE_AUDIT,
        'action_type' => 'LOGIN',
        'module_name' => 'Authentication',
        'description' => 'User 1 logged in',
    ]);

    $user2Log = AuditLog::create([
        'user_id' => $user2->id,
        'username' => $user2->email,
        'log_type' => AuditLog::TYPE_AUDIT,
        'action_type' => 'LOGIN',
        'module_name' => 'Authentication',
        'description' => 'User 2 logged in',
    ]);

    $systemLog = AuditLog::create([
        'user_id' => null,
        'username' => 'system',
        'log_type' => AuditLog::TYPE_SYSTEM,
        'action_type' => 'READ',
        'module_name' => 'External API',
        'description' => 'API call',
    ]);

    // Act as superadmin and filter by user1's ID
    $response = $this->actingAs($this->superadmin)
        ->get(route('audit-logs.index', ['user_id' => $user1->id]));

    // Assert: Only user1's log should be returned
    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('SuperAdmin/Logs')
        ->has('logs.data', 1)
        ->where('logs.data.0.id', $user1Log->id)
        ->where('logs.data.0.user_id', (string) $user1->id)  // Cast to string for comparison
    );
});

test('system logs are included in real-time polling when no user filter is applied', function () {
    // Validates: Requirement 2.1, 3.3 — Real-time polling includes system logs
    
    // Create an initial log to establish a baseline
    $initialLog = AuditLog::create([
        'user_id' => $this->superadmin->id,
        'username' => $this->superadmin->email,
        'log_type' => AuditLog::TYPE_AUDIT,
        'action_type' => 'READ',
        'module_name' => 'Audit Logs',
        'description' => 'Initial log',
    ]);

    $sinceId = $initialLog->id;

    // Create a new system log (simulating an API call that happens after page load)
    $newSystemLog = AuditLog::create([
        'user_id' => null,
        'username' => 'system',
        'log_type' => AuditLog::TYPE_SYSTEM,
        'action_type' => 'READ',
        'module_name' => 'External Medical API',
        'description' => 'New API call',
    ]);

    // Act as superadmin and poll for new logs
    $response = $this->actingAs($this->superadmin)
        ->get(route('audit-logs.check-new', ['since_id' => $sinceId]));

    // Assert: The new system log should be detected
    $response->assertStatus(200);
    $response->assertJson([
        'has_new' => true,
        'new_log_ids' => [$newSystemLog->id],
    ]);
});

test('system logs are excluded from polling when filtering by specific user', function () {
    // Preservation check: Ensure user-specific filtering works in polling
    // Validates: Requirement 3.1 — User filtering unchanged
    
    $user1 = User::factory()->create();

    $initialLog = AuditLog::create([
        'user_id' => $user1->id,
        'username' => $user1->email,
        'log_type' => AuditLog::TYPE_AUDIT,
        'action_type' => 'LOGIN',
        'module_name' => 'Authentication',
        'description' => 'Initial log',
    ]);

    $sinceId = $initialLog->id;

    // Create a new system log
    $newSystemLog = AuditLog::create([
        'user_id' => null,
        'username' => 'system',
        'log_type' => AuditLog::TYPE_SYSTEM,
        'action_type' => 'READ',
        'module_name' => 'External API',
        'description' => 'New API call',
    ]);

    // Create a new log for user1
    $newUser1Log = AuditLog::create([
        'user_id' => $user1->id,
        'username' => $user1->email,
        'log_type' => AuditLog::TYPE_AUDIT,
        'action_type' => 'LOGOUT',
        'module_name' => 'Authentication',
        'description' => 'User logged out',
    ]);

    // Act as superadmin and poll for new logs with user_id filter
    $response = $this->actingAs($this->superadmin)
        ->get(route('audit-logs.check-new', [
            'since_id' => $sinceId,
            'user_id' => $user1->id,
        ]));

    // Assert: Only user1's new log should be detected, not the system log
    $response->assertStatus(200);
    $response->assertJson([
        'has_new' => true,
        'new_log_ids' => [$newUser1Log->id],
    ]);
});
