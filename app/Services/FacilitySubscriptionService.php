<?php

namespace App\Services;

use App\Enums\BillingCycle;
use App\Enums\FacilitySubscriptionStatus;
use App\Exceptions\EntitlementException;
use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\FacilitySubscription;
use App\Models\FacilitySubscriptionEvent;
use App\Models\Plan;
use App\Models\User;

class FacilitySubscriptionService
{
    public function __construct(
        private readonly FacilityUsageService $usage,
        private readonly EntitlementService $entitlements,
    ) {}

    public function checkout(Facility $facility, Plan $plan, string $billingCycle, ?User $actor = null, array $meta = []): FacilitySubscription
    {
        if (! $plan->is_active) {
            throw new \RuntimeException('Plan not available.');
        }
        if ($plan->scope !== Plan::SCOPE_FACILITY) {
            throw new \RuntimeException('Not a facility plan.');
        }
        $cycle = BillingCycle::tryFrom($billingCycle) ?? BillingCycle::MONTHLY;
        $existing = $this->entitlements->subscriptionFor($facility);
        $replacing = $existing !== null;
        $previousStatus = $existing?->status?->value;
        if ($existing && ! $existing->status->isTerminal()) {
            throw new \RuntimeException('Already subscribed.');
        }
        $sub = $existing ?? new FacilitySubscription(['facility_id' => $facility->id]);
        $sub->facility_id = $facility->id;
        $sub->created_by = $actor?->id ?? $sub->created_by;
        $this->applyPlan($sub, $plan, $cycle);
        $effective = $cycle === BillingCycle::YEARLY ? (float) $plan->annual_price : (float) $plan->monthly_price;
        if ($effective <= 0) {
            $sub->status = FacilitySubscriptionStatus::ACTIVE;
            $sub->effective_amount = 0;
            $this->stampActivation($sub, $cycle);
        } else {
            $trialDays = $plan->trial_days ?? (int) config('services.subscriptions.trial_days', 7);
            if ($trialDays > 0) {
                $sub->status = FacilitySubscriptionStatus::TRIAL;
                $sub->trial_started_at = now();
                $sub->trial_ends_at = now()->addDays($trialDays);
                $sub->started_at = $sub->trial_started_at;
                $sub->next_billing_date = $sub->trial_ends_at;
                $sub->effective_amount = $effective;
            } else {
                $sub->status = FacilitySubscriptionStatus::PENDING_PAYMENT;
                $sub->effective_amount = $effective;
                $sub->started_at = null;
                $sub->next_billing_date = null;
            }
        }
        $sub->save();
        if ($effective <= 0) {
            $this->recordEvent($sub, 'activated', $actor, $previousStatus, $sub->status->value, [], $replacing ? 'Free re-subscribed.' : 'Free activated.');
        } elseif ($sub->status === FacilitySubscriptionStatus::TRIAL) {
            $this->recordEvent($sub, 'trial_started', $actor, $previousStatus, $sub->status->value, ['trial_days' => $trialDays], 'Trial begins.');
        } else {
            $this->recordEvent($sub, 'pending_payment', $actor, $previousStatus, $sub->status->value, ['amount' => $effective], 'Awaiting payment.');
        }
        $this->audit($actor?->id, 'subscription.checkout', $sub, [], $sub->plan_snapshot ?? [], $replacing ? 'Re-subscribed.' : null, $meta);

        return $sub->fresh();
    }

    public function confirmPayment(FacilitySubscription $sub, string $reference, ?User $actor = null, array $meta = []): FacilitySubscription
    {
        $from = $sub->status->value;
        $sub->status = FacilitySubscriptionStatus::ACTIVE;
        $sub->payment_reference = $reference;
        $sub->past_due_since = null;
        $sub->grace_ends_at = null;
        $sub->last_amount_paid = $sub->effective_amount;
        $this->stampActivation($sub, $sub->billing_cycle ?? BillingCycle::MONTHLY);
        $sub->save();
        $this->recordEvent($sub, 'payment_confirmed', $actor, $from, $sub->status->value, ['reference' => $reference], 'Payment confirmed.');
        $this->audit($actor?->id, 'subscription.payment_confirmed', $sub, ['status' => $from], ['status' => $sub->status->value, 'reference' => $reference], null, $meta);

        return $sub->fresh();
    }

    public function adminVerifyPayment(FacilitySubscription $sub, string $reference, string $paymentMethod, ?string $notes = null, ?User $actor = null, array $meta = []): FacilitySubscription
    {
        $from = $sub->status->value;
        $sub->status = FacilitySubscriptionStatus::ACTIVE;
        $sub->payment_reference = $reference;
        $sub->payment_method = $paymentMethod;
        $sub->past_due_since = null;
        $sub->grace_ends_at = null;
        $sub->last_amount_paid = $sub->effective_amount;
        $sub->verified_by = $actor?->id;
        $sub->verified_at = now();
        $this->stampActivation($sub, $sub->billing_cycle ?? BillingCycle::MONTHLY);
        $sub->save();
        $this->recordEvent($sub, 'payment_verified_by_admin', $actor, $from, $sub->status->value, [
            'reference' => $reference,
            'payment_method' => $paymentMethod,
            'notes' => $notes,
        ], 'Payment verified by admin ('.$paymentMethod.').');
        $this->audit($actor?->id, 'subscription.payment_verified_by_admin', $sub, ['status' => $from], ['status' => $sub->status->value, 'reference' => $reference, 'payment_method' => $paymentMethod], $notes, $meta);

        return $sub->fresh();
    }

    public function recordPaymentFailure(FacilitySubscription $sub, string $reason, ?User $actor = null, array $meta = []): FacilitySubscription
    {
        $from = $sub->status->value;
        $graceDays = (int) config('services.subscriptions.grace_period_days', 3);
        $sub->status = FacilitySubscriptionStatus::PAST_DUE;
        $sub->past_due_since = now();
        $sub->grace_ends_at = now()->addDays($graceDays);
        $sub->save();
        $this->recordEvent($sub, 'payment_failed', $actor, $from, $sub->status->value, ['reason' => $reason, 'grace_days' => $graceDays], 'Payment failed. Grace period started.');
        $this->audit($actor?->id, 'subscription.payment_failed', $sub, ['status' => $from], ['status' => $sub->status->value, 'reason' => $reason], null, $meta);

        return $sub->fresh();
    }

    public function changePlan(FacilitySubscription $sub, Plan $plan, ?string $cycleValue = null, ?User $actor = null, array $meta = []): FacilitySubscription
    {
        if ($plan->scope !== Plan::SCOPE_FACILITY) {
            throw new \RuntimeException('Not a facility plan.');
        }
        $currentDoctors = (int) ($sub->plan_snapshot['max_doctors'] ?? 0);
        $newDoctors = (int) ($plan->max_doctors ?? PHP_INT_MAX);
        $isUpgrade = $newDoctors >= $currentDoctors;
        $this->validateAgainstLimits($sub->facility, $plan, $isUpgrade ? 'upgrade' : 'the new plan');
        $cycle = $cycleValue ? BillingCycle::tryFrom($cycleValue) ?? BillingCycle::MONTHLY : ($sub->billing_cycle ?? BillingCycle::MONTHLY);
        $from = $sub->status->value;
        $fromSnapshot = $sub->plan_snapshot;
        $this->applyPlan($sub, $plan, $cycle);
        $sub->effective_amount = $cycle === BillingCycle::YEARLY ? (float) $plan->annual_price : (float) $plan->monthly_price;
        $sub->save();
        $event = $isUpgrade ? 'upgraded' : 'downgraded';
        $this->recordEvent($sub, $event, $actor, $from, $sub->status->value, ['plan_slug' => $plan->slug, 'cycle' => $cycle->value], $isUpgrade ? 'Upgraded.' : 'Downgraded.');
        $this->audit($actor?->id, $isUpgrade ? 'subscription.upgraded' : 'subscription.downgraded', $sub, $fromSnapshot ?? [], $sub->plan_snapshot ?? [], null, $meta);

        return $sub->fresh();
    }

    public function cancel(FacilitySubscription $sub, string $reason, ?User $actor = null, array $meta = []): FacilitySubscription
    {
        $from = $sub->status->value;
        $sub->status = FacilitySubscriptionStatus::CANCELLED;
        $sub->cancelled_at = now();
        $sub->cancelled_reason = $reason;
        $sub->cancel_at_period_end = (bool) config('services.subscriptions.cancel_at_period_end', true);
        $sub->save();
        $this->recordEvent($sub, 'cancelled', $actor, $from, $sub->status->value, ['reason' => $reason], $reason);
        $this->audit($actor?->id, 'subscription.cancelled', $sub, ['status' => $from], ['status' => $sub->status->value], $reason, $meta);

        return $sub->fresh();
    }

    public function adjust(FacilitySubscription $sub, string $status, ?string $reason = null, ?User $actor = null, array $meta = []): FacilitySubscription
    {
        $target = FacilitySubscriptionStatus::tryFrom($status);
        if (! $target) {
            throw new \RuntimeException("Unknown status: {$status}");
        }
        $from = $sub->status->value;
        $sub->status = $target;
        if ($target === FacilitySubscriptionStatus::EXPIRED && $sub->expired_at === null) {
            $sub->expired_at = now();
        }
        $sub->save();
        $this->recordEvent($sub, 'adjusted', $actor, $from, $sub->status->value, ['reason' => $reason], $reason ?? 'Admin adjustment.');
        $this->audit($actor?->id, 'subscription.adjusted', $sub, ['status' => $from], ['status' => $sub->status->value], $reason, $meta);

        return $sub->fresh();
    }

    public function sweep(): array
    {
        $affected = [];
        $now = now();
        foreach (FacilitySubscription::where('status', FacilitySubscriptionStatus::TRIAL->value)->get() as $sub) {
            if ($sub->trial_ends_at && $sub->trial_ends_at->isPast()) {
                $sub->status = FacilitySubscriptionStatus::PAST_DUE;
                $sub->past_due_since = $now;
                $sub->grace_ends_at = $now->copy()->addDays((int) config('services.subscriptions.grace_period_days', 3));
                $sub->save();
                $this->recordEvent($sub, 'trial_expired', null, 'trial', $sub->status->value, [], 'Trial elapsed.');
                $affected[] = ['subscription_id' => $sub->id, 'event' => 'trial_expired', 'status' => $sub->status->value];
            }
        }
        foreach (FacilitySubscription::where('status', FacilitySubscriptionStatus::PAST_DUE->value)->get() as $sub) {
            if ($sub->grace_ends_at && $sub->grace_ends_at->isPast()) {
                $sub->status = FacilitySubscriptionStatus::SUSPENDED;
                $sub->suspended_at = $now;
                $sub->save();
                $this->recordEvent($sub, 'suspended', null, 'past_due', $sub->status->value, [], 'Grace elapsed.');
                $affected[] = ['subscription_id' => $sub->id, 'event' => 'suspended', 'status' => $sub->status->value];
            }
        }
        foreach (FacilitySubscription::where('status', FacilitySubscriptionStatus::CANCELLED->value)->get() as $sub) {
            if ($sub->next_billing_date && $sub->next_billing_date->isPast()) {
                $sub->status = FacilitySubscriptionStatus::EXPIRED;
                $sub->expired_at = $now;
                $sub->save();
                $this->recordEvent($sub, 'expired', null, 'cancelled', $sub->status->value, [], 'Period ended.');
                $affected[] = ['subscription_id' => $sub->id, 'event' => 'expired', 'status' => $sub->status->value];
            }
        }
        $autoExpireDays = config('services.subscriptions.suspended_auto_expire_days');
        if ($autoExpireDays !== null) {
            foreach (FacilitySubscription::where('status', FacilitySubscriptionStatus::SUSPENDED->value)->get() as $sub) {
                if ($sub->suspended_at && $sub->suspended_at->copy()->addDays((int) $autoExpireDays)->isPast()) {
                    $sub->status = FacilitySubscriptionStatus::EXPIRED;
                    $sub->expired_at = $now;
                    $sub->save();
                    $this->recordEvent($sub, 'expired', null, 'suspended', $sub->status->value, [], 'Auto-expired.');
                    $affected[] = ['subscription_id' => $sub->id, 'event' => 'expired', 'status' => $sub->status->value];
                }
            }
        }

        return $affected;
    }

    protected function applyPlan(FacilitySubscription $sub, Plan $plan, BillingCycle $cycle): void
    {
        $sub->plan_id = $plan->id;
        $sub->plan_version = $plan->version;
        $sub->plan_snapshot = $plan->snapshotArray();
        $sub->billing_cycle = $cycle;
        $sub->currency = $plan->currency ?? 'KES';
        $sub->monthly_price = $plan->monthly_price ?? 0;
        $sub->annual_price = $plan->annual_price ?? 0;
    }

    protected function stampActivation(FacilitySubscription $sub, BillingCycle $cycle): void
    {
        $sub->activated_at = $sub->activated_at ?? now();
        $sub->started_at = $sub->started_at ?? now();
        $sub->last_billing_date = now();
        $sub->next_billing_date = $cycle->nextDate(now());
    }

    protected function validateAgainstLimits(Facility $facility, Plan $plan, string $action): void
    {
        $usedDoctors = $this->usage->doctorSeatsUsed($facility);
        $usedStaff = $this->usage->staffSeatsUsed($facility);
        $usedLocations = $this->usage->locationsUsed($facility);
        foreach ([['metric' => 'doctor', 'used' => $usedDoctors, 'max' => $plan->max_doctors, 'label' => 'doctor'], ['metric' => 'staff', 'used' => $usedStaff, 'max' => $plan->max_staff, 'label' => 'staff member'], ['metric' => 'locations', 'used' => $usedLocations, 'max' => $plan->max_locations, 'label' => 'location']] as $check) {
            if ($check['max'] !== null && $check['used'] > $check['max']) {
                throw EntitlementException::limit($check['metric'], ['used' => $check['used'], 'max' => $check['max'], 'required' => $check['used'], 'message' => ucfirst($check['label']).' usage exceeds limit ('.$check['used'].'/'.$check['max'].'). '.ucfirst($action).'.', 'action' => ucfirst($action).' or reduce usage.']);
            }
        }
    }

    protected function recordEvent(FacilitySubscription $sub, string $event, $actor, ?string $from, ?string $to, array $payload = [], ?string $reason = null, array $meta = []): FacilitySubscriptionEvent
    {
        return FacilitySubscriptionEvent::create(['facility_subscription_id' => $sub->id, 'event' => $event, 'actor_id' => $actor ? $actor->id : null, 'reason' => $reason, 'from_status' => $from, 'to_status' => $to, 'payload' => $payload, 'ip_address' => $meta['ip_address'] ?? null, 'user_agent' => $meta['user_agent'] ?? null]);
    }

    protected function audit(?int $actorId, string $action, FacilitySubscription $sub, array $before, array $after, ?string $reason, array $meta): void
    {
        AuditLog::record(actorId: $actorId, action: $action, resourceType: FacilitySubscription::class, resourceId: $sub->id, resourceLabel: $sub->facility?->name ?? "Facility #{$sub->facility_id}", before: $before, after: $after, reason: $reason, ipAddress: $meta['ip_address'] ?? null, userAgent: $meta['user_agent'] ?? null);
    }

    public function usage(): FacilityUsageService
    {
        return $this->usage;
    }
}
