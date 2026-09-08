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
    case INVITED = 'invited';
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
    case DECLINED = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::INVITED => 'Invited',
            self::PENDING => 'Pending Approval',
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::SUSPENDED => 'Suspended',
            self::DECLINED => 'Declined',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::INVITED => 'blue',
            self::PENDING => 'warning',
            self::ACTIVE => 'success',
            self::INACTIVE => 'grey',
            self::SUSPENDED => 'error',
            self::DECLINED => 'error',
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

    /**
     * Whether the relationship has been terminated (spec alias: ENDED).
     * Implemented by the INACTIVE state; the DB value stays "inactive".
     */
    public function isEnded(): bool
    {
        return $this === self::INACTIVE;
    }

    /**
     * Whether the relationship was refused by either party (spec alias: REJECTED).
     * Implemented by the DECLINED state; the DB value stays "declined".
     */
    public function isRejected(): bool
    {
        return $this === self::DECLINED;
    }

    /**
     * Phase 23: a doctor accepting a facility invitation makes the relationship
     * ACTIVE immediately (the facility already agreed by inviting). A facility
     * approving a doctor's PENDING join request also lands on ACTIVE.
     */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::INVITED => in_array($next, [self::ACTIVE, self::DECLINED], true),
            self::PENDING => in_array($next, [self::ACTIVE, self::DECLINED, self::SUSPENDED], true),
            self::ACTIVE => in_array($next, [self::INACTIVE, self::SUSPENDED], true),
            self::INACTIVE => in_array($next, [self::ACTIVE, self::SUSPENDED], true),
            self::SUSPENDED => in_array($next, [self::ACTIVE, self::INACTIVE], true),
            self::DECLINED => false,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->values()->all();
    }
}
