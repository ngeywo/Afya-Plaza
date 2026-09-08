<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoctorEarning;
use App\Models\Payment;
use App\Models\Payout;
use App\Services\MpesaService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaController extends Controller
{
    public function __construct(
        private MpesaService $mpesa,
        private PaymentService $paymentService,
    ) {}

    public function stkResult(Request $request): JsonResponse
    {
        Log::info('MpesaController: STK result', ['raw' => $request->all()]);

        $body = $request->input('Body.stkCallback');
        if (! $body) {
            return response()->json(['status' => 'ignored'], 200);
        }

        // Safaricom uses PascalCase in STK callbacks: CheckoutRequestID, ResultCode
        $checkoutRequestId = $body['CheckoutRequestID']
            ?? $body['checkoutRequestId']
            ?? null;
        $resultCode = (int) ($body['ResultCode']
            ?? $body['resultCode']
            ?? -1);
        $merchantRequestId = $body['MerchantRequestID']
            ?? $body['merchantRequestId']
            ?? null;

        $payment = $this->findPaymentByCheckoutId($checkoutRequestId);
        if (! $payment) {
            Log::warning('MpesaController: Payment not found', [
                'checkout_request_id' => $checkoutRequestId,
            ]);

            return response()->json(['status' => 'ignored'], 200);
        }

        if ($resultCode === 0) {
            $metadata = $body['CallbackMetadata']['Item'] ?? [];
            $amount = $receipt = $phone = null;
            foreach ($metadata as $item) {
                match ($item['Name'] ?? '') {
                    'Amount' => $amount = (string) ($item['Value'] ?? ''),
                    'MpesaReceiptNumber' => $receipt = (string) ($item['Value'] ?? ''),
                    'PhoneNumber' => $phone = (string) ($item['Value'] ?? ''),
                    default => null,
                };
            }

            if ($amount !== null
                && function_exists('bccomp')
                && bccomp($amount, (string) $payment->gross_amount, 2) !== 0
            ) {
                Log::error('MpesaController: STK amount mismatch', [
                    'expected' => $payment->gross_amount,
                    'received' => $amount,
                    'payment_id' => $payment->id,
                ]);
            }

            $payment = $this->paymentService->confirm($payment, $receipt, $phone);

            $payment->update([
                'metadata' => array_merge($payment->metadata ?? [], [
                    'mpesa_receipt' => $receipt,
                    'mpesa_checkout_id' => $checkoutRequestId,
                    'mpesa_merchant_id' => $merchantRequestId,
                ]),
            ]);

            Log::info('MpesaController: Payment confirmed', [
                'payment_id' => $payment->id,
                'mpesa_receipt' => $receipt,
            ]);
        } else {
            $reason = $this->mapResultCode($resultCode, $body['ResultDesc'] ?? $body['resultDesc'] ?? '');
            $this->paymentService->fail($payment, $reason, $checkoutRequestId);

            $payment->update([
                'metadata' => array_merge($payment->metadata ?? [], [
                    'mpesa_checkout_id' => $checkoutRequestId,
                    'mpesa_result_code' => $resultCode,
                    'mpesa_result_desc' => $body['ResultDesc'] ?? $body['resultDesc'] ?? '',
                ]),
            ]);

            Log::info('MpesaController: Payment failed', [
                'payment_id' => $payment->id,
                'result_code' => $resultCode,
            ]);
        }

        return response()->json(['status' => 'received'], 200);
    }

    public function stkTimeout(Request $request): JsonResponse
    {
        Log::info('MpesaController: STK timeout', ['body' => $request->all()]);

        $checkoutRequestId = $request->input('Body.stkCallback.CheckoutRequestID')
            ?? $request->input('Body.stkCallback.checkoutRequestID')
            ?? $request->input('Body.stkCallback.checkoutRequestId');
        $payment = $this->findPaymentByCheckoutId($checkoutRequestId);

        if ($payment && ! $payment->isPaid()) {
            $this->paymentService->fail(
                $payment,
                'M-Pesa STK push timed out — no customer response.',
                $checkoutRequestId,
            );
            $payment->update([
                'metadata' => array_merge($payment->metadata ?? [], [
                    'mpesa_timeout' => true,
                    'mpesa_checkout_id' => $checkoutRequestId,
                ]),
            ]);
        }

        return response()->json(['status' => 'received'], 200);
    }

    public function b2cResult(Request $request): JsonResponse
    {
        Log::info('MpesaController: B2C result', ['body' => $request->all()]);

        $result = $request->input('Result');
        if (! $result) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $resultCode = (int) ($result['ResultCode'] ?? -1);
        $conversationId = $result['ConversationID'] ?? null;

        // Payouts do not carry a `provider_reference` column — match on the
        // B2C conversation id stored in metadata, falling back to transaction id.
        $payout = Payout::whereJsonContains('metadata->b2c_conversation_id', $conversationId)
            ->when($result['TransactionID'] ?? null, function ($q, $txId) {
                $q->orWhereJsonContains('metadata->b2c_transaction_id', $txId);
            })
            ->orWhere('payment_reference', $conversationId)
            ->first();

        if (! $payout) {
            Log::warning('MpesaController: Payout not found', [
                'conversation_id' => $conversationId,
            ]);

            return response()->json(['status' => 'ignored'], 200);
        }

        if ($resultCode === 0) {
            $payout->update([
                'status' => Payout::STATUS_PAID,
                'metadata' => array_merge($payout->metadata ?? [], [
                    'b2c_receipt' => $result['TransactionID'] ?? null,
                    'b2c_completed_at' => now()->toIso8601String(),
                ]),
            ]);
            Log::info('MpesaController: B2C payout completed', ['payout_id' => $payout->id]);
        } else {
            $payout->update([
                'status' => Payout::STATUS_REJECTED,
                'metadata' => array_merge($payout->metadata ?? [], [
                    'b2c_result_code' => $resultCode,
                    'b2c_result_desc' => $result['ResultDesc'] ?? '',
                    'b2c_failed_at' => now()->toIso8601String(),
                ]),
            ]);

            // Release PAID_OUT earnings back to AVAILABLE so they can be re-paid
            // (Earnings moved to PAID_OUT when included in this payout)
            DoctorEarning::where('doctor_id', $payout->doctor_id)
                ->where('status', DoctorEarning::STATUS_PAID_OUT)
                ->whereHas('payouts', function ($q) use ($payout) {
                    $q->where('payouts.id', $payout->id);
                })
                ->update(['status' => DoctorEarning::STATUS_AVAILABLE]);

            Log::info('MpesaController: B2C payout failed', ['payout_id' => $payout->id]);
        }

        return response()->json(['status' => 'received'], 200);
    }

    public function stkStatus(Request $request, int $paymentId): JsonResponse
    {
        $user = $request->user();
        $payment = Payment::findOrFail($paymentId);

        $isOwner = $payment->user_id === $user->id
            || ($payment->doctor && $payment->doctor->user_id === $user->id)
            || $user->isSuperAdmin();

        if (! $isOwner) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $checkoutId = $payment->metadata['mpesa_checkout_id'] ?? null;
        if (! $checkoutId) {
            return response()->json([
                'status' => $payment->status,
                'message' => 'No M-Pesa checkout in progress.',
            ]);
        }

        try {
            $result = $this->mpesa->stkQuery($checkoutId);

            if ($result['result_code'] === 0 && ! $payment->isPaid()) {
                $payment = $this->paymentService->confirm(
                    $payment,
                    $result['mpesa_receipt'],
                );
            }

            return response()->json([
                'status' => $payment->status,
                'query_result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('MpesaController: STK query failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => $payment->status,
                'message' => 'Unable to query M-Pesa.',
            ], 503);
        }
    }

    private function findPaymentByCheckoutId(?string $checkoutId): ?Payment
    {
        if (! $checkoutId) {
            return null;
        }

        return Payment::whereJsonContains('metadata->mpesa_checkout_id', $checkoutId)->first();
    }

    private function mapResultCode(int $code, string $description): string
    {
        return match ($code) {
            1 => 'Insufficient funds in M-Pesa account.',
            17 => 'Transaction cancelled by user.',
            999 => 'Request timeout — customer did not respond.',
            default => $description ?: "M-Pesa transaction failed (code {$code}).",
        };
    }
}
