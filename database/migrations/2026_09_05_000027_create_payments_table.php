<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // Internal payment reference (e.g., PAY-YYYYMMDD-XXXXX)
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // payer (patient)
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('gross_amount', 10, 2);
            $table->decimal('commission_amount', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2); // gross - commission
            $table->string('currency', 3)->default('KES');

            $table->enum('method', ['mobile_money', 'card', 'bank', 'cash', 'other'])->default('mobile_money');
            $table->string('provider', 50)->default('simulation'); // e.g. 'mpesa', 'simulation'
            $table->string('provider_reference')->nullable(); // Provider's transaction id
            $table->string('provider_phone', 20)->nullable(); // e.g. for STK push

            $table->enum('status', [
                'pending',
                'processing',
                'paid',
                'failed',
                'cancelled',
                'refunded',
                'partially_refunded',
            ])->default('pending');
            $table->text('failure_reason')->nullable();
            $table->string('idempotency_key')->nullable(); // protects against duplicate webhooks

            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->decimal('refunded_amount', 10, 2)->default(0);

            // Snapshot of the financial allocation that was used at the moment of confirmation.
            // These columns are NEVER recomputed from current rules — they preserve historical truth.
            $table->unsignedTinyInteger('commission_type_snapshot')->nullable();
            $table->decimal('commission_rate_snapshot', 5, 4)->nullable();
            $table->decimal('fixed_commission_snapshot', 10, 2)->nullable();
            $table->string('commission_rule_source')->nullable(); // e.g. "plan:professional", "fallback:starter"

            $table->json('metadata')->nullable();
            $table->timestamps();

            // Idempotency: a unique provider transaction reference ensures we don't double-record
            $table->unique(['provider', 'provider_reference'], 'payments_provider_ref_unique');
            $table->unique('idempotency_key', 'payments_idempotency_unique');

            $table->index(['status']);
            $table->index(['doctor_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['confirmed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
