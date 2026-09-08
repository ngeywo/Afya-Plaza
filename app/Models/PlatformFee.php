<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

/**
 * Phase 23 (Facility Payments): configurable platform fee applied to
 * patient->facility payments. A persisted, active, effective row wins over the
 * config fallback — the fee is NEVER hard-coded into payment calculations.
 */
class PlatformFee extends Model
{
    use HasFactory;

    public const TYPE_PERCENTAGE = 1;

    public const TYPE_FIXED = 2;

    protected $fillable = [
        'name',
        'fee_type',
        'rate',
        'is_active',
        'effective_from',
        'effective_until',
        'created_by',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($q)
    {
        $today = today()->toDateString();

        return $q->where('is_active', true)
            ->where(fn ($inner) => $inner->whereNull('effective_from')->orWhere('effective_from', '<=', $today))
            ->where(fn ($inner) => $inner->whereNull('effective_until')->orWhere('effective_until', '>=', $today));
    }

    /**
     * Resolve the single applicable fee for right now (persisted first).
     */
    public static function resolveActiveRate(): ?float
    {
        $fee = static::query()->active()->orderByDesc('effective_from')->orderByDesc('id')->first();

        if ($fee) {
            return (float) $fee->rate;
        }

        Log::warning('PlatformFee: no persisted fee; relying on config fallback');

        return null;
    }

    public function isPercentage(): bool
    {
        return (int) $this->fee_type === self::TYPE_PERCENTAGE;
    }
}
