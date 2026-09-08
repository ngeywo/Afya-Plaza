<?php

namespace App\Services;

use App\Models\PlatformFee;

/**
 * Phase 23 (Facility Payments): determines the platform fee for a
 * patient->facility payment at the moment it is processed. The result is
 * snapshotted by PaymentService — historical payments are never recomputed.
 */
class PlatformFeeService
{
    /**
     * Calculate the platform fee for a facility-directed payment.
     *
     * @return array{gross_amount: string, fee_amount: string, facility_net: string,
     *                fee_type: int, fee_rate: string, rule_source: string}
     */
    public function calculateFacilityAllocation(float|string $grossAmount): array
    {
        $gross = (string) $grossAmount;
        $persisted = PlatformFee::query()->active()->orderByDesc('effective_from')->orderByDesc('id')->first();

        $feeType = $persisted ? (int) $persisted->fee_type
            : (int) config('services.payments.platform_fee_type', PlatformFee::TYPE_PERCENTAGE);
        $feeRate = $persisted ? (float) $persisted->rate
            : (float) config('services.payments.platform_fee_percent', 5.0);
        $fixedFee = $feeType === PlatformFee::TYPE_FIXED ? (float) $feeRate : (float) config('services.payments.platform_fee_fixed', 0);
        $ruleSource = $persisted ? 'platform_fee:persisted' : 'platform_fee:config';

        $feeAmount = $this->calculateFee($gross, $feeType, $feeRate, $fixedFee);

        // Never take more than the gross amount.
        if ($feeAmount > (float) $gross) {
            $feeAmount = (float) $gross;
        }

        $net = bcsub($gross, (string) $feeAmount, 6);

        return [
            'gross_amount' => $gross,
            'fee_amount' => (string) $feeAmount,
            'facility_net' => $net,
            'fee_type' => $feeType,
            'fee_rate' => (string) $feeRate,
            'rule_source' => $ruleSource,
        ];
    }

    private function calculateFee(string $gross, int $feeType, float $feeRate, float $fixedFee): float
    {
        if ($feeType === PlatformFee::TYPE_FIXED) {
            return $fixedFee;
        }

        $rateAsFraction = (string) round($feeRate / 100, 8);

        if (function_exists('bcmul')) {
            return (float) bcmul($gross, $rateAsFraction, 6);
        }

        return round((float) $gross * $feeRate / 100, 6);
    }
}
