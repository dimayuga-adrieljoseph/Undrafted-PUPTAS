<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Models\User;
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
     * Log user deletion to audit logs (via AuditLogService).
     */
    protected function logDeletion(User $user): void
    {
        try {
            app(\App\Services\AuditLogService::class)->logActivity(
                'DELETE',
                'Users',
                "User account {$user->firstname} {$user->lastname} ({$user->email}) deleted via account removal.",
                null,
                'USER_MANAGEMENT'
            );
        } catch (\Exception $e) {
            logger()->error('Failed to write audit log during user deletion', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
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
