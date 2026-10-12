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
    expect($audit->description)->toContain("Tagged applicant (user_id: {$applicant->id}, application_id: {$application->id})");
    expect($audit->description)->toContain("status: 'interview_passed' -> 'accepted'");
    expect($audit->description)->toContain("enrollment_status: 'temporary' -> 'officially_enrolled'");
    expect($audit->description)->toContain('Reason: All enrollment credentials verified and valid.');
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
    expect($audit->description)->toContain("Untagged applicant (user_id: {$applicant->id}, application_id: {$application->id})");
    expect($audit->description)->toContain("status: 'accepted' -> 'cleared_for_enrollment'");
    expect($audit->description)->toContain("enrollment_status: 'officially_enrolled' -> 'temporary'");
    expect($audit->description)->toContain('Reason: Applicant requested cancellation of enrollment registration.');
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

// ─── 4b. Synchronous Audit Failure Triggers DB Rollback ───────────

test('audit write failure during tag triggers rollback: status unchanged and no partial row', function () {
    $registrar = User::factory()->create(['role_id' => RoleId::Registrar->value]);
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    $application = createEligibleApplicationForTag((string) $applicant->id);

    // Mock AuditLogRepositoryInterface to simulate DB/audit failure
    $mockRepo = Mockery::mock(\App\Repositories\Contracts\AuditLogRepositoryInterface::class);
    $mockRepo->shouldReceive('create')->andThrow(new \Exception('Audit storage disk full'));
    app()->instance(\App\Repositories\Contracts\AuditLogRepositoryInterface::class, $mockRepo);

    $this->actingAs($registrar)
        ->postJson("/record-dashboard/tag/{$applicant->id}", [
            'reason' => 'Valid reason for tagging',
        ])
        ->assertStatus(500)
        ->assertJson([
            'message' => 'Failed to tag: Audit storage disk full',
        ]);

    // Verify database transaction rolled back status and records process
    $application->refresh();
    expect($application->enrollment_status)->toBe('temporary');
    expect($application->status)->toBe('interview_passed');

    // No partial records process
    expect(ApplicationProcess::where('application_id', $application->id)->where('stage', 'records')->exists())->toBeFalse();
    // No audit log
    expect(AuditLog::count())->toBe(0);
});

test('audit write failure during untag triggers rollback: status unchanged and no partial row', function () {
    $registrar = User::factory()->create(['role_id' => RoleId::Registrar->value]);
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    $application = createOfficiallyEnrolledApplication((string) $applicant->id);

    // Mock AuditLogRepositoryInterface to simulate DB/audit failure
    $mockRepo = Mockery::mock(\App\Repositories\Contracts\AuditLogRepositoryInterface::class);
    $mockRepo->shouldReceive('create')->andThrow(new \Exception('Audit storage disk full'));
    app()->instance(\App\Repositories\Contracts\AuditLogRepositoryInterface::class, $mockRepo);

    $this->actingAs($registrar)
        ->postJson("/record-dashboard/untag/{$applicant->id}", [
            'reason' => 'Valid reason for untagging',
        ])
        ->assertStatus(500)
        ->assertJson([
            'message' => 'Failed to untag: Audit storage disk full',
        ]);

    // Verify database transaction rolled back status
    $application->refresh();
    expect($application->enrollment_status)->toBe('officially_enrolled');
    expect($application->status)->toBe('accepted');

    // Records process remained completed
    $process = ApplicationProcess::where('application_id', $application->id)->where('stage', 'records')->first();
    expect($process->status)->toBe('completed');
    // No audit log
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

test('actor who performed document_evaluator, grade_evaluator, or interviewer gets 403 on tag', function (string $stage) {
    $admin = User::factory()->create(['role_id' => RoleId::Admin->value]);
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    $application = createEligibleApplicationForTag((string) $applicant->id);

    // Mark evaluation/interview process as performed by this admin
    ApplicationProcess::create([
        'application_id' => $application->id,
        'stage'          => $stage,
        'status'         => 'completed',
        'performed_by'   => $admin->id,
    ]);

    $this->actingAs($admin)
        ->postJson("/admin-dashboard/tag/{$applicant->id}", [
            'reason' => 'Attempting self-tag after evaluation or interview',
        ])
        ->assertForbidden()
        ->assertJson([
            'message' => 'Separation of duties violation: You cannot tag an applicant whose evaluation or interview you personally performed.',
        ]);

    // Ensure status unchanged
    $application->refresh();
    expect($application->enrollment_status)->toBe('temporary');
})->with([
    'document_evaluator stage' => ApplicationProcess::STAGE_DOCUMENT_EVALUATOR,
    'grade_evaluator stage'    => ApplicationProcess::STAGE_GRADE_EVALUATOR,
    'interviewer stage'        => ApplicationProcess::STAGE_INTERVIEWER,
]);

test('actor who only has a records row from previous tag/untag can re-tag successfully', function () {
    $registrar = User::factory()->create(['role_id' => RoleId::Registrar->value]);
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    $application = createEligibleApplicationForTag((string) $applicant->id);

    // Prior cycle: registrar had performed a records stage row (tagged then untagged)
    ApplicationProcess::create([
        'application_id' => $application->id,
        'stage'          => ApplicationProcess::STAGE_RECORDS,
        'status'         => 'in_progress',
        'performed_by'   => $registrar->id,
    ]);

    // Re-tagging by the same registrar is allowed because 'records' is not an evaluation stage
    $this->actingAs($registrar)
        ->postJson("/record-dashboard/tag/{$applicant->id}", [
            'reason' => 'Re-tagging after previous untag and verification.',
        ])
        ->assertOk()
        ->assertJson(['enrollment_status' => 'officially_enrolled']);

    $application->refresh();
    expect($application->enrollment_status)->toBe('officially_enrolled');
});

test('different actor who did not perform evaluation or interview can tag successfully', function () {
    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);
    $registrar = User::factory()->create(['role_id' => RoleId::Registrar->value]);
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    $application = createEligibleApplicationForTag((string) $applicant->id);

    // Document evaluator evaluated the applicant
    ApplicationProcess::create([
        'application_id' => $application->id,
        'stage'          => ApplicationProcess::STAGE_DOCUMENT_EVALUATOR,
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
        'stage'          => ApplicationProcess::STAGE_DOCUMENT_EVALUATOR,
        'status'         => 'returned',
        'performed_by'   => $originalEvaluator->id,
    ]);

    // 2nd cycle (resubmit): secondEvaluator passed
    ApplicationProcess::create([
        'application_id' => $application->id,
        'stage'          => ApplicationProcess::STAGE_DOCUMENT_EVALUATOR,
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
        'stage'          => ApplicationProcess::STAGE_DOCUMENT_EVALUATOR,
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
