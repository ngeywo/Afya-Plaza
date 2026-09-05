<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Doctor-Facility relationships: full governance lifecycle
        Schema::table('doctor_facilities', function (Blueprint $t) {
            $t->string('status', 32)->default('active')->after('is_active');
            $t->foreignId('invited_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->after('invited_by')->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable()->after('approved_by');
            $t->timestamp('declined_at')->nullable()->after('approved_at');
            $t->string('decline_reason', 255)->nullable()->after('declined_at');
            $t->timestamp('ended_at_governance')->nullable()->after('decline_reason');
            $t->foreignId('ended_by')->nullable()->after('ended_at_governance')->constrained('users')->nullOnDelete();
            $t->text('notes_governance')->nullable()->after('ended_by');
            $t->index('status', 'idx_doctor_facilities_status');
        });

        // Clinic Sessions: governance fields
        Schema::table('clinic_sessions', function (Blueprint $t) {
            $t->foreignId('cancelled_by')->nullable()->after('cancellation_reason')->constrained('users')->nullOnDelete();
            $t->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            $t->foreignId('confirmed_by_doctor_user')->nullable()->after('doctor_confirmed_at')->constrained('users')->nullOnDelete();
            $t->foreignId('confirmed_by_facility_user')->nullable()->after('facility_confirmed_at')->constrained('users')->nullOnDelete();
            $t->index('status', 'idx_clinic_sessions_status');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_sessions', function (Blueprint $t) {
            $t->dropForeign(['cancelled_by']);
            $t->dropForeign(['confirmed_by_doctor_user']);
            $t->dropForeign(['confirmed_by_facility_user']);
            $t->dropColumn(['cancelled_by', 'cancelled_at', 'confirmed_by_doctor_user', 'confirmed_by_facility_user']);
        });
        Schema::table('doctor_facilities', function (Blueprint $t) {
            $t->dropForeign(['invited_by']);
            $t->dropForeign(['approved_by']);
            $t->dropForeign(['ended_by']);
            $t->dropColumn([
                'status', 'invited_by', 'approved_by', 'approved_at',
                'declined_at', 'decline_reason', 'ended_at_governance',
                'ended_by', 'notes_governance',
            ]);
        });
    }
};
