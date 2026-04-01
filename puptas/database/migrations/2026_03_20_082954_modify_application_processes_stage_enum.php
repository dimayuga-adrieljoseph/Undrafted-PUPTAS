<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_processes', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['performed_by']);
            }
        });
        
        Schema::table('application_processes', function (Blueprint $table) {
            $table->string('performed_by')->nullable()->change();
        });
    }

    public function down(): void
    {
    }
};
