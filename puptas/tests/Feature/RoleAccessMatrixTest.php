<?php

namespace Tests\Feature;

use App\Enums\RoleId;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessMatrixTest extends TestCase
{
    use RefreshDatabase;
    /**
     * @dataProvider matrixDataProvider
     */
    public function test_role_access_matrix(string $groupName, string $method, string $uri, array $allowedRoles, ?int $testRole): void
    {
        // 1. Guest test
        if ($testRole === null) {
            $response = $method === 'POST'
                ? $this->post($uri, [])
                : $this->get($uri);

            $response->assertRedirect('/login');
            return;
        }

        // 2. Authenticated user with specific role
        /** @var User $user */
        $user = User::factory()->create([
            'role_id' => $testRole,
        ]);

        $response = $method === 'POST'
            ? $this->actingAs($user)->postJson($uri, [])
            : $this->actingAs($user)->getJson($uri);

        if (in_array($testRole, $allowedRoles, true)) {
            $this->assertNotEquals(
                403,
                $response->getStatusCode(),
                "Role {$testRole} should be permitted on [{$groupName}] {$method} {$uri}, but received 403 Forbidden."
            );
        } else {
            $this->assertEquals(
                403,
                $response->getStatusCode(),
                "Role {$testRole} should be forbidden on [{$groupName}] {$method} {$uri}, but received {$response->getStatusCode()}."
            );
        }
    }

    public static function matrixDataProvider(): array
    {
        $routeGroups = [
            'role:applicant' => [
                'method' => 'GET',
                'uri' => '/applicant-dashboard',
                'allowed' => [RoleId::Applicant->value],
            ],
            'role:2,3,7,8' => [
                'method' => 'POST',
                'uri' => '/evaluator/pass-application/999',
                'allowed' => [RoleId::Admin->value, RoleId::DocumentEvaluator->value, RoleId::SuperAdmin->value, RoleId::GradeEvaluator->value],
            ],
            'role:2,4,7' => [
                'method' => 'POST',
                'uri' => '/interviewer-dashboard/accept/999',
                'allowed' => [RoleId::Admin->value, RoleId::Interviewer->value, RoleId::SuperAdmin->value],
            ],
            'role:6' => [
                'method' => 'POST',
                'uri' => '/record-dashboard/tag/999',
                'allowed' => [RoleId::Registrar->value],
            ],
            'EnsureAdmin' => [
                'method' => 'POST',
                'uri' => '/programs',
                'allowed' => [RoleId::Admin->value, RoleId::SuperAdmin->value],
            ],
            'EnsureSuperAdmin' => [
                'method' => 'GET',
                'uri' => '/admin/cutoff-settings',
                'allowed' => [RoleId::SuperAdmin->value],
            ],
            'EnsureAdminOrRegistrar' => [
                'method' => 'GET',
                'uri' => '/test-passers',
                'allowed' => [RoleId::Admin->value, RoleId::Registrar->value, RoleId::SuperAdmin->value],
            ],
        ];

        $rolesToTest = [
            'guest'              => null,
            'role_1_applicant'   => RoleId::Applicant->value,
            'role_2_admin'       => RoleId::Admin->value,
            'role_3_doc_eval'    => RoleId::DocumentEvaluator->value,
            'role_4_interviewer' => RoleId::Interviewer->value,
            'role_5_medical'     => RoleId::Medical->value,
            'role_6_registrar'   => RoleId::Registrar->value,
            'role_7_superadmin'  => RoleId::SuperAdmin->value,
            'role_8_grade_eval'  => RoleId::GradeEvaluator->value,
        ];

        $cases = [];
        foreach ($routeGroups as $groupName => $routeInfo) {
            foreach ($rolesToTest as $roleLabel => $roleId) {
                $caseKey = "{$groupName} | {$roleLabel}";
                $cases[$caseKey] = [
                    $groupName,
                    $routeInfo['method'],
                    $routeInfo['uri'],
                    $routeInfo['allowed'],
                    $roleId,
                ];
            }
        }

        return $cases;
    }
}
