<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phase 23 (Facility Payments): the non-secret payment details shown to a
 * patient so they know who they are paying. Never stores provider secrets.
 */
class FacilityPaymentAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'provider',
        'account_type',
        'account_name',
        'account_number',
        'is_primary',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    public const TYPE_PAYBILL = 'paybill';

    public const TYPE_TILL = 'till_number';

    public const TYPE_BANK = 'bank';

    public const TYPE_MPESA_EXPRESS = 'mpesa_express';

    public const TYPE_CASH = 'cash';

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeByFacility($q, int $facilityId)
    {
        return $q->where('facility_id', $facilityId);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopePrimary($q)
    {
        return $q->where('is_primary', true);
    }

    /**
     * Mask the account number for safe partial display (e.g. "****8901").
     */
    public function maskedNumber(): string
    {
        $value = (string) $this->account_number;
        if (mb_strlen($value) <= 4) {
            return $value;
        }

        return '****'.mb_substr($value, -4);
    }
}
