<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make user_files.file_path nullable so that an "uploading" placeholder row
 * can be inserted before the actual file is stored to disk/S3.
 *
 * The reuploadFile() flow does updateOrCreate(..., ['status' => 'uploading'])
 * before calling FileService::storeRaw(), which means on a first upload there
 * is no file_path yet. MySQL rejects the INSERT when the column is NOT NULL
 * with no default, causing every first-time upload to fail with:
 *   SQLSTATE[HY000]: General error: 1364 Field 'file_path' doesn't have a default value
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_files', function (Blueprint $table) {
            $table->string('file_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Restore NOT NULL — set any nulls to empty string first to avoid constraint violation
        \Illuminate\Support\Facades\DB::table('user_files')
            ->whereNull('file_path')
            ->update(['file_path' => '']);

        Schema::table('user_files', function (Blueprint $table) {
            $table->string('file_path')->nullable(false)->change();
        });
    }
};
