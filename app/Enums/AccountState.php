<?php

namespace App\Enums;

/**
 * Phase 23: Account lifecycle state.
 *
 * Distinct from verification status and from the legacy `is_active`/`is_verified`
 * booleans. This is the authoritative control for whether an account may
 * authenticate and use the platform.
 */
enum AccountState: string
{
    case INVITED = 'invited';
    case REGISTERED = 'registered';
    case CONTACT_UNVERIFIED = 'contact_unverified';
    case CONTACT_VERIFIED = 'contact_verified';
    case PROFILE_INCOMPLETE = 'profile_incomplete';
    case PROFILE_COMPLETE = 'profile_complete';
    case VERIFICATION_PENDING = 'verification_pending';
    case VERIFIED = 'verified';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case DISABLED = 'disabled';
    case REJECTED = 'rejected';
    case DEACTIVATED = 'deactivated';

    public function label(): string
    {
        return match ($this) {
            self::INVITED => 'Invited',
            self::REGISTERED => 'Registered',
            self::CONTACT_UNVERIFIED => 'Contact Unverified',
            self::CONTACT_VERIFIED => 'Contact Verified',
            self::PROFILE_INCOMPLETE => 'Profile Incomplete',
            self::PROFILE_COMPLETE => 'Profile Complete',
            self::VERIFICATION_PENDING => 'Verification Pending',
            self::VERIFIED => 'Verified',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::DISABLED => 'Disabled',
            self::REJECTED => 'Rejected',
            self::DEACTIVATED => 'Deactivated',
        };
    }

    /**
     * Whether this state still allows authentication / login.
     */
    public function canAuthenticate(): bool
    {
        return in_array($this, [
            self::REGISTERED,
            self::CONTACT_UNVERIFIED,
            self::CONTACT_VERIFIED,
            self::PROFILE_INCOMPLETE,
            self::PROFILE_COMPLETE,
            self::VERIFICATION_PENDING,
            self::VERIFIED,
            self::ACTIVE,
        ], true);
    }

    /**
     * Whether this is a "closed" account that must not authenticate.
     */
    public function isClosed(): bool
    {
        return in_array($this, [
            self::SUSPENDED,
            self::DISABLED,
            self::REJECTED,
            self::DEACTIVATED,
        ], true);
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->values()->all();
    }
}
