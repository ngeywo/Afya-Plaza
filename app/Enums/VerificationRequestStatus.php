<?php

namespace App\Enums;

/**
 * Phase 23: Lifecycle of a verification request (provider/facility review).
 */
enum VerificationRequestStatus: string
{
    case PENDING = 'pending';
    case UNDER_REVIEW = 'under_review';
    case MORE_INFO = 'more_info';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Review',
            self::UNDER_REVIEW => 'Under Review',
            self::MORE_INFO => 'More Information Requested',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::SUSPENDED => 'Suspended',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::APPROVED, self::REJECTED, self::SUSPENDED], true);
    }
}
