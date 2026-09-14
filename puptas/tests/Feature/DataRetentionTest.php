<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EmailLog;
use App\Models\Role;
use App\Models\User;
use App\Models\ApplicantProfile;
use App\Models\UserFile;
use App\Services\DataRetentionService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure basic roles exist
        Role::firstOrCreate(['id' => 1], ['name' => 'Applicant']);
        Role::firstOrCreate(['id' => 7], ['name' => 'SuperAdmin']);
    }

    /**
     * Test soft-deletion, deactivation, and restoration of users (Ticket 5).
     */
    public function test_user_soft_delete_and_deactivation_lifecycle(): void
    {
        $userService = app(UserService::class);

        $user = User::create([
            'email' => 'retention_test@example.com',
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'role_id' => 1,
            'is_active' => true,
        ]);

        ApplicantProfile::create([
            'user_id' => (string) $user->id,
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'email' => 'retention_test@example.com',
        ]);

        $this->assertTrue($user->isActive());
        $this->assertFalse($user->isDeactivated());

        // Deactivate user
        $userService->deactivateUser($user->id, 'Testing deactivation');
        $user->refresh();
        $this->assertFalse($user->isActive());
        $this->assertTrue($user->isDeactivated());

        // Reactivate user
        $userService->reactivateUser($user->id);
        $user->refresh();
        $this->assertTrue($user->isActive());

        // Soft-delete user (Initiates Phase 1 Retention Hold)
        $userService->softDeleteUser($user->id, 'Testing soft-delete hold');
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertSoftDeleted('applicant_profiles', ['user_id' => (string) $user->id]);

        // Restore user within hold period
        $userService->restoreUser($user->id);
        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
        $this->assertNotSoftDeleted('applicant_profiles', ['user_id' => (string) $user->id]);
    }

    /**
     * Test that dry-run calculates eligible items without deleting data.
     */
    public function test_data_retention_dry_run_does_not_delete_records(): void
    {
        $service = app(DataRetentionService::class);

        // Create expired audit log
        $auditLog = AuditLog::create([
            'user_id' => null,
            'username' => 'test@example.com',
            'user_role' => 'SuperAdmin',
            'log_type' => AuditLog::TYPE_SYSTEM,
            'log_category' => AuditLog::CATEGORY_SYSTEM_OPERATION,
            'action_type' => AuditLog::ACTION_CREATE,
            'module_name' => 'Test',
            'description' => 'Old test audit log',
        ]);
        \Illuminate\Support\Facades\DB::table('audit_logs')
            ->where('id', $auditLog->id)
            ->update([
                'created_at' => now()->subDays(200),
                'updated_at' => now()->subDays(200),
            ]);

        // Run dry run
        $results = $service->purgeAll(dryRun: true);

        $this->assertEquals(1, $results['audit_logs']['records_purged']);
        $this->assertTrue($results['audit_logs']['dry_run']);

        // Assert record still exists in DB
        $this->assertDatabaseHas('audit_logs', ['id' => $auditLog->id]);
    }

    /**
     * Test permanent hard-purge of expired records and audit log generation.
     */
    public function test_data_retention_purge_removes_expired_records_and_logs_audit(): void
    {
        Storage::fake('public');
        $service = app(DataRetentionService::class);

        // Create expired soft-deleted user (older than 365 days)
        $user = User::create([
            'email' => 'expired_user@example.com',
            'firstname' => 'Old',
            'lastname' => 'Applicant',
            'role_id' => 1,
        ]);
        $user->delete();
        \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $user->id)
            ->update([
                'deleted_at' => now()->subDays(400),
                'updated_at' => now()->subDays(400),
            ]);

        $profile = ApplicantProfile::create([
            'user_id' => (string) $user->id,
            'firstname' => 'Old',
            'lastname' => 'Applicant',
            'email' => 'expired_user@example.com',
        ]);
        $profile->delete();
        \Illuminate\Support\Facades\DB::table('applicant_profiles')
            ->where('id', $profile->id)
            ->update([
                'deleted_at' => now()->subDays(400),
                'updated_at' => now()->subDays(400),
            ]);

        // Create fake uploaded file
        Storage::disk('public')->put('user_files/test_file.pdf', 'dummy PDF content');
        UserFile::create([
            'user_id' => (string) $user->id,
            'type' => 'grade_card',
            'file_path' => 'user_files/test_file.pdf',
            'status' => 'approved',
        ]);

        // Create expired email log (older than 90 days)
        $emailLog = EmailLog::create([
            'recipient_email' => 'test@example.com',
            'recipient_name' => 'Test',
            'email_type' => 'notification',
            'status' => 'sent',
        ]);
        \Illuminate\Support\Facades\DB::table('email_logs')
            ->where('id', $emailLog->id)
            ->update([
                'created_at' => now()->subDays(100),
                'updated_at' => now()->subDays(100),
            ]);

        // Active user (should NOT be purged)
        $activeUser = User::create([
            'email' => 'active_user@example.com',
            'firstname' => 'Active',
            'lastname' => 'Applicant',
            'role_id' => 1,
        ]);

        // Execute forced purge
        $results = $service->purgeAll(dryRun: false);

        // Assert expired records purged
        $this->assertDatabaseMissing('users', ['email' => 'expired_user@example.com']);
        $this->assertDatabaseMissing('applicant_profiles', ['email' => 'expired_user@example.com']);
        $this->assertDatabaseMissing('email_logs', ['recipient_email' => 'test@example.com']);

        // Assert physical file deleted
        Storage::disk('public')->assertMissing('user_files/test_file.pdf');

        // Assert active user remains untouched
        $this->assertDatabaseHas('users', ['email' => 'active_user@example.com']);

        // Assert immutable audit record created
        $this->assertDatabaseHas('audit_logs', [
            'module_name' => 'Data Retention & Disposal',
            'action_type' => AuditLog::ACTION_DELETE,
        ]);
    }

    /**
     * Test Artisan command execution.
     */
    public function test_artisan_data_retention_purge_command(): void
    {
        $this->artisan('data-retention:purge --dry-run')
            ->expectsOutputToContain('PUPTAS Data Retention & Automated Disposal Engine')
            ->expectsOutputToContain('[DRY-RUN MODE ENABLED]')
            ->assertExitCode(0);
    }
}
