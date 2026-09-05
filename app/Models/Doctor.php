<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'display_name',
        'slug',
        'biography',
        'qualifications',
        'license_number',
        'consultation_fee',
        'avatar',
        'cover_image',
        'gender',
        'date_of_birth',
        'years_of_experience',
        'is_verified',
        'is_active',
        'is_featured',
        'verified_at',
        'verified_by',
        'verification_status',
        'rejection_reason',
        'rejection_notes',
        'suspended_at',
        'suspended_by',
        'suspension_reason',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'verified_at' => 'datetime',
        'suspended_at' => 'datetime',
        'consultation_fee' => 'decimal:2',
        'verification_status' => \App\Enums\VerificationStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class, 'doctor_specialty')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function primarySpecialty(): BelongsToMany
    {
        return $this->specialties()->wherePivot('is_primary', true);
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'doctor_facilities')
            ->withPivot([
                'consultation_fee',
                'accepts_appointments',
                'is_active',
                'started_at',
                'ended_at',
                'notes',
            ])
            ->withTimestamps();
    }

    public function doctorFacilities(): HasMany
    {
        return $this->hasMany(DoctorFacility::class);
    }

    public function clinicSessions(): HasMany
    {
        return $this->hasMany(ClinicSession::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    // ─── Phase 13: Governance Scopes ──────────────────────────────────────────────

    public function activeRelationships()
    {
        return $this->doctorFacilities()->where('status', \App\Enums\DoctorRelationshipStatus::ACTIVE->value);
    }

    // ─── Phase 12: Financial Relations ───────────────────────────────────────────

    public function subscription()
    {
        return $this->hasOne(DoctorSubscription::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function earnings()
    {
        return $this->hasMany(DoctorEarning::class);
    }

    public function payouts()
    {
        return $this->hasMany(Payout::class);
    }

    // ─── Phase 13: Governance Helpers ────────────────────────────────────────────

    public function isTrustworthy(): bool
    {
        return $this->verification_status?->isTrusted() === true;
    }

    public function isBookable(): bool
    {
        if (!$this->is_active) return false;
        return in_array($this->verification_status, [
            \App\Enums\VerificationStatus::VERIFIED,
            \App\Enums\VerificationStatus::SUSPENDED,
        ], true);
    }

    public function isSuspended(): bool
    {
        return $this->verification_status === \App\Enums\VerificationStatus::SUSPENDED;
    }

    /**
     * Resolve the doctor's active subscription, creating a default if none exists.
     * Used by CommissionService at payment time to determine applicable commission rules.
     */
    public function resolveSubscription(): DoctorSubscription
    {
        $sub = $this->subscription()
            ->where('status', DoctorSubscription::STATUS_ACTIVE)
            ->first();

        if ($sub) return $sub;

        // Auto-assign the default plan (Starter) to doctors without a subscription
        $defaultPlan = Plan::active()->default()->first()
            ?? Plan::active()->orderBy('sort_order')->first();

        if (!$defaultPlan) {
            throw new \RuntimeException('No active plan configured in the system.');
        }

        return DoctorSubscription::create([
            'doctor_id' => $this->id,
            'plan_id' => $defaultPlan->id,
            'status' => DoctorSubscription::STATUS_ACTIVE,
            'subscribed_at' => now(),
            'effective_commission_rate' => $defaultPlan->default_commission_rate,
            'effective_commission_type' => $defaultPlan->commission_type,
            'effective_fixed_commission' => $defaultPlan->fixed_commission_amount,
        ]);
    }
}