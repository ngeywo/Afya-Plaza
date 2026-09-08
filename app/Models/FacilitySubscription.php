<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\FacilitySubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacilitySubscription extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_TRIAL = 'trial';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_GRACE_PERIOD = 'grace_period';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'facility_id',
        'plan_id',
        'plan_version',
        'plan_snapshot',
        'status',
        'billing_cycle',
        'currency',
        'monthly_price',
        'annual_price',
        'effective_amount',
        'trial_started_at',
        'trial_ends_at',
        'activated_at',
        'started_at',
        'last_billing_date',
        'next_billing_date',
        'past_due_since',
        'grace_ends_at',
        'suspended_at',
        'cancelled_at',
        'cancel_at_period_end',
        'cancelled_reason',
        'expired_at',
        'last_amount_paid',
        'payment_reference',
        'created_by',
    ];

    protected $casts = [
        'plan_snapshot' => 'array',
        'status' => FacilitySubscriptionStatus::class,
        'billing_cycle' => BillingCycle::class,
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'effective_amount' => 'decimal:2',
        'last_amount_paid' => 'decimal:2',
        'cancel_at_period_end' => 'boolean',
        'trial_started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'activated_at' => 'datetime',
        'started_at' => 'datetime',
        'last_billing_date' => 'datetime',
        'next_billing_date' => 'datetime',
        'past_due_since' => 'datetime',
        'grace_ends_at' => 'datetime',
        'suspended_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(FacilitySubscriptionEvent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Effective entitlement snapshot for this subscription. Prefer the frozen
     * snapshot (answers "what was this facility entitled to") over the live
     * plan, which may have been edited since.
     */
    public function snapshot(): array
    {
        return $this->plan_snapshot;
    }

    public function isOperational(): bool
    {
        if (! $this->status->isOperational()) {
            return false;
        }

        if ($this->status === FacilitySubscriptionStatus::CANCELLED) {
            // Entitled until the end of the cancelled period (cancel_at_period_end).
            return $this->next_billing_date !== null
                && $this->next_billing_date->isFuture();
        }

        return true;
    }

    public function isBlocked(): bool
    {
        return ! $this->isOperational();
    }

    public function remainingDays(): ?int
    {
        $boundary = match (true) {
            $this->status === FacilitySubscriptionStatus::TRIAL && $this->trial_ends_at !== null => $this->trial_ends_at,
            $this->next_billing_date !== null => $this->next_billing_date,
            default => null,
        };

        if ($boundary === null) {
            return null;
        }

        return max(0, now()->diffInDays($boundary, false));
    }

    public function effectiveAmount(): string
    {
        return $this->effective_amount ?? '0.00';
    }
}
