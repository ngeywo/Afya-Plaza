<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add facility_location_id to appointments to mirror the location
     * of the clinic session that was booked (Phase 9 / Section 19).
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('facility_location_id')->nullable()->after('facility_id')
                ->constrained('facility_locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('facility_location_id');
        });
    }
};
