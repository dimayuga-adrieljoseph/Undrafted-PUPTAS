<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

class ApiClientController extends Controller
{
    public function __construct(private AuditLogService $auditLogService)
    {
    }

    /**
     * Display all M2M clients (excludes personal access & password grant clients).
     */
    public function index(): Response
    {
        $clients = DB::table('oauth_clients')
            ->where('personal_access_client', false)
            ->where('password_client', false)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($client) => [
                'id'         => $client->id,
                'name'       => $client->name,
                'secret'     => null, // never expose
                'created_at' => $client->created_at,
                'revoked'    => $client->revoked,
            ]);

        $availableScopes = collect(Passport::scopes())->map(fn ($scope) => [
            'id'          => $scope->id,
            'description' => $scope->description,
        ]);

        // Map scopes per client via token grant records
        $clientsWithScopes = $clients->map(function ($client) {
            // Read scopes stored in the client record (Passport stores them on oauth_auth_codes for CC grants)
            // We store them on creation via the grant field — retrieve from oauth_clients directly
            $raw = DB::table('oauth_clients')->where('id', $client['id'])->value('scopes');
            $client['scopes'] = $raw ? json_decode($raw, true) : [];
            return $client;
        });

        return Inertia::render('SuperAdmin/ApiClients', [
            'clients'         => $clientsWithScopes,
            'available_scopes' => $availableScopes,
        ]);
    }

    /**
     * Create a new M2M (Client Credentials) client.
     */
    public function store(Request $request, ClientRepository $clients)
    {
        $validated = $request->validate([
            'name'   => 'required|string|max:255',
            'scopes' => 'required|array|min:1',
            'scopes.*' => 'string|in:' . implode(',', Passport::scopeIds()),
        ]);

        $client = $clients->create(
            null,               // user_id (null for M2M)
            $validated['name'],
            '',                 // redirect
            null,               // provider
            false,              // personal_access
            false,              // password
            true                // confidential
        );

        // Store scopes on the client record
        DB::table('oauth_clients')
            ->where('id', $client->id)
            ->update(['scopes' => json_encode($validated['scopes'])]);

        $this->auditLogService->logActivity(
            'CREATE',
            'API Client Management',
            sprintf(
                'Super Admin created M2M client "%s" (ID: %s) with scopes: %s.',
                $validated['name'],
                $client->id,
                implode(', ', $validated['scopes'])
            ),
            null,
            AuditLog::CATEGORY_ADMISSION_DATA
        );

        return back()->with([
            'new_client' => [
                'id'     => $client->id,
                'name'   => $client->name,
                'secret' => $client->secret, // one-time reveal
                'scopes' => $validated['scopes'],
            ],
        ]);
    }

    /**
     * Revoke a client (soft-delete).
     */
    public function destroy(string $id)
    {
        $client = DB::table('oauth_clients')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        abort_if(!$client, 404, 'Client not found.');

        DB::table('oauth_clients')
            ->where('id', $id)
            ->update([
                'revoked'    => true,
                'deleted_at' => now(),
            ]);

        // Revoke all tokens for this client
        DB::table('oauth_access_tokens')
            ->where('client_id', $id)
            ->update(['revoked' => true]);

        $this->auditLogService->logActivity(
            'DELETE',
            'API Client Management',
            sprintf('Super Admin revoked M2M client "%s" (ID: %s).', $client->name, $id),
            null,
            AuditLog::CATEGORY_ADMISSION_DATA
        );

        return back()->with('success', 'Client revoked successfully.');
    }

    /**
     * Regenerate the client secret.
     */
    public function regenerate(string $id, ClientRepository $clients)
    {
        $client = DB::table('oauth_clients')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        abort_if(!$client, 404, 'Client not found.');

        $passportClient = \Laravel\Passport\Client::find($id);
        abort_if(!$passportClient, 404);

        $regenerated = $clients->regenerateSecret($passportClient);

        $this->auditLogService->logActivity(
            'UPDATE',
            'API Client Management',
            sprintf('Super Admin regenerated secret for M2M client "%s" (ID: %s).', $client->name, $id),
            null,
            AuditLog::CATEGORY_ADMISSION_DATA
        );

        return back()->with([
            'new_client' => [
                'id'     => $regenerated->id,
                'name'   => $regenerated->name,
                'secret' => $regenerated->secret,
                'scopes' => json_decode($client->scopes ?? '[]', true),
            ],
        ]);
    }
}
