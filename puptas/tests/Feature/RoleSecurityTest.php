<?php

use App\Enums\RoleId;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Security hardening coverage:
 *  1. Applicant-only routes are gated by role middleware (staff cannot reach them).
 *  2. Program management routes are gated by admin middleware.
 *  3. role_id is no longer mass-assignable (no self-escalation).
 */

test('admin (role 2) is blocked from the applicant dashboard', function () {
    $admin = User::factory()->create(['role_id' => RoleId::Admin->value]);

    $this->actingAs($admin)->get('/applicant-dashboard')->assertForbidden();
});

test('evaluator (role 3) is blocked from the applicant profile', function () {
    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);

    $this->actingAs($evaluator)->get('/applicant-profile')->assertForbidden();
});

test('interviewer (role 4) is blocked from submitting grades', function () {
    $interviewer = User::factory()->create(['role_id' => RoleId::Interviewer->value]);

    $this->actingAs($interviewer)->post('/grades/store')->assertForbidden();
});

test('admin (role 2) is blocked from the applicant application view', function () {
    $admin = User::factory()->create(['role_id' => RoleId::Admin->value]);

    $this->actingAs($admin)->get('/user/application')->assertForbidden();
});

test('admin (role 2) is blocked from submitting the applicant application', function () {
    $admin = User::factory()->create(['role_id' => RoleId::Admin->value]);

    $this->actingAs($admin)->post('/user/application/submit')->assertForbidden();
});

test('admin (role 2) is blocked from the grade verification slip', function () {
    $admin = User::factory()->create(['role_id' => RoleId::Admin->value]);

    $this->actingAs($admin)->get('/applicant-dashboard/grade-verification-slip')->assertForbidden();
});

test('applicant (role 1) is blocked from creating programs', function () {
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);

    $this->actingAs($applicant)->post('/programs', [
        'code' => 'BSX',
        'name' => 'Test Program',
        'slots' => 10,
    ])->assertForbidden();
});

test('evaluator (role 3) is blocked from the add program form', function () {
    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);

    $this->actingAs($evaluator)->get('/addindex')->assertForbidden();
});

test('role_id is not in the User mass-assignment allowlist', function () {
    expect(in_array('role_id', (new User)->getFillable(), true))->toBeFalse();
});

test('role_id cannot be self-escalated via create even when injected', function () {
    $user = User::create([
        'firstname' => 'Test',
        'lastname' => 'User',
        'email' => 'escalation@test.com',
        'password' => bcrypt('password'),
        'role_id' => RoleId::SuperAdmin->value,
    ]);

    expect($user->fresh()->role_id)->toBe(RoleId::Applicant->value);
});

test('assignRole sets a role explicitly and persists it', function () {
    $user = User::create([
        'firstname' => 'Test',
        'lastname' => 'User',
        'email' => 'assignrole@test.com',
        'password' => bcrypt('password'),
    ]);

    $user->assignRole(RoleId::Admin->value)->save();

    expect($user->fresh()->role_id)->toBe(RoleId::Admin->value);
});