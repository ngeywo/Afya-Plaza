<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_number',
        'user_id',
        'doctor_id',
        'clinic_session_id',
        'facility_id',
        'appointment_date',
        'start_time',
        'end_time',
        'reason',
        'notes',
        'status',
        'confirmed_at',
        'checked_in_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'amount_paid',
        'payment_status',
        'cancelled_by',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'confirmed_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'amount_paid' => 'decimal:2',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function doctor(): BelongsTo { return $this->belongsTo(Doctor::class); }
    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function clinicSession(): BelongsTo { return $this->belongsTo(ClinicSession::class); }
    public function cancelledByUser(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }

    public static function generateNumber(): string
    {
        $prefix = 'APT';
        $date = now()->format('Ymd');
        $random = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        return "{$prefix}-{$date}-{$random}";
    }
}
