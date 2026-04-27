<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PruneExpiredRefreshTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokens:prune-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune expired refresh tokens from the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $totalDeleted = 0;

        do {
            $count = \App\Models\RefreshToken::where(function ($query) {
                $query->where('expires_at', '<', now()->subDays(30))
                      ->orWhere(function ($q) {
                          $q->where('expires_at', '<', now())
                            ->whereNull('refresh_token');
                      });
            })
            ->limit(1000)
            ->delete();

            $totalDeleted += $count;
        } while ($count > 0);

        $tokenLabel = $totalDeleted === 1 ? 'token' : 'tokens';

        $this->info("Successfully pruned {$totalDeleted} expired refresh {$tokenLabel}.");
    }
}
