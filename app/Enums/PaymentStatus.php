<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PAID = 'paid';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::PAID => 'Paid',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            self::REFUNDED => 'Refunded',
            self::PARTIALLY_REFUNDED => 'Partially Refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'var(--color-warning)',
            self::PROCESSING => 'var(--color-info)',
            self::PAID => 'var(--color-success)',
            self::FAILED => 'var(--color-error)',
            self::CANCELLED => 'var(--color-muted)',
            self::REFUNDED => 'var(--color-muted)',
            self::PARTIALLY_REFUNDED => 'var(--color-warning)',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::PAID, self::FAILED, self::CANCELLED, self::REFUNDED]);
    }
}
