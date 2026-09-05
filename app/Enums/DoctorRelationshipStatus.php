<?php

namespace App\Enums;

/**
 * Lifecycle states for a doctor-facility professional relationship.
 *
 * INVITED   — Facility sent an invitation; doctor has not yet responded
 * PENDING   — Doctor requested to join; facility has not yet approved
 * ACTIVE    — Both parties agreed; relationship is operational
 * INACTIVE  — Either party marked it inactive (not deleted; preserves history)
 * SUSPENDED — Platform suspended the relationship
 * DECLINED  — Doctor declined the invitation OR facility declined the request
 */
enum DoctorRelationshipStatus: string
{
    case INVITED   = 'invited';
    case PENDING   = 'pending';
    case ACTIVE    = 'active';
    case INACTIVE  = 'inactive';
    case SUSPENDED = 'suspended';
    case DECLINED  = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::INVITED   => 'Invited',
            self::PENDING   => 'Pending Approval',
            self::ACTIVE    => 'Active',
            self::INACTIVE  => 'Inactive',
            self::SUSPENDED => 'Suspended',
            self::DECLINED  => 'Declined',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::INVITED   => 'blue',
            self::PENDING   => 'warning',
            self::ACTIVE    => 'success',
            self::INACTIVE  => 'grey',
            self::SUSPENDED => 'error',
            self::DECLINED  => 'error',
        };
    }

    /**
     * Whether the relationship is considered authoritative for booking.
     */
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isVisibleToPatient(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::INVITED   => in_array($next, [self::PENDING, self::DECLINED], true),
            self::PENDING   => in_array($next, [self::ACTIVE, self::DECLINED, self::SUSPENDED], true),
            self::ACTIVE    => in_array($next, [self::INACTIVE, self::SUSPENDED], true),
            self::INACTIVE  => in_array($next, [self::ACTIVE, self::SUSPENDED], true),
            self::SUSPENDED => in_array($next, [self::ACTIVE, self::INACTIVE], true),
            self::DECLINED  => false,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->values()->all();
    }
}
