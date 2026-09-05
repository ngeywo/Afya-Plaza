<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 11: Clinic Day Operations
     *
     * - checked_in_by: records which facility staff performed the check-in
     * - consultation_started_at: when the doctor begins seeing the patient
     * - no_show_at / no_show_by: when the facility marks patient as absent
     *
     * NOTE: checked_in_at and completed_at already exist in the base schema.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('checked_in_by')
                ->nullable()
                ->after('checked_in_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('consultation_started_at')
                ->nullable()
                ->after('checked_in_at');

            $table->timestamp('no_show_at')
                ->nullable()
                ->after('completed_at');

            $table->foreignId('no_show_by')
                ->nullable()
                ->after('no_show_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['checked_in_by']);
            $table->dropForeign(['no_show_by']);
            $table->dropColumn(['checked_in_by', 'consultation_started_at', 'no_show_at', 'no_show_by']);
        });
    }
};
