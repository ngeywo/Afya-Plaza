<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case PENDING = 'pending';
    case UNDER_REVIEW = 'under_review';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::UNDER_REVIEW => 'Under Review',
            self::VERIFIED => 'Verified',
            self::REJECTED => 'Rejected',
            self::SUSPENDED => 'Suspended',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'grey',
            self::UNDER_REVIEW => 'blue',
            self::VERIFIED => 'success',
            self::REJECTED => 'error',
            self::SUSPENDED => 'warning',
        };
    }

    /**
     * Whether this status counts as "trusted" for marketplace visibility.
     */
    public function isTrusted(): bool
    {
        return $this === self::VERIFIED;
    }

    /**
     * Whether the entity can still operate (bookable, visible, etc.).
     */
    public function isOperational(): bool
    {
        return in_array($this, [self::VERIFIED, self::SUSPENDED], true);
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->values()->all();
    }
}
