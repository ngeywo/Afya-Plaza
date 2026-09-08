<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('event'); // checkout trial activated payment.received renewal payment.failed past_due grace_ended suspended expired upgraded downgraded cancelled adjusted
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_subscription_events');
    }
};
