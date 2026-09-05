<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorFacilitySchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_facility_id',
        'day_of_week',
        'start_time',
        'end_time',
        'slot_duration_minutes',
        'max_appointments',
        'is_active',
        'effective_from',
        'effective_until',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function doctorFacility(): BelongsTo
    {
        return $this->belongsTo(DoctorFacility::class);
    }

    public function doctor()
    {
        return $this->doctorFacility->doctor();
    }

    public function facility()
    {
        return $this->doctorFacility->facility();
    }
}
