<?php

namespace App\Services;

use App\Enums\AccountState;
use App\Enums\VerificationStatus;
use App\Models\User;

/**
 * Phase 23: Account state machine.
 *
 * Owns the authoritative derivation of a user's account state and the legal
 * allowed transitions. The frontend may never set account_state directly.
 */
class AccountStateMachine
{
    /**
     * Legal transitions mapping. Each state may transition only to the listed
     * states. This prevents arbitrary/illegal state changes.
     *
     * @var array<string, AccountState[]>
     */
    public const TRANSITIONS = [
        AccountState::INVITED->value => [
            AccountState::REGISTERED,
            AccountState::REJECTED,
            AccountState::DEACTIVATED,
        ],
        AccountState::REGISTERED->value => [
            AccountState::CONTACT_UNVERIFIED,
            AccountState::CONTACT_VERIFIED,
            AccountState::PROFILE_INCOMPLETE,
            AccountState::PROFILE_COMPLETE,
            AccountState::VERIFICATION_PENDING,
            AccountState::VERIFIED,
            AccountState::ACTIVE,
            AccountState::SUSPENDED,
            AccountState::DISABLED,
            AccountState::REJECTED,
            AccountState::DEACTIVATED,
        ],
        AccountState::CONTACT_UNVERIFIED->value => [
            AccountState::CONTACT_VERIFIED,
            AccountState::PROFILE_INCOMPLETE,
            AccountState::PROFILE_COMPLETE,
            AccountState::VERIFICATION_PENDING,
            AccountState::VERIFIED,
            AccountState::ACTIVE,
            AccountState::SUSPENDED,
            AccountState::DISABLED,
            AccountState::REJECTED,
            AccountState::DEACTIVATED,
        ],
        AccountState::CONTACT_VERIFIED->value => [
            AccountState::PROFILE_INCOMPLETE,
            AccountState::PROFILE_COMPLETE,
            AccountState::VERIFICATION_PENDING,
            AccountState::VERIFIED,
            AccountState::ACTIVE,
            AccountState::SUSPENDED,
            AccountState::DISABLED,
            AccountState::REJECTED,
            AccountState::DEACTIVATED,
        ],
        AccountState::PROFILE_INCOMPLETE->value => [
            AccountState::PROFILE_COMPLETE,
            AccountState::VERIFICATION_PENDING,
            AccountState::VERIFIED,
            AccountState::ACTIVE,
            AccountState::SUSPENDED,
            AccountState::DISABLED,
            AccountState::REJECTED,
            AccountState::DEACTIVATED,
        ],
        AccountState::PROFILE_COMPLETE->value => [
            AccountState::VERIFICATION_PENDING,
            AccountState::VERIFIED,
            AccountState::ACTIVE,
            AccountState::SUSPENDED,
            AccountState::DISABLED,
            AccountState::REJECTED,
            AccountState::DEACTIVATED,
        ],
        AccountState::VERIFICATION_PENDING->value => [
            AccountState::VERIFIED,
            AccountState::ACTIVE,
            AccountState::SUSPENDED,
            AccountState::DISABLED,
            AccountState::REJECTED,
            AccountState::DEACTIVATED,
        ],
        AccountState::VERIFIED->value => [
            AccountState::ACTIVE,
            AccountState::SUSPENDED,
            AccountState::DISABLED,
            AccountState::REJECTED,
            AccountState::DEACTIVATED,
        ],
        AccountState::ACTIVE->value => [
            AccountState::SUSPENDED,
            AccountState::DISABLED,
            AccountState::DEACTIVATED,
            AccountState::REJECTED,
        ],
        AccountState::SUSPENDED->value => [
            AccountState::ACTIVE,
            AccountState::VERIFIED,
            AccountState::DEACTIVATED,
        ],
        AccountState::DISABLED->value => [
            AccountState::ACTIVE,
            AccountState::DEACTIVATED,
        ],
        AccountState::REJECTED->value => [
            AccountState::ACTIVE,
            AccountState::DEACTIVATED,
        ],
        AccountState::DEACTIVATED->value => [
            AccountState::ACTIVE,
        ],
    ];

    /**
     * Whether the transition from $from to $to is legal.
     */
    public function allows(AccountState $from, AccountState $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value] ?? [], true);
    }

    /**
     * Application-level transition. Throws on illegal transitions.
     */
    public function transition(User $user, AccountState $to, bool $failClosed = true): bool
    {
        $from = $user->accountState();

        if (! $this->allows($from, $to)) {
            if ($failClosed) {
                throw new \DomainException("Illegal account state transition: {$from->value} → {$to->value}.");
            }

            return false;
        }

        $user->account_state = $to;
        $user->save();

        return true;
    }

    /**
     * Derive the correct state from the user's real attributes and move to it
     * if the transition is legal. Respects closed states (never silently
     * overrides a suspension/rejection/deactivation).
     */
    public function derive(User $user): AccountState
    {
        $current = $user->accountState();

        if ($current->isClosed()) {
            return $current;
        }

        $state = $this->compute($user);

        if ($state !== $current && $this->allows($current, $state)) {
            $user->account_state = $state;
            $user->save();
        }

        return $state;
    }

    /**
     * Compute the correct lifecycle state without persisting.
     */
    public function compute(User $user): AccountState
    {
        $user->loadMissing('roles');

        $contactVerified = $user->email_verified_at !== null || $user->hasVerifiedPhone();
        $isPatient = $user->roles->pluck('slug')->contains('patient');

        if ($isPatient) {
            return $contactVerified ? AccountState::ACTIVE : AccountState::CONTACT_UNVERIFIED;
        }

        $isDoctor = $user->roles->pluck('slug')->contains('doctor');
        $isFacilityAdmin = $user->roles->pluck('slug')->contains('facility-admin');

        if ($isDoctor) {
            $doctor = $user->doctor;
            if (! $contactVerified) {
                return AccountState::CONTACT_UNVERIFIED;
            }
            if ($doctor && ProfileCompletenessService::isDoctorComplete($doctor) === false) {
                return AccountState::PROFILE_INCOMPLETE;
            }
            if ($doctor && $doctor->verification_status?->value === VerificationStatus::PENDING->value) {
                return AccountState::VERIFICATION_PENDING;
            }
            if ($doctor && $doctor->verification_status?->value === VerificationStatus::VERIFIED->value) {
                return AccountState::ACTIVE;
            }

            return $contactVerified ? AccountState::PROFILE_INCOMPLETE : AccountState::CONTACT_UNVERIFIED;
        }

        if ($isFacilityAdmin) {
            $facility = $user->facilities()->first();
            if (! $facility) {
                return $contactVerified ? AccountState::PROFILE_INCOMPLETE : AccountState::CONTACT_UNVERIFIED;
            }
            if (! $contactVerified) {
                return AccountState::CONTACT_UNVERIFIED;
            }
            if (ProfileCompletenessService::isFacilityComplete($facility) === false) {
                return AccountState::PROFILE_INCOMPLETE;
            }
            if ($facility->verification_status?->value === VerificationStatus::PENDING->value) {
                return AccountState::VERIFICATION_PENDING;
            }
            if ($facility->verification_status?->value === VerificationStatus::VERIFIED->value) {
                return AccountState::ACTIVE;
            }
        }

        return $contactVerified ? AccountState::ACTIVE : AccountState::CONTACT_UNVERIFIED;
    }
}
