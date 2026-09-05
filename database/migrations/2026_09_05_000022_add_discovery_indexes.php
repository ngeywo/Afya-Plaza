<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 8: Patient Discovery Engine indexes.
     *
     * session_date + status + doctor_confirmation + facility_confirmation
     * covers the primary patient discovery query: "show confirmed sessions on a date".
     *
     * facility_id + session_date + status
     * covers location-filtered searches.
     *
     * doctor_id + session_date + status
     * covers per-doctor availability queries.
     */
    public function up(): void
    {
        Schema::table('clinic_sessions', function (Blueprint $table) {
            // Primary discovery: confirmed sessions on a date, at a location
            $table->index(['session_date', 'status', 'doctor_confirmation', 'facility_confirmation'], 'idx_discovery_confirmed');
            // Location filtering: sessions at a facility on a date
            $table->index(['facility_id', 'session_date', 'status'], 'idx_discovery_facility');
            // Doctor availability: sessions for a doctor on a date
            $table->index(['doctor_id', 'session_date', 'status'], 'idx_discovery_doctor');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_discovery_confirmed');
            $table->dropIndex('idx_discovery_facility');
            $table->dropIndex('idx_discovery_doctor');
        });
    }
};
