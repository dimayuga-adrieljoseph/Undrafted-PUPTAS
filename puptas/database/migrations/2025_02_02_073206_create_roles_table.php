<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'Applicant'],
            ['id' => 2, 'name' => 'Admin'],
            ['id' => 3, 'name' => 'Evaluator'],
            ['id' => 4, 'name' => 'Interviewer'],
            ['id' => 5, 'name' => 'Medical'],
            ['id' => 6, 'name' => 'Record Staff'],
            ['id' => 7, 'name' => 'Superadmin'],
        ]);

        // Add the FK constraint now that roles table exists
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
        Schema::dropIfExists('roles');
    }
};
