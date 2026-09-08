<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Facility;

/**
 * Phase 23: Profile completeness.
 *
 * Distinguishes required vs recommended fields. A provider is never blocked
 * from basic account management just because recommended fields are missing.
 */
class ProfileCompletenessService
{
    /**
     * Required then recommended doctor fields.
     *
     * @var array{required: array, recommended: array}
     */
    public const DOCTOR_FIELDS = [
        'required' => [
            'display_name',
            'specialty',
            'registry_number',
            'contact_verified',
        ],
        'recommended' => [
            'biography',
            'languages',
            'areas_of_practice',
            'consultation_fee',
            'avatar',
        ],
    ];

    public const FACILITY_FIELDS = [
        'required' => [
            'name',
            'type',
            'address',
            'county',
            'contact_verified',
        ],
        'recommended' => [
            'registry_number',
            'description',
            'locations',
            'latitude',
            'longitude',
        ],
    ];

    public static function doctorCompleteness(Doctor $doctor): array
    {
        $required = self::DOCTOR_FIELDS['required'];
        $recommended = self::DOCTOR_FIELDS['recommended'];

        $present = [
            'display_name' => filled($doctor->display_name),
            'specialty' => $doctor->specialties()->exists(),
            'registry_number' => filled($doctor->registry_number ?? $doctor->license_number),
            'contact_verified' => $doctor->user?->hasVerifiedContact() ?? false,
            'biography' => filled($doctor->biography),
            'languages' => filled($doctor->languages),
            'areas_of_practice' => filled($doctor->areas_of_practice),
            'consultation_fee' => $doctor->consultation_fee > 0,
            'avatar' => filled($doctor->avatar),
        ];

        return self::compute($present, $required, $recommended);
    }

    public static function facilityCompleteness(Facility $facility): array
    {
        $required = self::FACILITY_FIELDS['required'];
        $recommended = self::FACILITY_FIELDS['recommended'];

        $countyCompleteness = $facility->county()->exists() ? 1 : 0;

        $present = [
            'name' => filled($facility->name),
            'type' => filled($facility->type),
            'address' => filled($facility->address),
            'county' => (bool) $countyCompleteness,
            'contact_verified' => filled($facility->email) && filled($facility->phone),
            'registry_number' => filled($facility->registry_number),
            'description' => filled($facility->description),
            'locations' => $facility->allLocations()->exists(),
            'latitude' => filled($facility->latitude),
            'longitude' => filled($facility->longitude),
        ];

        return self::compute($present, $required, $recommended);
    }

    public static function isDoctorComplete(Doctor $doctor): bool
    {
        $result = self::doctorCompleteness($doctor);

        return $result['required_met'];
    }

    public static function isFacilityComplete(Facility $facility): bool
    {
        $result = self::facilityCompleteness($facility);

        return $result['required_met'];
    }

    /**
     * @param  array<string,bool>  $present
     * @param  string[]  $required
     * @param  string[]  $recommended
     */
    private static function compute(array $present, array $required, array $recommended): array
    {
        $missingRequired = array_values(array_filter($required, fn ($f) => empty($present[$f])));
        $missingRecommended = array_values(array_filter($recommended, fn ($f) => empty($present[$f])));

        $total = count($required) + count($recommended);
        $met = ($total - count($missingRequired) - count($missingRecommended));
        $percent = $total > 0 ? (int) round(($met / $total) * 100) : 100;

        return [
            'percent' => $percent,
            'required_met' => count($missingRequired) === 0,
            'recommended_met' => count($missingRecommended) === 0,
            'required' => $required,
            'recommended' => $recommended,
            'missing_required' => $missingRequired,
            'missing_recommended' => $missingRecommended,
            'present' => $present,
        ];
    }
}
