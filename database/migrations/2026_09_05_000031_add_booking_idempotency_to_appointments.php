<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 17: Booking idempotency.
     *
     * A patient browser retry / double-click / slow network must never create
     * two appointments. Callers send an idempotency_key with the booking request;
     * the same (user_id, idempotency_key) always resolves to the same appointment.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->after('appointment_number');
            $table->unique(['user_id', 'idempotency_key'], 'appointments_user_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('appointments_user_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
