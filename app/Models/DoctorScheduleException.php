<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorScheduleException extends Model
{
    use HasFactory;

    protected $table = 'doctor_schedule_exceptions';

    protected $fillable = [
        'doctor_facility_id',
        'date',
        'type',
        'reason',
        'target_facility_id',
        'new_start_time',
        'new_end_time',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function doctorFacility(): BelongsTo
    {
        return $this->belongsTo(DoctorFacility::class);
    }

    public function targetFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'target_facility_id');
    }
}
