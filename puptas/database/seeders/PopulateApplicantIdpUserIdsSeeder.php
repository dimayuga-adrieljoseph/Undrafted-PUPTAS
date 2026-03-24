<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class PopulateApplicantIdpUserIdsSeeder extends Seeder
{
    public function run(): void
    {
        User::query()
            ->where('role_id', 1)
            ->whereNull('idp_user_id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    // Deterministic and unique format for easy cross-system tracing.
                    $user->update([
                        'idp_user_id' => 'idp-applicant-' . $user->id,
                    ]);
                }
            });
    }
}
