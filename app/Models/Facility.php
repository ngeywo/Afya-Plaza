<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'address',
        'city',
        'county_id',
        'latitude',
        'longitude',
        'phone',
        'email',
        'logo',
        'banner',
        'is_verified',
        'is_active',
        'type',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'doctor_facilities')
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

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'facility_admin')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
