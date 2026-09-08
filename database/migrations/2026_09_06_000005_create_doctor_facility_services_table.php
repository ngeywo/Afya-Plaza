<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 23 (Facility Management): the services a doctor offers at ONE specific
     * facility, each with that facility's price. Relationship-scoped — facility A
     * can never see or edit facility B's services.
     */
    public function up(): void
    {
        Schema::create('doctor_facility_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_facility_id')->constrained()->cascadeOnDelete();
            $table->string('service_name', 191);
            $table->decimal('price', 10, 2);
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['doctor_facility_id', 'service_name'], 'df_services_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_facility_services');
    }
};
