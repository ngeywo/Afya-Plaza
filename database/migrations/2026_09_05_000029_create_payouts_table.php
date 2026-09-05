<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // e.g. PO-YYYYMMDD-XXXXX
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('KES');

            $table->enum('status', [
                'requested',  // doctor asked for a payout
                'processing', // admin is preparing
                'paid',       // money sent
                'rejected',
                'cancelled',
            ])->default('requested');

            $table->text('notes')->nullable();
            $table->string('payment_reference')->nullable(); // external bank/mobile ref once paid
            $table->string('payment_method')->nullable();

            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['doctor_id', 'status']);
            $table->index(['status', 'requested_at']);
        });

        // Pivot: which earnings are included in which payout
        Schema::create('payout_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('earning_id')->constrained('doctor_earnings')->cascadeOnDelete();
            $table->decimal('amount', 10, 2); // captured at the time the link was made
            $table->timestamps();

            $table->unique(['payout_id', 'earning_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_earnings');
        Schema::dropIfExists('payouts');
    }
};
