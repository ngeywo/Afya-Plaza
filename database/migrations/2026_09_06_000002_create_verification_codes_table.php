<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 23: One-time verification codes for email & phone confirmation.
 *
 * Enforces expiration, attempt limits, resend limits and used/unused status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 'email' | 'phone'
            $t->string('channel', 10);
            $t->string('address', 191)->nullable(); // maskable snapshot of target

            $t->string('code', 12);
            $t->string('token', 100)->nullable()->unique()->comment('Reference token exposed to client');

            $t->timestamp('expires_at');
            $t->integer('attempts')->default(0);
            $t->integer('max_attempts')->default(5);
            $t->integer('max_resends')->default(5);
            $t->integer('resends')->default(0);

            $t->boolean('used')->default(false);
            $t->timestamp('used_at')->nullable();
            $t->timestamp('last_resent_at')->nullable();

            $t->timestamps();
            $t->index(['user_id', 'channel', 'used'], 'idx_vcodes_user_channel_used');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
    }
};
