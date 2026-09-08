<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Phase 17: Raised when a requested appointment slot is no longer available.
 * Controllers map this to HTTP 409 with the SLOT_TAKEN / SLOT_NO_LONGER_AVAILABLE code.
 */
class SlotConflictException extends RuntimeException
{
    public static function taken(string $slot): self
    {
        return new self(
            "The selected time slot ({$slot}) has already been booked. Please choose another time.",
        );
    }
}
