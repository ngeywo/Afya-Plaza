<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Snapshot of the financial plan used for this appointment's payment
            $table->string('plan_slug')->nullable()->after('payment_status');
            $table->decimal('consultation_fee_snapshot', 10, 2)->nullable()->after('plan_slug');
            // Which fee was locked at booking time (clinic session fee at booking moment)
            $table->decimal('platform_commission_snapshot', 10, 2)->nullable()->after('consultation_fee_snapshot');
            $table->decimal('doctor_earning_snapshot', 10, 2)->nullable()->after('platform_commission_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'plan_slug',
                'consultation_fee_snapshot',
                'platform_commission_snapshot',
                'doctor_earning_snapshot',
            ]);
        });
    }
};
