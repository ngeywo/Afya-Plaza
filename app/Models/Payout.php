<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'doctor_id',
        'amount',
        'currency',
        'status',
        'notes',
        'payment_reference',
        'payment_method',
        'requested_by',
        'approved_by',
        'rejected_by',
        'requested_at',
        'approved_at',
        'paid_at',
        'rejected_at',
        'rejection_reason',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'rejected_at' => 'datetime',
        'metadata' => 'array',
    ];

    // Status constants
    public const STATUS_REQUESTED = 'requested';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PAID = 'paid';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public static function generateReference(): string
    {
        return 'PO-'.now()->format('Ymd').'-'.str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function earnings(): BelongsToMany
    {
        return $this->belongsToMany(DoctorEarning::class, 'payout_earnings');
    }

    public function scopeRequested($q)
    {
        return $q->where('status', self::STATUS_REQUESTED);
    }

    public function scopeProcessing($q)
    {
        return $q->where('status', self::STATUS_PROCESSING);
    }

    public function scopePaid($q)
    {
        return $q->where('status', self::STATUS_PAID);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isRequested(): bool
    {
        return $this->status === self::STATUS_REQUESTED;
    }
}
