<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 23: Verification requests (evidence-backed provider/facility review).
 *
 * A dedicated, historical record of every verification submission — who applied,
 * what was submitted, who reviewed it, the verdict, evidence reference and the
 * reason for rejection. Verification status on doctors/facilities is derived
 * from, and kept in sync with, the latest request on this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_requests', function (Blueprint $t) {
            $t->id();

            // Polymorphic subject: doctor | facility
            $t->morphs('verifiable');

            $t->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('Applicant');

            // 'doctor' | 'facility' | 'facility_license' | 'profile_change'
            $t->string('type', 40);

            // pending | under_review | approved | rejected | more_info | suspended
            $t->string('status', 24)->default('pending')->index();

            $t->string('registry_number', 191)->nullable()->comment('Professional/license/registration identifier');

            $t->json('submitted_data')->nullable()->comment('Snapshot of submitted profile information');
            $t->json('evidence')->nullable()->comment('Document references / external source ids');
            $t->string('verification_source', 191)->nullable()->comment('Configurable provider/system source');

            $t->text('reviewer_notes')->nullable();
            $t->string('rejection_reason', 255)->nullable();

            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('expires_at')->nullable();

            // For the "more info requested" round-trip
            $t->json('requested_changes')->nullable();

            $t->timestamps();
            $t->index(['verifiable_type', 'status'], 'idx_verifications_type_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_requests');
    }
};
