<?php

use App\Enums\RoleId;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('POST /test-passers/bulk-enroll returns 404 for roles 2, 6 and 7', function (int $roleId) {
    $user = User::factory()->create(['role_id' => $roleId]);

    $this->actingAs($user)
        ->postJson('/test-passers/bulk-enroll', ['passer_ids' => [1]])
        ->assertNotFound();
})->with([
    'admin (role 2)'      => RoleId::Admin->value,
    'registrar (role 6)'  => RoleId::Registrar->value,
    'superadmin (role 7)' => RoleId::SuperAdmin->value,
]);
