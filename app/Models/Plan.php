<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    public const SCOPE_DOCTOR = 'doctor';

    public const SCOPE_FACILITY = 'facility';

    public const TYPE_PERCENTAGE = 1;

    public const TYPE_FIXED = 2;

    /**
     * Catalog of feature flags shown on the facility comparison matrix.
     * Keys are stored in the plans.features JSON column.
     */
    public const FEATURES = [
        'doctor_management' => 'Doctor management',
        'clinic_sessions' => 'Clinic sessions',
        'advanced_scheduling' => 'Advanced scheduling',
        'online_booking' => 'Online booking',
        'facility_payments' => 'Facility payments',
        'staff_management' => 'Staff management',
        'multiple_locations' => 'Multiple locations',
        'notifications' => 'Notifications',
        'sms_notifications' => 'SMS notifications',
        'email_notifications' => 'Email notifications',
        'patient_reminders' => 'Patient reminders',
        'analytics' => 'Analytics',
        'advanced_reports' => 'Advanced reports',
        'priority_support' => 'Priority support',
        'export_reports' => 'Export reports',
        'api_access' => 'API access',
        'custom_branding' => 'Custom branding',
    ];

    protected $fillable = [
        'name',
        'slug',
        'scope',
        'description',
        'version',
        'monthly_price',
        'annual_price',
        'currency',
        'annual_discount_percent',
        'default_commission_rate',
        'commission_type',
        'fixed_commission_amount',
        'is_active',
        'is_default',
        'sort_order',
        'trial_days',
        'support_level',
        'max_doctors',
        'max_staff',
        'max_locations',
        'max_monthly_bookings',
        'max_sms',
        'max_storage_mb',
        'max_admin_users',
        'features',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'annual_discount_percent' => 'decimal:2',
        'default_commission_rate' => 'decimal:4',
        'fixed_commission_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'version' => 'integer',
        'trial_days' => 'integer',
        'max_doctors' => 'integer',
        'max_staff' => 'integer',
        'max_locations' => 'integer',
        'max_monthly_bookings' => 'integer',
        'max_sms' => 'integer',
        'max_storage_mb' => 'integer',
        'max_admin_users' => 'integer',
        'features' => 'array',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(DoctorSubscription::class);
    }

    public function facilitySubscriptions(): HasMany
    {
        return $this->hasMany(FacilitySubscription::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PlanVersion::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeDefault($q)
    {
        return $q->where('is_default', true);
    }

    public function scopeDoctor($q)
    {
        return $q->where('scope', self::SCOPE_DOCTOR);
    }

    public function scopeFacility($q)
    {
        return $q->where('scope', self::SCOPE_FACILITY);
    }

    public function isFacilityScope(): bool
    {
        return $this->scope === self::SCOPE_FACILITY;
    }

    public function feature(string $key): bool
    {
        return (bool) data_get($this->features, $key, false);
    }

    /**
     * Enabled feature keys (truthy entries in the features map).
     */
    public function featureList(): array
    {
        $features = $this->features ?? [];

        if (! is_array($features)) {
            return [];
        }

        return collect($features)->filter()->keys()->values()->all();
    }

    public function isUnlimitedFor(string $metric): bool
    {
        return $this->{$metric} === null;
    }

    /**
     * Immutable snapshot captured when the plan is applied to a subscription
     * (or recorded as a PlanVersion). Preserves what a facility was entitled
     * to regardless of later plan edits.
     */
    public function snapshotArray(): array
    {
        return [
            'plan_id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'version' => $this->version,
            'currency' => $this->currency ?? 'KES',
            'monthly_price' => (string) ($this->monthly_price ?? 0),
            'annual_price' => (string) ($this->annual_price ?? 0),
            'annual_discount_percent' => (string) ($this->annual_discount_percent ?? 0),
            'trial_days' => $this->trial_days,
            'support_level' => $this->support_level,
            'max_doctors' => $this->max_doctors,
            'max_staff' => $this->max_staff,
            'max_locations' => $this->max_locations,
            'max_monthly_bookings' => $this->max_monthly_bookings,
            'max_sms' => $this->max_sms,
            'max_storage_mb' => $this->max_storage_mb,
            'max_admin_users' => $this->max_admin_users,
            'features' => $this->featureList(),
        ];
    }

    /**
     * Meta rows for the comparison matrix (frontend renders generically).
     */
    public function comparisonMeta(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'currency' => $this->currency ?? 'KES',
            'monthly_price' => (string) ($this->monthly_price ?? 0),
            'annual_price' => (string) ($this->annual_price ?? 0),
            'annual_discount_percent' => (string) ($this->annual_discount_percent ?? 0),
            'trial_days' => $this->trial_days,
            'support_level' => $this->support_level,
            'sort_order' => $this->sort_order,
        ];
    }
}
