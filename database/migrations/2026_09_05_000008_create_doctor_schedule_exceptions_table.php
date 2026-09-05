<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Schedule exceptions represent changes to the regular schedule.
     * e.g., doctor changes location on a specific day, or cancels a clinic.
     */
    public function up(): void
    {
        Schema::create('doctor_schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_facility_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('type', ['cancelled', 'rescheduled', 'additional', 'location_changed']);
            $table->string('reason')->nullable();
            $table->foreignId('target_facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->time('new_start_time')->nullable();
            $table->time('new_end_time')->nullable();
            $table->timestamps();

            $table->index(['doctor_facility_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_schedule_exceptions');
    }
};
