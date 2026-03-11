<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Consolidated: final human-readable audit trail schema (includes detail fields)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username')->nullable();
            $table->string('user_role', 50)->nullable();
            $table->string('log_category', 50)->nullable();
            $table->string('action_type', 50)->nullable();
            $table->string('module_name', 100)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('login_time')->nullable();
            $table->timestamp('logout_time')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('request_url', 512)->nullable();
            $table->string('session_id', 100)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();

            $table->index('action_type');
            $table->index('module_name');
            $table->index('ip_address');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
