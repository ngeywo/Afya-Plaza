<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Doctors: full verification lifecycle
        Schema::table('doctors', function (Blueprint $t) {
            $t->string('verification_status', 32)->default('pending')->after('is_verified');
            $t->string('rejection_reason', 255)->nullable()->after('verified_at');
            $t->text('rejection_notes')->nullable()->after('rejection_reason');
            $t->timestamp('suspended_at')->nullable()->after('rejection_notes');
            $t->foreignId('suspended_by')->nullable()->after('suspended_at')->constrained('users')->nullOnDelete();
            $t->string('suspension_reason', 255)->nullable()->after('suspended_by');
            $t->index(['verification_status', 'is_active'], 'idx_doctors_status_active');
        });

        // Facilities: full verification lifecycle
        Schema::table('facilities', function (Blueprint $t) {
            $t->string('verification_status', 32)->default('pending')->after('is_verified');
            $t->string('rejection_reason', 255)->nullable()->after('is_active');
            $t->text('rejection_notes')->nullable()->after('rejection_reason');
            $t->timestamp('suspended_at')->nullable()->after('rejection_notes');
            $t->foreignId('suspended_by')->nullable()->after('suspended_at')->constrained('users')->nullOnDelete();
            $t->string('suspension_reason', 255)->nullable()->after('suspended_by');
            $t->index(['verification_status', 'is_active'], 'idx_facilities_status_active');
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $t) {
            $t->dropForeign(['suspended_by']);
            $t->dropColumn(['verification_status', 'rejection_reason', 'rejection_notes', 'suspended_at', 'suspended_by', 'suspension_reason']);
        });
        Schema::table('doctors', function (Blueprint $t) {
            $t->dropForeign(['suspended_by']);
            $t->dropColumn(['verification_status', 'rejection_reason', 'rejection_notes', 'suspended_at', 'suspended_by', 'suspension_reason']);
        });
    }
};
