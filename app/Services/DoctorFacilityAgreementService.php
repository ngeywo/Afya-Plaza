<?php

namespace App\Services;

use App\Enums\DoctorFacilityAgreementType;
use App\Models\DoctorFacility;
use App\Models\DoctorFacilityAgreement;

/**
 * DoctorFacilityAgreementService — Phase 25
 *
 * Manages doctor-facility commercial agreements.
 */
class DoctorFacilityAgreementService
{
    /**
     * Create a new agreement.
     */
    public function create(
        DoctorFacility $doctorFacility,
        DoctorFacilityAgreementType $type,
        array $data,
        int $createdBy
    ): DoctorFacilityAgreement {
        // Validate that no overlapping active agreement exists
        $this->validateNoOverlap(
            $doctorFacility->id,
            $data['effective_from'],
            $data['effective_until'] ?? null,
        );

        return DoctorFacilityAgreement::create([
            'doctor_facility_id' => $doctorFacility->id,
            'agreement_type' => $type,
            'doctor_share_percentage' => $data['doctor_share_percentage'] ?? null,
            'facility_share_percentage' => $data['facility_share_percentage'] ?? null,
            'fixed_doctor_amount' => $data['fixed_doctor_amount'] ?? null,
            'fixed_facility_amount' => $data['fixed_facility_amount'] ?? null,
            'effective_from' => $data['effective_from'],
            'effective_until' => $data['effective_until'] ?? null,
            'is_active' => true,
            'notes' => $data['notes'] ?? null,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Update an existing agreement.
     */
    public function update(DoctorFacilityAgreement $agreement, array $data): DoctorFacilityAgreement
    {
        // If changing effective dates, validate no overlap
        if (isset($data['effective_from']) || isset($data['effective_until'])) {
            $this->validateNoOverlap(
                $agreement->doctor_facility_id,
                $data['effective_from'] ?? $agreement->effective_from,
                $data['effective_until'] ?? $agreement->effective_until,
                $agreement->id,
            );
        }

        $agreement->update(array_filter([
            'doctor_share_percentage' => $data['doctor_share_percentage'] ?? null,
            'facility_share_percentage' => $data['facility_share_percentage'] ?? null,
            'fixed_doctor_amount' => $data['fixed_doctor_amount'] ?? null,
            'fixed_facility_amount' => $data['fixed_facility_amount'] ?? null,
            'effective_from' => $data['effective_from'] ?? null,
            'effective_until' => $data['effective_until'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], fn ($v) => $v !== null));

        return $agreement->fresh();
    }

    /**
     * End an agreement (set effective_until date).
     */
    public function end(DoctorFacilityAgreement $agreement, \DateTimeInterface $endDate): DoctorFacilityAgreement
    {
        $agreement->update(['effective_until' => $endDate]);

        return $agreement->fresh();
    }

    /**
     * Deactivate an agreement.
     */
    public function deactivate(DoctorFacilityAgreement $agreement): DoctorFacilityAgreement
    {
        $agreement->update(['is_active' => false]);

        return $agreement->fresh();
    }

    /**
     * Get current agreement for a doctor-facility.
     */
    public function getCurrentAgreement(DoctorFacility $doctorFacility): ?DoctorFacilityAgreement
    {
        return DoctorFacilityAgreement::getForDoctorFacility($doctorFacility->id);
    }

    /**
     * Get agreement history for a doctor-facility.
     */
    public function getHistory(DoctorFacility $doctorFacility): array
    {
        return DoctorFacilityAgreement::where('doctor_facility_id', $doctorFacility->id)
            ->orderBy('effective_from', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Validate no overlapping active agreements.
     */
    private function validateNoOverlap(
        int $doctorFacilityId,
        \DateTimeInterface $effectiveFrom,
        ?\DateTimeInterface $effectiveUntil,
        ?int $excludeId = null
    ): void {
        $query = DoctorFacilityAgreement::where('doctor_facility_id', $doctorFacilityId)
            ->where('is_active', true)
            ->where('effective_from', '<=', $effectiveUntil ?? now()->addYears(10));

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($effectiveUntil) {
            $query->where(function ($q) use ($effectiveFrom) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $effectiveFrom);
            });
        }

        if ($query->exists()) {
            throw new \InvalidArgumentException('An overlapping active agreement already exists for this period');
        }
    }
}
