<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['active', 'cancelled', 'expired', 'pending'])->default('active');
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->decimal('effective_commission_rate', 5, 4)->nullable();
            // Commission rate at the time of subscription — used for historical appointments
            $table->unsignedTinyInteger('effective_commission_type')->nullable();
            $table->decimal('effective_fixed_commission', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['doctor_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_subscriptions');
    }
};
