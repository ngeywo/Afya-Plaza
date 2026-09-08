<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoctorSubscription;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * DoctorSubscriptionController — doctor plan subscription.
 *
 * A doctor chooses a commission plan (pricing tier). The chosen plan's
 * commission rules are snapshotted onto the subscription so future pricing
 * changes on the plan do not retroactively affect existing doctors.
 * (Billing integration is intentionally deferred; the subscription is made
 * active immediately and can be moved behind a payment gateway later.)
 */
class DoctorSubscriptionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $doctor = $request->user()->doctor;
        if (! $doctor) {
            return response()->json(['error' => 'Doctor profile not found.'], 404);
        }

        $sub = $doctor->subscription()->active()->with('plan')->first();

        return response()->json(['data' => [
            'subscription' => $sub ? [
                'id' => $sub->id,
                'status' => $sub->status,
                'plan' => $this->planPayload($sub->plan),
                'effective_commission_rate' => $sub->commission_rate,
                'effective_commission_type' => $sub->effective_commission_type,
                'effective_fixed_commission' => $sub->effective_fixed_commission,
                'subscribed_at' => $sub->subscribed_at?->toIso8601String(),
                'expires_at' => $sub->expires_at?->toIso8601String(),
            ] : null,
            'plans' => Plan::active()->doctor()->orderBy('sort_order')->get()->map(fn ($p) => $this->planPayload($p)),
        ]]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $doctor = $request->user()->doctor;
        if (! $doctor) {
            return response()->json(['error' => 'Doctor profile not found.'], 404);
        }

        $validated = $request->validate(['plan_id' => 'required|integer|exists:plans,id']);
        $plan = Plan::active()->doctor()->findOrFail($validated['plan_id']);

        $sub = DB::transaction(function () use ($doctor, $plan) {
            $doctor->subscription()->active()->update([
                'status' => DoctorSubscription::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            return DoctorSubscription::create([
                'doctor_id' => $doctor->id,
                'plan_id' => $plan->id,
                'status' => DoctorSubscription::STATUS_ACTIVE,
                'subscribed_at' => now(),
                'expires_at' => now()->addMonth(),
                'effective_commission_rate' => $plan->default_commission_rate,
                'effective_commission_type' => $plan->commission_type,
                'effective_fixed_commission' => $plan->fixed_commission_amount ?? null,
            ]);
        });

        return response()->json([
            'data' => [
                'id' => $sub->id,
                'status' => $sub->status,
                'plan' => $this->planPayload($plan),
                'effective_commission_rate' => $sub->commission_rate,
            ],
            'message' => "Subscribed to {$plan->name}.",
        ], 201);
    }

    private function planPayload(Plan $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'description' => $p->description,
            'monthly_price' => $p->monthly_price,
            'default_commission_rate' => $p->default_commission_rate,
            'commission_type' => $p->commission_type,
            'fixed_commission_amount' => $p->fixed_commission_amount,
            'is_default' => $p->is_default,
            'sort_order' => $p->sort_order,
        ];
    }
}
