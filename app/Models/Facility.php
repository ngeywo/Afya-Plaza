<?php

namespace App\Models;

use App\Enums\DoctorRelationshipStatus;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Facility extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'address', 'city', 'county_id',
        'latitude', 'longitude', 'phone', 'email',
        'logo', 'banner', 'is_verified', 'is_active', 'type',
        'registry_number',
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
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'suspended_at' => 'datetime',
        'verification_status' => VerificationStatus::class,
    ];

    /**
     * Phase 23 (Unified Facility Identity): normalize the facility registration
     * identifier before every save.
     */
    protected static function booted(): void
    {
        static::saving(function (Facility $facility) {
            if ($facility->registry_number === null || trim($facility->registry_number) === '') {
                $facility->registry_number = null;
            } else {
                $facility->registry_number = strtoupper(preg_replace('/\s+/', '', $facility->registry_number));
            }
        });
    }

    // ─── Phase 13: Governance Relations ─────────────────────────────────────────

    public function suspendedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    public function activeDoctorFacilities()
    {
        return $this->doctorFacilities()
            ->where('status', DoctorRelationshipStatus::ACTIVE->value);
    }

    // ─── Phase 13: Governance Helpers ─────────────────────────────────────────

    public function isTrustworthy(): bool
    {
        return $this->verification_status?->isTrusted() === true;
    }

    public function isOperational(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return in_array($this->verification_status, [
            VerificationStatus::VERIFIED,
            VerificationStatus::PENDING,
        ], true);
    }

    public function isSuspended(): bool
    {
        return $this->verification_status === VerificationStatus::SUSPENDED;
    }

    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'doctor_facilities')
            ->withPivot(['consultation_fee', 'accepts_appointments', 'is_active', 'started_at', 'ended_at', 'notes'])
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

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'facility_admin')
            ->withPivot('is_primary')->withTimestamps();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(FacilityLocation::class)->where('is_active', true);
    }

    public function allLocations(): HasMany
    {
        return $this->hasMany(FacilityLocation::class);
    }

    public function verificationRequests(): MorphMany
    {
        return $this->morphMany(VerificationRequest::class, 'verifiable');
    }

    public function operatingHours(): HasMany
    {
        return $this->hasMany(FacilityOperatingHour::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(FacilitySetting::class);
    }
}
