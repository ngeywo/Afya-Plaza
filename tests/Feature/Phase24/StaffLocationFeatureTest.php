<?php

namespace Tests\Feature\Phase24;

use App\Models\FacilityPaymentAccount;
use App\Models\Plan;
use App\Services\EntitlementService;

class StaffLocationFeatureTest extends Phase24TestCase
{
    public function test_staff_seats_count_primary_admin_and_additions(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->subscribeFacility($facility, $this->facilityPlan('facility-starter'));

        $usage = $this->app->make(EntitlementService::class)->usage();
        $this->assertSame(1, $usage->staffSeatsUsed($facility));

        $this->actingAsUser($admin);
        $staff1 = $this->makeVerifiedUser('patient');
        $staff2 = $this->makeVerifiedUser('patient');

        $this->postJson('/api/facility/staff', ['email' => $staff1->email])->assertStatus(201);
        $this->postJson('/api/facility/staff', ['email' => $staff2->email, 'role' => 'facility-staff'])->assertStatus(201);
        $this->assertSame(3, $usage->staffSeatsUsed($facility));
        $this->assertTrue($staff2->fresh()->hasRole('facility-staff'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'facility.staff_added']);

        // Staff usage reflected on the subscription dashboard.
        $this->getJson('/api/facility/subscription')->assertJsonPath('data.usage.staff.used', 3);
    }

    public function test_adding_staff_at_limit_is_blocked(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->subscribeFacility($facility, $this->facilityPlan('facility-starter')); // max 5

        $this->actingAsUser($admin);
        for ($i = 0; $i < 4; $i++) {
            $user = $this->makeVerifiedUser('patient');
            $this->postJson('/api/facility/staff', ['email' => $user->email])->assertStatus(201);
        }

        $this->assertEquals(5, $this->app->make(EntitlementService::class)->usage()->staffSeatsUsed($facility));

        $sixth = $this->makeVerifiedUser('patient');
        $response = $this->postJson('/api/facility/staff', ['email' => $sixth->email]);
        $response->assertStatus(422)->assertJsonPath('code', 'STAFF_LIMIT_REACHED');
        $this->assertSame(5, $response->json('context.max'));
        $this->assertSame(5, $response->json('context.used'));
    }

    public function test_location_creation_respects_plan_ceiling(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->subscribeFacility($facility, $this->facilityPlan('facility-starter')); // max 1

        $this->actingAsUser($admin);
        $this->postJson('/api/facility/locations', [
            'name' => 'Main Clinic', 'address' => 'Nairobi CBD', 'city' => 'Nairobi', 'is_primary' => true,
        ])->assertStatus(201);

        $response = $this->postJson('/api/facility/locations', ['name' => 'Branch Clinic']);
        $response->assertStatus(422)->assertJsonPath('code', 'LOCATION_LIMIT_REACHED');

        // Growing the plan unlocks more locations.
        $this->postJson('/api/facility/subscription/upgrade', ['plan_slug' => 'facility-growth'])->assertStatus(200);
        $this->postJson('/api/facility/locations', ['name' => 'Branch Clinic'])->assertStatus(201);
        $this->assertSame(2, $facility->allLocations()->where('is_active', true)->count());
    }

    public function test_facility_without_subscription_falls_back_to_default_location_capability(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);

        $this->actingAsUser($admin);
        $this->postJson('/api/facility/locations', ['name' => 'Main Clinic'])->assertStatus(201);
        $this->postJson('/api/facility/locations', ['name' => 'Second'])->assertStatus(422);
    }

    public function test_facility_payments_feature_gates_payment_accounts(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);

        // Custom plan WITHOUT facility_payments.
        $plan = $this->makePricedFacilityPlan(['features' => [
            'doctor_management' => true,
            'clinic_sessions' => true,
            'online_booking' => true,
            'staff_management' => true,
        ]]);
        $this->subscribeFacility($facility, $plan);

        $this->actingAsUser($admin);
        $response = $this->postJson('/api/facility/payment-accounts', [
            'facility_id' => $facility->id,
            'provider' => 'mpesa',
            'account_type' => 'paybill',
            'account_name' => 'Nairobi Clinic',
            'account_number' => '247247',
        ]);
        $response->assertStatus(403)->assertJsonPath('code', 'FEATURE_NOT_ENABLED');
        $this->assertSame(0, FacilityPaymentAccount::count());

        // Upgrade to starter (facility_payments on) → allowed.
        $this->postJson('/api/facility/subscription/upgrade', ['plan_slug' => 'facility-starter'])->assertStatus(200);
        $this->postJson('/api/facility/payment-accounts', [
            'facility_id' => $facility->id,
            'provider' => 'mpesa',
            'account_type' => 'paybill',
            'account_name' => 'Nairobi Clinic',
            'account_number' => '247247',
        ])->assertStatus(201);
    }

    public function test_subscription_dashboard_reports_usage_and_feature_map(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->subscribeFacility($facility, $this->facilityPlan('facility-starter'));

        $this->actingAsUser($admin);
        $response = $this->getJson('/api/facility/subscription')->assertStatus(200);

        $usage = $response->json('data.usage');
        $this->assertSame('doctors', $usage['doctors']['key']);
        $this->assertSame(0, $usage['doctors']['used']);
        $this->assertSame(2, $usage['doctors']['max']);
        $this->assertSame('staff', $usage['staff']['key']);
        $this->assertSame(1, $usage['staff']['used']);
        $this->assertSame(5, $usage['staff']['max']);

        $features = $response->json('data.features');
        $this->assertTrue($features['facility_payments']['enabled']);
        $this->assertTrue($features['online_booking']['enabled']);
        $this->assertFalse($features['advanced_scheduling']['enabled']);
        $this->assertFalse($response->json('data.restricted'));
    }
}
