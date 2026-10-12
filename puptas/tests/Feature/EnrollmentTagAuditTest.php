<?php

use App\Enums\RoleId;
use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\AuditLog;
use App\Models\Program;
use App\Models\User;
use App\Models\UserFile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function getOrCreateProgram(): Program
{
    return Program::firstOrCreate(
        ['code' => 'BSCS'],
        [
            'name'     => 'BS Computer Science',
            'capacity' => 50,
            'slots'    => 50,
        ]
    );
}

function createEligibleApplicationForTag(string $userId): Application
{
    $program = getOrCreateProgram();

    $application = Application::create([
        'user_id'           => $userId,
        'program_id'        => $program->id,
        'status'            => 'interview_passed',
        'enrollment_status' => 'temporary',
    ]);

    // Medical process completed
    ApplicationProcess::create([
        'application_id' => $application->id,
        'stage'          => 'medical',
        'status'         => 'completed',
    ]);

    // Uploaded COR Front & Back
    UserFile::create([
        'user_id'   => $userId,
        'type'      => 'cor_front',
        'file_path' => 'cor/front.jpg',
    ]);

    UserFile::create([
        'user_id'   => $userId,
        'type'      => 'cor_back',
        'file_path' => 'cor/back.jpg',
    ]);

    return $application;
}

function createOfficiallyEnrolledApplication(string $userId): Application
{
    $program = getOrCreateProgram();

    $application = Application::create([
        'user_id'           => $userId,
        'program_id'        => $program->id,
        'status'            => 'accepted',
        'enrollment_status' => 'officially_enrolled',
    ]);

    ApplicationProcess::create([
        'application_id' => $application->id,
        'stage'          => 'records',
        'status'         => 'completed',
    ]);

    return $application;
}

// ─── 1. Validation Tests ──────────────────────────────────────────

test('missing, whitespace-only, or too-short reason returns 422 on tag', function ($invalidReason) {
    $registrar = User::factory()->create(['role_id' => RoleId::Registrar->value]);
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    createEligibleApplicationForTag((string) $applicant->id);

    $payload = $invalidReason === null ? [] : ['reason' => $invalidReason];

    $this->actingAs($registrar)
        ->postJson("/record-dashboard/tag/{$applicant->id}", $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);

    // Ensure status was not updated and no audit row written
    expect(AuditLog::count())->toBe(0);
})->with([
    'missing'         => null,
    'empty string'    => '',
    'whitespace only' => '    ',
    'too short (3)'   => 'abc',
    'too short (4)'   => 'abcd',
]);

test('missing, whitespace-only, or too-short reason returns 422 on untag', function ($invalidReason) {
    $registrar = User::factory()->create(['role_id' => RoleId::Registrar->value]);
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    createOfficiallyEnrolledApplication((string) $applicant->id);

    $payload = $invalidReason === null ? [] : ['reason' => $invalidReason];

    $this->actingAs($registrar)
        ->postJson("/record-dashboard/untag/{$applicant->id}", $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);

    // Ensure status was not reverted and no audit row written
    expect(AuditLog::count())->toBe(0);
})->with([
    'missing'         => null,
    'empty string'    => '',
    'whitespace only' => '    ',
    'too short (3)'   => 'abc',
    'too short (4)'   => 'abcd',
]);

// ─── 2. Audit Trail & Status Transition on Valid Tag ─────────────

test('valid tag writes exactly one audit row with reason, previous and new status', function () {
    $registrar = User::factory()->create(['role_id' => RoleId::Registrar->value]);
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    $application = createEligibleApplicationForTag((string) $applicant->id);

    $this->actingAs($registrar)
        ->postJson("/record-dashboard/tag/{$applicant->id}", [
            'reason' => 'All enrollment credentials verified and valid.',
        ])
        ->assertOk()
        ->assertJson([
            'enrollment_status'  => 'officially_enrolled',
            'application_status' => 'accepted',
        ]);

    $application->refresh();
    expect($application->enrollment_status)->toBe('officially_enrolled');
    expect($application->status)->toBe('accepted');

    // Audit Log check
    expect(AuditLog::count())->toBe(1);
    $audit = AuditLog::first();
    expect($audit->action_type)->toBe('UPDATE');
    expect($audit->module_name)->toBe('Applications');
    expect((int) $audit->user_id)->toBe((int) $registrar->id);
    expect($audit->description)->toContain((string) $applicant->id);
    expect($audit->description)->toContain('interview_passed');
    expect($audit->description)->toContain('accepted');
    expect($audit->description)->toContain('temporary');
    expect($audit->description)->toContain('officially_enrolled');
    expect($audit->description)->toContain('All enrollment credentials verified and valid.');
});

// ─── 3. Audit Trail & Status Transition on Valid Untag ───────────

test('valid untag writes exactly one audit row with reason, previous and new status', function () {
    $registrar = User::factory()->create(['role_id' => RoleId::Registrar->value]);
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    $application = createOfficiallyEnrolledApplication((string) $applicant->id);

    $this->actingAs($registrar)
        ->postJson("/record-dashboard/untag/{$applicant->id}", [
            'reason' => 'Applicant requested cancellation of enrollment registration.',
        ])
        ->assertOk();

    $application->refresh();
    expect($application->enrollment_status)->toBe('temporary');
    expect($application->status)->toBe('cleared_for_enrollment');

    // Audit Log check
    expect(AuditLog::count())->toBe(1);
    $audit = AuditLog::first();
    expect($audit->action_type)->toBe('UPDATE');
    expect($audit->module_name)->toBe('Applications');
    expect((int) $audit->user_id)->toBe((int) $registrar->id);
    expect($audit->description)->toContain((string) $applicant->id);
    expect($audit->description)->toContain('officially_enrolled');
    expect($audit->description)->toContain('temporary');
    expect($audit->description)->toContain('Applicant requested cancellation of enrollment registration.');
});

// ─── 4. Guard Failures Write No Audit Row ────────────────────────

test('failed guard on tag writes no audit row and changes no status', function () {
    $registrar = User::factory()->create(['role_id' => RoleId::Registrar->value]);
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    $program = getOrCreateProgram();

    // Application without completed medical or uploaded COR
    $application = Application::create([
        'user_id'           => (string) $applicant->id,
        'program_id'        => $program->id,
        'status'            => 'interview_passed',
        'enrollment_status' => 'temporary',
    ]);

    $this->actingAs($registrar)
        ->postJson("/record-dashboard/tag/{$applicant->id}", [
            'reason' => 'Attempting tag without completed medical',
        ])
        ->assertStatus(403);

    $application->refresh();
    expect($application->enrollment_status)->toBe('temporary');
    expect(AuditLog::count())->toBe(0);
});

test('failed guard on untag writes no audit row and changes no status', function () {
    $registrar = User::factory()->create(['role_id' => RoleId::Registrar->value]);
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    $program = getOrCreateProgram();

    // Application that is not officially enrolled
    $application = Application::create([
        'user_id'           => (string) $applicant->id,
        'program_id'        => $program->id,
        'status'            => 'cleared_for_enrollment',
        'enrollment_status' => 'temporary',
    ]);

    $this->actingAs($registrar)
        ->postJson("/record-dashboard/untag/{$applicant->id}", [
            'reason' => 'Attempting untag on temporary application',
        ])
        ->assertStatus(400);

    $application->refresh();
    expect($application->enrollment_status)->toBe('temporary');
    expect(AuditLog::count())->toBe(0);
});

// ─── 5. Role Authorization Tests ──────────────────────────────────

test('non-permitted roles (1, 3, 4, 5, 8) receive 403 on tag and untag', function (int $roleId) {
    $user = User::factory()->create(['role_id' => $roleId]);
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    createEligibleApplicationForTag((string) $applicant->id);

    // Tag route
    $this->actingAs($user)
        ->postJson("/record-dashboard/tag/{$applicant->id}", [
            'reason' => 'Unauthorized tag attempt',
        ])
        ->assertForbidden();

    // Untag route
    $this->actingAs($user)
        ->postJson("/record-dashboard/untag/{$applicant->id}", [
            'reason' => 'Unauthorized untag attempt',
        ])
        ->assertForbidden();

    expect(AuditLog::count())->toBe(0);
})->with([
    'applicant (role 1)'       => RoleId::Applicant->value,
    'doc evaluator (role 3)'   => RoleId::DocumentEvaluator->value,
    'interviewer (role 4)'     => RoleId::Interviewer->value,
    'medical (role 5)'         => RoleId::Medical->value,
    'grade evaluator (role 8)' => RoleId::GradeEvaluator->value,
]);

test('registrar (6), admin (2), and superadmin (7) are permitted on tag and untag', function (int $roleId, string $routePrefix) {
    $staff = User::factory()->create(['role_id' => $roleId]);
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    createEligibleApplicationForTag((string) $applicant->id);

    // Tag
    $this->actingAs($staff)
        ->postJson("{$routePrefix}/tag/{$applicant->id}", [
            'reason' => 'Authorized staff enrollment tag',
        ])
        ->assertOk();

    // Untag
    $this->actingAs($staff)
        ->postJson("{$routePrefix}/untag/{$applicant->id}", [
            'reason' => 'Authorized staff enrollment untag',
        ])
        ->assertOk();

    expect(AuditLog::count())->toBe(2);
})->with([
    'registrar on /record-dashboard' => [RoleId::Registrar->value, '/record-dashboard'],
    'admin on /admin-dashboard'      => [RoleId::Admin->value, '/admin-dashboard'],
    'superadmin on /admin-dashboard' => [RoleId::SuperAdmin->value, '/admin-dashboard'],
    'admin on /admin/records'        => [RoleId::Admin->value, '/admin/records'],
    'superadmin on /admin/records'   => [RoleId::SuperAdmin->value, '/admin/records'],
]);

// ─── 6. Separation of Duties (SoD) Tests ──────────────────────────

test('same user who performed document evaluation or interview gets 403 on tag', function (string $stage) {
    $admin = User::factory()->create(['role_id' => RoleId::Admin->value]);
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    $application = createEligibleApplicationForTag((string) $applicant->id);

    // Mark process as performed by this admin
    ApplicationProcess::create([
        'application_id' => $application->id,
        'stage'          => $stage,
        'status'         => 'completed',
        'performed_by'   => $admin->id,
    ]);

    $this->actingAs($admin)
        ->postJson("/admin-dashboard/tag/{$applicant->id}", [
            'reason' => 'Attempting self-tag after evaluation',
        ])
        ->assertForbidden()
        ->assertJson([
            'message' => 'Separation of duties violation: You cannot tag an applicant whose document evaluation or interview you personally performed.',
        ]);

    // Ensure status unchanged
    $application->refresh();
    expect($application->enrollment_status)->toBe('temporary');
})->with([
    'document_evaluator stage' => 'document_evaluator',
    'interviewer stage'        => 'interviewer',
]);

test('different user who did not perform evaluation or interview can tag successfully', function () {
    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);
    $registrar = User::factory()->create(['role_id' => RoleId::Registrar->value]);
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    $application = createEligibleApplicationForTag((string) $applicant->id);

    // Document evaluator evaluated the applicant
    ApplicationProcess::create([
        'application_id' => $application->id,
        'stage'          => 'document_evaluator',
        'status'         => 'completed',
        'performed_by'   => $evaluator->id,
    ]);

    // Registrar tags applicant
    $this->actingAs($registrar)
        ->postJson("/record-dashboard/tag/{$applicant->id}", [
            'reason' => 'Approved by independent records officer.',
        ])
        ->assertOk()
        ->assertJson(['enrollment_status' => 'officially_enrolled']);
});

test('resubmit cycle still works: different evaluator resubmission allows independent tag', function () {
    $originalEvaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);
    $secondEvaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);
    $registrar = User::factory()->create(['role_id' => RoleId::Registrar->value]);

    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    $application = createEligibleApplicationForTag((string) $applicant->id);

    // 1st cycle: returned by originalEvaluator
    ApplicationProcess::create([
        'application_id' => $application->id,
        'stage'          => 'document_evaluator',
        'status'         => 'returned',
        'performed_by'   => $originalEvaluator->id,
    ]);

    // 2nd cycle (resubmit): secondEvaluator passed
    ApplicationProcess::create([
        'application_id' => $application->id,
        'stage'          => 'document_evaluator',
        'status'         => 'completed',
        'performed_by'   => $secondEvaluator->id,
    ]);

    // Registrar (independent) tags
    $this->actingAs($registrar)
        ->postJson("/record-dashboard/tag/{$applicant->id}", [
            'reason' => 'Resubmitted files verified and approved.',
        ])
        ->assertOk()
        ->assertJson(['enrollment_status' => 'officially_enrolled']);
});

test('untag is unchanged: user who evaluated can still untag', function () {
    $admin = User::factory()->create(['role_id' => RoleId::Admin->value]);
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    $application = createOfficiallyEnrolledApplication((string) $applicant->id);

    // Admin evaluated this applicant earlier
    ApplicationProcess::create([
        'application_id' => $application->id,
        'stage'          => 'document_evaluator',
        'status'         => 'completed',
        'performed_by'   => $admin->id,
    ]);

    // Untag works without SoD restriction
    $this->actingAs($admin)
        ->postJson("/admin-dashboard/untag/{$applicant->id}", [
            'reason' => 'Administrative reversion of enrollment.',
        ])
        ->assertOk()
        ->assertJson(['message' => 'Reverted to temporary enrolled successfully']);

    $application->refresh();
    expect($application->enrollment_status)->toBe('temporary');
});
