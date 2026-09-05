<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'facility_id',
        'doctor_facility_schedule_id',
        'session_date',
        'start_time',
        'end_time',
        'slot_duration_minutes',
        'max_appointments',
        'booked_appointments',
        'consultation_fee',
        'status',
        'doctor_confirmation',
        'doctor_confirmed_at',
        'facility_confirmation',
        'facility_confirmed_at',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'session_date' => 'date',
        'doctor_confirmed_at' => 'datetime',
        'facility_confirmed_at' => 'datetime',
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

    public function getAvailableSlotsAttribute(): int
    {
        if ($this->max_appointments === null) {
            return 999; // Unlimited
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
}
