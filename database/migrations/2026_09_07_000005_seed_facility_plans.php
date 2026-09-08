<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed the four configurable FACILITY subscription plans.
     *
     * Prices are deliberately 0 placeholders inside the framework: they are
     * business configuration, not code. Operators set them through the admin
     * plan editor (prices/features remains fully configurable at runtime).
     */
    public function up(): void
    {
        $now = now();
        $rows = [
            [
                'name' => 'Facility Starter',
                'slug' => 'facility-starter',
                'description' => 'For new clinics getting started on the marketplace. Up to 2 doctors, 5 staff and a single clinic location.',
                'sort_order' => 1,
                'trial_days' => 0,
                'support_level' => 'standard',
                'max_doctors' => 2,
                'max_staff' => 5,
                'max_locations' => 1,
                'max_monthly_bookings' => 300,
                'max_sms' => 100,
                'max_storage_mb' => 5000,
                'max_admin_users' => 1,
                'features' => [
                    'doctor_management' => true,
                    'clinic_sessions' => true,
                    'online_booking' => true,
                    'facility_payments' => true,
                    'staff_management' => true,
                    'notifications' => true,
                    'email_notifications' => true,
                    'patient_reminders' => true,
                    'export_reports' => true,
                ],
            ],
            [
                'name' => 'Facility Growth',
                'slug' => 'facility-growth',
                'description' => 'For growing practices. More doctors and staff, advanced scheduling and usage analytics.',
                'sort_order' => 2,
                'trial_days' => 7,
                'support_level' => 'standard',
                'max_doctors' => 5,
                'max_staff' => 15,
                'max_locations' => 2,
                'max_monthly_bookings' => 800,
                'max_sms' => 1000,
                'max_storage_mb' => 20000,
                'max_admin_users' => 3,
                'features' => [
                    'doctor_management' => true,
                    'clinic_sessions' => true,
                    'advanced_scheduling' => true,
                    'online_booking' => true,
                    'facility_payments' => true,
                    'staff_management' => true,
                    'notifications' => true,
                    'sms_notifications' => true,
                    'email_notifications' => true,
                    'patient_reminders' => true,
                    'analytics' => true,
                    'export_reports' => true,
                ],
            ],
            [
                'name' => 'Facility Professional',
                'slug' => 'facility-professional',
                'description' => 'For established facilities operating across multiple locations with a larger team.',
                'sort_order' => 3,
                'trial_days' => 7,
                'support_level' => 'priority',
                'max_doctors' => 15,
                'max_staff' => 30,
                'max_locations' => 5,
                'max_monthly_bookings' => 2000,
                'max_sms' => 5000,
                'max_storage_mb' => 100000,
                'max_admin_users' => 10,
                'features' => [
                    'doctor_management' => true,
                    'clinic_sessions' => true,
                    'advanced_scheduling' => true,
                    'online_booking' => true,
                    'facility_payments' => true,
                    'staff_management' => true,
                    'multiple_locations' => true,
                    'notifications' => true,
                    'sms_notifications' => true,
                    'email_notifications' => true,
                    'patient_reminders' => true,
                    'analytics' => true,
                    'advanced_reports' => true,
                    'priority_support' => true,
                    'export_reports' => true,
                ],
            ],
            [
                'name' => 'Facility Enterprise',
                'slug' => 'facility-enterprise',
                'description' => 'Unlimited capacity and the full marketplace toolkit for large healthcare networks.',
                'sort_order' => 4,
                'trial_days' => 7,
                'support_level' => 'dedicated',
                'max_doctors' => null,
                'max_staff' => null,
                'max_locations' => null,
                'max_monthly_bookings' => null,
                'max_sms' => null,
                'max_storage_mb' => null,
                'max_admin_users' => null,
                'features' => [
                    'doctor_management' => true,
                    'clinic_sessions' => true,
                    'advanced_scheduling' => true,
                    'online_booking' => true,
                    'facility_payments' => true,
                    'staff_management' => true,
                    'multiple_locations' => true,
                    'notifications' => true,
                    'sms_notifications' => true,
                    'email_notifications' => true,
                    'patient_reminders' => true,
                    'analytics' => true,
                    'advanced_reports' => true,
                    'priority_support' => true,
                    'export_reports' => true,
                    'api_access' => true,
                    'custom_branding' => true,
                ],
            ],
        ];

        foreach ($rows as $row) {
            // idempotent seed (RefreshDatabase runs migrations per test/database)
            if (DB::table('plans')->where('slug', $row['slug'])->exists()) {
                continue;
            }

            DB::table('plans')->insert(array_merge([
                'monthly_price' => 0,
                'annual_price' => 0,
                'currency' => 'KES',
                'annual_discount_percent' => 10,
                'default_commission_rate' => 0.0,
                'commission_type' => 1,
                'fixed_commission_amount' => 0,
                'is_active' => 1,
                'is_default' => 0,
                'scope' => 'facility',
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ], $row, [
                'features' => json_encode($row['features']),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('plans')->where('scope', 'facility')->delete();
    }
};
