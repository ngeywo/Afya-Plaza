<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phase 23 (Facility Management): a service a doctor performs at ONE specific
 * facility, priced per facility. Relationship-scoped — Facility A can never see
 * Facility B's services.
 */
class DoctorFacilityService extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_facility_id',
        'service_name',
        'price',
        'duration_minutes',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public const CONSULTATION = 'Consultation';

    public function doctorFacility(): BelongsTo
    {
        return $this->belongsTo(DoctorFacility::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeByRelationship($q, int $doctorFacilityId)
    {
        return $q->where('doctor_facility_id', $doctorFacilityId);
    }

    public function scopeConsultation($q)
    {
        return $q->where('service_name', self::CONSULTATION);
    }
}
