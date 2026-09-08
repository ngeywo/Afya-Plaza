<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 23 (Facility Payments): payments belong to the FACILITY. The patient
     * pays the facility; commission = configurable platform fee; net = facility's
     * proceeds. A payment may optionally reference the facility payment account
     * the patient was shown.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('recipient_type', ['facility', 'doctor'])->default('facility')->after('facility_id');
            $table->foreignId('facility_payment_account_id')->nullable()->after('recipient_type')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['facility_payment_account_id']);
            $table->dropColumn(['recipient_type', 'facility_payment_account_id']);
        });
    }
};
