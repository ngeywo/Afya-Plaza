<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

/**
 * Lifecycle states of a facility subscription (billing plan).
 *
 * PENDING_PAYMENT — checkout completed, payment not yet confirmed
 * TRIAL           — trial period running, full entitlements apply
 * ACTIVE          — paid period, full entitlements apply
 * PAST_DUE        — payment failed / missed, grace period running
 * GRACE_PERIOD    — explicit extension after past-due (entitlements may vary)
 * SUSPENDED       — payment still outstanding after grace; capacity frozen
 * CANCELLED       — facility requested cancellation (entitled until period end)
 * EXPIRED         — no longer entitled to consume new capacity
 */
enum FacilitySubscriptionStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case TRIAL = 'trial';
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case GRACE_PERIOD = 'grace_period';
    case SUSPENDED = 'suspended';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_PAYMENT => 'Pending Payment',
            self::TRIAL => 'Trial',
            self::ACTIVE => 'Active',
            self::PAST_DUE => 'Past Due',
            self::GRACE_PERIOD => 'Grace Period',
            self::SUSPENDED => 'Suspended',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING_PAYMENT => 'warning',
            self::TRIAL => 'info',
            self::ACTIVE => 'success',
            self::PAST_DUE => 'error',
            self::GRACE_PERIOD => 'warning',
            self::SUSPENDED => 'error',
            self::CANCELLED => 'grey',
            self::EXPIRED => 'grey',
        };
    }

    /**
     * Whether the facility can keep operating under this subscription.
     * SUSPENDED / EXPIRED / PENDING_PAYMENT never confer rights.
     * CANCELLED confers rights only until next_billing_date (checked by model).
     */
    public function isOperational(): bool
    {
        return in_array($this, [self::TRIAL, self::ACTIVE, self::PAST_DUE, self::GRACE_PERIOD, self::CANCELLED], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::CANCELLED, self::EXPIRED], true);
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->values()->all();
    }

    public static function resolveStale(?Carbon $graceEndsAt): self
    {
        // After an explicit grace window closes, a past-due subscription suspends.
        return self::SUSPENDED;
    }
}
