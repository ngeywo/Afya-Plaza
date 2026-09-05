<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();

            $table->decimal('gross_amount', 10, 2);
            $table->decimal('net_amount', 10, 2); // what the doctor earned (after platform commission)
            $table->decimal('commission_amount', 10, 2);
            $table->string('currency', 3)->default('KES');

            // Snapshot of plan at time of earning creation
            $table->string('plan_slug')->nullable();
            $table->decimal('commission_rate_snapshot', 5, 4)->nullable();
            $table->unsignedTinyInteger('commission_type_snapshot')->nullable();

            // Earning lifecycle:
            //   pending   = appointment completed, money collected, awaiting payout eligibility
            //   available = doctor can request payout
            //   paid_out  = included in a processed payout
            //   reversed  = adjustment (e.g. refund processed, payment reversed)
            $table->enum('status', ['pending', 'available', 'paid_out', 'reversed'])->default('pending');
            $table->timestamp('available_at')->nullable(); // when earning became available for payout
            $table->timestamp('paid_out_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('description')->nullable(); // human-readable: "Consultation — APT-YYYYMMDD-XXXXX"
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(['payment_id']); // one earning record per payment

            $table->index(['doctor_id', 'status']);
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_earnings');
    }
};
