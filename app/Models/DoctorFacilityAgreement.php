<?php

namespace App\Models;

use App\Enums\DoctorFacilityAgreementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorFacilityAgreement extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_facility_id',
        'agreement_type',
        'doctor_share_percentage',
        'facility_share_percentage',
        'fixed_doctor_amount',
        'fixed_facility_amount',
        'effective_from',
        'effective_until',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'agreement_type' => DoctorFacilityAgreementType::class,
        'doctor_share_percentage' => 'decimal:2',
        'facility_share_percentage' => 'decimal:2',
        'fixed_doctor_amount' => 'decimal:2',
        'fixed_facility_amount' => 'decimal:2',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean',
    ];

    public function doctorFacility(): BelongsTo
    {
        return $this->belongsTo(DoctorFacility::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->doctorFacility->doctor();
    }

    public function facility(): BelongsTo
    {
        return $this->doctorFacility->facility();
    }

    /**
     * Get the applicable agreement for a given date.
     */
    public static function getForDoctorFacility(int $doctorFacilityId, ?\DateTimeInterface $date = null): ?self
    {
        $date = $date ?? now();

        return static::where('doctor_facility_id', $doctorFacilityId)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $date);
            })
            ->where('is_active', true)
            ->orderBy('effective_from', 'desc')
            ->first();
    }

    /**
     * Check if this agreement requires per-appointment settlement.
     */
    public function requiresSettlement(): bool
    {
        return $this->agreement_type->requiresPerAppointmentSettlement();
    }

    /**
     * Calculate settlement amounts for a given gross amount.
     */
    public function calculateSettlement(string $grossAmount, string $platformCommission): array
    {
        $gross = bcmul($grossAmount, '1', 2);
        $platform = bcmul($platformCommission, '1', 2);
        $afterPlatform = bcsub($gross, $platform, 2);

        if ($afterPlatform < 0) {
            $afterPlatform = '0.00';
        }

        return match ($this->agreement_type) {
            DoctorFacilityAgreementType::REVENUE_SHARE => $this->calculateRevenueShare($afterPlatform),
            DoctorFacilityAgreementType::FIXED => $this->calculateFixed($grossAmount),
            DoctorFacilityAgreementType::SALARIED => [
                'doctor_amount' => '0.00',
                'facility_amount' => $afterPlatform,
            ],
            DoctorFacilityAgreementType::CUSTOM => [
                'doctor_amount' => '0.00',
                'facility_amount' => $afterPlatform,
            ],
        };
    }

    private function calculateRevenueShare(string $afterPlatform): array
    {
        $doctorPct = bcmul((string) ($this->doctor_share_percentage ?? 70), '0.01', 4);
        $facilityPct = bcmul((string) ($this->facility_share_percentage ?? 30), '0.01', 4);

        $doctorAmount = bcmul($afterPlatform, $doctorPct, 2);
        $facilityAmount = bcmul($afterPlatform, $facilityPct, 2);

        return [
            'doctor_amount' => $doctorAmount,
            'facility_amount' => $facilityAmount,
        ];
    }

    private function calculateFixed(string $grossAmount): array
    {
        return [
            'doctor_amount' => (string) ($this->fixed_doctor_amount ?? '0.00'),
            'facility_amount' => (string) ($this->fixed_facility_amount ?? '0.00'),
        ];
    }

    /**
     * Create a snapshot of this agreement for historical records.
     */
    public function toSnapshot(): array
    {
        return [
            'agreement_type' => $this->agreement_type->value,
            'doctor_share_percentage' => $this->doctor_share_percentage,
            'facility_share_percentage' => $this->facility_share_percentage,
            'fixed_doctor_amount' => $this->fixed_doctor_amount,
            'fixed_facility_amount' => $this->fixed_facility_amount,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_until' => $this->effective_until?->toDateString(),
        ];
    }
}
