<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ClinicSession is the authoritative source of "where the doctor is working
     * on a particular date". Patient-visible availability must come from here,
     * NOT from a doctor_facility relationship.
     */
    public function up(): void
    {
        Schema::create('clinic_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_facility_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->date('session_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('slot_duration_minutes')->default(30);
            $table->integer('max_appointments')->nullable();
            $table->integer('booked_appointments')->default(0);
            $table->decimal('consultation_fee', 10, 2)->default(0);
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
            $table->enum('doctor_confirmation', ['pending', 'confirmed', 'declined'])->default('pending');
            $table->timestamp('doctor_confirmed_at')->nullable();
            $table->enum('facility_confirmation', ['pending', 'confirmed', 'declined'])->default('pending');
            $table->timestamp('facility_confirmed_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['doctor_id', 'session_date']);
            $table->index(['facility_id', 'session_date']);
            $table->index(['session_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinic_sessions');
    }
};
