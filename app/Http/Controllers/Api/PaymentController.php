<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PaymentController — Phase 12
 *
 * Handles:
 *   POST /api/payments/initiate   → create a pending payment record
 *   POST /api/payments/callback   → payment provider webhook (authoritative)
 *   POST /api/payments/simulate   → dev/test simulation of successful payment
 *   GET  /api/payments/{id}       → fetch a payment (IDOR-protected)
 *
 * SECURITY: Never trust frontend "payment successful" claims.
 * All real payments must go through the callback endpoint after provider verification.
 */
class PaymentController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    /**
     * POST /api/payments/initiate
     *
     * Creates a PENDING payment record for the given appointment.
     * Idempotent: returns the existing pending/processing payment if one already exists.
     */
    /**
     * POST /api/payments/initiate
     *
     * Creates a PENDING payment record for the given appointment.
     * If method=mobile_money and provider=mpesa, also triggers M-Pesa STK Push
     * so the customer receives an M-Pesa prompt immediately.
     *
     * Body:
     *   appointment_id  required
     *   method         optional (default: mobile_money)
     *   provider       optional (default: mpesa for mobile_money, simulation otherwise)
     *   phone          required when provider=mpesa (patient's M-Pesa number)
     *
     * Idempotent: returns existing pending/processing payment if one already exists.
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'appointment_id' => 'required|integer|exists:appointments,id',
            'method'  => 'nullable|string|in:mobile_money,card,bank_transfer,cash,other',
            'provider' => 'nullable|string|in:mpesa,simulation,card,bank_transfer,cash',
            'phone'   => 'nullable|string|max:20',
        ]);

        $user       = $request->user();
        $appointment = Appointment::with('clinicSession')->findOrFail($validated['appointment_id']);

        if ($appointment->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        if ($appointment->payment_status === 'paid') {
            return response()->json(['error' => 'This appointment is already paid.'], 422);
        }

        if (in_array($appointment->status, ['cancelled', 'no_show'], true)) {
            return response()->json(['error' => 'Cannot pay for a cancelled appointment.'], 422);
        }

        $existing = Payment::where('appointment_id', $appointment->id)
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])
            ->first();

        if ($existing) {
            return response()->json([
                'data'    => $this->formatPayment($existing),
                'message' => 'Payment already exists.',
            ], 200);
        }

        $method  = $validated['method']  ?? 'mobile_money';
        $provider = $validated['provider'] ?? ($method === 'mobile_money' ? 'mpesa' : 'simulation');
        $phone   = $validated['phone']    ?? null;

        // Try to recover phone from user profile if not provided
        if ($provider === 'mpesa' && !$phone) {
            $phone = $user->phone ?? $user->profile?->phone ?? null;
        }

        $payment = $this->paymentService->initiate(
            appointment:     $appointment,
            method:         $method,
            provider:        $provider,
            idempotencyKey: "initiate:{$appointment->id}:{$user->id}",
        );

        // If M-Pesa STK Push, trigger the actual M-Pesa request
        if ($provider === 'mpesa' && $phone) {
            try {
                $mpesaService = app(\App\Services\MpesaService::class);

                $gross = (string) ($appointment->clinicSession?->consultation_fee
                    ?? $appointment->amount_paid
                    ?? '0');

                $stkResult = $mpesaService->stkPush(
                    amount:      (int) round((float) $gross),
                    phone:       $phone,
                    reference:   $payment->reference,
                    description: "Dr. Plaza appointment payment",
                );

                $payment->update([
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'mpesa_checkout_id' => $stkResult['checkout_request_id'],
                        'mpesa_merchant_id' => $stkResult['merchant_request_id'],
                        'mpesa_initiated_at' => now()->toIso8601String(),
                    ]),
                ]);

                Log::info('PaymentController: M-Pesa STK Push triggered', [
                    'payment_id'          => $payment->id,
                    'checkout_request_id' => $stkResult['checkout_request_id'],
                ]);

                return response()->json([
                    'data'    => $this->formatPayment($payment->fresh()),
                    'message' => 'M-Pesa STK Push sent. Check your phone and enter your M-Pesa PIN.',
                    'mpesa'   => [
                        'checkout_request_id' => $stkResult['checkout_request_id'],
                        'status'             => 'pending_customer',
                    ],
                ], 201);

            } catch (\Throwable $e) {
                Log::error('PaymentController: M-Pesa STK Push failed', [
                    'payment_id' => $payment->id,
                    'error'      => $e->getMessage(),
                ]);

                $payment->update([
                    'status'         => Payment::STATUS_FAILED,
                    'failure_reason' => 'M-Pesa STK Push failed: ' . $e->getMessage(),
                ]);

                return response()->json([
                    'error'   => 'Failed to initiate M-Pesa payment. Please try again.',
                    'details' => $e->getMessage(),
                ], 502);
            }
        }

        return response()->json([
            'data'    => $this->formatPayment($payment),
            'message' => 'Payment initiated. Proceed to payment provider.',
        ], 201);
    }
    /**
     * POST /api/payments/callback
     *
     * Authoritative payment confirmation endpoint (payment provider webhook).
     * Idempotent: returns 'already_processed' for already-confirmed payments.
     */
    public function callback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|string|max:50',
            'provider_reference' => 'nullable|string|max:255',
            'provider_phone' => 'nullable|string|max:20',
            'status' => 'required|string|in:success,failed',
            'appointment_id' => 'required|integer|exists:appointments,id',
            'amount' => 'nullable|numeric|min:0',
            'failure_reason' => 'nullable|string|max:500',
        ]);

        $appointment = Appointment::with('clinicSession')->findOrFail($validated['appointment_id']);

        $payment = Payment::where('appointment_id', $appointment->id)
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])
            ->first();

        if (!$payment) {
            $payment = $this->paymentService->initiate($appointment);
        }

        if ($payment->isPaid()) {
            return response()->json([
                'status' => 'already_processed',
                'reference' => $payment->reference,
            ]);
        }

        try {
            if ($validated['status'] === 'success') {
                if (isset($validated['amount'])) {
                    Log::info('Payment callback amount received', [
                        'payment_id' => $payment->id,
                        'provider_amount' => $validated['amount'],
                    ]);
                }

                $payment = $this->paymentService->confirm(
                    $payment,
                    $validated['provider_reference'] ?? 'N/A',
                    $validated['provider_phone'] ?? null,
                );

                return response()->json([
                    'status' => 'confirmed',
                    'reference' => $payment->reference,
                    'data' => $this->formatPayment($payment),
                ]);
            } else {
                $this->paymentService->fail(
                    $payment,
                    $validated['failure_reason'] ?? 'Payment failed at provider.',
                    $validated['provider_reference'] ?? null,
                );

                return response()->json(['status' => 'failed', 'reference' => $payment->reference]);
            }
        } catch (\RuntimeException $e) {
            Log::error('Payment callback error', [
                'payment_id' => $payment->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
    /**
     * POST /api/payments/simulate
     *
     * Development/test endpoint: simulates a successful payment without a real provider.
     * Guarded in non-local environments by X-Simulation-Secret header.
     */
    public function simulate(Request $request): JsonResponse
    {
        if (!app()->environment('local', 'development', 'testing')) {
            $secret = $request->header('X-Simulation-Secret');
            if ($secret !== env('PAYMENT_SIMULATION_SECRET', 'dev-secret')) {
                return response()->json(['error' => 'Unauthorized.'], 403);
            }
        }

        $validated = $request->validate([
            'appointment_id' => 'required|integer|exists:appointments,id',
        ]);

        $appointment = Appointment::with('clinicSession')->findOrFail($validated['appointment_id']);

        $payment = Payment::where('appointment_id', $appointment->id)
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])
            ->first();

        if (!$payment) {
            $payment = $this->paymentService->initiate($appointment);
        }

        if ($payment->isPaid()) {
            return response()->json([
                'status' => 'already_processed',
                'reference' => $payment->reference,
                'data' => $this->formatPayment($payment),
            ]);
        }

        $simRef = 'SIM-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);
        $payment = $this->paymentService->confirm($payment, $simRef);

        return response()->json([
            'message' => 'Payment confirmed (simulation).',
            'data' => $this->formatPayment($payment),
        ]);
    }

    /**
     * GET /api/payments/{id} — fetch a payment. IDOR-protected.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $payment = Payment::with(['appointment', 'doctor'])->findOrFail($id);
        $user = $request->user();

        $isOwner = $payment->user_id === $user->id
            || ($payment->doctor && $payment->doctor->user_id === $user->id)
            || $user->isSuperAdmin();

        if (!$isOwner) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        return response()->json(['data' => $this->formatPayment($payment)]);
    }

    private function formatPayment(Payment $p): array
    {
        return [
            'id' => $p->id,
            'reference' => $p->reference,
            'status' => $p->status,
            'gross_amount' => $p->gross_amount,
            'commission_amount' => $p->commission_amount,
            'net_amount' => $p->net_amount,
            'currency' => $p->currency,
            'method' => $p->method,
            'provider' => $p->provider,
            'provider_reference' => $p->provider_reference,
            'commission_type_snapshot' => $p->commission_type_snapshot,
            'commission_rate_snapshot' => $p->commission_rate_snapshot,
            'fixed_commission_snapshot' => $p->fixed_commission_snapshot,
            'commission_rule_source' => $p->commission_rule_source,
            'initiated_at' => $p->initiated_at?->toIso8601String(),
            'confirmed_at' => $p->confirmed_at?->toIso8601String(),
            'failed_at' => $p->failed_at?->toIso8601String(),
            'refunded_at' => $p->refunded_at?->toIso8601String(),
            'refunded_amount' => $p->refunded_amount,
            'metadata' => $p->metadata,
            'mpesa_checkout_id' => $p->metadata['mpesa_checkout_id'] ?? null,
            'appointment' => $p->appointment ? [
                'id' => $p->appointment->id,
                'appointment_number' => $p->appointment->appointment_number,
                'status' => $p->appointment->status,
                'appointment_date' => $p->appointment->appointment_date instanceof \Carbon\Carbon
                    ? $p->appointment->appointment_date->format('Y-m-d')
                    : $p->appointment->appointment_date,
                'start_time' => substr($p->appointment->start_time, 0, 5),
            ] : null,
            'doctor' => $p->doctor ? ['id' => $p->doctor->id, 'name' => $p->doctor->display_name] : null,
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }
}