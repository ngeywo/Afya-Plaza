<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete()->unique();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedInteger('plan_version');
            $table->json('plan_snapshot');

            $table->string('status')->default('pending_payment'); // FacilitySubscriptionStatus enum
            $table->string('billing_cycle')->default('monthly');  // BillingCycle enum
            $table->string('currency', 3)->default('KES');
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->decimal('annual_price', 10, 2)->default(0);
            $table->decimal('effective_amount', 10, 2)->nullable(); // amount due for current billing period

            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_billing_date')->nullable();
            $table->timestamp('next_billing_date')->nullable();
            $table->timestamp('past_due_since')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(true);
            $table->string('cancelled_reason')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->decimal('last_amount_paid', 10, 2)->nullable();
            $table->string('payment_reference')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_subscriptions');
    }
};
