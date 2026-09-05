<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoctorEarning;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Plan;
use App\Services\EarningsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FinanceController — Phase 12
 *
 * Handles doctor earnings and super-admin marketplace finance views.
 */
class FinanceController extends Controller
{
    public function __construct(private EarningsService $earningsService) {}

    /**
     * GET /api/finance/earnings
     *
     * Returns the authenticated doctor's earnings summary and transaction list.
     */
    public function earnings(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->doctor) {
            return response()->json(['error' => 'Doctor profile not found.'], 404);
        }

        $doctorId = $user->doctor->id;
        $summary = $this->earningsService->summarizeForDoctor($doctorId);

        $perPage = (int) $request->input('per_page', 20);
        $status = $request->input('status');
        $from = $request->input('from');
        $to = $request->input('to');

        $query = DoctorEarning::with(['appointment:id,appointment_number,appointment_date,start_time,user_id'])
            ->where('doctor_id', $doctorId)
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to))
            ->orderByDesc('created_at');

        $earnings = $query->paginate($perPage);

        return response()->json([
            'summary' => $this->formatSummary($summary),
            'data' => $earnings->getCollection()->map(fn($e) => $this->formatEarning($e)),
            'meta' => [
                'total' => $earnings->total(),
                'per_page' => $earnings->perPage(),
                'current_page' => $earnings->currentPage(),
                'last_page' => $earnings->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/finance/earnings/{id} — single earning. IDOR-protected.
     */
    public function earningDetail(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $earning = DoctorEarning::with(['appointment', 'payment', 'doctor'])->findOrFail($id);

        if ($earning->doctor_id !== $user->doctor?->id && !$user->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        return response()->json(['data' => $this->formatEarning($earning, true)]);
    }

    /**
     * POST /api/finance/payout-request
     *
     * Moves all available earnings into a new payout request.
     * Idempotent: returns existing pending/processing payout if one exists.
     */
    public function requestPayout(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->doctor) {
            return response()->json(['error' => 'Doctor profile not found.'], 404);
        }

        $doctorId = $user->doctor->id;

        $existing = Payout::where('doctor_id', $doctorId)
            ->whereIn('status', [Payout::STATUS_REQUESTED, Payout::STATUS_PROCESSING])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'A payout is already pending or processing.',
                'data' => $this->formatPayout($existing),
            ], 422);
        }

        try {
            $payout = $this->earningsService->requestPayout($doctorId, $user->id);
            return response()->json([
                'message' => 'Payout requested successfully.',
                'data' => $this->formatPayout($payout),
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
    /**
     * GET /api/finance/marketplace — platform-wide financial overview for Super Admin.
     */
    public function marketplace(Request $request): JsonResponse
    {
        $from = $request->input('from', Carbon::today()->subMonth()->toDateString());
        $to = $request->input('to', Carbon::today()->toDateString());

        $confirmed = Payment::where('status', Payment::STATUS_PAID)
            ->whereBetween('confirmed_at', [$from, $to . ' 23:59:59']);

        $grossVolume = (clone $confirmed)->sum('gross_amount');
        $platformRevenue = (clone $confirmed)->sum('commission_amount');
        $netVolume = (clone $confirmed)->sum('net_amount');
        $appointmentCount = (clone $confirmed)->count();

        $pendingPayouts = Payout::whereIn('status', [
            Payout::STATUS_REQUESTED, Payout::STATUS_PROCESSING])->sum('amount');

        $completedPayouts = Payout::where('status', Payout::STATUS_PAID)
            ->whereBetween('paid_at', [$from, $to . ' 23:59:59'])->sum('amount');

        $refundedPayments = Payment::where('status', Payment::STATUS_REFUNDED)
            ->whereBetween('refunded_at', [$from, $to . ' 23:59:59']);
        $refundTotal = (clone $refundedPayments)->sum('refunded_amount');
        $refundCount = (clone $refundedPayments)->count();

        $planBreakdown = Plan::active()
            ->withCount(['subscriptions' => fn($q) => $q->where('status', 'active')])
            ->get()->map(fn($p) => [
                'name' => $p->name,
                'slug' => $p->slug,
                'monthly_price' => $p->monthly_price,
                'default_commission_rate' => $p->default_commission_rate,
                'active_subscriptions' => $p->subscriptions_count,
            ]);

        return response()->json(['data' => [
            'period' => ['from' => $from, 'to' => $to],
            'gross_volume' => $grossVolume,
            'platform_revenue' => $platformRevenue,
            'net_volume' => $netVolume,
            'appointment_count' => $appointmentCount,
            'pending_payouts' => $pendingPayouts,
            'completed_payouts' => $completedPayouts,
            'refund_total' => $refundTotal,
            'refund_count' => $refundCount,
            'currency' => 'KES',
            'plan_breakdown' => $planBreakdown,
        ]]);
    }

    /**
     * GET /api/finance/payments — paginated payment list (Super Admin).
     */
    public function payments(Request $request): JsonResponse
    {
        $from = $request->input('from', Carbon::today()->subMonth()->toDateString());
        $to = $request->input('to', Carbon::today()->toDateString());
        $perPage = (int) $request->input('per_page', 25);

        $query = Payment::with(['appointment:id,appointment_number,appointment_date',
                'doctor:id,display_name', 'payer:id,name'])
            ->when($request->input('status'), fn($q, $s) => $q->where('status', $s))
            ->when($request->input('doctor_id'), fn($q, $d) => $q->where('doctor_id', $d))
            ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
            ->orderByDesc('created_at');

        $payments = $query->paginate($perPage);

        return response()->json([
            'data' => $payments->getCollection()->map(fn($p) => $this->formatPayment($p)),
            'meta' => [
                'total' => $payments->total(),
                'per_page' => $payments->perPage(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/finance/plans — list all plans (Super Admin).
     */
    public function plans(): JsonResponse
    {
        $plans = Plan::active()->orderBy('sort_order')->get()->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'description' => $p->description,
            'monthly_price' => $p->monthly_price,
            'default_commission_rate' => $p->default_commission_rate,
            'commission_type' => $p->commission_type,
            'fixed_commission_amount' => $p->fixed_commission_amount,
            'is_default' => $p->is_default,
            'active_subscriptions' => $p->subscriptions()->where('status', 'active')->count(),
        ]);

        return response()->json(['data' => $plans]);
    }
    // ─── Formatters ─────────────────────────────────────────────────────────────

    private function formatSummary(array $s): array
    {
        return [
            'available' => number_format((float) ($s['available'] ?? 0), 2),
            'pending' => number_format((float) ($s['pending'] ?? 0), 2),
            'paid_out' => number_format((float) ($s['paid_out'] ?? 0), 2),
            'reversed' => number_format((float) ($s['reversed'] ?? 0), 2),
            'currency' => $s['currency'] ?? 'KES',
        ];
    }

    private function formatEarning(DoctorEarning $e, bool $detailed = false): array
    {
        $base = [
            'id' => $e->id,
            'status' => $e->status,
            'gross_amount' => $e->gross_amount,
            'net_amount' => $e->net_amount,
            'commission_amount' => $e->commission_amount,
            'currency' => $e->currency,
            'plan_slug' => $e->plan_slug,
            'commission_rate_snapshot' => $e->commission_rate_snapshot,
            'description' => $e->description,
            'available_at' => $e->available_at?->toIso8601String(),
            'paid_out_at' => $e->paid_out_at?->toIso8601String(),
            'reversed_at' => $e->reversed_at?->toIso8601String(),
            'reversal_reason' => $e->reversal_reason,
            'created_at' => $e->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $base['appointment'] = $e->appointment ? [
                'id' => $e->appointment->id,
                'appointment_number' => $e->appointment->appointment_number,
                'appointment_date' => $e->appointment->appointment_date instanceof Carbon
                    ? $e->appointment->appointment_date->format('Y-m-d')
                    : $e->appointment->appointment_date,
                'start_time' => substr($e->appointment->start_time ?? '', 0, 5),
            ] : null;
            $base['payment'] = $e->payment ? [
                'id' => $e->payment->id,
                'reference' => $e->payment->reference,
                'provider' => $e->payment->provider,
                'confirmed_at' => $e->payment->confirmed_at?->toIso8601String(),
            ] : null;
        }

        return $base;
    }

    private function formatPayout(Payout $p): array
    {
        return [
            'id' => $p->id,
            'reference' => $p->reference,
            'amount' => $p->amount,
            'currency' => $p->currency,
            'status' => $p->status,
            'notes' => $p->notes,
            'payment_reference' => $p->payment_reference,
            'payment_method' => $p->payment_method,
            'requested_at' => $p->requested_at?->toIso8601String(),
            'approved_at' => $p->approved_at?->toIso8601String(),
            'paid_at' => $p->paid_at?->toIso8601String(),
            'rejected_at' => $p->rejected_at?->toIso8601String(),
            'rejection_reason' => $p->rejection_reason,
            'created_at' => $p->created_at?->toIso8601String(),
        ];
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
            'commission_rule_source' => $p->commission_rule_source,
            'confirmed_at' => $p->confirmed_at?->toIso8601String(),
            'appointment' => $p->appointment ? [
                'id' => $p->appointment->id,
                'appointment_number' => $p->appointment->appointment_number,
                'appointment_date' => $p->appointment->appointment_date instanceof Carbon
                    ? $p->appointment->appointment_date->format('Y-m-d')
                    : $p->appointment->appointment_date,
            ] : null,
            'doctor' => $p->doctor ? ['id' => $p->doctor->id, 'name' => $p->doctor->display_name] : null,
            'payer' => $p->payer ? ['id' => $p->payer->id, 'name' => $p->payer->name] : null,
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }
}