<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_sessions', function (Blueprint $table) {
            $table->foreignId('facility_location_id')->nullable()->after('facility_id')->constrained('facility_locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clinic_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('facility_location_id');
        });
    }
};
