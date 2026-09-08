<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('scope')->default('doctor')->after('slug'); // doctor (legacy) | facility
            $table->unsignedInteger('version')->default(1)->after('description');

            $table->decimal('annual_price', 10, 2)->default(0)->after('monthly_price');
            $table->string('currency', 3)->default('KES')->after('annual_price');
            $table->decimal('annual_discount_percent', 5, 2)->default(0)->after('currency');
            $table->unsignedInteger('trial_days')->nullable()->after('annual_discount_percent');
            $table->string('support_level')->default('standard')->after('trial_days');

            $table->unsignedInteger('max_doctors')->nullable()->after('support_level');
            $table->unsignedInteger('max_staff')->nullable()->after('max_doctors');
            $table->unsignedInteger('max_locations')->nullable()->after('max_staff');
            $table->unsignedInteger('max_monthly_bookings')->nullable()->after('max_locations');
            $table->unsignedInteger('max_sms')->nullable()->after('max_monthly_bookings');
            $table->unsignedInteger('max_storage_mb')->nullable()->after('max_sms');
            $table->unsignedInteger('max_admin_users')->nullable()->after('max_storage_mb');

            $table->json('features')->nullable()->after('max_admin_users');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'scope',
                'version',
                'annual_price',
                'currency',
                'annual_discount_percent',
                'trial_days',
                'support_level',
                'max_doctors',
                'max_staff',
                'max_locations',
                'max_monthly_bookings',
                'max_sms',
                'max_storage_mb',
                'max_admin_users',
                'features',
            ]);
        });
    }
};
