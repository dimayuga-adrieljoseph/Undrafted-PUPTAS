<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Application;
use App\Services\IdpService;
use App\Jobs\UpdateIdpAccountType;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SyncEnrolledStudentsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminJwt;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.idp.base_url', 'https://test-idp.example.com');
        $this->adminJwt = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkFkbWluIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
    }

    /** @test */
    public function it_fails_when_admin_access_token_is_missing()
    {
        Session::forget('idp_access_token');
        Config::set('services.idp.admin_access_token', null);

        $this->artisan('idp:sync-enrolled-students', ['--force' => true])
            ->expectsOutputToContain('Admin access token not found')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_handles_dry_run_without_making_actual_idp_calls()
    {
        Session::put('idp_access_token', $this->adminJwt);
        Http::fake();

        $this->artisan('idp:sync-enrolled-students', ['--dry-run' => true, '--force' => true])
            ->expectsOutputToContain('No officially enrolled students found')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    /** @test */
    public function it_fails_immediately_when_first_student_sync_is_unauthorized()
    {
        Session::put('idp_access_token', $this->adminJwt);
        Queue::fake();

        // Create student user with idp_user_id
        $studentUser = User::factory()->create([
            'idp_user_id' => 'idp-student-1',
            'email' => 'student1@example.com',
            'role_id' => 1,
        ]);

        Application::create([
            'user_id' => $studentUser->id,
            'enrollment_status' => 'officially_enrolled',
            'status' => 'accepted',
        ]);

        // Mock 401 unauthorized with empty body from IDP
        Http::fake([
            'https://test-idp.example.com/api/v1/admin/users/idp-student-1' => Http::response('', 401),
        ]);

        $this->artisan('idp:sync-enrolled-students', [
            '--force' => true,
            '--mfa-code' => '123456',
        ])
            ->expectsOutputToContain('IDP Sync Failed')
            ->assertExitCode(1);

        // Queue should NOT have dispatched any sync jobs since the first sync failed
        Queue::assertNotPushed(UpdateIdpAccountType::class);
    }

    /** @test */
    public function it_verifies_first_student_with_mfa_and_queues_remaining_students_without_replaying_mfa()
    {
        Session::put('idp_access_token', $this->adminJwt);
        Queue::fake();

        // Create 2 student users
        $student1 = User::factory()->create([
            'idp_user_id' => 'idp-student-1',
            'email' => 'student1@example.com',
            'role_id' => 1,
        ]);
        Application::create([
            'user_id' => $student1->id,
            'enrollment_status' => 'officially_enrolled',
            'status' => 'accepted',
        ]);

        $student2 = User::factory()->create([
            'idp_user_id' => 'idp-student-2',
            'email' => 'student2@example.com',
            'role_id' => 1,
        ]);
        Application::create([
            'user_id' => $student2->id,
            'enrollment_status' => 'officially_enrolled',
            'status' => 'accepted',
        ]);

        // Mock successful sync for first student
        Http::fake([
            'https://test-idp.example.com/api/v1/admin/users/idp-student-1' => Http::response([
                'message' => 'User account type updated successfully',
            ], 200),
        ]);

        $this->artisan('idp:sync-enrolled-students', [
            '--force' => true,
            '--mfa-code' => '123456',
        ])
            ->expectsOutputToContain('Verified with IDP')
            ->assertExitCode(0);

        // Verify first student was sent MFA code directly
        Http::assertSent(function ($request) {
            return $request->url() === 'https://test-idp.example.com/api/v1/admin/users/idp-student-1' &&
                   $request['mfa_code'] === '123456' &&
                   $request['account_type_id'] === 3 &&
                   $request['role_id'] === 0;
        });

        // Verify remaining student was queued with mfaCode = null so TOTP is not replayed
        Queue::assertPushed(UpdateIdpAccountType::class, function ($job) {
            $ref = new \ReflectionClass($job);
            $prop = $ref->getProperty('mfaCode');
            $prop->setAccessible(true);
            return $prop->getValue($job) === null;
        });
    }

    /** @test */
    public function it_returns_422_with_extracted_error_when_trigger_sync_fails()
    {
        Session::put('idp_access_token', $this->adminJwt);

        $superAdmin = User::factory()->create([
            'role_id' => 7,
            'email' => 'superadmin@example.com',
        ]);

        $student = User::factory()->create([
            'idp_user_id' => 'idp-student-1',
            'email' => 'student1@example.com',
            'role_id' => 1,
        ]);
        Application::create([
            'user_id' => $student->id,
            'enrollment_status' => 'officially_enrolled',
            'status' => 'accepted',
        ]);

        // Mock 401 unauthorized from IDP
        Http::fake([
            'https://test-idp.example.com/api/v1/admin/users/idp-student-1' => Http::response('', 401),
        ]);

        $response = $this->actingAs($superAdmin)
            ->postJson('/api/super-admin/idp-sync-monitor/trigger-sync', [
                'mfa_code' => '123456',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringContainsString('IDP Authentication Failed', $response->json('message'));
    }

    /** @test */
    public function it_returns_session_based_sudo_status_in_metrics()
    {
        $superAdmin = User::factory()->create([
            'role_id' => 7,
            'email' => 'superadmin@example.com',
        ]);

        $idpService = app(IdpService::class);
        $idpService->clearSudoToken();

        $response = $this->actingAs($superAdmin)
            ->getJson('/api/super-admin/idp-sync-monitor/metrics');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'queue_status' => [
                        'sudo_active' => false,
                        'sudo_session_based' => true,
                    ],
                ],
            ]);

        // Cache a valid session sudo token
        $idpService->cacheSudoToken($this->adminJwt);

        $responseActive = $this->actingAs($superAdmin)
            ->getJson('/api/super-admin/idp-sync-monitor/metrics');

        $responseActive->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'queue_status' => [
                        'sudo_active' => true,
                        'sudo_session_based' => true,
                    ],
                ],
            ]);
    }
}
