<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\ClientRepository;

/**
 * Creates the Passport client-credentials clients needed for external API access.
 *
 * Safe to run multiple times — checks for existing clients by name before creating.
 *
 * Usage:
 *   php artisan db:seed --class=PassportClientSeeder
 *
 * On Railway / staging, set the generated client_id and client_secret as
 * environment variables (PASSPORT_MEDICAL_CLIENT_ID, etc.) so partners can use them.
 */
class PassportClientSeeder extends Seeder
{
    /**
     * Client definitions.
     * Each entry will create one client-credentials client if it doesn't already exist.
     */
    private array $clients = [
        [
            'name' => 'Medical Integration Client',
        ],
        [
            'name' => 'Student Admission Client',
        ],
        [
            'name' => 'API Docs Test Client',
        ],
    ];

    public function run(): void
    {
        $repository = app(ClientRepository::class);

        foreach ($this->clients as $definition) {
            $existing = \Laravel\Passport\Client::where('name', $definition['name'])
                ->where('personal_access_client', false)
                ->where('password_client', false)
                ->first();

            if ($existing) {
                $this->command->info("  Client already exists: [{$existing->id}] {$definition['name']}");
                continue;
            }

            $client = $repository->create(
                userId: null,
                name: $definition['name'],
                redirect: '',
                provider: null,
                personalAccess: false,
                password: false,
                confidential: true,
            );

            $this->command->info("  Created client: [{$client->id}] {$definition['name']}");
            $this->command->line("    client_id:     {$client->id}");
            $this->command->line("    client_secret: {$client->plainSecret}");
            $this->command->warn("  ⚠  Save the secret above — it won't be shown again.");
        }
    }
}
