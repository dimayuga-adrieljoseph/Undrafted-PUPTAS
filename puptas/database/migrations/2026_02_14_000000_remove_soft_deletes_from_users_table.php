<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration removes soft delete functionality from the users table
     * to enable email address reuse after account deletion.
     */
    public function up(): void
    {
        // Check if the deleted_at column exists before trying to query it
        if (Schema::hasColumn('users', 'deleted_at')) {
            // First, permanently delete any soft-deleted users
            // This ensures no conflicts when removing the column
            $softDeletedCount = DB::table('users')
                ->whereNotNull('deleted_at')
                ->count();

            if ($softDeletedCount > 0) {
                logger()->info('Permanently deleting soft-deleted users', [
                    'count' => $softDeletedCount
                ]);

                // Log these deletions to audit_logs before removing
                $softDeletedUsers = DB::table('users')
                    ->whereNotNull('deleted_at')
                    ->get(['id', 'email', 'firstname', 'lastname', 'deleted_at']);

                foreach ($softDeletedUsers as $user) {
                    try {
                        DB::table('audit_logs')->insert([
                            'user_id' => null,
                            'model_type' => 'User',
                            'model_id' => $user->id,
                            'action' => 'permanently_deleted_via_migration',
                            'old_values' => json_encode([
                                'email' => $user->email,
                                'firstname' => $user->firstname,
                                'lastname' => $user->lastname,
                                'soft_deleted_at' => $user->deleted_at,
                            ]),
                            'new_values' => null,
                            'ip_address' => '127.0.0.1',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } catch (\Exception $e) {
                        logger()->error('Failed to create audit log for soft-deleted user', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Permanently delete soft-deleted users
                DB::table('users')
                    ->whereNotNull('deleted_at')
                    ->delete();

                logger()->info('Soft-deleted users permanently removed', [
                    'count' => $softDeletedCount
                ]);
            }

            // Remove the deleted_at column
            Schema::table('users', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });

            logger()->info('Removed deleted_at column from users table - hard delete now active');
        } else {
            logger()->info('Column deleted_at does not exist on users table - skipping migration');
        }
    }

    /**
     * Reverse the migrations.
     * 
     * Re-adds the soft delete column if rollback is needed.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        logger()->info('Re-added deleted_at column to users table - soft delete restored');
    }
};
