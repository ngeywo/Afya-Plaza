<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Payment;
use App\Models\Plan;

/**
 * CommissionService — Phase 12
 *
 * Determines the applicable marketplace commission rate at the moment a payment
 * is processed. The result is passed to PaymentService which snapshots it into
 * the Payment record for historical accuracy.
 *
 * Historical appointments are NEVER recalculated — they use the snapped values
 * stored in the Payment and Appointment records.
 */
class CommissionService
{
    /**
     * Calculate commission allocation for a given gross amount and doctor.
     *
     * @param  float|string  $grossAmount  Consultation fee (KES)
     * @return array{gross_amount: string, commission_amount: string, net_amount: string,
     *                commission_rate: string, commission_type: int, rule_source: string}
     */
    public function calculate(float|string $grossAmount, Doctor $doctor): array
    {
        $subscription = $doctor->resolveSubscription();
        $plan = $subscription->plan;

        $gross = (string) $grossAmount;
        $commissionType = (int) ($subscription->effective_commission_type ?? Plan::TYPE_PERCENTAGE);
        $commissionAmount = '0';
        $ruleSource = 'fallback:starter';

        if ($plan) {
            $ruleSource = "plan:{$plan->slug}";

            if ($commissionType === Plan::TYPE_PERCENTAGE) {
                $rate = (string) ($subscription->effective_commission_rate ?? $plan->default_commission_rate ?? '0');
                // gross * rate → commission (e.g. 2000 * 0.15 = 300)
                $commissionAmount = $this->multiply($gross, $rate);
            } else {
                // Fixed amount commission
                $commissionAmount = (string) ($subscription->effective_fixed_commission ?? $plan->fixed_commission_amount ?? '0');
            }
        }

        // Cap commission at gross amount (no negative earnings)
        if (bccomp($commissionAmount, $gross, 6) > 0) {
            $commissionAmount = $gross;
        }

        $netAmount = bcsub($gross, $commissionAmount, 6);
        $rate = $gross !== '0' && $gross !== '0.00'
            ? bcdiv($commissionAmount, $gross, 6)
            : '0';

        return [
            'gross_amount' => $gross,
            'commission_amount' => $commissionAmount,
            'net_amount' => $netAmount,
            'commission_rate' => $rate,
            'commission_type' => $commissionType,
            'rule_source' => $ruleSource,
        ];
    }

    /**
     * Multiply two decimal strings with 6-digit precision.
     * Uses BCMath when available, falls back to PHP floats for portability.
     */
    private function multiply(string $a, string $b): string
    {
        if (function_exists('bcmul')) {
            return bcmul($a, $b, 6);
        }

        return (string) round((float) $a * (float) $b, 6);
    }
}
