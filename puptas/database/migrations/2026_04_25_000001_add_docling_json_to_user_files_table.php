<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_files', function (Blueprint $table) {
            // Stores the structured JSON output from Docling OCR conversion.
            // Nullable — populated asynchronously after the WebP is stored.
            $table->json('docling_json')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('user_files', function (Blueprint $table) {
            $table->dropColumn('docling_json');
        });
    }
};
