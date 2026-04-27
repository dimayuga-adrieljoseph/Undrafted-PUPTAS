<?php

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it prunes expired refresh tokens', function () {
    $user = User::factory()->create();

    // Create an expired token
    RefreshToken::create([
        'user_id' => $user->id,
        'access_token' => 'expired_access',
        'refresh_token' => 'expired_refresh',
        'expires_at' => now()->subDay(),
    ]);

    // Create an active token
    RefreshToken::create([
        'user_id' => $user->id,
        'access_token' => 'active_access',
        'refresh_token' => 'active_refresh',
        'expires_at' => now()->addDay(),
    ]);

    $this->artisan('tokens:prune-expired')
        ->expectsOutput('Successfully pruned 1 expired refresh tokens.')
        ->assertExitCode(0);

    expect(RefreshToken::count())->toBe(1);
    expect(RefreshToken::where('access_token', 'active_access')->exists())->toBeTrue();
    expect(RefreshToken::where('access_token', 'expired_access')->exists())->toBeFalse();
});
