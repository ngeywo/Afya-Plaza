<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->decimal('gross_amount', 10, 2); // total patient payment
            $table->decimal('platform_commission', 10, 2); // Afya Plaza commission
            $table->decimal('facility_amount', 10, 2); // facility retains
            $table->decimal('doctor_amount', 10, 2); // doctor receives
            $table->string('agreement_type'); // snapshot of agreement type used
            $table->json('agreement_snapshot')->nullable(); // full snapshot of terms
            $table->string('currency', 3)->default('KES');
            $table->string('status')->default('pending'); // pending, approved, paid, on_hold, cancelled
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['facility_id', 'status']);
            $table->index(['doctor_id', 'status']);
            $table->unique('appointment_id'); // prevent duplicate settlements
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
