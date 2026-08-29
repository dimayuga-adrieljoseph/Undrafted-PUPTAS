<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ApplicantProfile;
use App\Models\AuditLog;
use App\Helpers\DataMaskingHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnonymizationAndMaskingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function data_masking_helper_correctly_masks_various_pii_formats()
    {
        // Name masking
        $this->assertEquals('J*** C*** D*** C***', DataMaskingHelper::maskName('Juan Carlos Dela Cruz'));
        $this->assertEquals('M***', DataMaskingHelper::maskName('Maria'));
        $this->assertEquals('', DataMaskingHelper::maskName(null));

        // Email masking
        $this->assertEquals('st****nt@pup.edu.ph', DataMaskingHelper::maskEmail('student@pup.edu.ph'));
        $this->assertEquals('j***@pup.edu.ph', DataMaskingHelper::maskEmail('jd@pup.edu.ph'));
        $this->assertEquals('', DataMaskingHelper::maskEmail(null));

        // Phone masking
        $this->assertEquals('0912****789', DataMaskingHelper::maskPhone('09123456789'));
        $this->assertEquals('', DataMaskingHelper::maskPhone(null));

        // Reference number masking
        $this->assertEquals('REF-****-12345', DataMaskingHelper::maskReferenceNumber('REF-2026-12345'));
        $this->assertEquals('', DataMaskingHelper::maskReferenceNumber(null));
    }

    /** @test */
    public function user_anonymize_scrubs_pii_sets_timestamp_and_records_audit_log()
    {
        $uuid = Str::uuid()->toString();
        $user = User::factory()->create([
            'idp_user_id' => $uuid,
            'email'       => 'test.applicant@gmail.com',
            'firstname'   => 'Juan',
            'lastname'    => 'Dela Cruz',
            'middlename'  => 'Santos',
            'is_active'   => true,
        ]);

        $profile = ApplicantProfile::create([
            'user_id'                 => $user->id,
            'email'                   => 'test.applicant@gmail.com',
            'firstname'               => 'Juan',
            'lastname'                => 'Dela Cruz',
            'middlename'              => 'Santos',
            'student_number'          => '2026-00001-TG-0',
            'former_school_address'   => '123 School St, Taguig',
            'former_school_principal' => 'Dr. Principal',
        ]);

        $this->assertFalse($user->isAnonymized());
        $this->assertNull($user->anonymized_at);

        // Execute database anonymization
        $result = $user->anonymize();
        $this->assertTrue($result);

        $user->refresh();
        $profile->refresh();

        // Verify User scrubbed state
        $this->assertTrue($user->isAnonymized());
        $this->assertNotNull($user->anonymized_at);
        $this->assertFalse($user->is_active);
        $this->assertEquals('ANONYMIZED', $user->firstname);
        $this->assertEquals('USER_' . $user->id, $user->lastname);
        $this->assertNull($user->middlename);
        $this->assertNull($user->idp_user_id);
        $this->assertStringStartsWith("anon_{$user->id}_", $user->email);
        $this->assertStringEndsWith('@privacy.local', $user->email);

        // Verify cascading ApplicantProfile scrubbed state
        $this->assertEquals('ANONYMIZED', $profile->firstname);
        $this->assertEquals('APPLICANT_' . $profile->id, $profile->lastname);
        $this->assertNull($profile->middlename);
        $this->assertNull($profile->student_number);
        $this->assertNull($profile->former_school_address);
        $this->assertNull($profile->former_school_principal);

        // Verify Audit Log entry
        $this->assertDatabaseHas('audit_logs', [
            'action_type'  => AuditLog::ACTION_UPDATE,
            'module_name'  => 'User Management',
            'log_category' => AuditLog::CATEGORY_USER_MANAGEMENT,
        ]);
    }

    /** @test */
    public function multiple_anonymizations_do_not_collide_on_unique_email_index()
    {
        $user1 = User::factory()->create(['email' => 'user1@pup.edu.ph']);
        $user2 = User::factory()->create(['email' => 'user2@pup.edu.ph']);

        $user1->anonymize();
        $user2->anonymize();

        $this->assertNotEquals($user1->fresh()->email, $user2->fresh()->email);
        $this->assertTrue($user1->fresh()->isAnonymized());
        $this->assertTrue($user2->fresh()->isAnonymized());
    }

    /** @test */
    public function confirmed_applicants_endpoint_returns_masked_data_by_default()
    {
        $admin = User::factory()->create(['role_id' => 2]); // Admin role

        $applicantUser = User::factory()->create([
            'email'     => 'real.student@pup.edu.ph',
            'firstname' => 'Real',
            'lastname'  => 'Student',
            'role_id'   => 1,
        ]);

        $profile = ApplicantProfile::create([
            'user_id'   => $applicantUser->id,
            'email'     => 'real.student@pup.edu.ph',
            'firstname' => 'Real',
            'lastname'  => 'Student',
        ]);

        $app = \App\Models\Application::create([
            'user_id' => $applicantUser->id,
            'status'  => 'cleared_for_enrollment',
        ]);

        // Request without unmask query parameter -> Masked by default
        $response = $this->actingAs($admin)->getJson('/confirmed-applicants/list');

        $response->assertOk();
        $data = $response->json();
        $this->assertNotEmpty($data);

        $first = $data[0];
        $this->assertTrue($first['is_masked']);
        $this->assertEquals('R***', $first['firstname']);
        $this->assertEquals('S***', $first['lastname']);
        $this->assertEquals('re****nt@pup.edu.ph', $first['email']);

        // Verify NO unmask audit log was created
        $this->assertDatabaseMissing('audit_logs', [
            'log_category' => AuditLog::CATEGORY_AUDIT_ACCESS,
        ]);
    }

    /** @test */
    public function confirmed_applicants_endpoint_with_unmask_param_returns_unmasked_data_and_logs_audit_trail()
    {
        $admin = User::factory()->create(['role_id' => 2]);

        $applicantUser = User::factory()->create([
            'email'     => 'real.student@pup.edu.ph',
            'firstname' => 'Real',
            'lastname'  => 'Student',
            'role_id'   => 1,
        ]);

        $profile = ApplicantProfile::create([
            'user_id'   => $applicantUser->id,
            'email'     => 'real.student@pup.edu.ph',
            'firstname' => 'Real',
            'lastname'  => 'Student',
        ]);

        $app = \App\Models\Application::create([
            'user_id' => $applicantUser->id,
            'status'  => 'cleared_for_enrollment',
        ]);

        // Request with unmask=1
        $response = $this->actingAs($admin)->getJson('/confirmed-applicants/list?unmask=1');

        $response->assertOk();
        $data = $response->json();
        $first = $data[0];

        $this->assertFalse($first['is_masked']);
        $this->assertEquals('Real', $first['firstname']);
        $this->assertEquals('Student', $first['lastname']);
        $this->assertEquals('real.student@pup.edu.ph', $first['email']);

        // Verify AUDIT_ACCESS log entry was created
        $this->assertDatabaseHas('audit_logs', [
            'action_type'  => AuditLog::ACTION_READ,
            'log_category' => AuditLog::CATEGORY_AUDIT_ACCESS,
            'module_name'  => 'Admission Data',
        ]);
    }

    /** @test */
    public function unauthorized_role_cannot_bypass_masking_via_unmask_param()
    {
        // Interviewer role (role_id = 4)
        $interviewer = User::factory()->create(['role_id' => 4]);

        $response = $this->actingAs($interviewer)->getJson('/confirmed-applicants/list?unmask=1');

        $response->assertStatus(403);
    }

    /** @test */
    public function artisan_anonymize_user_command_runs_successfully()
    {
        $user = User::factory()->create([
            'email'     => 'target@pup.edu.ph',
            'firstname' => 'Target',
            'lastname'  => 'User',
        ]);

        // Test Dry Run
        $this->artisan("app:anonymize-user {$user->id} --dry-run")
            ->expectsOutputToContain('[DRY RUN] Simulation complete')
            ->assertExitCode(0);

        $this->assertFalse($user->fresh()->isAnonymized());

        // Test Live Run
        $this->artisan("app:anonymize-user {$user->id}")
            ->expectsConfirmation("Are you sure you want to permanently scrub all PII for User ID [{$user->id}]? This operation cannot be undone.", 'yes')
            ->expectsOutputToContain("User [{$user->id}] successfully anonymized")
            ->assertExitCode(0);

        $this->assertTrue($user->fresh()->isAnonymized());
    }

    /** @test */
    public function dev_login_bypasses_idp_and_redirects_to_dashboard()
    {
        $admin = User::factory()->create([
            'email'   => 'admin_dev_test@pup.edu.ph',
            'role_id' => 2,
        ]);

        $response = $this->get("/dev-login/{$admin->id}");

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->assertTrue(session('local_bypass'));
    }

    /** @test */
    public function test_passers_endpoint_returns_masked_data_by_default()
    {
        $admin = User::factory()->create(['role_id' => 2]);
        \App\Models\TestPasser::create([
            'first_name' => 'Alexander',
            'surname' => 'Hamilton',
            'email' => 'alexander.hamilton@test.edu.ph',
            'contact_number' => '09123456789',
            'reference_number' => 'REF-2026-99999',
            'passer_status_id' => 1,
            'school_year' => '2026-2027',
            'batch_number' => 'Batch 1',
        ]);

        $response = $this->actingAs($admin)->get('/test-passers');

        $response->assertOk();
        $passersData = $response->viewData('page')['props']['passers']['data'];
        $this->assertNotEmpty($passersData);
        $this->assertTrue($passersData[0]['is_masked']);
        $this->assertEquals('A***', $passersData[0]['first_name']);
        $this->assertEquals('H***', $passersData[0]['surname']);
        $this->assertEquals('al****on@test.edu.ph', $passersData[0]['email']);
    }

    /** @test */
    public function test_passers_endpoint_with_unmask_param_returns_unmasked_data_and_logs_audit_trail()
    {
        $admin = User::factory()->create(['role_id' => 2]);
        \App\Models\TestPasser::create([
            'first_name' => 'George',
            'surname' => 'Washington',
            'email' => 'george.washington@test.edu.ph',
            'passer_status_id' => 1,
            'school_year' => '2026-2027',
            'batch_number' => 'Batch 1',
        ]);

        $response = $this->actingAs($admin)->get('/test-passers?unmask=1');

        $response->assertOk();
        $passersData = $response->viewData('page')['props']['passers']['data'];
        $this->assertNotEmpty($passersData);
        $this->assertFalse($passersData[0]['is_masked']);
        $this->assertEquals('George', $passersData[0]['first_name']);
        $this->assertEquals('Washington', $passersData[0]['surname']);
        $this->assertEquals('george.washington@test.edu.ph', $passersData[0]['email']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'      => $admin->id,
            'action_type'  => 'READ',
            'log_category' => 'AUDIT_ACCESS',
        ]);
    }
}
