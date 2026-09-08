<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_facility_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_facility_id')->constrained()->cascadeOnDelete();
            $table->string('agreement_type');
            $table->decimal('doctor_share_percentage', 5, 2)->nullable();
            $table->decimal('facility_share_percentage', 5, 2)->nullable();
            $table->decimal('fixed_doctor_amount', 10, 2)->nullable();
            $table->decimal('fixed_facility_amount', 10, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index('doctor_facility_id', 'dfa_df_id_idx');
            $table->index(['effective_from', 'effective_until'], 'dfa_dates_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_facility_agreements');
    }
};
