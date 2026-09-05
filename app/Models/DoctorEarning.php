<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DoctorEarning extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'payment_id',
        'appointment_id',
        'gross_amount',
        'net_amount',
        'commission_amount',
        'currency',
        'plan_slug',
        'commission_rate_snapshot',
        'commission_type_snapshot',
        'status',
        'available_at',
        'paid_out_at',
        'reversed_at',
        'reversal_reason',
        'reversed_by',
        'description',
        'metadata',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'commission_rate_snapshot' => 'decimal:4',
        'available_at' => 'datetime',
        'paid_out_at' => 'datetime',
        'reversed_at' => 'datetime',
        'metadata' => 'array',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_PAID_OUT = 'paid_out';
    public const STATUS_REVERSED = 'reversed';

    public function doctor(): BelongsTo { return $this->belongsTo(Doctor::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function appointment(): BelongsTo { return $this->belongsTo(Appointment::class); }
    public function payouts(): BelongsToMany { return $this->belongsToMany(Payout::class, 'payout_earnings'); }

    public function scopePending($q) { return $q->where('status', self::STATUS_PENDING); }
    public function scopeAvailable($q) { return $q->where('status', self::STATUS_AVAILABLE); }
    public function scopePaidOut($q) { return $q->where('status', self::STATUS_PAID_OUT); }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    public function makeAvailable(): void
    {
        $this->update([
            'status' => self::STATUS_AVAILABLE,
            'available_at' => now(),
        ]);
    }

    public function markPaidOut(): void
    {
        $this->update([
            'status' => self::STATUS_PAID_OUT,
            'paid_out_at' => now(),
        ]);
    }
}
