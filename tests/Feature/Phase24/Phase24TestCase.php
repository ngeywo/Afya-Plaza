<?php

namespace Tests\Feature\Phase24;

use App\Enums\BillingCycle;
use App\Models\Facility;
use App\Models\FacilitySubscription;
use App\Models\Plan;
use App\Services\FacilitySubscriptionService;
use Tests\Feature\Phase23\Phase23TestCase;

/**
 * Phase 24 base: facility plan + subscription helpers on top of the Phase 23
 * seeded roles/permissions/fixture helpers. Facility plans are seeded by the
 * migration framework, matching production (configurable plans, not test seeds).
 */
abstract class Phase24TestCase extends Phase23TestCase
{
    protected function facilityPlan(string $slug): Plan
    {
        return Plan::where('slug', $slug)->where('scope', Plan::SCOPE_FACILITY)->firstOrFail();
    }

    /**
     * A configurable priced plan (used for PENDING_PAYMENT / TRIAL lifecycle
     * states, which the seeded 0-priced plans never enter).
     */
    protected function makePricedFacilityPlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Custom Facility '.uniqid(),
            'slug' => 'custom-facility-'.uniqid(),
            'scope' => Plan::SCOPE_FACILITY,
            'description' => 'Test priced plan.',
            'monthly_price' => 10000,
            'annual_price' => 108000,
            'currency' => 'KES',
            'annual_discount_percent' => 10,
            'trial_days' => 0,
            'support_level' => 'standard',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 50,
            'version' => 1,
            'max_doctors' => 2,
            'max_staff' => 5,
            'max_locations' => 1,
            'max_monthly_bookings' => 100,
            'features' => [
                'doctor_management' => true,
                'clinic_sessions' => true,
                'online_booking' => true,
                'facility_payments' => true,
                'staff_management' => true,
            ],
        ], $overrides));
    }

    protected function subscribeFacility(Facility $facility, Plan $plan, string $cycle = BillingCycle::MONTHLY->value): FacilitySubscription
    {
        $service = $this->app->make(FacilitySubscriptionService::class);

        return $service->checkout($facility, $plan, $cycle);
    }

    protected function makeDoctors(int $count = 1): array
    {
        $doctors = [];
        for ($i = 0; $i < $count; $i++) {
            $doctors[] = $this->makeCompleteDoctor($this->makeVerifiedUser('doctor'));
        }

        return $doctors;
    }
}
