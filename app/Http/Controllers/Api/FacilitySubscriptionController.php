<?php

namespace App\Http\Controllers\Api;

use App\Enums\BillingCycle;
use App\Exceptions\EntitlementException;
use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Plan;
use App\Services\EntitlementService;
use App\Services\FacilityAccessService;
use App\Services\FacilitySubscriptionService;
use App\Services\PlanCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 24: Facility subscription surface for facility-admins.
 *
 * Subscription payment (facility → platform) is a separate ledger from patient
 * appointment payment (patient → facility). The default gateway is "manual":
 * /payment/confirm marks a subscription active; swap in a real gateway behind
 * the same endpoint without changing the API contract.
 */
class FacilitySubscriptionController extends Controller
{
    public function __construct(
        private FacilitySubscriptionService $subscriptions,
        private EntitlementService $entitlements,
        private PlanCatalogService $plans,
        private FacilityAccessService $facilityAccess,
    ) {}

    private function facilityOrNotFound(Request $request): Facility
    {
        $facility = $this->facilityAccess->authorizedFacility($request, ['facility-admin']);

        if (! $facility) {
            abort(404, 'No authorized facility subscription.');
        }

        return $facility;
    }

    private function meta(Request $request): array
    {
        return [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }

    private function format(Facility $facility, Request $request): array
    {
        $subscription = $this->entitlements->subscriptionFor($facility);
        $display = $this->entitlements->displayEntitlement($facility);
        $usage = $this->entitlements->usage()->usage($facility, $display);
        $window = $this->entitlements->billingWindow($facility);

        return [
            'facility' => ['id' => $facility->id, 'name' => $facility->name, 'slug' => $facility->slug],
            'subscription' => $this->subscriptionJson($subscription),
            'plan' => $display,
            'usage' => $usage,
            'features' => $this->entitlements->featureMap($facility),
            'restricted' => $this->entitlements->isRestricted($facility),
            'restrictions' => config('services.subscriptions.expiry_restrictions', []),
            'billing_window' => [
                'from' => $window['from']?->toDateTimeString(),
                'to' => $window['to']?->toDateTimeString(),
            ],
        ];
    }

    private function subscriptionJson(?object $subscription): ?array
    {
        if (! $subscription) {
            return null;
        }

        return [
            'id' => $subscription->id,
            'status' => $subscription->status->value,
            'status_label' => $subscription->status->label(),
            'status_color' => $subscription->status->color(),
            'billing_cycle' => $subscription->billing_cycle->value,
            'currency' => $subscription->currency,
            'monthly_price' => (string) $subscription->monthly_price,
            'annual_price' => (string) $subscription->annual_price,
            'effective_amount' => (string) $subscription->effective_amount,
            'next_billing_date' => $subscription->next_billing_date?->toDateTimeString(),
            'trial_ends_at' => $subscription->trial_ends_at?->toDateTimeString(),
            'grace_ends_at' => $subscription->grace_ends_at?->toDateTimeString(),
            'activated_at' => $subscription->activated_at?->toDateTimeString(),
            'cancelled_at' => $subscription->cancelled_at?->toDateTimeString(),
            'cancel_at_period_end' => $subscription->cancel_at_period_end,
            'expired_at' => $subscription->expired_at?->toDateTimeString(),
            'last_amount_paid' => (string) $subscription->last_amount_paid,
            'payment_reference' => $subscription->payment_reference,
            'operational' => $subscription->isOperational(),
            'plan' => $subscription->snapshot(),
            'recent_events' => $subscription->events()->latest()->limit(10)->get()->map(fn ($e) => [
                'event' => $e->event,
                'reason' => $e->reason,
                'from_status' => $e->from_status,
                'to_status' => $e->to_status,
                'created_at' => $e->created_at?->toDateTimeString(),
            ]),
        ];
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->format($this->facilityOrNotFound($request), $request)]);
    }

    public function plans(): JsonResponse
    {
        return response()->json(['data' => $this->plans->comparison()]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $facility = $this->facilityOrNotFound($request);

        $validated = $request->validate([
            'plan_slug' => 'required|string|exists:plans,slug',
            'billing_cycle' => 'sometimes|nullable|string|in:'.implode(',', BillingCycle::allowed()),
        ]);

        try {
            $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();
            $subscription = $this->subscriptions->checkout(
                $facility,
                $plan,
                $validated['billing_cycle'] ?? BillingCycle::MONTHLY->value,
                $request->user(),
                $this->meta($request),
            );
        } catch (EntitlementException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => $e->businessCode(), 'context' => $e->context()], $e->httpStatus());
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $this->format($facility, $request), 'message' => 'Checkout complete.'], 201);
    }

    public function confirmPayment(Request $request): JsonResponse
    {
        $facility = $this->facilityOrNotFound($request);
        $subscription = $this->entitlements->subscriptionFor($facility);

        if (! $subscription) {
            return response()->json(['error' => 'No subscription to confirm. Start a checkout first.'], 404);
        }

        $validated = $request->validate(['reference' => 'required|string|max:191']);

        try {
            $subscription = $this->subscriptions->confirmPayment(
                $subscription,
                $validated['reference'],
                $request->user(),
                $this->meta($request),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $this->subscriptionJson($subscription), 'message' => 'Payment confirmed. Subscription is active.'], 200);
    }

    public function paymentFailure(Request $request): JsonResponse
    {
        $facility = $this->facilityOrNotFound($request);
        $subscription = $this->entitlements->subscriptionFor($facility);

        if (! $subscription) {
            return response()->json(['error' => 'No subscription on record.'], 404);
        }

        $validated = $request->validate(['reason' => 'sometimes|nullable|string|max:1000']);

        $subscription = $this->subscriptions->recordPaymentFailure(
            $subscription,
            $validated['reason'] ?? 'Payment gateway declined the transaction.',
            $request->user(),
            $this->meta($request),
        );

        return response()->json(['data' => $this->subscriptionJson($subscription), 'message' => 'Payment failure recorded.'], 200);
    }

    public function changePlan(Request $request, string $direction): JsonResponse
    {
        $facility = $this->facilityOrNotFound($request);
        $subscription = $this->entitlements->subscriptionFor($facility);

        if (! $subscription) {
            return response()->json(['error' => 'This facility has no subscription. Start a checkout first.'], 404);
        }

        $validated = $request->validate([
            'plan_slug' => 'required|string|exists:plans,slug',
            'billing_cycle' => 'sometimes|nullable|string|in:'.implode(',', BillingCycle::allowed()),
        ]);

        try {
            $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();
            $subscription = $this->subscriptions->changePlan(
                $subscription,
                $plan,
                $validated['billing_cycle'] ?? null,
                $request->user(),
                $this->meta($request),
            );
        } catch (EntitlementException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => $e->businessCode(), 'context' => $e->context()], $e->httpStatus());
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        $verb = $direction === 'upgrade' ? 'upgraded' : 'downgraded';

        return response()->json(['data' => $this->subscriptionJson($subscription), 'message' => "Plan {$verb}."], 200);
    }

    public function upgrade(Request $request): JsonResponse
    {
        return $this->changePlan($request, 'upgrade');
    }

    public function downgrade(Request $request): JsonResponse
    {
        return $this->changePlan($request, 'downgrade');
    }

    public function cancel(Request $request): JsonResponse
    {
        $facility = $this->facilityOrNotFound($request);
        $subscription = $this->entitlements->subscriptionFor($facility);

        if (! $subscription) {
            return response()->json(['error' => 'This facility has no subscription to cancel.'], 404);
        }

        $validated = $request->validate(['reason' => 'required|string|max:1000']);

        try {
            $subscription = $this->subscriptions->cancel($subscription, $validated['reason'], $request->user(), $this->meta($request));
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $this->subscriptionJson($subscription), 'message' => 'Subscription cancelled.'], 200);
    }
}
