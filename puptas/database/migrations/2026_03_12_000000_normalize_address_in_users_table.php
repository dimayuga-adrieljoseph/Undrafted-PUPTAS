<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add the new structured address columns
        Schema::table('users', function (Blueprint $table) {
            $table->string('street_address')->nullable()->after('address');
            $table->string('barangay')->nullable()->after('street_address');
            $table->string('city', 100)->nullable()->after('barangay');
            $table->string('province', 100)->nullable()->after('city');
            $table->string('postal_code', 10)->nullable()->after('province');
        });

        // 2. Migrate existing data: copy old address value into street_address
        DB::statement('UPDATE users SET street_address = address WHERE address IS NOT NULL AND address != \'\'');

        // 3. Drop the old address column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }

    public function down(): void
    {
        // Re-add the old address column
        Schema::table('users', function (Blueprint $table) {
            $table->string('address')->nullable()->after('contactnumber');
        });

        // Reconstruct a combined address string from structured columns
        DB::statement("
            UPDATE users
            SET address = TRIM(
                CONCAT_WS(', ',
                    NULLIF(street_address, ''),
                    NULLIF(barangay, ''),
                    NULLIF(city, ''),
                    NULLIF(province, ''),
                    NULLIF(postal_code, '')
                )
            )
        ");

        // Drop the new structured columns
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['street_address', 'barangay', 'city', 'province', 'postal_code']);
        });
    }
};
