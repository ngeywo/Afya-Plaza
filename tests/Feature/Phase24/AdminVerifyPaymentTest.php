<?php

namespace Tests\Feature\Phase24;

use App\Enums\FacilitySubscriptionStatus;
use App\Models\FacilitySubscription;
use App\Models\Plan;

class AdminVerifyPaymentTest extends Phase24TestCase
{
    public function test_super_admin_can_verify_offline_payment(): void
    {
        $admin = $this->makeVerifiedUser('super-admin');
        $facilityAdmin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($facilityAdmin);

        $this->actingAsUser($facilityAdmin);
        $plan = Plan::where('slug', 'facility-growth')->firstOrFail();
        $this->postJson('/api/facility/subscription/checkout', [
            'plan_slug' => $plan->slug,
            'billing_cycle' => 'monthly',
        ])->assertStatus(201);

        $subscription = FacilitySubscription::first();

        // Simulate a pending payment (facility paid by cash/cheque, awaiting admin verification)
        $subscription->status = FacilitySubscriptionStatus::PENDING_PAYMENT;
        $subscription->payment_reference = null;
        $subscription->save();

        $this->actingAsUser($admin);
        $response = $this->postJson("/api/admin/subscriptions/{$subscription->id}/verify-payment", [
            'reference' => 'CHQ-2024-001',
            'payment_method' => 'cheque',
            'notes' => 'Cheque #12345 received from facility admin',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.payment_reference', 'CHQ-2024-001')
            ->assertJsonPath('data.payment_method', 'cheque');

        $subscription->refresh();
        $this->assertSame('active', $subscription->status->value);
        $this->assertSame('CHQ-2024-001', $subscription->payment_reference);
        $this->assertSame('cheque', $subscription->payment_method);
        $this->assertNotNull($subscription->verified_at);
        $this->assertSame($admin->id, $subscription->verified_by);

        $this->assertDatabaseHas('facility_subscription_events', [
            'facility_subscription_id' => $subscription->id,
            'event' => 'payment_verified_by_admin',
        ]);
    }

    public function test_super_admin_can_verify_cash_payment(): void
    {
        $admin = $this->makeVerifiedUser('super-admin');
        $facilityAdmin = $this->makeVerifiedUser('facility-admin');
        $facility = $this->makeCompleteFacility($facilityAdmin);

        $this->actingAsUser($facilityAdmin);
        $plan = Plan::where('slug', 'facility-starter')->firstOrFail();
        $this->postJson('/api/facility/subscription/checkout', [
            'plan_slug' => $plan->slug,
        ])->assertStatus(201);

        $subscription = FacilitySubscription::first();

        // Simulate a pending payment
        $subscription->status = FacilitySubscriptionStatus::PENDING_PAYMENT;
        $subscription->payment_reference = null;
        $subscription->save();

        $this->actingAsUser($admin);
        $response = $this->postJson("/api/admin/subscriptions/{$subscription->id}/verify-payment", [
            'reference' => 'CASH-001',
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.payment_method', 'cash');
    }

    public function test_verify_payment_requires_reference(): void
    {
        $admin = $this->makeVerifiedUser('super-admin');
        $facilityAdmin = $this->makeVerifiedUser('facility-admin');
        $this->makeCompleteFacility($facilityAdmin);

        $this->actingAsUser($facilityAdmin);
        $plan = Plan::where('slug', 'facility-starter')->firstOrFail();
        $this->postJson('/api/facility/subscription/checkout', [
            'plan_slug' => $plan->slug,
        ])->assertStatus(201);

        $subscription = FacilitySubscription::first();

        $subscription->status = FacilitySubscriptionStatus::PENDING_PAYMENT;
        $subscription->save();

        $this->actingAsUser($admin);
        $response = $this->postJson("/api/admin/subscriptions/{$subscription->id}/verify-payment", [
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reference']);
    }

    public function test_verify_payment_requires_valid_payment_method(): void
    {
        $admin = $this->makeVerifiedUser('super-admin');
        $facilityAdmin = $this->makeVerifiedUser('facility-admin');
        $this->makeCompleteFacility($facilityAdmin);

        $this->actingAsUser($facilityAdmin);
        $plan = Plan::where('slug', 'facility-starter')->firstOrFail();
        $this->postJson('/api/facility/subscription/checkout', [
            'plan_slug' => $plan->slug,
        ])->assertStatus(201);

        $subscription = FacilitySubscription::first();

        $subscription->status = FacilitySubscriptionStatus::PENDING_PAYMENT;
        $subscription->save();

        $this->actingAsUser($admin);
        $response = $this->postJson("/api/admin/subscriptions/{$subscription->id}/verify-payment", [
            'reference' => 'REF-001',
            'payment_method' => 'invalid_method',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_non_admin_cannot_verify_payment(): void
    {
        $facilityAdmin = $this->makeVerifiedUser('facility-admin');
        $this->makeCompleteFacility($facilityAdmin);

        $this->actingAsUser($facilityAdmin);
        $plan = Plan::where('slug', 'facility-starter')->firstOrFail();
        $this->postJson('/api/facility/subscription/checkout', [
            'plan_slug' => $plan->slug,
        ])->assertStatus(201);

        $subscription = FacilitySubscription::first();

        $subscription->status = FacilitySubscriptionStatus::PENDING_PAYMENT;
        $subscription->save();

        $otherAdmin = $this->makeVerifiedUser('facility-admin');
        $this->actingAsUser($otherAdmin);
        $response = $this->postJson("/api/admin/subscriptions/{$subscription->id}/verify-payment", [
            'reference' => 'REF-001',
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(403);
    }
}
