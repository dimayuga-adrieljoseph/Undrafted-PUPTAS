<?php

use App\Models\User;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Test suite for Schedule Route Authentication
 * 
 * Validates Requirements 2.1, 2.2, 2.3, 2.4 from high-priority-security-fixes spec
 */

test('unauthenticated user accessing schedule index redirects to login', function () {
    $response = $this->get(route('schedules.index'));
    
    $response->assertRedirect('/auth/idp/redirect');
});

test('unauthenticated user accessing schedule create redirects to login', function () {
    $response = $this->get(route('schedules.create'));
    
    $response->assertRedirect('/auth/idp/redirect');
});

test('unauthenticated user accessing schedule store redirects to login', function () {
    $response = $this->post(route('schedules.store'), [
        'name' => 'Test Schedule',
        'start' => now()->addDays(1)->toDateTimeString(),
        'end' => now()->addDays(2)->toDateTimeString(),
        'type' => 'interview',
    ]);
    
    $response->assertRedirect('/auth/idp/redirect');
});

test('unauthenticated user accessing schedule show redirects to login', function () {
    $schedule = Schedule::create([
        'name' => 'Test Schedule',
        'start' => now()->addDays(1),
        'end' => now()->addDays(2),
        'type' => 'interview',
        'created_by' => 1,
    ]);
    
    $response = $this->get(route('schedules.show', $schedule->id));
    
    $response->assertRedirect('/auth/idp/redirect');
});

test('unauthenticated user accessing schedule edit redirects to login', function () {
    $schedule = Schedule::create([
        'name' => 'Test Schedule',
        'start' => now()->addDays(1),
        'end' => now()->addDays(2),
        'type' => 'interview',
        'created_by' => 1,
    ]);
    
    $response = $this->get(route('schedules.edit', $schedule->id));
    
    $response->assertRedirect('/auth/idp/redirect');
});

test('unauthenticated user accessing schedule update redirects to login', function () {
    $schedule = Schedule::create([
        'name' => 'Test Schedule',
        'start' => now()->addDays(1),
        'end' => now()->addDays(2),
        'type' => 'interview',
        'created_by' => 1,
    ]);
    
    $response = $this->put(route('schedules.update', $schedule->id), [
        'name' => 'Updated Schedule',
        'start' => now()->addDays(1)->toDateTimeString(),
        'end' => now()->addDays(2)->toDateTimeString(),
        'type' => 'interview',
    ]);
    
    $response->assertRedirect('/auth/idp/redirect');
});

test('unauthenticated user accessing schedule destroy redirects to login', function () {
    $schedule = Schedule::create([
        'name' => 'Test Schedule',
        'start' => now()->addDays(1),
        'end' => now()->addDays(2),
        'type' => 'interview',
        'created_by' => 1,
    ]);
    
    $response = $this->delete(route('schedules.destroy', $schedule->id));
    
    $response->assertRedirect('/auth/idp/redirect');
});

test('authenticated admin (role 2) can access schedule index', function () {
    $admin = User::factory()->create(['role_id' => 2]);
    
    $response = $this->actingAs($admin)->get(route('schedules.index'));
    
    $response->assertOk();
});

test('authenticated admin (role 2) can access schedule show', function () {
    $admin = User::factory()->create(['role_id' => 2]);
    $schedule = Schedule::create([
        'name' => 'Test Schedule',
        'start' => now()->addDays(1),
        'end' => now()->addDays(2),
        'type' => 'interview',
        'created_by' => $admin->id,
    ]);
    
    $response = $this->actingAs($admin)->getJson(route('schedules.show', $schedule->id));
    
    $response->assertOk();
});

test('authenticated admin (role 2) can create schedule', function () {
    $admin = User::factory()->create(['role_id' => 2]);
    
    $response = $this->actingAs($admin)->postJson(route('schedules.store'), [
        'name' => 'Admin Created Schedule',
        'start' => now()->addDays(1)->toDateTimeString(),
        'end' => now()->addDays(2)->toDateTimeString(),
        'type' => 'interview',
        'description' => 'Test description',
        'location' => 'Test location',
    ]);
    
    $response->assertCreated();
    $this->assertDatabaseHas('schedules', [
        'name' => 'Admin Created Schedule',
    ]);
});

test('authenticated admin (role 2) can update schedule', function () {
    $admin = User::factory()->create(['role_id' => 2]);
    $schedule = Schedule::create([
        'name' => 'Test Schedule',
        'start' => now()->addDays(1),
        'end' => now()->addDays(2),
        'type' => 'interview',
        'created_by' => $admin->id,
    ]);
    
    $response = $this->actingAs($admin)->putJson(route('schedules.update', $schedule->id), [
        'name' => 'Updated by Admin',
        'start' => now()->addDays(1)->toDateTimeString(),
        'end' => now()->addDays(2)->toDateTimeString(),
        'type' => 'interview',
        'description' => 'Updated description',
        'location' => 'Updated location',
    ]);
    
    $response->assertOk();
    $this->assertDatabaseHas('schedules', [
        'id' => $schedule->id,
        'name' => 'Updated by Admin',
    ]);
});

test('authenticated admin (role 2) can delete schedule', function () {
    $admin = User::factory()->create(['role_id' => 2]);
    $schedule = Schedule::create([
        'name' => 'Test Schedule',
        'start' => now()->addDays(1),
        'end' => now()->addDays(2),
        'type' => 'interview',
        'created_by' => $admin->id,
    ]);
    
    $response = $this->actingAs($admin)->deleteJson(route('schedules.destroy', $schedule->id));
    
    $response->assertNoContent();
    $this->assertDatabaseMissing('schedules', [
        'id' => $schedule->id,
    ]);
});

test('authenticated interviewer (role 4) can access schedule index', function () {
    $interviewer = User::factory()->create(['role_id' => 4]);
    
    $response = $this->actingAs($interviewer)->get(route('schedules.index'));
    
    $response->assertOk();
});

test('authenticated interviewer (role 4) can access schedule show', function () {
    $interviewer = User::factory()->create(['role_id' => 4]);
    $schedule = Schedule::create([
        'name' => 'Test Schedule',
        'start' => now()->addDays(1),
        'end' => now()->addDays(2),
        'type' => 'interview',
        'created_by' => $interviewer->id,
    ]);
    
    $response = $this->actingAs($interviewer)->getJson(route('schedules.show', $schedule->id));
    
    $response->assertOk();
});

test('authenticated interviewer (role 4) can create schedule', function () {
    $interviewer = User::factory()->create(['role_id' => 4]);
    
    $response = $this->actingAs($interviewer)->postJson(route('schedules.store'), [
        'name' => 'Interviewer Created Schedule',
        'start' => now()->addDays(1)->toDateTimeString(),
        'end' => now()->addDays(2)->toDateTimeString(),
        'type' => 'interview',
        'description' => 'Test description',
        'location' => 'Test location',
    ]);
    
    $response->assertCreated();
    $this->assertDatabaseHas('schedules', [
        'name' => 'Interviewer Created Schedule',
    ]);
});

test('authenticated interviewer (role 4) can update schedule', function () {
    $interviewer = User::factory()->create(['role_id' => 4]);
    $schedule = Schedule::create([
        'name' => 'Test Schedule',
        'start' => now()->addDays(1),
        'end' => now()->addDays(2),
        'type' => 'interview',
        'created_by' => $interviewer->id,
    ]);
    
    $response = $this->actingAs($interviewer)->putJson(route('schedules.update', $schedule->id), [
        'name' => 'Updated by Interviewer',
        'start' => now()->addDays(1)->toDateTimeString(),
        'end' => now()->addDays(2)->toDateTimeString(),
        'type' => 'interview',
        'description' => 'Updated description',
        'location' => 'Updated location',
    ]);
    
    $response->assertOk();
    $this->assertDatabaseHas('schedules', [
        'id' => $schedule->id,
        'name' => 'Updated by Interviewer',
    ]);
});

test('authenticated interviewer (role 4) can delete schedule', function () {
    $interviewer = User::factory()->create(['role_id' => 4]);
    $schedule = Schedule::create([
        'name' => 'Test Schedule',
        'start' => now()->addDays(1),
        'end' => now()->addDays(2),
        'type' => 'interview',
        'created_by' => $interviewer->id,
    ]);
    
    $response = $this->actingAs($interviewer)->deleteJson(route('schedules.destroy', $schedule->id));
    
    $response->assertNoContent();
    $this->assertDatabaseMissing('schedules', [
        'id' => $schedule->id,
    ]);
});

test('authenticated applicant (role 1) receives 403 when accessing schedule index', function () {
    $applicant = User::factory()->create(['role_id' => 1]);
    
    $response = $this->actingAs($applicant)->get(route('schedules.index'));
    
    $response->assertForbidden();
});

test('authenticated applicant (role 1) receives 403 when accessing schedule show', function () {
    $applicant = User::factory()->create(['role_id' => 1]);
    $schedule = Schedule::create([
        'name' => 'Test Schedule',
        'start' => now()->addDays(1),
        'end' => now()->addDays(2),
        'type' => 'interview',
        'created_by' => 1,
    ]);
    
    $response = $this->actingAs($applicant)->getJson(route('schedules.show', $schedule->id));
    
    $response->assertForbidden();
});

test('authenticated applicant (role 1) receives 403 when creating schedule', function () {
    $applicant = User::factory()->create(['role_id' => 1]);
    
    $response = $this->actingAs($applicant)->postJson(route('schedules.store'), [
        'name' => 'Applicant Schedule',
        'start' => now()->addDays(1)->toDateTimeString(),
        'end' => now()->addDays(2)->toDateTimeString(),
        'type' => 'interview',
    ]);
    
    $response->assertForbidden();
});

test('authenticated applicant (role 1) receives 403 when updating schedule', function () {
    $applicant = User::factory()->create(['role_id' => 1]);
    $schedule = Schedule::create([
        'name' => 'Test Schedule',
        'start' => now()->addDays(1),
        'end' => now()->addDays(2),
        'type' => 'interview',
        'created_by' => 1,
    ]);
    
    $response = $this->actingAs($applicant)->putJson(route('schedules.update', $schedule->id), [
        'name' => 'Updated Schedule',
        'start' => now()->addDays(1)->toDateTimeString(),
        'end' => now()->addDays(2)->toDateTimeString(),
        'type' => 'interview',
    ]);
    
    $response->assertForbidden();
});

test('authenticated applicant (role 1) receives 403 when deleting schedule', function () {
    $applicant = User::factory()->create(['role_id' => 1]);
    $schedule = Schedule::create([
        'name' => 'Test Schedule',
        'start' => now()->addDays(1),
        'end' => now()->addDays(2),
        'type' => 'interview',
        'created_by' => 1,
    ]);
    
    $response = $this->actingAs($applicant)->deleteJson(route('schedules.destroy', $schedule->id));
    
    $response->assertForbidden();
});

test('authenticated evaluator (role 3) receives 403 when accessing schedule index', function () {
    $evaluator = User::factory()->create(['role_id' => 3]);
    
    $response = $this->actingAs($evaluator)->get(route('schedules.index'));
    
    $response->assertForbidden();
});

test('authenticated record staff (role 6) receives 403 when accessing schedule index', function () {
    $recordStaff = User::factory()->create(['role_id' => 6]);
    
    $response = $this->actingAs($recordStaff)->get(route('schedules.index'));
    
    $response->assertForbidden();
});

test('authenticated registrar (role 7) receives 403 when accessing schedule index', function () {
    $registrar = User::factory()->create(['role_id' => 7]);
    
    $response = $this->actingAs($registrar)->get(route('schedules.index'));
    
    $response->assertForbidden();
});
