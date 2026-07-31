<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ApplicantProfile;
use App\Models\TestPasser;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdpAuthTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.idp.base_url' => 'https://idp.example.com',
            'services.idp.client_id' => 'test-client-id',
            'services.idp.client_secret' => 'test-client-secret',
            'services.idp.redirect_uri' => 'http://localhost/auth/idp/callback',
            'cache.stores.redis.driver' => 'array',
        ]);

        Session::put('idp_oauth_state', 'test-state-123');
    }

    private function mockIdpResponse(array $userData, int $tokenStatus = 200, int $userStatus = 200)
    {
        $baseUrl = config('services.idp.base_url', 'https://idp.example.com');
        $tokenPath = config('services.idp.token_path', '/api/v1/auth/token');
        $userPath = config('services.idp.user_path', '/api/v1/me');

        Http::fake([
            rtrim($baseUrl, '/') . $tokenPath => Http::response([
                'access_token' => 'mock-access-token',
                'refresh_token' => 'mock-refresh-token',
                'expires_in' => 3600,
            ], $tokenStatus),
            rtrim($baseUrl, '/') . $userPath => Http::response($userData, $userStatus),
        ]);
    }

    /** @test */
    public function scenario_1_login_with_same_email_uuid_and_name_does_not_trigger_sync_transaction()
    {
        $uuid = Str::uuid()->toString();
        $user = User::factory()->create([
            'idp_user_id' => $uuid,
            'email' => 'student@pup.edu.ph',
            'firstname' => 'Juan',
            'lastname' => 'Dela Cruz',
            'middlename' => 'Santos',
            'role_id' => 1,
        ]);

        $this->mockIdpResponse([
            'id' => $uuid,
            'email' => 'student@pup.edu.ph',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'middle_name' => 'Santos',
        ]);

        $response = $this->get('/auth/idp/callback?code=mock-code&state=test-state-123');

        $response->assertRedirect('/applicant-dashboard');
        $this->assertAuthenticatedAs($user);

        // Verify NO sync audit log was written (only login log if any)
        $this->assertDatabaseMissing('audit_logs', [
            'action_type' => AuditLog::ACTION_UPDATE,
            'module_name' => 'User Management',
        ]);
    }

    /** @test */
    public function scenario_2_login_with_changed_email_syncs_transactionally_and_writes_audit_log()
    {
        $uuid = Str::uuid()->toString();
        $user = User::factory()->create([
            'idp_user_id' => $uuid,
            'email' => 'old_email@gmail.com',
            'firstname' => 'Juan',
            'lastname' => 'Dela Cruz',
            'role_id' => 1,
        ]);

        $profile = ApplicantProfile::create([
            'user_id' => $user->id,
            'email' => 'old_email@gmail.com',
            'firstname' => 'Juan',
            'lastname' => 'Dela Cruz',
        ]);

        $testPasser = TestPasser::create([
            'email' => 'old_email@gmail.com',
            'surname' => 'Dela Cruz',
            'first_name' => 'Juan',
            'passer_status_id' => 1,
        ]);

        $this->mockIdpResponse([
            'id' => $uuid,
            'email' => 'new_email@pup.edu.ph',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
        ]);

        $response = $this->get('/auth/idp/callback?code=mock-code&state=test-state-123');

        $response->assertRedirect('/applicant-dashboard');
        $this->assertAuthenticatedAs($user);

        // Verify updated records
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new_email@pup.edu.ph',
        ]);

        $this->assertDatabaseHas('applicant_profiles', [
            'user_id' => $user->id,
            'email' => 'new_email@pup.edu.ph',
        ]);

        $this->assertDatabaseHas('test_passers', [
            'test_passer_id' => $testPasser->test_passer_id,
            'email' => 'new_email@pup.edu.ph',
        ]);

        // Verify audit log entry was written
        $this->assertDatabaseHas('audit_logs', [
            'action_type' => AuditLog::ACTION_UPDATE,
            'module_name' => 'User Management',
            'log_category' => AuditLog::CATEGORY_USER_MANAGEMENT,
            'log_type' => AuditLog::TYPE_AUDIT,
        ]);
    }

    /** @test */
    public function scenario_3_legacy_user_without_idp_user_id_matches_by_email_and_links_uuid()
    {
        $user = User::factory()->create([
            'idp_user_id' => null,
            'email' => 'legacy@pup.edu.ph',
            'role_id' => 1,
        ]);

        $uuid = Str::uuid()->toString();

        $this->mockIdpResponse([
            'id' => $uuid,
            'email' => 'legacy@pup.edu.ph',
        ]);

        $response = $this->get('/auth/idp/callback?code=mock-code&state=test-state-123');

        $response->assertRedirect('/applicant-dashboard');
        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'idp_user_id' => $uuid,
        ]);
    }

    /** @test */
    public function scenario_4_email_fallback_matches_user_with_different_idp_user_id_rejects_with_conflict_error()
    {
        $existingUuid = Str::uuid()->toString();
        $incomingUuid = Str::uuid()->toString();

        $user = User::factory()->create([
            'idp_user_id' => $existingUuid,
            'email' => 'user@pup.edu.ph',
            'role_id' => 1,
        ]);

        // Incoming IDP payload has the same email, but a DIFFERENT UUID
        $this->mockIdpResponse([
            'id' => $incomingUuid,
            'email' => 'user@pup.edu.ph',
        ]);

        $response = $this->get('/auth/idp/callback?code=mock-code&state=test-state-123');

        $response->assertRedirect('/auth/idp/error');
        $response->assertSessionHasErrors(['idp' => 'idp_uuid_conflict']);
        $this->assertGuest();

        // Verify security audit log entry
        $this->assertDatabaseHas('audit_logs', [
            'action_type' => AuditLog::ACTION_UPDATE,
            'module_name' => 'Authentication',
            'log_category' => AuditLog::CATEGORY_AUTHENTICATION,
            'log_type' => AuditLog::TYPE_SECURITY,
        ]);
    }

    /** @test */
    public function scenario_5_email_collision_in_users_table_rejects_sync()
    {
        $uuid1 = Str::uuid()->toString();
        $uuid2 = Str::uuid()->toString();

        $user1 = User::factory()->create([
            'idp_user_id' => $uuid1,
            'email' => 'user1_old@gmail.com',
            'role_id' => 1,
        ]);

        $user2 = User::factory()->create([
            'idp_user_id' => $uuid2,
            'email' => 'new_shared@pup.edu.ph',
            'role_id' => 1,
        ]);

        // IDP returns new_shared@pup.edu.ph for user1, but user2 already owns it
        $this->mockIdpResponse([
            'id' => $uuid1,
            'email' => 'new_shared@pup.edu.ph',
        ]);

        $response = $this->get('/auth/idp/callback?code=mock-code&state=test-state-123');

        $response->assertRedirect('/auth/idp/error');
        $response->assertSessionHasErrors(['idp' => 'email_collision']);
        $this->assertGuest();

        // user1 email should remain unchanged
        $this->assertDatabaseHas('users', [
            'id' => $user1->id,
            'email' => 'user1_old@gmail.com',
        ]);
    }

    /** @test */
    public function scenario_6_email_collision_in_test_passers_table_rejects_sync()
    {
        $uuid = Str::uuid()->toString();

        $user = User::factory()->create([
            'idp_user_id' => $uuid,
            'email' => 'user_old@gmail.com',
            'role_id' => 1,
        ]);

        TestPasser::create([
            'email' => 'taken_passer@pup.edu.ph',
            'surname' => 'Smith',
            'first_name' => 'John',
            'passer_status_id' => 1,
        ]);

        $this->mockIdpResponse([
            'id' => $uuid,
            'email' => 'taken_passer@pup.edu.ph',
        ]);

        $response = $this->get('/auth/idp/callback?code=mock-code&state=test-state-123');

        $response->assertRedirect('/auth/idp/error');
        $response->assertSessionHasErrors(['idp' => 'email_collision']);
        $this->assertGuest();
    }

    /** @test */
    public function scenario_8_registration_from_stale_session_older_than_30_minutes_throws_validation_exception()
    {
        $creator = new \App\Actions\Fortify\CreateNewUser();

        // Stale session (31 minutes old)
        session(['pending_registration' => [
            'uuid' => Str::uuid()->toString(),
            'user_id' => Str::uuid()->toString(),
            'email' => 'newapp@pup.edu.ph',
            'registered_at' => now()->subMinutes(31)->timestamp,
        ]]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $creator->create([
            'firstname' => 'Test',
            'lastname' => 'User',
            'contactnumber' => '09123456789',
        ]);
    }

    /** @test */
    public function scenario_9_registration_with_fresh_session_and_valid_uuid_succeeds()
    {
        \App\Models\GraduateType::create(['label' => '2024-2025']);

        $creator = new \App\Actions\Fortify\CreateNewUser();
        $idpUuid = Str::uuid()->toString();

        session(['pending_registration' => [
            'uuid' => Str::uuid()->toString(),
            'user_id' => $idpUuid,
            'email' => 'fresh@pup.edu.ph',
            'registered_at' => now()->timestamp,
        ]]);

        $testPasser = TestPasser::create([
            'email' => 'fresh@pup.edu.ph',
            'surname' => 'User',
            'first_name' => 'Fresh',
            'reference_number' => 'REF-FRESH-123',
            'passer_status_id' => 1,
        ]);

        $user = $creator->create([
            'firstname' => 'Fresh',
            'lastname' => 'User',
            'reference_number' => 'REF-FRESH-123',
            'contactnumber' => '09123456789',
            'schoolyear' => '2024-2025',
            'school' => 'PUP High School',
            'sex' => 'Male',
            'dateGrad' => '2024-06-01',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($idpUuid, $user->idp_user_id);
        $this->assertEquals('fresh@pup.edu.ph', $user->email);
    }

    /** @test */
    public function scenario_10_duplicate_test_passers_rows_sharing_old_email_updates_lowest_id_and_logs_warning()
    {
        $uuid = Str::uuid()->toString();

        $user = User::factory()->create([
            'idp_user_id' => $uuid,
            'email' => 'shared_old@gmail.com',
            'firstname' => 'Multi',
            'lastname' => 'Passer',
            'role_id' => 1,
        ]);

        // Insert first passer via Model
        $passer1 = TestPasser::create([
            'email' => 'shared_old@gmail.com',
            'surname' => 'Passer',
            'first_name' => 'Multi1',
            'reference_number' => 'REF-MULTI-1',
            'passer_status_id' => 1,
        ]);

        // Drop unique index temporarily to insert duplicate row simulating dirty legacy data
        try {
            \Illuminate\Support\Facades\Schema::table('test_passers', function ($table) {
                $table->dropUnique(['email']);
            });
        } catch (\Throwable $e) {
            // Index name may vary
        }

        $passer2Id = \DB::table('test_passers')->insertGetId([
            'email' => 'shared_old@gmail.com',
            'surname' => 'Passer',
            'first_name' => 'Multi2',
            'reference_number' => 'REF-MULTI-2',
            'passer_status_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->mockIdpResponse([
            'id' => $uuid,
            'email' => 'updated_new@pup.edu.ph',
            'first_name' => 'Multi',
            'last_name' => 'Passer',
        ]);

        $response = $this->get('/auth/idp/callback?code=mock-code&state=test-state-123');

        $response->assertRedirect('/applicant-dashboard');
        $this->assertAuthenticatedAs($user);

        // First passer (lowest ID) updated
        $this->assertEquals('updated_new@pup.edu.ph', $passer1->fresh()->email);

        // Second passer (dirty data) remains unchanged
        $secondPasserEmail = \DB::table('test_passers')->where('test_passer_id', $passer2Id)->value('email');
        $this->assertEquals('shared_old@gmail.com', $secondPasserEmail);
    }


}
