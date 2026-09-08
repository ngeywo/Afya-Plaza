<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

enum BillingCycle: string
{
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::MONTHLY => 'Monthly',
            self::QUARTERLY => 'Quarterly',
            self::YEARLY => 'Yearly',
        };
    }

    public function nextDate(Carbon $from): Carbon
    {
        return match ($this) {
            self::MONTHLY => $from->copy()->addMonth(),
            self::QUARTERLY => $from->copy()->addQuarter(),
            self::YEARLY => $from->copy()->addYear(),
        };
    }

    public static function allowed(): array
    {
        return [self::MONTHLY->value, self::YEARLY->value]; // quarterly reserved for future
    }
}
