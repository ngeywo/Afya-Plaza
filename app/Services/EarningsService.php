<?php

namespace App\Services;

use App\Models\DoctorEarning;
use App\Models\Payment;
use App\Models\Payout;
use Illuminate\Support\Facades\DB;

/**
 * EarningsService — Phase 12
 *
 * Manages the doctor-side view of money earned from completed appointments.
 * Earnings move through these states:
 *   pending → available (after appointment is completed)
 *   available → paid_out (when included in a processed payout)
 *   any → reversed (if the underlying payment is refunded/reversed)
 */
class EarningsService
{
    /**
     * Create a DoctorEarning record from a confirmed payment.
     * Idempotent: returns existing record if one already exists.
     */
    public function createFromPayment(Payment $payment, array $allocation): DoctorEarning
    {
        $existing = DoctorEarning::where('payment_id', $payment->id)->first();
        if ($existing) {
            return $existing;
        }

        $doctor = $payment->doctor;
        $planSlug = str_replace('plan:', '', $allocation['rule_source']);

        return DoctorEarning::create([
            'doctor_id' => $doctor->id,
            'payment_id' => $payment->id,
            'appointment_id' => $payment->appointment_id,
            'gross_amount' => $allocation['gross_amount'],
            'commission_amount' => $allocation['commission_amount'],
            'net_amount' => $allocation['net_amount'],
            'currency' => $payment->currency ?? 'KES',
            'plan_slug' => $planSlug,
            'commission_rate_snapshot' => $allocation['commission_rate'],
            'commission_type_snapshot' => $allocation['commission_type'],
            'status' => DoctorEarning::STATUS_PENDING,
            'description' => "Consultation — {$payment->appointment?->appointment_number}",
        ]);
    }

    /**
     * Move pending earnings to available (typically called when appointment completes).
     */
    public function makeAvailableForAppointment(int $appointmentId): int
    {
        $count = 0;
        DoctorEarning::where('appointment_id', $appointmentId)
            ->where('status', DoctorEarning::STATUS_PENDING)
            ->get()
            ->each(function (DoctorEarning $earning) use (&$count) {
                $earning->makeAvailable();
                $count++;
            });

        return $count;
    }

    /**
     * Reverse earnings tied to a payment (e.g. on refund).
     * Marks the earning as reversed; never deletes it.
     */
    public function reverseForPayment(Payment $payment, string $amount, string $reason): int
    {
        $earning = DoctorEarning::where('payment_id', $payment->id)->first();
        if (! $earning) {
            return 0;
        }

        if ($earning->status === DoctorEarning::STATUS_PAID_OUT) {
            // Already paid out — must be handled by a future deduction mechanism
            // For Phase 12 we just record the metadata; do not delete.
            $earning->update([
                'metadata' => array_merge($earning->metadata ?? [], [
                    'pending_deduction_amount' => $amount,
                    'pending_deduction_reason' => $reason,
                ]),
            ]);

            return 0;
        }

        $earning->update([
            'status' => DoctorEarning::STATUS_REVERSED,
            'reversed_at' => now(),
            'reversal_reason' => $reason,
            'reversed_by' => auth()->id(),
        ]);

        return 1;
    }

    /**
     * Compute a doctor's current balance summary.
     */
    public function summarizeForDoctor(int $doctorId): array
    {
        $pending = (string) DoctorEarning::where('doctor_id', $doctorId)
            ->where('status', DoctorEarning::STATUS_PENDING)
            ->sum('net_amount');

        $available = (string) DoctorEarning::where('doctor_id', $doctorId)
            ->where('status', DoctorEarning::STATUS_AVAILABLE)
            ->sum('net_amount');

        $paidOut = (string) DoctorEarning::where('doctor_id', $doctorId)
            ->where('status', DoctorEarning::STATUS_PAID_OUT)
            ->sum('net_amount');

        $reversed = (string) DoctorEarning::where('doctor_id', $doctorId)
            ->where('status', DoctorEarning::STATUS_REVERSED)
            ->sum('net_amount');

        return [
            'available' => $available,
            'pending' => $pending,
            'paid_out' => $paidOut,
            'reversed' => $reversed,
            'currency' => 'KES',
        ];
    }

    /**
     * Request a payout for a doctor — moves all available earnings into a new Payout.
     */
    public function requestPayout(int $doctorId, ?int $requestedBy = null): Payout
    {
        $earnings = DoctorEarning::where('doctor_id', $doctorId)
            ->where('status', DoctorEarning::STATUS_AVAILABLE)
            ->get();

        if ($earnings->isEmpty()) {
            throw new \RuntimeException('No available earnings to pay out.');
        }

        return DB::transaction(function () use ($doctorId, $earnings, $requestedBy) {
            $total = '0';
            foreach ($earnings as $earning) {
                $total = function_exists('bcadd')
                    ? bcadd($total, $earning->net_amount, 6)
                    : (string) ((float) $total + (float) $earning->net_amount);
            }

            $payout = Payout::create([
                'reference' => Payout::generateReference(),
                'doctor_id' => $doctorId,
                'amount' => $total,
                'currency' => 'KES',
                'status' => Payout::STATUS_REQUESTED,
                'requested_by' => $requestedBy ?? $doctorId,
                'requested_at' => now(),
            ]);

            foreach ($earnings as $earning) {
                $payout->earnings()->attach($earning->id, [
                    'amount' => $earning->net_amount,
                ]);
            }

            return $payout;
        });
    }
}
