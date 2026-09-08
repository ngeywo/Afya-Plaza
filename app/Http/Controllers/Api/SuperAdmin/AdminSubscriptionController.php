<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\FacilitySubscriptionStatus;
use App\Exceptions\EntitlementException;
use App\Http\Controllers\Controller;
use App\Models\FacilitySubscription;
use App\Services\EntitlementService;
use App\Services\FacilitySubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSubscriptionController extends Controller
{
    public function __construct(
        private FacilitySubscriptionService $subscriptions,
        private EntitlementService $entitlements,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = FacilitySubscription::query()
            ->with(['facility:id,name,slug', 'plan:id,name,slug'])
            ->withCount('events');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $rows = $query->orderByDesc('updated_at')->paginate((int) $request->get('per_page', 25));

        $data = $rows->through(function (FacilitySubscription $sub) {
            $usage = $sub->facility ? $this->entitlements->usage()->usage($sub->facility, $sub->snapshot()) : [];

            return [
                'id' => $sub->id,
                'facility' => $sub->facility ? ['id' => $sub->facility->id, 'name' => $sub->facility->name, 'slug' => $sub->facility->slug] : null,
                'plan' => $sub->plan ? ['id' => $sub->plan->id, 'name' => $sub->plan->name, 'slug' => $sub->plan->slug] : null,
                'plan_version' => $sub->plan_version,
                'status' => $sub->status->value,
                'status_label' => $sub->status->label(),
                'billing_cycle' => $sub->billing_cycle->value,
                'currency' => $sub->currency,
                'monthly_price' => (string) $sub->monthly_price,
                'effective_amount' => (string) $sub->effective_amount,
                'next_billing_date' => $sub->next_billing_date?->toDateTimeString(),
                'activated_at' => $sub->activated_at?->toDateTimeString(),
                'trial_ends_at' => $sub->trial_ends_at?->toDateTimeString(),
                'grace_ends_at' => $sub->grace_ends_at?->toDateTimeString(),
                'expired_at' => $sub->expired_at?->toDateTimeString(),
                'last_amount_paid' => (string) $sub->last_amount_paid,
                'payment_reference' => $sub->payment_reference,
                'operational' => $sub->isOperational(),
                'usage' => $usage,
                'events_count' => $sub->events_count,
            ];
        });

        return response()->json(['data' => $data->values(), 'meta' => ['total' => $rows->total(), 'per_page' => $rows->perPage(), 'current_page' => $rows->currentPage()]]);
    }

    public function show(FacilitySubscription $subscription): JsonResponse
    {
        $subscription->load(['facility', 'plan', 'events' => fn ($q) => $q->latest()->limit(30), 'creator']);

        return response()->json(['data' => [
            'id' => $subscription->id,
            'facility' => $subscription->facility ? ['id' => $subscription->facility->id, 'name' => $subscription->facility->name, 'slug' => $subscription->facility->slug] : null,
            'plan' => $subscription->plan ? ['id' => $subscription->plan->id, 'name' => $subscription->plan->name, 'slug' => $subscription->plan->slug] : null,
            'plan_version' => $subscription->plan_version,
            'status' => $subscription->status->value,
            'status_label' => $subscription->status->label(),
            'billing_cycle' => $subscription->billing_cycle->value,
            'plan_snapshot' => $subscription->snapshot(),
            'next_billing_date' => $subscription->next_billing_date?->toDateTimeString(),
            'trial_ends_at' => $subscription->trial_ends_at?->toDateTimeString(),
            'grace_ends_at' => $subscription->grace_ends_at?->toDateTimeString(),
            'activated_at' => $subscription->activated_at?->toDateTimeString(),
            'started_at' => $subscription->started_at?->toDateTimeString(),
            'cancelled_at' => $subscription->cancelled_at?->toDateTimeString(),
            'cancelled_reason' => $subscription->cancelled_reason,
            'expired_at' => $subscription->expired_at?->toDateTimeString(),
            'last_amount_paid' => (string) $subscription->last_amount_paid,
            'payment_reference' => $subscription->payment_reference,
            'created_by' => $subscription->creator?->name,
            'events' => $subscription->events->map(fn ($e) => [
                'event' => $e->event,
                'reason' => $e->reason,
                'from_status' => $e->from_status,
                'to_status' => $e->to_status,
                'payload' => $e->payload,
                'actor' => $e->actor?->name,
                'created_at' => $e->created_at?->toDateTimeString(),
                'ip_address' => $e->ip_address,
            ]),
        ]]);
    }

    public function verifyPayment(Request $request, FacilitySubscription $subscription): JsonResponse
    {
        $validated = $request->validate([
            'reference' => 'required|string|max:191',
            'payment_method' => 'required|string|in:cash,cheque,bank_transfer,mpesa,other',
            'notes' => 'sometimes|nullable|string|max:1000',
        ]);

        try {
            $subscription = $this->subscriptions->adminVerifyPayment(
                $subscription,
                $validated['reference'],
                $validated['payment_method'],
                $validated['notes'] ?? null,
                $request->user(),
                ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()],
            );
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json([
            'data' => [
                'id' => $subscription->id,
                'status' => $subscription->status->value,
                'status_label' => $subscription->status->label(),
                'payment_reference' => $subscription->payment_reference,
                'payment_method' => $subscription->payment_method,
                'verified_at' => $subscription->verified_at instanceof Carbon ? $subscription->verified_at->toDateTimeString() : $subscription->verified_at,
            ],
            'message' => 'Payment verified by admin. Subscription is now active.',
        ]);
    }

    public function adjust(Request $request, FacilitySubscription $subscription): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:'.implode(',', array_column(FacilitySubscriptionStatus::cases(), 'value')),
            'reason' => 'sometimes|nullable|string|max:1000',
        ]);

        try {
            $subscription = $this->subscriptions->adjust(
                $subscription,
                $validated['status'],
                $validated['reason'] ?? null,
                $request->user(),
                ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()],
            );
        } catch (EntitlementException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => $e->businessCode(), 'context' => $e->context()], $e->httpStatus());
        }

        return response()->json(['data' => ['id' => $subscription->id, 'status' => $subscription->status->value, 'status_label' => $subscription->status->label()], 'message' => 'Subscription adjusted.']);
    }
}
