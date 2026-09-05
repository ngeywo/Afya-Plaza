<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'appointment_id',
        'user_id',
        'doctor_id',
        'facility_id',
        'gross_amount',
        'commission_amount',
        'net_amount',
        'currency',
        'method',
        'provider',
        'provider_reference',
        'provider_phone',
        'status',
        'failure_reason',
        'idempotency_key',
        'initiated_at',
        'confirmed_at',
        'failed_at',
        'refunded_at',
        'refunded_amount',
        'commission_type_snapshot',
        'commission_rate_snapshot',
        'fixed_commission_snapshot',
        'commission_rule_source',
        'metadata',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'commission_rate_snapshot' => 'decimal:4',
        'fixed_commission_snapshot' => 'decimal:2',
        'initiated_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'failed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'metadata' => 'array',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    // Method constants
    public const METHOD_MOBILE_MONEY = 'mobile_money';
    public const METHOD_CARD = 'card';
    public const METHOD_BANK = 'bank';
    public const METHOD_CASH = 'cash';
    public const METHOD_OTHER = 'other';

    // Commission type snapshot constants (mirror Plan types)
    public const COMMISSION_TYPE_PERCENTAGE = 1;
    public const COMMISSION_TYPE_FIXED = 2;

    public static function generateReference(): string
    {
        return 'PAY-' . now()->format('Ymd') . '-' . str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function earning(): HasOne
    {
        return $this->hasOne(DoctorEarning::class);
    }

    public function scopePaid($q)
    {
        return $q->where('status', self::STATUS_PAID);
    }

    public function scopeFailed($q)
    {
        return $q->where('status', self::STATUS_FAILED);
    }

    public function scopePending($q)
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    public function scopeByDoctor($q, int $doctorId)
    {
        return $q->where('doctor_id', $doctorId);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function formatMoney(string $field): string
    {
        return 'KES ' . number_format((float) $this->{$field}, 2);
    }
}
