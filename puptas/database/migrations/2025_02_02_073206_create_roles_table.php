<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Insert default roles
        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'Applicant'],
            ['id' => 2, 'name' => 'Admin'],
            ['id' => 3, 'name' => 'Evaluator'],
            ['id' => 4, 'name' => 'Interviewer'],
            ['id' => 5, 'name' => 'Medical'],
            ['id' => 6, 'name' => 'Record Staff'],
        ]);
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
