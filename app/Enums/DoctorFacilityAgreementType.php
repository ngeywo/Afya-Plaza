<?php

namespace App\Enums;

enum DoctorFacilityAgreementType: string
{
    case REVENUE_SHARE = 'revenue_share';
    case FIXED = 'fixed';
    case SALARIED = 'salaried';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::REVENUE_SHARE => 'Revenue Share',
            self::FIXED => 'Fixed Per Appointment',
            self::SALARIED => 'Salaried (No Settlement)',
            self::CUSTOM => 'Custom Agreement',
        };
    }

    public function requiresPerAppointmentSettlement(): bool
    {
        return match ($this) {
            self::REVENUE_SHARE, self::FIXED => true,
            self::SALARIED, self::CUSTOM => false,
        };
    }
}
