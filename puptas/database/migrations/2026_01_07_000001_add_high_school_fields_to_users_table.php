<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('school')->nullable()->after('address');
            $table->string('school_address')->nullable()->after('school');
            $table->string('school_year')->nullable()->after('school_address');
            $table->date('date_graduated')->nullable()->after('school_year');
            $table->string('strand')->nullable()->after('date_graduated');
            $table->string('track')->nullable()->after('strand');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'school',
                'school_address',
                'school_year',
                'date_graduated',
                'strand',
                'track',
            ]);
        });
    }
};
