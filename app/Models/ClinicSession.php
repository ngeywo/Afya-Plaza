<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicSession extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'doctor_id', 'facility_id', 'doctor_facility_id', 'facility_location_id', 'doctor_facility_schedule_id',
        'session_date', 'start_time', 'end_time', 'slot_duration_minutes',
        'max_appointments', 'booked_appointments', 'consultation_fee',
        'status', 'doctor_confirmation', 'doctor_confirmed_at',
        'facility_confirmation', 'facility_confirmed_at',
        'cancellation_reason', 'notes',
        // Phase 13: Governance
        'cancelled_by',
        'cancelled_at',
        'confirmed_by_doctor_user',
        'confirmed_by_facility_user',
    ];

    protected $casts = [
        'session_date' => 'date',
        'doctor_confirmed_at' => 'datetime',
        'facility_confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'consultation_fee' => 'decimal:2',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function doctorFacility(): BelongsTo
    {
        return $this->belongsTo(DoctorFacility::class);
    }

    public function facilityLocation(): BelongsTo
    {
        return $this->belongsTo(FacilityLocation::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DoctorFacilitySchedule::class, 'doctor_facility_schedule_id');
    }

    public function availabilitySlots(): HasMany
    {
        return $this->hasMany(AvailabilitySlot::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    // Phase 13: Governance actor relations
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function doctorConfirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_doctor_user');
    }

    public function facilityConfirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_facility_user');
    }

    public function getAvailableSlotsAttribute(): int
    {
        if ($this->max_appointments === null) {
            return 999;
        }

        return max(0, $this->max_appointments - $this->booked_appointments);
    }

    public function getIsConfirmedAttribute(): bool
    {
        return $this->doctor_confirmation === 'confirmed'
            && $this->facility_confirmation === 'confirmed';
    }

    public function getIsBookableAttribute(): bool
    {
        return $this->status === 'confirmed'
            && $this->available_slots > 0
            && $this->session_date->gte(today());
    }

    // ─── Phase 13: Trust Chain ──────────────────────────────────────────────────

    /**
     * Whether the underlying doctor is currently bookable (verified/active).
     */
    public function getDoctorIsBookableAttribute(): bool
    {
        return $this->doctor && $this->doctor->isBookable();
    }

    /**
     * Whether the underlying facility is currently operational.
     */
    public function getFacilityIsOperationalAttribute(): bool
    {
        return $this->facility && $this->facility->isOperational();
    }

    /**
     * Whether the doctor-facility relationship is active (governed).
     */
    public function getRelationshipIsAuthoritativeAttribute(): bool
    {
        if (! $this->doctor || ! $this->facility) {
            return false;
        }
        $rel = DoctorFacility::where('doctor_id', $this->doctor_id)
            ->where('facility_id', $this->facility_id)
            ->first();

        return $rel ? $rel->isAuthoritative() : false;
    }
}
