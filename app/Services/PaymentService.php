<?php

namespace App\Services;

use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use App\Models\Appointment;
use App\Models\DoctorEarning;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * PaymentService — Phase 12
 *
 * Orchestrates the financial lifecycle of a marketplace appointment:
 *   1. Initiate  → creates a pending Payment record
 *   2. Confirm   → marks paid, snapshots commission, creates DoctorEarning
 *   3. Fail      → marks failed, emits PaymentFailed event
 *   4. Refund    → reverses earnings, updates payment status
 *
 * Idempotency is enforced via:
 *   - payment.status (already paid → return existing)
 *   - (provider, provider_reference) unique index in DB
 *   - idempotency_key unique index in DB
 */
class PaymentService
{
    public function __construct(
        private CommissionService $commission,
        private EarningsService $earnings,
    ) {}

    /**
     * Initiate a payment for an appointment.
     * Returns the existing non-failed payment if one already exists.
     */
    public function initiate(
        Appointment $appointment,
        string $method = 'mobile_money',
        string $provider = 'mpesa',
        ?string $idempotencyKey = null,
    ): Payment {
        $existing = Payment::where('appointment_id', $appointment->id)
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING, Payment::STATUS_PAID])
            ->first();

        if ($existing) return $existing;

        $gross = (string) ($appointment->clinicSession?->consultation_fee
            ?? $appointment->amount_paid
            ?? '0.00');

        return Payment::create([
            'reference' => Payment::generateReference(),
            'appointment_id' => $appointment->id,
            'user_id' => $appointment->user_id,
            'doctor_id' => $appointment->doctor_id,
            'facility_id' => $appointment->facility_id,
            'gross_amount' => $gross,
            'commission_amount' => '0',
            'net_amount' => '0',
            'currency' => 'KES',
            'method' => $method,
            'provider' => $provider,
            'status' => Payment::STATUS_PENDING,
            'idempotency_key' => $idempotencyKey,
            'initiated_at' => now(),
        ]);
    }

    /**
     * Confirm a payment as successful. Idempotent.
     * Throws RuntimeException on conflicts (duplicate provider_reference).
     */
    public function confirm(
        Payment $payment,
        ?string $providerReference = null,
        ?string $providerPhone = null,
    ): Payment {
        if ($payment->isPaid()) return $payment;

        if ($providerReference) {
            $conflict = Payment::where('provider', $payment->provider)
                ->where('provider_reference', $providerReference)
                ->where('id', '!=', $payment->id)
                ->first();

            if ($conflict) {
                throw new \RuntimeException(
                    "Duplicate provider reference [{$providerReference}]. Payment [{$conflict->reference}] already exists."
                );
            }
        }

        return DB::transaction(function () use ($payment, $providerReference, $providerPhone) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->isPaid()) return $payment;

            $appointment = $payment->appointment;
            $doctor = $appointment?->doctor;

            if (!$doctor) {
                throw new \RuntimeException("Cannot confirm payment: doctor not found for appointment [{$appointment?->id}].");
            }

            $allocation = $this->commission->calculate($payment->gross_amount, $doctor);

            $payment->update([
                'status' => Payment::STATUS_PAID,
                'provider_reference' => $providerReference,
                'provider_phone' => $providerPhone,
                'commission_amount' => $allocation['commission_amount'],
                'net_amount' => $allocation['net_amount'],
                'confirmed_at' => now(),
                // Historical snapshot — NEVER recomputed
                'commission_type_snapshot' => $allocation['commission_type'],
                'commission_rate_snapshot' => $allocation['commission_rate'],
                'fixed_commission_snapshot' => $allocation['commission_type'] === 2
                    ? $allocation['commission_amount']
                    : null,
                'commission_rule_source' => $allocation['rule_source'],
            ]);

            $this->earnings->createFromPayment($payment, $allocation);

            $appointment->update([
                'payment_status' => 'paid',
                'amount_paid' => $payment->gross_amount,
                'plan_slug' => $allocation['rule_source'],
                'consultation_fee_snapshot' => $payment->gross_amount,
                'platform_commission_snapshot' => $allocation['commission_amount'],
                'doctor_earning_snapshot' => $allocation['net_amount'],
            ]);

            PaymentSucceeded::dispatch($payment);

            return $payment->fresh();
        });
    }

    /**
     * Mark a payment as failed.
     */
    public function fail(Payment $payment, string $reason, ?string $providerReference = null): Payment
    {
        if ($payment->isPaid() || $payment->isFailed()) return $payment;

        $payment->update([
            'status' => Payment::STATUS_FAILED,
            'failure_reason' => $reason,
            'provider_reference' => $providerReference,
            'failed_at' => now(),
        ]);

        $payment->appointment?->update(['payment_status' => 'failed']);
        PaymentFailed::dispatch($payment);

        return $payment->fresh();
    }

    /**
     * Refund a payment (full or partial). Reverses the associated earnings.
     */
    public function refund(Payment $payment, string $amount = 'full', ?string $reason = null): Payment
    {
        if (!$payment->isPaid()) {
            throw new \RuntimeException("Cannot refund a payment that is not in PAID status.");
        }

        $refundAmount = $amount === 'full'
            ? (string) $payment->gross_amount
            : (string) $amount;

        if (function_exists('bccomp') && bccomp($refundAmount, $payment->gross_amount, 6) > 0) {
            throw new \RuntimeException("Refund amount exceeds payment gross amount.");
        }

        return DB::transaction(function () use ($payment, $refundAmount, $reason) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);

            $newStatus = (string) $refundAmount === (string) $payment->gross_amount
                ? Payment::STATUS_REFUNDED
                : Payment::STATUS_PARTIALLY_REFUNDED;

            $previousRefunded = (string) ($payment->refunded_amount ?? '0');
            $newRefunded = function_exists('bcadd')
                ? bcadd($previousRefunded, $refundAmount, 6)
                : (string) ((float) $previousRefunded + (float) $refundAmount);

            $payment->update([
                'status' => $newStatus,
                'refunded_amount' => $newRefunded,
                'refunded_at' => now(),
            ]);

            $this->earnings->reverseForPayment($payment, $refundAmount, $reason ?? 'Refund processed');

            return $payment->fresh();
        });
    }
}
