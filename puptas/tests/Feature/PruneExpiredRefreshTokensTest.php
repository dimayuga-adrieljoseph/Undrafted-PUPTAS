<?php

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it prunes expired refresh tokens properly', function () {
    $user = User::factory()->create();

    // 1. Active access token (should be kept)
    RefreshToken::create([
        'user_id' => $user->id,
        'access_token' => 'active_access',
        'refresh_token' => 'active_refresh',
        'expires_at' => now()->addDay(),
    ]);

    // 2. Expired access token, but refresh token still valid (< 30 days) (should be kept)
    RefreshToken::create([
        'user_id' => $user->id,
        'access_token' => 'expired_access_valid_refresh',
        'refresh_token' => 'valid_refresh',
        'expires_at' => now()->subDays(5),
    ]);

    // 3. Expired access token, no refresh token (should be deleted)
    RefreshToken::create([
        'user_id' => $user->id,
        'access_token' => 'expired_access_no_refresh',
        'refresh_token' => null,
        'expires_at' => now()->subDay(),
    ]);

    // 4. Expired access token, refresh token expired (> 30 days) (should be deleted)
    RefreshToken::create([
        'user_id' => $user->id,
        'access_token' => 'expired_access_expired_refresh',
        'refresh_token' => 'expired_refresh',
        'expires_at' => now()->subDays(31),
    ]);

    $this->artisan('tokens:prune-expired')
        ->expectsOutput('Successfully pruned 2 expired refresh tokens.')
        ->assertExitCode(0);

    expect(RefreshToken::count())->toBe(2);
    expect(RefreshToken::where('access_token', 'active_access')->exists())->toBeTrue();
    expect(RefreshToken::where('access_token', 'expired_access_valid_refresh')->exists())->toBeTrue();
    expect(RefreshToken::where('access_token', 'expired_access_no_refresh')->exists())->toBeFalse();
    expect(RefreshToken::where('access_token', 'expired_access_expired_refresh')->exists())->toBeFalse();
});
