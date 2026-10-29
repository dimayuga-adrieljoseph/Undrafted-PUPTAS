<?php

use App\Enums\RoleId;
use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\AuditLog;
use App\Models\Grade;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function sah_createProgram(string $code, array $overrides = []): Program
{
    return Program::create(array_merge([
        'code'     => $code,
        'name'     => $code . ' Program',
        'capacity' => 50,
        'slots'    => 10,
        'math'     => 85,
        'science'  => 85,
        'english'  => 85,
        'gwa'      => 85,
    ], $overrides));
}

// ─── 1. Staff Program Assignment Auditing ─────────────────────────

test('updating staff program assignments writes audit log with target user and old vs new program ids', function () {
    $superAdmin = User::factory()->create(['role_id' => RoleId::SuperAdmin->value]);
    $evaluator = User::factory()->create([
        'firstname' => 'Elena',
        'lastname'  => 'Reyes',
        'email'     => 'elena.reyes@example.com',
        'role_id'   => RoleId::DocumentEvaluator->value,
    ]);

    $prog1 = sah_createProgram('BSCS');
    $prog2 = sah_createProgram('BSIT');
    $prog3 = sah_createProgram('BSME');

    $evaluator->programs()->sync([
        $prog1->id => ['role_id' => RoleId::DocumentEvaluator->value],
    ]);

    $this->actingAs($superAdmin)
        ->put("/users/{$evaluator->id}", [
            'firstname' => 'Elena',
            'lastname'  => 'Reyes',
            'email'     => 'elena.reyes@example.com',
            'role_id'   => RoleId::DocumentEvaluator->value,
            'program'   => ['BSIT', 'BSME'],
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    $audit = AuditLog::latest('id')->first();
    expect($audit)->not->toBeNull();
    expect($audit->action_type)->toBe('UPDATE');
    expect($audit->module_name)->toBe('Users');
    expect($audit->description)->toContain('elena.reyes@example.com');
    expect($audit->description)->toContain("program_ids: [{$prog1->id}] -> [{$prog2->id}, {$prog3->id}]");
    expect($audit->old_values['program_ids'])->toBe([$prog1->id]);
    expect($audit->new_values['program_ids'])->toBe([$prog2->id, $prog3->id]);
});

// ─── 2. UserController::update Role & Password Auditing + Self-Guards ──

test('updating user role and password logs old/new role_id and password changed without exposing password value', function () {
    $superAdmin = User::factory()->create(['role_id' => RoleId::SuperAdmin->value]);
    $staffUser = User::factory()->create([
        'firstname' => 'Marco',
        'lastname'  => 'Santos',
        'email'     => 'marco.santos@example.com',
        'role_id'   => RoleId::Interviewer->value,
    ]);

    $secretPassword = 'MySuperSecretNewPassword!99';

    $this->actingAs($superAdmin)
        ->put("/users/{$staffUser->id}", [
            'firstname' => 'Marco',
            'lastname'  => 'Santos',
            'email'     => 'marco.santos@example.com',
            'role_id'   => RoleId::Registrar->value,
            'password'  => $secretPassword,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    $staffUser->refresh();
    expect((int) $staffUser->role_id)->toBe(RoleId::Registrar->value);
    expect(Hash::check($secretPassword, $staffUser->password))->toBeTrue();

    $audit = AuditLog::latest('id')->first();
    expect($audit)->not->toBeNull();
    expect($audit->description)->toContain('role_id: ' . RoleId::Interviewer->value . ' -> ' . RoleId::Registrar->value);
    expect($audit->description)->toContain('password changed');
    expect($audit->description)->not->toContain($secretPassword);
    expect(json_encode($audit->old_values))->not->toContain($secretPassword);
    expect(json_encode($audit->new_values))->not->toContain($secretPassword);
    expect($audit->old_values['role_id'])->toBe(RoleId::Interviewer->value);
    expect($audit->new_values['role_id'])->toBe(RoleId::Registrar->value);
    expect($audit->new_values['password_changed'])->toBeTrue();
});

test('superadmin cannot change their own role in update', function () {
    $superAdmin = User::factory()->create([
        'firstname' => 'Self',
        'lastname'  => 'Admin',
        'email'     => 'self.superadmin@example.com',
        'role_id'   => RoleId::SuperAdmin->value,
    ]);

    $this->actingAs($superAdmin)
        ->put("/users/{$superAdmin->id}", [
            'firstname' => 'Self',
            'lastname'  => 'Admin',
            'email'     => 'self.superadmin@example.com',
            'role_id'   => RoleId::Admin->value,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('error', 'You cannot change your own role.');

    $superAdmin->refresh();
    expect((int) $superAdmin->role_id)->toBe(RoleId::SuperAdmin->value);
    expect(AuditLog::count())->toBe(0);
});

test('superadmin cannot delete or deactivate their own account', function () {
    $superAdmin = User::factory()->create([
        'idp_user_id' => 'idp-self-superadmin-uuid',
        'role_id'     => RoleId::SuperAdmin->value,
        'is_active'   => true,
    ]);

    // Self-delete by numeric ID
    $this->actingAs($superAdmin)
        ->delete("/users/{$superAdmin->id}", ['reason' => 'Testing self delete'])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('error', 'You cannot delete your own account.');

    // Self-delete by IDP UUID
    $this->actingAs($superAdmin)
        ->delete("/users/{$superAdmin->idp_user_id}", ['reason' => 'Testing self delete by uuid'])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('error', 'You cannot delete your own account.');

    // Self-deactivate by numeric ID
    $this->actingAs($superAdmin)
        ->post("/users/{$superAdmin->id}/deactivate", ['reason' => 'Testing self deactivate'])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('error', 'You cannot deactivate your own account.');

    // Self-deactivate by IDP UUID
    $this->actingAs($superAdmin)
        ->post("/users/{$superAdmin->idp_user_id}/deactivate", ['reason' => 'Testing self deactivate by uuid'])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('error', 'You cannot deactivate your own account.');

    $superAdmin->refresh();
    expect($superAdmin->trashed())->toBeFalse();
    expect((bool) $superAdmin->is_active)->toBeTrue();
    expect(AuditLog::count())->toBe(0);
});

// ─── 3. Dead Route GET /admin/audit-logs/{id} Removed ─────────────

test('dead route GET /admin/audit-logs/{id} is removed and returns 404', function () {
    expect(Route::has('audit-logs.show'))->toBeFalse();

    $superAdmin = User::factory()->create(['role_id' => RoleId::SuperAdmin->value]);

    $this->actingAs($superAdmin)
        ->get('/admin/audit-logs/123')
        ->assertNotFound();
});

// ─── 4. InterviewerDashboardController::accept SuperAdmin Grade Override Audit ──

test('superadmin accepting applicant with below-requirement grades logs grade requirement override by SuperAdmin', function () {
    $superAdmin = User::factory()->create(['role_id' => RoleId::SuperAdmin->value]);
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    $program = sah_createProgram('BSCE', ['math' => 88, 'science' => 88, 'english' => 88]);

    $application = Application::create([
        'user_id'    => (string) $applicant->id,
        'program_id' => $program->id,
        'status'     => 'submitted',
    ]);

    ApplicationProcess::create(['application_id' => $application->id, 'stage' => 'document_evaluator', 'status' => 'completed', 'action' => 'passed']);
    ApplicationProcess::create(['application_id' => $application->id, 'stage' => 'grade_evaluator',    'status' => 'completed', 'action' => 'passed']);
    ApplicationProcess::create(['application_id' => $application->id, 'stage' => 'interviewer',        'status' => 'in_progress']);

    // Grades below 88 requirement
    Grade::create([
        'user_id'     => (string) $applicant->id,
        'mathematics' => 75,
        'science'     => 78,
        'english'     => 80,
    ]);

    $this->actingAs($superAdmin)
        ->postJson("/interviewer-dashboard/accept/{$applicant->id}", [
            'program_id' => $program->id,
            'notes'      => 'Approved via override',
        ])
        ->assertOk()
        ->assertJson(['message' => 'Application accepted.']);

    $audit = AuditLog::latest('id')->first();
    expect($audit)->not->toBeNull();
    expect($audit->action_type)->toBe('UPDATE');
    expect($audit->module_name)->toBe('Applications');
    expect($audit->description)->toBe(
        "Interviewer accepted application for applicant ID {$applicant->id} into program ID {$program->id}. (grade requirement override by SuperAdmin)"
    );
});

test('superadmin accepting applicant who meets grade requirements does not log grade override marker', function () {
    $superAdmin = User::factory()->create(['role_id' => RoleId::SuperAdmin->value]);
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    $program = sah_createProgram('BSEE', ['math' => 85, 'science' => 85, 'english' => 85]);

    $application = Application::create([
        'user_id'    => (string) $applicant->id,
        'program_id' => $program->id,
        'status'     => 'submitted',
    ]);

    ApplicationProcess::create(['application_id' => $application->id, 'stage' => 'document_evaluator', 'status' => 'completed', 'action' => 'passed']);
    ApplicationProcess::create(['application_id' => $application->id, 'stage' => 'grade_evaluator',    'status' => 'completed', 'action' => 'passed']);
    ApplicationProcess::create(['application_id' => $application->id, 'stage' => 'interviewer',        'status' => 'in_progress']);

    // Grades meet 85 requirement
    Grade::create([
        'user_id'     => (string) $applicant->id,
        'mathematics' => 90,
        'science'     => 91,
        'english'     => 89,
    ]);

    $this->actingAs($superAdmin)
        ->postJson("/interviewer-dashboard/accept/{$applicant->id}", [
            'program_id' => $program->id,
        ])
        ->assertOk();

    $audit = AuditLog::latest('id')->first();
    expect($audit)->not->toBeNull();
    expect($audit->description)->toBe(
        "Interviewer accepted application for applicant ID {$applicant->id} into program ID {$program->id}."
    );
    expect($audit->description)->not->toContain('grade requirement override by SuperAdmin');
});

test('non-superadmin (interviewer and admin) is still blocked with 400 when grades are below requirement', function (int $roleId) {
    $actor = User::factory()->create(['role_id' => $roleId]);
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    $program = sah_createProgram('BSME_' . $roleId, ['math' => 88, 'science' => 88, 'english' => 88]);

    if ($roleId === RoleId::Interviewer->value) {
        $actor->programs()->attach($program->id, ['role_id' => RoleId::Interviewer->value]);
    }

    $application = Application::create([
        'user_id'    => (string) $applicant->id,
        'program_id' => $program->id,
        'status'     => 'submitted',
    ]);

    ApplicationProcess::create(['application_id' => $application->id, 'stage' => 'document_evaluator', 'status' => 'completed', 'action' => 'passed']);
    ApplicationProcess::create(['application_id' => $application->id, 'stage' => 'grade_evaluator',    'status' => 'completed', 'action' => 'passed']);
    ApplicationProcess::create(['application_id' => $application->id, 'stage' => 'interviewer',        'status' => 'in_progress']);

    Grade::create([
        'user_id'     => (string) $applicant->id,
        'mathematics' => 75,
        'science'     => 78,
        'english'     => 80,
    ]);

    $this->actingAs($actor)
        ->postJson("/interviewer-dashboard/accept/{$applicant->id}", [
            'program_id' => $program->id,
        ])
        ->assertStatus(400)
        ->assertJson(['message' => 'User does not meet the grade requirements for this program.']);

    expect(AuditLog::count())->toBe(0);
})->with([
    'interviewer (role 4)' => RoleId::Interviewer->value,
    'admin (role 2)'       => RoleId::Admin->value,
]);

