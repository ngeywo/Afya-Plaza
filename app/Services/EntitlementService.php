<?php

namespace App\Services;

use App\Exceptions\EntitlementException;
use App\Models\Facility;
use App\Models\FacilitySubscription;
use App\Models\Plan;

/**
 * Resolves what a facility is entitled to from its subscription and converts
 * plan limits into enforceable assertions.
 *
 * No subscription  -> implicit default facility plan capacity (marketplace stays
 *                     usable at the base level — never left with zero capacity).
 * Operational sub  -> the frozen plan snapshot captured at checkout/upgrade.
 * Suspended/expired -> display limits still shown, but new-capacity actions are
 *                     blocked via expiry_restrictions (configurable per metric).
 */
class EntitlementService
{
    public function __construct(private readonly FacilityUsageService $usage) {}

    public function usage(): FacilityUsageService
    {
        return $this->usage;
    }

    public function subscriptionFor(Facility $facility): ?FacilitySubscription
    {
        return FacilitySubscription::query()
            ->where('facility_id', $facility->id)
            ->first();
    }

    public function defaultFacilityPlan(): Plan
    {
        return Plan::query()
            ->facility()
            ->active()
            ->orderBy('is_default', 'desc')
            ->orderBy('sort_order')
            ->firstOr(function () {
                return Plan::query()
                    ->facility()
                    ->active()
                    ->orderBy('sort_order')
                    ->first();
            }) ?? throw new \RuntimeException('No active facility plan is configured.');
    }

    /**
     * Entitlement limits shown on the dashboard (read-only). Never throws.
     */
    public function displayEntitlement(Facility $facility): array
    {
        $subscription = $this->subscriptionFor($facility);

        if ($subscription !== null) {
            return $subscription->snapshot();
        }

        return $this->defaultFacilityPlan()->snapshotArray();
    }

    /**
     * Whether the facility is currently restricted on new capacity actions.
     */
    public function isRestricted(Facility $facility): bool
    {
        $subscription = $this->subscriptionFor($facility);

        return $subscription !== null && ! $subscription->isOperational();
    }

    public function feature(Facility $facility, string $key): bool
    {
        return in_array($key, $this->displayEntitlement($facility)['features'] ?? [], true);
    }

    public function featureMap(Facility $facility): array
    {
        $features = [];
        foreach (Plan::FEATURES as $key => $label) {
            $features[$key] = [
                'label' => $label,
                'enabled' => $this->feature($facility, $key),
            ];
        }

        return $features;
    }

    public function limitFor(Facility $facility, string $metric): ?int
    {
        $entitlement = $this->displayEntitlement($facility);
        $key = 'max_'.str_replace('_limit', '', $metric);

        return $entitlement[$key] ?? null;
    }

    public function billingWindow(Facility $facility): array
    {
        $subscription = $this->subscriptionFor($facility);

        if ($subscription !== null && ($subscription->started_at || $subscription->activated_at)) {
            return [
                'from' => $subscription->last_billing_date ?? $subscription->activated_at ?? $subscription->started_at,
                'to' => $subscription->next_billing_date ?? now(),
            ];
        }

        return [
            'from' => now()->startOfMonth(),
            'to' => now()->endOfMonth(),
        ];
    }

    public function assertCanAddDoctor(Facility $facility): void
    {
        $this->assertCanGrow($facility, 'doctors', 'new_doctors', 'doctor');
    }

    public function assertCanAddStaff(Facility $facility): void
    {
        $this->assertCanGrow($facility, 'staff', 'new_staff', 'staff member');
    }

    public function assertCanAddLocation(Facility $facility): void
    {
        $this->assertCanGrow($facility, 'locations', 'new_locations', 'location');
    }

    /**
     * Bookings enforcement is opt-in (services.subscriptions.bookings_enforcement
     * = 'block'). Never reached by default so patient care is never refused.
     */
    public function assertCanBook(Facility $facility): void
    {
        if (config('services.subscriptions.bookings_enforcement') !== 'block') {
            return;
        }

        $this->assertCanGrow($facility, 'monthly_bookings', 'new_bookings', 'booking');
    }

    protected function assertCanGrow(Facility $facility, string $metric, string $restrictionKey, string $label): void
    {
        $subscription = $this->subscriptionFor($facility);

        if ($subscription !== null && ! $subscription->isOperational()) {
            $restricted = config("services.subscriptions.expiry_restrictions.{$restrictionKey}", true);
            if ($restricted) {
                $ctx = [
                    'status' => $subscription->status->value,
                    'status_label' => $subscription->status->label(),
                    'facility_id' => $facility->id,
                    'subscription_id' => $subscription->id,
                ];

                if ($subscription->status?->value === 'suspended') {
                    throw EntitlementException::subscriptionRestricted(
                        'Your subscription is suspended. Pay outstanding dues before adding a new '.$label.'.',
                        $ctx,
                    );
                }

                if ($subscription->status?->value === 'pending_payment') {
                    throw EntitlementException::subscriptionRestricted(
                        'Your subscription is awaiting payment confirmation before you can add a new '.$label.'.',
                        $ctx,
                    );
                }

                throw EntitlementException::subscriptionRestricted(
                    'Your subscription has expired. Renew to add a new '.$label.'.',
                    $ctx,
                );
            }
        }

        $max = $this->limitFor($facility, $metric);

        if ($max === null) {
            return; // unlimited
        }

        $used = match ($metric) {
            'doctors' => $this->usage->doctorSeatsUsed($facility),
            'staff' => $this->usage->staffSeatsUsed($facility),
            'locations' => $this->usage->locationsUsed($facility),
            'monthly_bookings' => $this->usage->bookingsUsed($facility, ...array_values($this->billingWindow($facility))),
            default => 0,
        };

        if ($used >= $max) {
            $action = $metric === 'doctors'
                ? 'End an existing relationship or upgrade your facility plan.'
                : 'Remove current usage or upgrade your facility plan.';

            $code = match ($metric) {
                'doctors' => 'DOCTOR',
                'locations' => 'LOCATION',
                'monthly_bookings' => 'BOOKINGS',
                default => 'STAFF',
            };

            throw EntitlementException::limit($code, [
                'used' => $used,
                'max' => $max,
                'required' => $used + 1,
                'message' => ucfirst($label).' limit reached: '.$used.' of '.$max.' in use.',
                'action' => $action,
            ]);
        }
    }
}
