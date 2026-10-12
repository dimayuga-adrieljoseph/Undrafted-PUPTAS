<?php

use App\Enums\RoleId;
use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\Grade;
use App\Models\Program;
use App\Models\User;
use App\Models\UserFile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function getScopingProgram(string $code = 'BSCS'): Program
{
    return Program::firstOrCreate(
        ['code' => $code],
        [
            'name'     => $code . ' Program',
            'capacity' => 50,
            'slots'    => 50,
            'math'     => 80,
            'science'  => 80,
            'english'  => 80,
        ]
    );
}

function createApplicantWithProgram(Program $program): User
{
    $applicant = User::factory()->create(['role_id' => RoleId::Applicant->value]);

    Application::create([
        'user_id'    => (string) $applicant->id,
        'program_id' => $program->id,
        'status'     => 'submitted',
    ]);

    Grade::create([
        'user_id'     => (string) $applicant->id,
        'mathematics' => 90,
        'science'     => 90,
        'english'     => 90,
    ]);

    UserFile::create([
        'user_id'   => (string) $applicant->id,
        'type'      => 'grade_11_card',
        'file_path' => 'files/card.pdf',
        'status'    => 'approved',
    ]);

    return $applicant;
}

// ─────────────────────────────────────────────────────────────────────────────
// user-grades via EvaluatorDashboardController & InterviewerDashboardController
// ─────────────────────────────────────────────────────────────────────────────

test('assigned document evaluator (role 3) can access user-grades', function () {
    $progA = getScopingProgram('BSCS');
    $applicant = createApplicantWithProgram($progA);

    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);
    $evaluator->programs()->attach($progA->id, ['role_id' => RoleId::DocumentEvaluator->value]);

    $this->actingAs($evaluator)
        ->getJson("/dashboard/user-grades/{$applicant->id}")
        ->assertOk()
        ->assertJsonStructure(['grades']);
});

test('unassigned document evaluator (role 3) gets 403 on user-grades', function () {
    $progA = getScopingProgram('BSCS');
    $progB = getScopingProgram('BSIT');
    $applicant = createApplicantWithProgram($progA);

    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);
    $evaluator->programs()->attach($progB->id, ['role_id' => RoleId::DocumentEvaluator->value]);

    $this->actingAs($evaluator)
        ->getJson("/dashboard/user-grades/{$applicant->id}")
        ->assertForbidden()
        ->assertJson(['message' => 'You are not authorized to evaluate applicants for this program.']);
});

test('assigned interviewer (role 4) can access user-grades on interviewer dashboard', function () {
    $progA = getScopingProgram('BSCS');
    $applicant = createApplicantWithProgram($progA);

    $interviewer = User::factory()->create(['role_id' => RoleId::Interviewer->value]);
    $interviewer->programs()->attach($progA->id, ['role_id' => RoleId::Interviewer->value]);

    $this->actingAs($interviewer)
        ->getJson("/interviewer-dashboard/user-grades/{$applicant->id}")
        ->assertOk()
        ->assertJsonStructure(['grades']);
});

test('unassigned interviewer (role 4) gets 403 on user-grades', function () {
    $progA = getScopingProgram('BSCS');
    $progB = getScopingProgram('BSIT');
    $applicant = createApplicantWithProgram($progA);

    $interviewer = User::factory()->create(['role_id' => RoleId::Interviewer->value]);
    $interviewer->programs()->attach($progB->id, ['role_id' => RoleId::Interviewer->value]);

    $this->actingAs($interviewer)
        ->getJson("/interviewer-dashboard/user-grades/{$applicant->id}")
        ->assertForbidden()
        ->assertJson(['message' => 'You are not authorized to evaluate applicants for this program.']);
});

test('exempt roles (Admin 2, SuperAdmin 7, Grade Evaluator 8) access user-grades without program assignment', function (int $roleId) {
    $progA = getScopingProgram('BSCS');
    $applicant = createApplicantWithProgram($progA);

    $staff = User::factory()->create(['role_id' => $roleId]);

    $this->actingAs($staff)
        ->getJson("/dashboard/user-grades/{$applicant->id}")
        ->assertOk()
        ->assertJsonStructure(['grades']);
})->with([
    'admin (role 2)'           => RoleId::Admin->value,
    'superadmin (role 7)'      => RoleId::SuperAdmin->value,
    'grade evaluator (role 8)' => RoleId::GradeEvaluator->value,
]);

// ─────────────────────────────────────────────────────────────────────────────
// start-review endpoint
// ─────────────────────────────────────────────────────────────────────────────

test('assigned evaluator (role 3) can start review', function () {
    $progA = getScopingProgram('BSCS');
    $applicant = createApplicantWithProgram($progA);
    $app = Application::where('user_id', (string) $applicant->id)->firstOrFail();

    $process = ApplicationProcess::create([
        'application_id' => $app->id,
        'stage'          => 'document_evaluator',
        'status'         => 'in_progress',
    ]);

    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);
    $evaluator->programs()->attach($progA->id, ['role_id' => RoleId::DocumentEvaluator->value]);

    $this->actingAs($evaluator)
        ->postJson("/evaluator/start-review/{$process->id}")
        ->assertOk()
        ->assertJsonStructure(['started_at']);
});

test('unassigned evaluator (role 3) gets 403 on start review', function () {
    $progA = getScopingProgram('BSCS');
    $progB = getScopingProgram('BSIT');
    $applicant = createApplicantWithProgram($progA);
    $app = Application::where('user_id', (string) $applicant->id)->firstOrFail();

    $process = ApplicationProcess::create([
        'application_id' => $app->id,
        'stage'          => 'document_evaluator',
        'status'         => 'in_progress',
    ]);

    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);
    $evaluator->programs()->attach($progB->id, ['role_id' => RoleId::DocumentEvaluator->value]);

    $this->actingAs($evaluator)
        ->postJson("/evaluator/start-review/{$process->id}")
        ->assertForbidden()
        ->assertJson(['message' => 'You are not authorized to evaluate applicants for this program.']);
});

test('exempt roles (Admin 2, SuperAdmin 7, Grade Evaluator 8) can start review without program assignment', function (int $roleId) {
    $progA = getScopingProgram('BSCS');
    $applicant = createApplicantWithProgram($progA);
    $app = Application::where('user_id', (string) $applicant->id)->firstOrFail();

    $process = ApplicationProcess::create([
        'application_id' => $app->id,
        'stage'          => 'document_evaluator',
        'status'         => 'in_progress',
    ]);

    $staff = User::factory()->create(['role_id' => $roleId]);

    $this->actingAs($staff)
        ->postJson("/evaluator/start-review/{$process->id}")
        ->assertOk()
        ->assertJsonStructure(['started_at']);
})->with([
    'admin (role 2)'           => RoleId::Admin->value,
    'superadmin (role 7)'      => RoleId::SuperAdmin->value,
    'grade evaluator (role 8)' => RoleId::GradeEvaluator->value,
]);

// ─────────────────────────────────────────────────────────────────────────────
// lazy-load endpoints (loadDocument, loadDocumentsBatch, loadGrades)
// ─────────────────────────────────────────────────────────────────────────────

test('assigned evaluator/interviewer can lazy load documents and grades', function () {
    $progA = getScopingProgram('BSCS');
    $applicant = createApplicantWithProgram($progA);

    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);
    $evaluator->programs()->attach($progA->id, ['role_id' => RoleId::DocumentEvaluator->value]);

    $this->actingAs($evaluator)
        ->getJson("/api/lazy-load/document/{$applicant->id}/grade_11_card")
        ->assertOk();

    $this->actingAs($evaluator)
        ->postJson("/api/lazy-load/documents-batch/{$applicant->id}", [
            'fileTypes' => ['grade_11_card']
        ])
        ->assertOk();

    $this->actingAs($evaluator)
        ->getJson("/api/lazy-load/grades/{$applicant->id}")
        ->assertOk();
});

test('unassigned evaluator/interviewer gets 403 on all lazy-load endpoints', function () {
    $progA = getScopingProgram('BSCS');
    $progB = getScopingProgram('BSIT');
    $applicant = createApplicantWithProgram($progA);

    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);
    $evaluator->programs()->attach($progB->id, ['role_id' => RoleId::DocumentEvaluator->value]);

    $this->actingAs($evaluator)
        ->getJson("/api/lazy-load/document/{$applicant->id}/grade_11_card")
        ->assertForbidden()
        ->assertJson(['message' => 'You are not authorized to evaluate applicants for this program.']);

    $this->actingAs($evaluator)
        ->postJson("/api/lazy-load/documents-batch/{$applicant->id}", [
            'fileTypes' => ['grade_11_card']
        ])
        ->assertForbidden()
        ->assertJson(['message' => 'You are not authorized to evaluate applicants for this program.']);

    $this->actingAs($evaluator)
        ->getJson("/api/lazy-load/grades/{$applicant->id}")
        ->assertForbidden()
        ->assertJson(['message' => 'You are not authorized to evaluate applicants for this program.']);
});

test('exempt roles (Admin 2, Registrar 6, Grade Evaluator 8) can access lazy-load without program assignment', function (int $roleId) {
    $progA = getScopingProgram('BSCS');
    $applicant = createApplicantWithProgram($progA);

    $staff = User::factory()->create(['role_id' => $roleId]);

    $this->actingAs($staff)
        ->getJson("/api/lazy-load/document/{$applicant->id}/grade_11_card")
        ->assertOk();

    $this->actingAs($staff)
        ->postJson("/api/lazy-load/documents-batch/{$applicant->id}", [
            'fileTypes' => ['grade_11_card']
        ])
        ->assertOk();

    $this->actingAs($staff)
        ->getJson("/api/lazy-load/grades/{$applicant->id}")
        ->assertOk();
})->with([
    'admin (role 2)'           => RoleId::Admin->value,
    'registrar (role 6)'       => RoleId::Registrar->value,
    'grade evaluator (role 8)' => RoleId::GradeEvaluator->value,
]);

test('lazy-load allows access if applicant has several applications and ANY matches evaluator programs', function () {
    $progA = getScopingProgram('BSCS');
    $progB = getScopingProgram('BSIT');
    $applicant = createApplicantWithProgram($progA);

    // Second application in ProgB
    Application::create([
        'user_id'    => (string) $applicant->id,
        'program_id' => $progB->id,
        'status'     => 'submitted',
    ]);

    // Evaluator is assigned ONLY to ProgB
    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);
    $evaluator->programs()->attach($progB->id, ['role_id' => RoleId::DocumentEvaluator->value]);

    $this->actingAs($evaluator)
        ->getJson("/api/lazy-load/document/{$applicant->id}/grade_11_card")
        ->assertOk();

    $this->actingAs($evaluator)
        ->getJson("/api/lazy-load/grades/{$applicant->id}")
        ->assertOk();
});

test('lazy-load returns 403 for roles 3 and 4 when applicant has zero applications', function () {
    $progA = getScopingProgram('BSCS');
    $applicantWithoutApp = User::factory()->create(['role_id' => RoleId::Applicant->value]);

    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);
    $evaluator->programs()->attach($progA->id, ['role_id' => RoleId::DocumentEvaluator->value]);

    $this->actingAs($evaluator)
        ->getJson("/api/lazy-load/document/{$applicantWithoutApp->id}/grade_11_card")
        ->assertForbidden()
        ->assertJson(['message' => 'You are not authorized to evaluate applicants for this program.']);

    $this->actingAs($evaluator)
        ->getJson("/api/lazy-load/grades/{$applicantWithoutApp->id}")
        ->assertForbidden()
        ->assertJson(['message' => 'You are not authorized to evaluate applicants for this program.']);
});
