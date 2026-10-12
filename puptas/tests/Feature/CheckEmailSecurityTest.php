<?php

use App\Enums\RoleId;
use App\Models\TestPasser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('check-email');
});

test('unauthenticated request to /check-email redirects to login', function () {
    $this->postJson('/check-email', ['email' => 'anyone@example.com'])
        ->assertUnauthorized();
});

test('non-superadmin roles are forbidden from probing /check-email', function (int $roleId) {
    $user = User::factory()->create(['role_id' => $roleId]);

    $this->actingAs($user)
        ->postJson('/check-email', ['email' => 'probe@example.com'])
        ->assertForbidden();
})->with([
    'applicant (role 1)'       => RoleId::Applicant->value,
    'admin (role 2)'           => RoleId::Admin->value,
    'doc evaluator (role 3)'   => RoleId::DocumentEvaluator->value,
    'interviewer (role 4)'     => RoleId::Interviewer->value,
    'medical (role 5)'         => RoleId::Medical->value,
    'registrar (role 6)'       => RoleId::Registrar->value,
    'grade evaluator (role 8)' => RoleId::GradeEvaluator->value,
]);

test('superadmin (role 7) can check email and gets taken true when user exists', function () {
    $superadmin = User::factory()->create(['role_id' => RoleId::SuperAdmin->value]);
    User::factory()->create(['email' => 'existing@example.com']);

    $this->actingAs($superadmin)
        ->postJson('/check-email', ['email' => 'existing@example.com'])
        ->assertOk()
        ->assertExactJson(['taken' => true]);
});

test('superadmin (role 7) can check email and gets taken true when test passer exists', function () {
    $superadmin = User::factory()->create(['role_id' => RoleId::SuperAdmin->value]);
    TestPasser::factory()->create(['email' => 'passer@example.com']);

    $this->actingAs($superadmin)
        ->postJson('/check-email', ['email' => 'passer@example.com'])
        ->assertOk()
        ->assertExactJson(['taken' => true]);
});

test('superadmin (role 7) gets taken false when email does not exist', function () {
    $superadmin = User::factory()->create(['role_id' => RoleId::SuperAdmin->value]);

    $this->actingAs($superadmin)
        ->postJson('/check-email', ['email' => 'nonexistent@example.com'])
        ->assertOk()
        ->assertExactJson(['taken' => false]);
});

test('superadmin (role 7) is throttled after 30 requests in a minute', function () {
    $superadmin = User::factory()->create(['role_id' => RoleId::SuperAdmin->value]);

    for ($i = 1; $i <= 30; $i++) {
        $this->actingAs($superadmin)
            ->postJson('/check-email', ['email' => "test{$i}@example.com"])
            ->assertOk();
    }

    $this->actingAs($superadmin)
        ->postJson('/check-email', ['email' => 'test31@example.com'])
        ->assertStatus(429);
});
