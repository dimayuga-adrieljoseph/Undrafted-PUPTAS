<?php

/**
 * WorkflowConcurrencyTest – AJ-38 workflow actions
 *
 * Tests that a repeated sequential request to each workflow action returns
 * the existing error and creates no extra process row, no extra audit row,
 * and no extra slot decrement (for interviewer accept).
 */

use App\Enums\RoleId;
use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\AuditLog;
use App\Models\Grade;
use App\Models\Program;
use App\Models\User;
use App\Models\UserFile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- Helpers ------------------------------------------------------------------

function wc_program(string $code = 'BSCS'): Program
{
    return Program::firstOrCreate(
        ['code' => $code],
        [
            'name'     => $code . ' Program',
            'capacity' => 50,
            'slots'    => 10,
            'math'     => 70,
            'science'  => 70,
            'english'  => 70,
        ]
    );
}

function wc_applicantAtInterviewerStage(): array
{
    $program   = wc_program();
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);

    $app = Application::create([
        'user_id'    => (string) $applicant->id,
        'program_id' => $program->id,
        'status'     => 'submitted',
    ]);

    ApplicationProcess::create(['application_id' => $app->id, 'stage' => 'document_evaluator', 'status' => 'completed', 'action' => 'passed']);
    ApplicationProcess::create(['application_id' => $app->id, 'stage' => 'grade_evaluator',    'status' => 'completed', 'action' => 'passed']);
    ApplicationProcess::create(['application_id' => $app->id, 'stage' => 'interviewer',        'status' => 'in_progress']);

    Grade::create(['user_id' => (string) $applicant->id, 'mathematics' => 90, 'science' => 90, 'english' => 90]);

    return ['applicant' => $applicant, 'application' => $app, 'program' => $program];
}

function wc_applicantAtEvaluatorStage(string $stage = 'document_evaluator'): array
{
    $program   = wc_program();
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);

    $app = Application::create([
        'user_id'    => (string) $applicant->id,
        'program_id' => $program->id,
        'status'     => 'submitted',
    ]);

    ApplicationProcess::create(['application_id' => $app->id, 'stage' => $stage, 'status' => 'in_progress']);

    return ['applicant' => $applicant, 'application' => $app, 'program' => $program];
}

function wc_applicantForTag(): array
{
    $program   = wc_program();
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);

    $app = Application::create([
        'user_id'           => (string) $applicant->id,
        'program_id'        => $program->id,
        'status'            => 'accepted',
        'enrollment_status' => 'temporary',
    ]);

    ApplicationProcess::create(['application_id' => $app->id, 'stage' => 'medical', 'status' => 'completed']);

    UserFile::create(['user_id' => (string) $applicant->id, 'type' => 'cor_front', 'file_path' => 'cor/front.jpg']);
    UserFile::create(['user_id' => (string) $applicant->id, 'type' => 'cor_back',  'file_path' => 'cor/back.jpg']);

    return ['applicant' => $applicant, 'application' => $app, 'program' => $program];
}

// --- 1. Interviewer accept -----------------------------------------------------

test('second sequential interviewer accept returns 409 with no extra process row or slot decrement', function () {
    ['applicant' => $applicant, 'application' => $app, 'program' => $program] = wc_applicantAtInterviewerStage();

    $interviewer = User::factory()->create(['role_id' => RoleId::Interviewer->value]);
    $interviewer->programs()->attach($program->id, ['role_id' => RoleId::Interviewer->value]);

    $payload     = ['program_id' => $program->id, 'notes' => ''];
    $slotsBefore = $program->slots;

    $this->actingAs($interviewer)->postJson("/interviewer-dashboard/accept/{$applicant->id}", $payload)->assertStatus(200);

    $program->refresh();
    expect($program->slots)->toBe($slotsBefore - 1);
    expect(ApplicationProcess::where('application_id', $app->id)->where('stage', 'medical')->count())->toBe(1);

    // Second accept: process already completed ? 409
    $this->actingAs($interviewer)->postJson("/interviewer-dashboard/accept/{$applicant->id}", $payload)->assertStatus(409);

    $program->refresh();
    expect($program->slots)->toBe($slotsBefore - 1); // no extra decrement
    expect(ApplicationProcess::where('application_id', $app->id)->where('stage', 'medical')->count())->toBe(1);
});

// --- 2. Interviewer reject ----------------------------------------------------

test('second sequential interviewer reject returns 409 with no extra process row', function () {
    ['applicant' => $applicant, 'application' => $app, 'program' => $program] = wc_applicantAtInterviewerStage();

    $interviewer = User::factory()->create(['role_id' => RoleId::Interviewer->value]);
    $interviewer->programs()->attach($program->id, ['role_id' => RoleId::Interviewer->value]);

    $payload = ['program_id' => $program->id, 'notes' => ''];

    $this->actingAs($interviewer)->postJson("/interviewer-dashboard/reject/{$applicant->id}", $payload)->assertStatus(200);

    // Manually close the process so the second reject hits the guard
    ApplicationProcess::where('application_id', $app->id)->where('stage', 'interviewer')->update(['status' => 'completed', 'action' => 'passed']);

    $this->actingAs($interviewer)->postJson("/interviewer-dashboard/reject/{$applicant->id}", $payload)->assertStatus(409);

    expect(ApplicationProcess::where('application_id', $app->id)->where('stage', 'interviewer')->count())->toBe(1);
});

// --- 3. Evaluator pass --------------------------------------------------------

test('second sequential evaluator pass returns 409 with no extra next-stage process row', function () {
    ['applicant' => $applicant, 'application' => $app, 'program' => $program] = wc_applicantAtEvaluatorStage('document_evaluator');

    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);
    $evaluator->programs()->attach($program->id, ['role_id' => RoleId::DocumentEvaluator->value]);

    $this->actingAs($evaluator)->postJson("/evaluator/pass-application/{$applicant->id}", ['note' => ''])->assertStatus(200);

    expect(ApplicationProcess::where('application_id', $app->id)->where('stage', 'grade_evaluator')->count())->toBe(1);

    $this->actingAs($evaluator)->postJson("/evaluator/pass-application/{$applicant->id}", ['note' => ''])->assertStatus(409);

    expect(ApplicationProcess::where('application_id', $app->id)->where('stage', 'grade_evaluator')->count())->toBe(1);
});

// --- 4. Evaluator reject ------------------------------------------------------

test('second sequential evaluator reject returns 409 with no extra process row', function () {
    ['applicant' => $applicant, 'application' => $app, 'program' => $program] = wc_applicantAtEvaluatorStage('document_evaluator');

    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);
    $evaluator->programs()->attach($program->id, ['role_id' => RoleId::DocumentEvaluator->value]);

    $countBefore = ApplicationProcess::where('application_id', $app->id)->count();

    $this->actingAs($evaluator)->postJson("/evaluator/reject-application/{$applicant->id}", ['note' => ''])->assertStatus(200);
    $this->actingAs($evaluator)->postJson("/evaluator/reject-application/{$applicant->id}", ['note' => ''])->assertStatus(409);

    expect(ApplicationProcess::where('application_id', $app->id)->count())->toBe($countBefore);
});

// --- 5. Evaluator return (returnApplication) ----------------------------------

test('second sequential evaluator return after process is completed returns 409 with no extra process row', function () {
    ['applicant' => $applicant, 'application' => $app, 'program' => $program] = wc_applicantAtEvaluatorStage('document_evaluator');

    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);
    $evaluator->programs()->attach($program->id, ['role_id' => RoleId::DocumentEvaluator->value]);

    $countBefore = ApplicationProcess::where('application_id', $app->id)->count();

    $this->actingAs($evaluator)
        ->postJson("/dashboard/return-files/{$applicant->id}", ['note' => 'Please re-upload.', 'files' => []])
        ->assertStatus(200);

    // Manually finalise the process to simulate lock guard rejection
    ApplicationProcess::where('application_id', $app->id)
        ->where('stage', 'document_evaluator')
        ->update(['status' => 'completed', 'action' => 'passed']);

    $this->actingAs($evaluator)
        ->postJson("/dashboard/return-files/{$applicant->id}", ['note' => 'Duplicate return.', 'files' => []])
        ->assertStatus(409);

    expect(ApplicationProcess::where('application_id', $app->id)->count())->toBe($countBefore);
});

// --- 6. Tag – second call returns 409 with exactly one audit row --------------

test('second sequential tag returns 409 with exactly one audit row', function () {
    ['applicant' => $applicant, 'application' => $app] = wc_applicantForTag();

    $registrar = User::factory()->create(['role_id' => RoleId::Registrar->value]);
    $payload   = ['reason' => 'All credentials verified and complete.'];

    $this->actingAs($registrar)->postJson("/record-dashboard/tag/{$applicant->id}", $payload)->assertStatus(200);

    expect(AuditLog::count())->toBe(1);
    $app->refresh();
    expect($app->enrollment_status)->toBe('officially_enrolled');

    $r2 = $this->actingAs($registrar)->postJson("/record-dashboard/tag/{$applicant->id}", $payload);
    $r2->assertStatus(409);
    $r2->assertJsonPath('message', 'Application is already officially enrolled.');

    expect(AuditLog::count())->toBe(1); // still exactly one
    $app->refresh();
    expect($app->enrollment_status)->toBe('officially_enrolled'); // unchanged
});

// --- 7. Untag – second call returns 400 with one audit row -------------------

test('second sequential untag returns 400 with exactly one audit row', function () {
    ['applicant' => $applicant, 'application' => $app] = wc_applicantForTag();

    $registrar = User::factory()->create(['role_id' => RoleId::Registrar->value]);
    $app->update(['enrollment_status' => 'officially_enrolled', 'status' => 'accepted']);
    ApplicationProcess::create(['application_id' => $app->id, 'stage' => 'records', 'status' => 'completed']);

    $payload = ['reason' => 'Applicant requested de-enrollment.'];

    $this->actingAs($registrar)->postJson("/record-dashboard/untag/{$applicant->id}", $payload)->assertStatus(200);

    expect(AuditLog::count())->toBe(1);
    $app->refresh();
    expect($app->enrollment_status)->toBe('temporary');

    $r2 = $this->actingAs($registrar)->postJson("/record-dashboard/untag/{$applicant->id}", $payload);
    $r2->assertStatus(400);

    expect(AuditLog::count())->toBe(1); // still exactly one
});

// --- 8. Change-course – second identical call returns 422 --------------------

test('second sequential change-course to same program returns 422 with no extra process row', function () {
    $progA = wc_program('BSCS');
    $progB = wc_program('BSIT');

    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);
    $admin     = User::factory()->create(['role_id' => RoleId::Admin->value]);

    $app = Application::create([
        'user_id'           => (string) $applicant->id,
        'program_id'        => $progA->id,
        'status'            => 'submitted',
        'enrollment_status' => 'temporary',
    ]);

    ApplicationProcess::create(['application_id' => $app->id, 'stage' => 'medical', 'status' => 'completed']);
    $countBefore = ApplicationProcess::where('application_id', $app->id)->count();

    // First change: A -> B
    $this->actingAs($admin)->postJson("/record-dashboard/change-course/{$applicant->id}", ['program_id' => $progB->id])->assertStatus(200);

    expect(ApplicationProcess::where('application_id', $app->id)->count())->toBe($countBefore + 1);

    // Second: still B ? 422
    $r2 = $this->actingAs($admin)->postJson("/record-dashboard/change-course/{$applicant->id}", ['program_id' => $progB->id]);
    $r2->assertStatus(422);
    $r2->assertJsonPath('message', 'The selected program must be different from the current program.');

    expect(ApplicationProcess::where('application_id', $app->id)->count())->toBe($countBefore + 1);
});
