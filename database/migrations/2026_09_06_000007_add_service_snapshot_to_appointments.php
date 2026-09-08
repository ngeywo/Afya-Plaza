<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 23 (Facility-specific services & pricing): record which service was
     * booked and freeze its price at booking time so later price edits NEVER
     * rewrite a historical appointment.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('doctor_facility_service_id')->nullable()->after('clinic_session_id')->constrained()->nullOnDelete();
            $table->string('service_name', 191)->nullable()->after('doctor_facility_service_id');
            $table->decimal('service_price_snapshot', 10, 2)->nullable()->after('service_name');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['doctor_facility_service_id']);
            $table->dropColumn(['doctor_facility_service_id', 'service_name', 'service_price_snapshot']);
        });
    }
};
