<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\IdpService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

class IdpServiceTest extends TestCase
{
    protected IdpService $idpService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->idpService = new IdpService();
        
        // Set up IDP configuration
        Config::set('services.idp.base_url', 'https://test-idp.example.com');
    }

    /** @test */
    public function it_successfully_updates_user_account_type_without_mfa()
    {
        Http::fake([
            'https://test-idp.example.com/api/v1/admin/users/test-user-123' => Http::response([
                'message' => 'User details updated successfully',
                'user' => [
                    'id' => 'test-user-123',
                    'account_type_id' => 3,
                ],
            ], 200),
        ]);

        $result = $this->idpService->updateUserAccountType(
            'test-user-123',
            3,
            'fake-admin-token'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('User details updated successfully', $result['message']);
        
        Http::assertSent(function ($request) {
            return $request->url() === 'https://test-idp.example.com/api/v1/admin/users/test-user-123' &&
                   $request->method() === 'PATCH' &&
                   $request['account_type_id'] === 3 &&
                   !isset($request['mfa_code']);
        });
    }

    /** @test */
    public function it_handles_mfa_required_response()
    {
        Http::fake([
            'https://test-idp.example.com/api/v1/admin/users/test-user-123' => Http::response([
                'error' => 'MFA code is required.',
                'title' => 'MFA Required',
            ], 400),
        ]);

        $result = $this->idpService->updateUserAccountType(
            'test-user-123',
            3,
            'fake-admin-token'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('MFA_REQUIRED', $result['error']);
        $this->assertTrue($result['requires_mfa'] ?? false);
    }

    /** @test */
    public function it_successfully_updates_with_mfa_code()
    {
        Http::fake([
            'https://test-idp.example.com/api/v1/admin/users/test-user-123' => Http::response([
                'message' => 'User details updated successfully',
            ], 200),
        ]);

        $result = $this->idpService->updateUserAccountType(
            'test-user-123',
            3,
            'fake-admin-token',
            '123456'
        );

        $this->assertTrue($result['success']);
        
        Http::assertSent(function ($request) {
            return $request['mfa_code'] === '123456';
        });
    }

    /** @test */
    public function it_handles_unauthorized_response()
    {
        Http::fake([
            'https://test-idp.example.com/api/v1/admin/users/test-user-123' => Http::response([
                'error' => 'Invalid MFA code or insufficient permissions',
            ], 401),
        ]);

        $result = $this->idpService->updateUserAccountType(
            'test-user-123',
            3,
            'fake-admin-token'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('UNAUTHORIZED', $result['error']);
    }

    /** @test */
    public function it_handles_user_not_found()
    {
        Http::fake([
            'https://test-idp.example.com/api/v1/admin/users/nonexistent-user' => Http::response([
                'error' => 'User not found',
            ], 404),
        ]);

        $result = $this->idpService->updateUserAccountType(
            'nonexistent-user',
            3,
            'fake-admin-token'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('USER_NOT_FOUND', $result['error']);
    }

    /** @test */
    public function it_handles_idp_configuration_missing()
    {
        Config::set('services.idp.base_url', null);

        $result = $this->idpService->updateUserAccountType(
            'test-user-123',
            3,
            'fake-admin-token'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('IDP_CONFIG_INCOMPLETE', $result['error']);
    }

    /** @test */
    public function it_gets_admin_token_from_session()
    {
        // Use a properly formatted JWT token for testing
        $testToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
        Session::put('idp_access_token', $testToken);

        $token = $this->idpService->getAdminAccessToken();

        $this->assertEquals($testToken, $token);
    }

    /** @test */
    public function it_gets_admin_token_from_config_as_fallback()
    {
        // Use a properly formatted JWT token for testing
        $testToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
        Config::set('services.idp.admin_access_token', $testToken);

        $token = $this->idpService->getAdminAccessToken();

        $this->assertEquals($testToken, $token);
    }

    /** @test */
    public function it_returns_null_when_no_admin_token_available()
    {
        $token = $this->idpService->getAdminAccessToken();

        $this->assertNull($token);
    }

    /** @test */
    public function it_handles_network_exceptions()
    {
        Http::fake(function () {
            throw new \Exception('Connection timeout');
        });

        $result = $this->idpService->updateUserAccountType(
            'test-user-123',
            3,
            'fake-admin-token'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('EXCEPTION', $result['error']);
        $this->assertStringContainsString('Connection timeout', $result['message']);
    }

    /** @test */
    public function it_rejects_invalid_jwt_format_in_session()
    {
        // Store an invalid token (not JWT format)
        Session::put('idp_access_token', 'invalid-token-format');

        $token = $this->idpService->getAdminAccessToken();

        // Should return null because token format is invalid
        $this->assertNull($token);
    }

    /** @test */
    public function it_validates_account_type_id()
    {
        $result = $this->idpService->updateUserAccountType(
            'test-user-123',
            2, // Invalid: trying to set to admin account type
            'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_ACCOUNT_TYPE', $result['error']);
    }

    /** @test */
    public function it_validates_mfa_code_format()
    {
        $result = $this->idpService->updateUserAccountType(
            'test-user-123',
            3,
            'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c',
            'abc123' // Invalid: should be 6 digits
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_MFA_FORMAT', $result['error']);
    }

    /** @test */
    public function it_handles_unauthorized_empty_body_response_with_clear_admin_permission_message()
    {
        Http::fake([
            'https://test-idp.example.com/api/v1/admin/users/test-user-123' => Http::response('', 401),
        ]);

        $testJwt = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI2NDQyNzY2ZC0yNjMyLTQ4ODAtOTc0Yy01ODUxOWQ3ZmVjMzMiLCJlbWFpbCI6InB1cHRhZG1pc3Npb25AZ21haWwuY29tIiwicm9sZSI6InN1cGVyYWRtaW4iLCJpYXQiOjE1MTYyMzkwMjJ9.dummy';

        $result = $this->idpService->updateUserAccountType(
            'test-user-123',
            3,
            $testJwt
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('UNAUTHORIZED', $result['error']);
        $this->assertStringContainsString('IDP Authentication Failed', $result['message']);
        $this->assertStringContainsString('puptadmission@gmail.com', $result['message']);
    }

    /** @test */
    public function it_sends_role_id_zero_and_no_extraneous_headers()
    {
        Http::fake([
            'https://test-idp.example.com/api/v1/admin/users/test-user-123' => Http::response([
                'message' => 'User details updated successfully',
            ], 200),
        ]);

        $this->idpService->updateUserAccountType(
            'test-user-123',
            3,
            'fake-token',
            '654321'
        );

        Http::assertSent(function ($request) {
            return $request['account_type_id'] === 3 &&
                   $request['role_id'] === 0 &&
                   $request['mfa_code'] === '654321' &&
                   !isset($request['otp']) &&
                   !isset($request['code']) &&
                   !$request->hasHeader('X-MFA-Code') &&
                   !$request->hasHeader('X-OTP') &&
                   $request->hasHeader('Cookie') &&
                   str_contains($request->header('Cookie')[0], 'access_token=fake-token') &&
                   $request->hasHeader('Authorization') &&
                   $request->header('Authorization')[0] === 'Bearer fake-token';
        });
    }
}
