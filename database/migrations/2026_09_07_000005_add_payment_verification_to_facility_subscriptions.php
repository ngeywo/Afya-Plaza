<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_subscriptions', function (Blueprint $table) {
            $table->string('payment_method', 50)->nullable()->after('payment_reference');
            $table->unsignedBigInteger('verified_by')->nullable()->after('payment_method');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->text('verification_notes')->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('facility_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'verified_by', 'verified_at', 'verification_notes']);
        });
    }
};
