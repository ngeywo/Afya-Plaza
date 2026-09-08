<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 23 (Unified Doctor Identity): record who initiated a doctor-initiated
     * join request (status PENDING). INVITED records who the facility invited.
     */
    public function up(): void
    {
        Schema::table('doctor_facilities', function (Blueprint $table) {
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('doctor_facilities', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->dropColumn('requested_by');
        });
    }
};
