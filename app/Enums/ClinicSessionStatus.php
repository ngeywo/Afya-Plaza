<?php

namespace App\Enums;

/**
 * Operational state of a single clinic session (a doctor appearing at a facility on a date).
 */
enum ClinicSessionStatus: string
{
    case DRAFT     = 'draft';
    case PENDING   = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT     => 'Draft',
            self::PENDING   => 'Pending Confirmation',
            self::CONFIRMED => 'Confirmed',
            self::CANCELLED => 'Cancelled',
            self::COMPLETED => 'Completed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT     => 'grey',
            self::PENDING   => 'warning',
            self::CONFIRMED => 'success',
            self::CANCELLED => 'error',
            self::COMPLETED => 'info',
        };
    }

    public function isBookable(): bool
    {
        return $this === self::CONFIRMED;
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->values()->all();
    }
}
