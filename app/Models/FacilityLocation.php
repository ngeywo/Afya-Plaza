<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacilityLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'name',
        'address',
        'city',
        'county_id',
        'latitude',
        'longitude',
        'phone',
        'email',
        'is_primary',
        'is_active',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    public function clinicSessions(): HasMany
    {
        return $this->hasMany(ClinicSession::class);
    }
}
