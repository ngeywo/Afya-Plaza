<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'plan_id',
        'status',
        'subscribed_at',
        'expires_at',
        'cancelled_at',
        'effective_commission_rate',
        'effective_commission_type',
        'effective_fixed_commission',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'effective_commission_rate' => 'decimal:4',
        'effective_fixed_commission' => 'decimal:2',
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_PENDING = 'pending';

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopeActive($q)
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function getCommissionRateAttribute(): string
    {
        return bcadd($this->effective_commission_rate ?? $this->plan?->default_commission_rate ?? '0', '0', 4);
    }
}
