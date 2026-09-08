<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 23: Account lifecycle on users.
 *
 * Distinguishes account existence, authentication ability, contact
 * verification, profile completeness and active/suspended/deactivated state
 * rather than a single boolean.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            // Contact verification (independent of the legacy is_verified bool)
            $t->timestamp('phone_verified_at')->nullable()->after('is_verified');

            // Lifecycle state — this is the authoritative account control flag.
            $t->string('account_state', 24)->default('registered')->after('phone_verified_at');
            $t->timestamp('deactivated_at')->nullable()->after('account_state');

            // Rejection information for applications that did not pass review.
            $t->string('account_rejection_reason', 255)->nullable()->after('deactivated_at');
            $t->timestamp('account_rejection_at')->nullable()->after('account_rejection_reason');

            // When the account becomes active (distinct from is_active legacy slug)
            $t->timestamp('account_activated_at')->nullable()->after('account_rejection_at');

            $t->index('account_state', 'idx_users_account_state');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex('idx_users_account_state');
            $t->dropColumn([
                'phone_verified_at',
                'account_state',
                'deactivated_at',
                'account_rejection_reason',
                'account_rejection_at',
                'account_activated_at',
            ]);
        });
    }
};
