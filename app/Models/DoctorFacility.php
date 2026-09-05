<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoctorFacility extends Model
{
    use HasFactory;

    protected $table = 'doctor_facilities';

    protected $fillable = [
        'doctor_id',
        'facility_id',
        'consultation_fee',
        'accepts_appointments',
        'is_active',
        'started_at',
        'ended_at',
        'notes',
    ];

    protected $casts = [
        'consultation_fee' => 'decimal:2',
        'accepts_appointments' => 'boolean',
        'is_active' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DoctorFacilitySchedule::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(DoctorScheduleException::class);
    }
}
