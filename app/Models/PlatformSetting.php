<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    use HasFactory;

    public const KEY_PLATFORM_NAME = 'platform_name';

    public const KEY_SUPPORT_EMAIL = 'support_email';

    public const KEY_DEFAULT_CURRENCY = 'default_currency';

    public const KEY_REGISTRATION_OPEN = 'registration_open';

    public const KEY_BOOKING_ENABLED = 'booking_enabled';

    public const KEY_MAINTENANCE_MODE = 'maintenance_mode';

    public const KEY_DEFAULT_COMMISSION_RATE = 'default_commission_rate';

    protected $fillable = [
        'key', 'value', 'group', 'description',
    ];

    protected $casts = [
        'value' => 'string',
    ];

    public static function allowedKeys(): array
    {
        return [
            self::KEY_PLATFORM_NAME,
            self::KEY_SUPPORT_EMAIL,
            self::KEY_DEFAULT_CURRENCY,
            self::KEY_REGISTRATION_OPEN,
            self::KEY_BOOKING_ENABLED,
            self::KEY_MAINTENANCE_MODE,
            self::KEY_DEFAULT_COMMISSION_RATE,
        ];
    }
}
