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

test('unassigned interviewer (role 4) can access user-grades on interviewer dashboard (read-exempt)', function () {
    $progA = getScopingProgram('BSCS');
    $progB = getScopingProgram('BSIT');
    $applicant = createApplicantWithProgram($progA);

    $interviewer = User::factory()->create(['role_id' => RoleId::Interviewer->value]);
    $interviewer->programs()->attach($progB->id, ['role_id' => RoleId::Interviewer->value]);

    $this->actingAs($interviewer)
        ->getJson("/interviewer-dashboard/user-grades/{$applicant->id}")
        ->assertOk()
        ->assertJsonStructure(['grades']);
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

test('assigned evaluator (role 3) can lazy load documents and grades', function () {
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

test('unassigned evaluator (role 3) gets 403 on all lazy-load endpoints', function () {
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

test('unassigned interviewer (role 4) can access lazy-load endpoints (read-exempt)', function () {
    $progA = getScopingProgram('BSCS');
    $progB = getScopingProgram('BSIT');
    $applicant = createApplicantWithProgram($progA);

    $interviewer = User::factory()->create(['role_id' => RoleId::Interviewer->value]);
    $interviewer->programs()->attach($progB->id, ['role_id' => RoleId::Interviewer->value]);

    $this->actingAs($interviewer)
        ->getJson("/api/lazy-load/document/{$applicant->id}/grade_11_card")
        ->assertOk();

    $this->actingAs($interviewer)
        ->postJson("/api/lazy-load/documents-batch/{$applicant->id}", [
            'fileTypes' => ['grade_11_card']
        ])
        ->assertOk();

    $this->actingAs($interviewer)
        ->getJson("/api/lazy-load/grades/{$applicant->id}")
        ->assertOk();
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

test('lazy-load returns 403 for document evaluator (role 3) when applicant has zero applications', function () {
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

// ─────────────────────────────────────────────────────────────────────────────
// user-files via EvaluatorDashboardController (role 3) & InterviewerDashboardController (role 4)
// ─────────────────────────────────────────────────────────────────────────────

test('assigned document evaluator (role 3) can access user-files', function () {
    $progA = getScopingProgram('BSCS');
    $applicant = createApplicantWithProgram($progA);
    $app = Application::where('user_id', (string) $applicant->id)->firstOrFail();

    ApplicationProcess::create([
        'application_id' => $app->id,
        'stage'          => 'document_evaluator',
        'status'         => 'in_progress',
    ]);

    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);
    $evaluator->programs()->attach($progA->id, ['role_id' => RoleId::DocumentEvaluator->value]);

    $this->actingAs($evaluator)
        ->getJson("/dashboard/user-files/{$applicant->id}")
        ->assertOk()
        ->assertJsonStructure(['user', 'uploadedFiles']);
});

test('unassigned document evaluator (role 3) gets 403 on user-files', function () {
    $progA = getScopingProgram('BSCS');
    $progB = getScopingProgram('BSIT');
    $applicant = createApplicantWithProgram($progA);
    $app = Application::where('user_id', (string) $applicant->id)->firstOrFail();

    ApplicationProcess::create([
        'application_id' => $app->id,
        'stage'          => 'document_evaluator',
        'status'         => 'in_progress',
    ]);

    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);
    $evaluator->programs()->attach($progB->id, ['role_id' => RoleId::DocumentEvaluator->value]);

    $this->actingAs($evaluator)
        ->getJson("/dashboard/user-files/{$applicant->id}")
        ->assertForbidden()
        ->assertJson(['message' => 'You are not authorized to evaluate applicants for this program.']);
});

test('user-files returns 403 for document evaluator (role 3) when applicant has zero applications', function () {
    $progA = getScopingProgram('BSCS');
    $applicantWithoutApp = User::factory()->create(['role_id' => RoleId::Applicant->value]);

    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);
    $evaluator->programs()->attach($progA->id, ['role_id' => RoleId::DocumentEvaluator->value]);

    $this->actingAs($evaluator)
        ->getJson("/dashboard/user-files/{$applicantWithoutApp->id}")
        ->assertForbidden()
        ->assertJson(['message' => 'You are not authorized to evaluate applicants for this program.']);
});

test('user-files allows access if applicant has several applications and ANY matches evaluator programs', function () {
    $progA = getScopingProgram('BSCS');
    $progB = getScopingProgram('BSIT');
    $applicant = createApplicantWithProgram($progA);

    $appB = Application::create([
        'user_id'    => (string) $applicant->id,
        'program_id' => $progB->id,
        'status'     => 'submitted',
    ]);

    ApplicationProcess::create([
        'application_id' => $appB->id,
        'stage'          => 'document_evaluator',
        'status'         => 'in_progress',
    ]);

    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);
    $evaluator->programs()->attach($progB->id, ['role_id' => RoleId::DocumentEvaluator->value]);

    $this->actingAs($evaluator)
        ->getJson("/dashboard/user-files/{$applicant->id}")
        ->assertOk()
        ->assertJsonStructure(['user', 'uploadedFiles']);
});

test('exempt roles (Admin 2, SuperAdmin 7, Grade Evaluator 8) access user-files without program assignment', function (int $roleId) {
    $progA = getScopingProgram('BSCS');
    $applicant = createApplicantWithProgram($progA);
    $app = Application::where('user_id', (string) $applicant->id)->firstOrFail();

    ApplicationProcess::create([
        'application_id' => $app->id,
        'stage'          => 'grade_evaluator',
        'status'         => 'in_progress',
    ]);

    $staff = User::factory()->create(['role_id' => $roleId]);

    $this->actingAs($staff)
        ->getJson("/dashboard/user-files/{$applicant->id}")
        ->assertOk()
        ->assertJsonStructure(['user', 'uploadedFiles']);
})->with([
    'admin (role 2)'           => RoleId::Admin->value,
    'superadmin (role 7)'      => RoleId::SuperAdmin->value,
    'grade evaluator (role 8)' => RoleId::GradeEvaluator->value,
]);

test('assigned interviewer (role 4) can access interviewer application files', function () {
    $progA = getScopingProgram('BSCS');
    $applicant = createApplicantWithProgram($progA);
    $app = Application::where('user_id', (string) $applicant->id)->firstOrFail();

    ApplicationProcess::create([
        'application_id' => $app->id,
        'stage'          => 'interviewer',
        'status'         => 'in_progress',
    ]);

    $interviewer = User::factory()->create(['role_id' => RoleId::Interviewer->value]);
    $interviewer->programs()->attach($progA->id, ['role_id' => RoleId::Interviewer->value]);

    $this->actingAs($interviewer)
        ->getJson("/interviewer-dashboard/application/{$applicant->id}")
        ->assertOk()
        ->assertJsonStructure(['user', 'uploadedFiles']);
});

test('unassigned interviewer (role 4) can access application files at interview stage with different first-choice program', function () {
    $progA = getScopingProgram('BSCS');
    $progB = getScopingProgram('BSIT');
    $applicant = createApplicantWithProgram($progA);
    $app = Application::where('user_id', (string) $applicant->id)->firstOrFail();

    ApplicationProcess::create([
        'application_id' => $app->id,
        'stage'          => 'interviewer',
        'status'         => 'in_progress',
    ]);

    $interviewer = User::factory()->create(['role_id' => RoleId::Interviewer->value]);
    $interviewer->programs()->attach($progB->id, ['role_id' => RoleId::Interviewer->value]);

    $this->actingAs($interviewer)
        ->getJson("/interviewer-dashboard/application/{$applicant->id}")
        ->assertOk()
        ->assertJsonStructure(['user', 'uploadedFiles']);
});

test('interviewer (role 4) gets 403 on application files if applicant has no interviewer-stage process', function () {
    $progA = getScopingProgram('BSCS');
    $applicant = createApplicantWithProgram($progA);
    $app = Application::where('user_id', (string) $applicant->id)->firstOrFail();

    // Application is only at document_evaluator stage, not interviewer stage
    ApplicationProcess::create([
        'application_id' => $app->id,
        'stage'          => 'document_evaluator',
        'status'         => 'in_progress',
    ]);

    $interviewer = User::factory()->create(['role_id' => RoleId::Interviewer->value]);
    $interviewer->programs()->attach($progA->id, ['role_id' => RoleId::Interviewer->value]);

    $this->actingAs($interviewer)
        ->getJson("/interviewer-dashboard/application/{$applicant->id}")
        ->assertForbidden()
        ->assertJson(['message' => 'Unauthorized access. Application is not at the interviewer stage.']);
});

// ─────────────────────────────────────────────────────────────────────────────
// interviewer accept / reject program scoping
// ─────────────────────────────────────────────────────────────────────────────

test('interviewer assigned to program A cannot accept applicant into program B (403)', function () {
    $progA = getScopingProgram('BSCS');
    $progB = getScopingProgram('BSIT');
    $applicant = createApplicantWithProgram($progA);
    $app = Application::where('user_id', (string) $applicant->id)->firstOrFail();

    ApplicationProcess::create([
        'application_id' => $app->id,
        'stage'          => 'grade_evaluator',
        'status'         => 'completed',
    ]);

    ApplicationProcess::create([
        'application_id' => $app->id,
        'stage'          => 'interviewer',
        'status'         => 'in_progress',
    ]);

    $interviewer = User::factory()->create(['role_id' => RoleId::Interviewer->value]);
    $interviewer->programs()->attach($progA->id, ['role_id' => RoleId::Interviewer->value]);

    $this->actingAs($interviewer)
        ->postJson("/interviewer-dashboard/accept/{$applicant->id}", [
            'program_id' => $progB->id,
        ])
        ->assertForbidden()
        ->assertJson(['message' => 'You are not authorized to accept applicants for this program.']);
});

test('interviewer assigned to program A can accept applicant into program A', function () {
    $progA = getScopingProgram('BSCS');
    $applicant = createApplicantWithProgram($progA);
    $app = Application::where('user_id', (string) $applicant->id)->firstOrFail();

    ApplicationProcess::create([
        'application_id' => $app->id,
        'stage'          => 'grade_evaluator',
        'status'         => 'completed',
    ]);

    ApplicationProcess::create([
        'application_id' => $app->id,
        'stage'          => 'interviewer',
        'status'         => 'in_progress',
    ]);

    $interviewer = User::factory()->create(['role_id' => RoleId::Interviewer->value]);
    $interviewer->programs()->attach($progA->id, ['role_id' => RoleId::Interviewer->value]);

    $this->actingAs($interviewer)
        ->postJson("/interviewer-dashboard/accept/{$applicant->id}", [
            'program_id' => $progA->id,
        ])
        ->assertOk()
        ->assertJson(['message' => 'Application accepted.']);
});

test('exempt roles (Admin 2, SuperAdmin 7) can access interviewer application files without program assignment', function (int $roleId) {
    $progA = getScopingProgram('BSCS');
    $applicant = createApplicantWithProgram($progA);
    $app = Application::where('user_id', (string) $applicant->id)->firstOrFail();

    ApplicationProcess::create([
        'application_id' => $app->id,
        'stage'          => 'interviewer',
        'status'         => 'in_progress',
    ]);

    $staff = User::factory()->create(['role_id' => $roleId]);

    $this->actingAs($staff)
        ->getJson("/interviewer-dashboard/application/{$applicant->id}")
        ->assertOk()
        ->assertJsonStructure(['user', 'uploadedFiles']);
})->with([
    'admin (role 2)'      => RoleId::Admin->value,
    'superadmin (role 7)' => RoleId::SuperAdmin->value,
]);
