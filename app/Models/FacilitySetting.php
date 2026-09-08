<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilitySetting extends Model
{
    public const KEY_CURRENCY = 'currency';

    public const KEY_SLOT_DURATION = 'slot_duration_minutes';

    public const KEY_BOOKING_NOTICE = 'booking_notice_minutes';

    public const KEY_CANCEL_HORIZON = 'cancel_horizon_hours';

    public const KEY_INSTANT_BOOKING = 'instant_booking';

    public const KEY_TIMEZONE = 'timezone';

    protected $fillable = [
        'facility_id',
        'key',
        'value',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
