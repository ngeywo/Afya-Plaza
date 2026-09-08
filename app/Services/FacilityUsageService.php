<?php

namespace App\Services;

use App\Enums\DoctorRelationshipStatus;
use App\Models\Appointment;
use App\Models\DoctorFacility;
use App\Models\Facility;
use App\Models\FacilityLocation;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Authoritative consumption counters for a facility against its plan limits.
 *
 * Doctor seats: ACTIVE + INVITED + PENDING + SUSPENDED relationships consume a
 * slot. INVITED/PENDING RESERVE a seat so a facility cannot over-book while
 * relationships await a response; SUSPENDED consumes to block the
 * suspend->add->reactivate bypass. INACTIVE(ended)/DECLINED free their slot.
 *
 * Staff seats: active users attached through the facility_admin pivot. Role
 * exclusions are configurable (services.subscriptions.staff_role_exclusions).
 */
class FacilityUsageService
{
    public function doctorSeatsUsed(Facility $facility): int
    {
        return DoctorFacility::query()
            ->where('facility_id', $facility->id)
            ->whereIn('status', [
                DoctorRelationshipStatus::ACTIVE->value,
                DoctorRelationshipStatus::INVITED->value,
                DoctorRelationshipStatus::PENDING->value,
                DoctorRelationshipStatus::SUSPENDED->value,
            ])
            ->count();
    }

    public function staffSeatsUsed(Facility $facility): int
    {
        $excludedRoles = config('services.subscriptions.staff_role_exclusions', []);

        $query = User::query()
            ->where('is_active', true)
            ->whereHas('facilities', fn ($q) => $q->where('facilities.id', $facility->id));

        foreach ($excludedRoles as $roleSlug) {
            $query->whereDoesntHave('roles', fn ($q) => $q->where('slug', $roleSlug));
        }

        return $query->count();
    }

    public function locationsUsed(Facility $facility): int
    {
        return FacilityLocation::query()
            ->where('facility_id', $facility->id)
            ->where('is_active', true)
            ->count();
    }

    /**
     * Bookings consumed within the given window. Only statuses representing a
     * committed/consumed booking count; cancelled and no-show never do.
     */
    public function bookingsUsed(Facility $facility, ?Carbon $from = null, ?Carbon $to = null): int
    {
        $query = Appointment::query()
            ->where('facility_id', $facility->id)
            ->whereIn('status', ['confirmed', 'checked_in', 'in_progress', 'completed']);

        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('created_at', '<=', $to);
        }

        return $query->count();
    }

    public function usage(Facility $facility, array $limits): array
    {
        return [
            'doctors' => $this->metric('doctors', $this->doctorSeatsUsed($facility), $limits['max_doctors'] ?? null),
            'staff' => $this->metric('staff', $this->staffSeatsUsed($facility), $limits['max_staff'] ?? null),
            'locations' => $this->metric('locations', $this->locationsUsed($facility), $limits['max_locations'] ?? null),
            'monthly_bookings' => $this->metric('monthly_bookings', $this->bookingsUsed($facility), $limits['max_monthly_bookings'] ?? null),
        ];
    }

    protected function metric(string $key, int $used, ?int $max): array
    {
        $limit = $max === null ? null : max(0, $max);

        return [
            'key' => $key,
            'used' => $used,
            'max' => $limit,
            'unlimited' => $limit === null,
            'percent' => $limit === null || $limit === 0 ? 0 : (int) round(($used / $limit) * 100),
            'status' => match (true) {
                $limit === null => 'unlimited',
                $used >= $limit => 'at_limit',
                $used >= $limit * 0.8 => 'near_limit',
                default => 'ok',
            },
        ];
    }
}
