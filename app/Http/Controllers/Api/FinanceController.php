<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoctorEarning;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Plan;
use App\Services\EarningsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function __construct(private EarningsService $earningsService) {}

    public function earnings(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->doctor) {
            return response()->json(['error' => 'Doctor profile not found.'], 404);
        }
        $summary = $this->earningsService->summarizeForDoctor($user->doctor->id);
        $earnings = DoctorEarning::where('doctor_id', $user->doctor->id)->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'summary' => ['available' => $summary['available'], 'pending' => $summary['pending'], 'paid_out' => $summary['paid_out'], 'currency' => 'KES'],
            'data' => $earnings->getCollection()->map(fn ($e) => $this->formatEarning($e)),
        ]);
    }

    public function doctorPayouts(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->doctor) {
            return response()->json(['error' => 'Doctor not found'], 404);
        }
        $payouts = Payout::where('doctor_id', $user->doctor->id)->orderByDesc('created_at')->paginate(20);

        return response()->json(['data' => $payouts->getCollection()->map(fn ($p) => $this->formatPayout($p))]);
    }

    public function requestPayout(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->doctor) {
            return response()->json(['error' => 'Doctor not found'], 404);
        }
        try {
            $payout = $this->earningsService->requestPayout($user->doctor->id, $user->id);

            return response()->json(['message' => 'Payout requested', 'data' => $this->formatPayout($payout)], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function marketplace(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->endOfMonth()->toDateString());

        return response()->json(['data' => [
            'gross_volume' => Payment::paid()->whereBetween('confirmed_at', [$from, $to])->sum('gross_amount'),
            'platform_revenue' => Payment::paid()->whereBetween('confirmed_at', [$from, $to])->sum('commission_amount'),
            'pending_payouts' => Payout::whereIn('status', [Payout::STATUS_REQUESTED, Payout::STATUS_PROCESSING])->sum('amount'),
            'appointment_count' => Payment::paid()->whereBetween('confirmed_at', [$from, $to])->count(),
            'currency' => 'KES',
        ]]);
    }

    public function payments(Request $request): JsonResponse
    {
        $payments = Payment::with(['doctor:id,user_id,display_name'])->orderByDesc('created_at')->paginate(20);

        return response()->json(['data' => $payments->getCollection()->map(fn ($p) => $this->formatPayment($p))]);
    }

    public function plans(): JsonResponse
    {
        $plans = Plan::active()->orderBy('sort_order')->get()->map(fn ($p) => [
            'id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'monthly_price' => $p->monthly_price,
            'default_commission_rate' => $p->default_commission_rate,
        ]);

        return response()->json(['data' => $plans]);
    }

    public function adminPayouts(Request $request): JsonResponse
    {
        $payouts = Payout::with(['doctor:id,user_id,display_name'])->orderByDesc('created_at')->paginate(20);

        return response()->json(['data' => $payouts->getCollection()->map(fn ($p) => $this->formatAdminPayout($p))]);
    }

    public function approvePayout(Request $request, int $id): JsonResponse
    {
        $payout = Payout::findOrFail($id);
        $payout->update(['status' => Payout::STATUS_PAID, 'paid_at' => now(), 'approved_by' => $request->user()->id]);
        $payout->earnings()->each(fn (DoctorEarning $e) => $e->markPaidOut());

        return response()->json(['message' => 'Payout approved', 'data' => $this->formatAdminPayout($payout->fresh())]);
    }

    public function rejectPayout(Request $request, int $id): JsonResponse
    {
        $payout = Payout::findOrFail($id);
        if ($payout->isPaid()) {
            return response()->json(['error' => 'Paid payouts cannot be rejected.'], 422);
        }
        $request->validate(['reason' => 'nullable|string|max:500']);
        $payout->update([
            'status' => Payout::STATUS_REJECTED,
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
            'rejection_reason' => $request->input('reason'),
        ]);
        $payout->earnings()->each(fn (DoctorEarning $e) => $e->makeAvailable());

        return response()->json(['message' => 'Payout rejected', 'data' => $this->formatAdminPayout($payout->fresh())]);
    }

    public function cancelPayout(Request $request, int $id): JsonResponse
    {
        $payout = Payout::findOrFail($id);
        if ($payout->isPaid()) {
            return response()->json(['error' => 'Paid payouts cannot be cancelled.'], 422);
        }
        $payout->update(['status' => Payout::STATUS_CANCELLED]);
        $payout->earnings()->each(fn (DoctorEarning $e) => $e->makeAvailable());

        return response()->json(['message' => 'Payout cancelled', 'data' => $this->formatAdminPayout($payout->fresh())]);
    }

    private function formatEarning(DoctorEarning $e): array
    {
        return ['id' => $e->id, 'gross_amount' => $e->gross_amount, 'net_amount' => $e->net_amount, 'status' => $e->status, 'created_at' => $e->created_at?->toIso8601String()];
    }

    private function formatPayout(Payout $p): array
    {
        return ['id' => $p->id, 'reference' => $p->reference, 'amount' => $p->amount, 'status' => $p->status, 'created_at' => $p->created_at?->toIso8601String()];
    }

    private function formatPayment(Payment $p): array
    {
        return ['id' => $p->id, 'reference' => $p->reference, 'status' => $p->status, 'gross_amount' => $p->gross_amount, 'created_at' => $p->created_at?->toIso8601String()];
    }

    private function formatAdminPayout(Payout $p): array
    {
        return ['id' => $p->id, 'reference' => $p->reference, 'amount' => $p->amount, 'status' => $p->status, 'doctor' => ['name' => $p->doctor?->display_name], 'created_at' => $p->created_at?->toIso8601String()];
    }
}
