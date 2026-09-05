<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DoctorFacilitySchedule represents the recurring schedule a doctor has
     * at a particular facility. e.g., "Every Thursday at Busia, 10am-4pm"
     */
    public function up(): void
    {
        Schema::create('doctor_facility_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_facility_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('day_of_week'); // 0=Sunday, 6=Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('slot_duration_minutes')->default(30);
            $table->integer('max_appointments')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->index(['doctor_facility_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_facility_schedules');
    }
};
