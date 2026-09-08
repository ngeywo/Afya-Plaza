<?php

namespace Tests\Feature\Phase24;

use App\Enums\FacilitySubscriptionStatus;
use App\Models\DoctorFacility;
use App\Models\FacilitySubscription;
use App\Models\FacilitySubscriptionEvent;
use App\Models\Plan;
use App\Models\User;
use App\Services\DoctorRelationshipService;
use App\Services\FacilitySubscriptionService;

class FacilitySubscriptionTest extends Phase24TestCase
{
    public function test_facility_plans_are_configurable_and_seeded(): void
    {
        $starter = $this->facilityPlan('facility-starter');
        $enterprise = $this->facilityPlan('facility-enterprise');

        $this->assertSame(2, $starter->max_doctors);
        $this->assertSame(1, $starter->max_locations);
        $this->assertTrue($starter->feature('facility_payments'));
        $this->assertFalse($starter->feature('advanced_scheduling'));
        $this->assertTrue($enterprise->isUnlimitedFor('max_doctors'));
        $this->assertTrue($enterprise->feature('api_access'));
    }

    public function test_checkout_free_plan_activates_immediately(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->actingAsUser($admin);

        $response = $this->postJson('/api/facility/subscription/checkout', ['plan_slug' => 'facility-starter', 'billing_cycle' => 'monthly']);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertSame('active', $data['subscription']['status']);
        $this->assertSame('facility-starter', $data['subscription']['plan']['slug']);
        $this->assertNotEmpty($data['subscription']['next_billing_date']);
        $this->assertSame(2, $data['subscription']['plan']['max_doctors']);
        $this->assertSame(0, $data['usage']['doctors']['used']); // no relationships yet; staff seat = primary admin, separate
        $this->assertSame(1, $data['usage']['staff']['used']);

        $this->assertDatabaseHas('facility_subscription_events', ['event' => 'activated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.checkout']);
    }

    public function test_checkout_priced_plan_needs_payment_confirmation(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $plan = $this->makePricedFacilityPlan();
        $this->actingAsUser($admin);

        $response = $this->postJson('/api/facility/subscription/checkout', ['plan_slug' => $plan->slug, 'billing_cycle' => 'yearly']);

        $response->assertStatus(201);
        $this->assertSame('pending_payment', $response->json('data.subscription.status'));
        $this->assertSame('108000.00', $response->json('data.subscription.effective_amount'));

        $confirm = $this->postJson('/api/facility/subscription/payment/confirm', ['reference' => 'MP-REF-001']);

        $confirm->assertStatus(200)->assertJsonPath('data.status', 'active');
        $this->assertSame('MP-REF-001', FacilitySubscription::first()->payment_reference);
        $this->assertNotNull(FacilitySubscription::first()->next_billing_date);
    }

    public function test_payment_failure_moves_to_past_due_then_suspended_on_sweep(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $plan = $this->makePricedFacilityPlan();
        $subscription = $this->subscribeFacility($facility, $plan);
        $service = $this->app->make(FacilitySubscriptionService::class);
        $service->confirmPayment($subscription, 'REF-1');

        $this->actingAsUser($admin);
        $this->postJson('/api/facility/subscription/payment/failure', ['reason' => 'Insufficient balance'])->assertStatus(200);
        $this->assertSame('past_due', $subscription->fresh()->status->value);
        $this->assertNotNull($subscription->fresh()->grace_ends_at);

        $subscription->update(['grace_ends_at' => now()->subDay()]);
        $affected = $service->sweep();
        $this->assertSame('suspended', $subscription->fresh()->status->value);
        $this->assertTrue(collect($affected)->contains(fn ($a) => $a['event'] === 'suspended'));
    }

    public function test_trial_expires_into_past_due_then_suspends(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $plan = $this->makePricedFacilityPlan(['trial_days' => 7]);
        $subscription = $this->subscribeFacility($facility, $plan);

        $this->assertSame('trial', $subscription->status->value);
        $this->assertTrue($subscription->isOperational());

        $subscription->update(['trial_ends_at' => now()->subDay()]);
        $service = $this->app->make(FacilitySubscriptionService::class);
        $service->sweep();
        $this->assertSame('past_due', $subscription->fresh()->status->value);

        $subscription->fresh()->update(['grace_ends_at' => now()->subDay()]);
        $service->sweep();
        $this->assertSame('suspended', $subscription->fresh()->status->value);
    }

    public function test_cancel_keeps_entitlements_until_period_end_then_expires(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $subscription = $this->subscribeFacility($facility, $this->facilityPlan('facility-starter'));

        $this->actingAsUser($admin);
        $this->postJson('/api/facility/subscription/cancel', ['reason' => 'Moving to another platform'])->assertStatus(200);

        $subscription->refresh();
        $this->assertSame('cancelled', $subscription->status->value);
        $this->assertTrue($subscription->isOperational()); // entitled until next_billing_date

        $subscription->update(['next_billing_date' => now()->subDay()]);
        $this->assertFalse($subscription->fresh()->isOperational());
        $this->app->make(FacilitySubscriptionService::class)->sweep();
        $this->assertSame('expired', $subscription->fresh()->status->value);
    }

    public function test_double_subscription_is_rejected(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->actingAsUser($admin);
        $this->postJson('/api/facility/subscription/checkout', ['plan_slug' => 'facility-starter'])->assertStatus(201);
        $this->postJson('/api/facility/subscription/checkout', ['plan_slug' => 'facility-growth'])->assertStatus(409);
    }

    public function test_re_subscription_after_expiry_reuses_one_row(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->actingAsUser($admin);

        $this->postJson('/api/facility/subscription/checkout', ['plan_slug' => 'facility-starter'])->assertStatus(201);
        $subscription = FacilitySubscription::first();
        $this->app->make(FacilitySubscriptionService::class)->adjust($subscription, FacilitySubscriptionStatus::EXPIRED->value, 'test', $admin);

        $this->postJson('/api/facility/subscription/checkout', ['plan_slug' => 'facility-growth'])->assertStatus(201);
        $this->assertSame(1, FacilitySubscription::count());
        $this->assertSame('facility-growth', FacilitySubscription::first()->snapshot()['slug']);
        $this->assertGreaterThan(1, FacilitySubscriptionEvent::where('facility_subscription_id', $subscription->id)->count());
    }

    public function test_upgrade_applies_immediately_and_raises_capacity(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->actingAsUser($admin);

        $this->postJson('/api/facility/subscription/checkout', ['plan_slug' => 'facility-starter'])->assertStatus(201);

        $response = $this->postJson('/api/facility/subscription/upgrade', ['plan_slug' => 'facility-growth']);
        $response->assertStatus(200)->assertJsonPath('data.plan.slug', 'facility-growth');
        $sub = FacilitySubscription::first();
        $this->assertSame('facility-growth', $sub->snapshot()['slug']);
        $this->assertDatabaseHas('facility_subscription_events', ['event' => 'upgraded']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.upgraded']);
    }

    public function test_downgrade_is_blocked_when_usage_exceeds_target(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $relationships = $this->app->make(DoctorRelationshipService::class);

        $this->subscribeFacility($facility, $this->facilityPlan('facility-growth')); // 5 doctors
        foreach ($this->makeDoctors(5) as $doctor) {
            $relationships->invite($doctor, $facility, $admin);
        }
        $this->assertSame(5, $facility->doctorFacilities()->whereIn('status', ['invited', 'pending', 'active', 'suspended'])->count());

        $this->actingAsUser($admin);

        // The plan ceiling is the hard cap — even invites obey it.
        [$extra] = $this->makeDoctors(1);
        $this->postJson("/api/facility/doctors/{$extra->id}/invite", ['facility_id' => $facility->id])
            ->assertStatus(422)->assertJsonPath('code', 'DOCTOR_LIMIT_REACHED');

        $response = $this->postJson('/api/facility/subscription/downgrade', ['plan_slug' => 'facility-starter']);

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'DOCTOR_LIMIT_REACHED');
        $this->assertSame(5, $response->json('context.used'));
        $this->assertSame(2, $response->json('context.max'));
        $this->assertSame('facility-growth', FacilitySubscription::first()->snapshot()['slug']);
    }

    public function test_downgrade_allowed_when_usage_fits(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);

        $this->subscribeFacility($facility, $this->facilityPlan('facility-growth'));

        $this->actingAsUser($admin);
        $response = $this->postJson('/api/facility/subscription/downgrade', ['plan_slug' => 'facility-starter']);
        $response->assertStatus(200)->assertJsonPath('data.plan.slug', 'facility-starter');
    }

    public function test_plan_edit_bumps_version_and_preserves_snapshot(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->subscribeFacility($facility, $this->facilityPlan('facility-starter'));
        $sub = FacilitySubscription::first();
        $snapshotVersion = $sub->snapshot()['version'];

        $superAdmin = $this->makeVerifiedUser('super-admin');
        $this->actingAsUser($superAdmin);
        $plan = $this->facilityPlan('facility-starter');
        $this->putJson("/api/admin/plans/{$plan->id}", ['max_doctors' => 9, 'name' => 'Facility Starter v2'])->assertStatus(200);

        $plan->refresh();
        $this->assertSame(2, $plan->version);
        $this->assertSame(9, $plan->max_doctors);
        $this->assertDatabaseHas('plan_versions', ['plan_id' => $plan->id, 'version' => 2]);

        // Existing subscription still answers to the entitlements it checked out with.
        $this->assertSame($snapshotVersion, $sub->fresh()->snapshot()['version']);
        $this->assertSame(2, $sub->fresh()->snapshot()['max_doctors']);
    }

    public function test_public_plan_comparison_is_generated_from_backend(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $this->actingAsUser($admin);

        $response = $this->getJson('/api/facility/subscription/plans');
        $response->assertStatus(200);
        $slugs = collect($response->json('data.plans'))->pluck('slug');
        $this->assertContains('facility-starter', $slugs);
        $this->assertContains('facility-enterprise', $slugs);
        $this->assertTrue($response->json('data.plans.3.features') !== []);
    }

    public function test_facility_cannot_manage_another_facilitys_subscription(): void
    {
        $adminA = $this->makeVerifiedUser('facility-admin');
        $adminB = $this->makeVerifiedUser('facility-admin');
        $facilityA = $this->makeCompleteFacility($adminA);
        $facilityB = $this->makeCompleteFacility($adminB);
        $this->actingAsUser($adminA);
        $this->postJson('/api/facility/subscription/checkout', ['plan_slug' => 'facility-starter'])->assertStatus(201);

        // Query facility A's subscription using facility A's admin works.
        $this->getJson('/api/facility/subscription')->assertStatus(200);

        // Facility B's admin sees only B (no subscription → default plan fallback). Never A's.
        $this->actingAsUser($adminB);
        $response = $this->getJson('/api/facility/subscription')->assertStatus(200);
        $this->assertSame($facilityB->id, $response->json('data.facility.id'));
        $this->assertNull($response->json('data.subscription'));
        $this->assertSame('facility-starter', $response->json('data.plan.slug'));
        $this->postJson('/api/facility/subscription/checkout', ['plan_slug' => 'facility-starter', 'facility_id' => $facilityA->id])->assertStatus(404);

        $this->assertSame(1, FacilitySubscription::count());
        $this->assertSame($facilityA->id, FacilitySubscription::first()->facility_id);
    }

    public function test_facility_staff_cannot_access_subscription_surface(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $staff = $this->makeVerifiedUser('facility-staff');
        $facility->admins()->attach($staff->id, ['is_primary' => false]);
        $this->actingAsUser($staff);

        $this->getJson('/api/facility/subscription')->assertStatus(404);
        $this->postJson('/api/facility/subscription/checkout', ['plan_slug' => 'facility-starter'])->assertStatus(404);

        $this->assertSame(0, FacilitySubscription::count());
    }

    public function test_admin_sweep_and_adjust_are_audited(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->subscribeFacility($facility, $this->facilityPlan('facility-starter'));
        $sub = FacilitySubscription::first();

        $superAdmin = $this->makeVerifiedUser('super-admin');
        $this->actingAsUser($superAdmin);

        $this->getJson('/api/admin/subscriptions')->assertStatus(200)->assertJsonPath('meta.total', 1);
        $this->postJson("/api/admin/subscriptions/{$sub->id}/adjust", ['status' => 'expired', 'reason' => 'Non-payment override'])->assertStatus(200);

        $sub->refresh();
        $this->assertSame('expired', $sub->status->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.adjusted']);
        $this->assertDatabaseHas('facility_subscription_events', ['event' => 'adjusted']);
    }

    public function test_non_admin_cannot_adjust_subscriptions(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->subscribeFacility($facility, $this->facilityPlan('facility-starter'));
        $sub = FacilitySubscription::first();

        $this->actingAsUser($admin);
        $this->postJson("/api/admin/subscriptions/{$sub->id}/adjust", ['status' => 'expired'])->assertStatus(403);
    }

    public function test_expired_subscription_blocks_new_doctor_until_renewed(): void
    {
        $admin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($admin);
        $this->subscribeFacility($facility, $this->facilityPlan('facility-starter'));
        $sub = FacilitySubscription::first();
        $this->app->make(FacilitySubscriptionService::class)->adjust($sub, FacilitySubscriptionStatus::EXPIRED->value, 'test', $admin);

        $this->actingAsUser($admin);
        [$doctor] = $this->makeDoctors(1);
        $response = $this->postJson("/api/facility/doctors/{$doctor->id}/invite", ['facility_id' => $facility->id]);

        $response->assertStatus(403);
        $response->assertJsonPath('code', 'SUBSCRIPTION_EXPIRED');
        $this->assertEquals(0, DoctorFacility::count());
    }

    public function test_admin_can_create_and_activate_facility_plans(): void
    {
        $superAdmin = $this->makeVerifiedUser('super-admin');
        $this->actingAsUser($superAdmin);

        $response = $this->postJson('/api/admin/plans', [
            'name' => 'Clinic XL',
            'slug' => 'clinic-xl',
            'description' => 'Big clinic tier',
            'monthly_price' => 25000,
            'max_doctors' => 40,
            'max_staff' => 100,
            'features' => ['doctor_management' => true, 'api_access' => true],
        ])->assertStatus(201);

        $plan = Plan::where('slug', 'clinic-xl')->firstOrFail();
        $this->assertSame(1, $plan->version);
        $this->assertSame('facility', $plan->scope);
        $this->assertTrue($plan->feature('api_access'));
        $this->assertFalse($plan->feature('advanced_scheduling'));
        $this->assertDatabaseHas('plan_versions', ['version' => 1]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.created']);

        $this->postJson("/api/admin/plans/{$plan->id}/status", ['active' => false, 'reason' => 'Retiring tier'])->assertStatus(200);
        $this->assertFalse($plan->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.deactivated']);
    }

    public function test_legacy_doctor_plans_are_not_editable_via_facility_admin(): void
    {
        $superAdmin = $this->makeVerifiedUser('super-admin');
        $this->actingAsUser($superAdmin);
        $dotorPlan = Plan::where('slug', 'starter')->where('scope', 'doctor')->firstOrFail();

        $this->putJson("/api/admin/plans/{$dotorPlan->id}", ['max_doctors' => 9])->assertStatus(422);
        $this->assertSame(1, $dotorPlan->fresh()->version);
    }

    private function makeCompleteUser(): User
    {
        return $this->makeVerifiedUser('patient');
    }
}
