<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section 9: Uniqueness rule — a doctor cannot have two sessions
     * at the same facility on the same date.
     */
    public function up(): void
    {
        Schema::table('clinic_sessions', function (Blueprint $table) {
            $table->unique(
                ['doctor_id', 'facility_id', 'session_date'],
                'unique_doctor_facility_date'
            );
        });
    }

    public function down(): void
    {
        Schema::table('clinic_sessions', function (Blueprint $table) {
            $table->dropUnique('unique_doctor_facility_date');
        });
    }
};
