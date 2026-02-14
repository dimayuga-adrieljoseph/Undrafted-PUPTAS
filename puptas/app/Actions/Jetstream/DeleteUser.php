<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Contracts\DeletesTeams;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Create a new action instance.
     */
    public function __construct(protected DeletesTeams $deletesTeams) {}

    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Log the deletion for audit purposes before removing the user
            $this->logDeletion($user);

            $this->deleteTeams($user);
            $user->tokens->each->delete();
            $user->delete();
        });
    }

    /**
     * Log user deletion to audit logs.
     */
    protected function logDeletion(User $user): void
    {
        try {
            AuditLog::create([
                'user_id' => $user->id,
                'model_type' => 'User',
                'model_id' => $user->id,
                'action' => 'deleted',
                'old_values' => [
                    'email' => $user->email,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'role_id' => $user->role_id,
                ],
                'new_values' => null,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            // Log the error but don't prevent deletion
            logger()->error('Failed to create audit log during user deletion', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Delete the teams and team associations attached to the user.
     */
    protected function deleteTeams(User $user): void
    {
        $user->teams()->detach();

        $user->ownedTeams->each(function (Team $team) {
            $this->deletesTeams->delete($team);
        });
    }
}
