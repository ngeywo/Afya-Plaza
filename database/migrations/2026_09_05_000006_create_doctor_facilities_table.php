<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DoctorFacility represents the relationship between a doctor and a facility.
     * It means: "Dr. X practices at Facility Y"
     * It does NOT mean: "Dr. X is at Facility Y today"
     */
    public function up(): void
    {
        Schema::create('doctor_facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->decimal('consultation_fee', 10, 2)->nullable();
            $table->boolean('accepts_appointments')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['doctor_id', 'facility_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_facilities');
    }
};
