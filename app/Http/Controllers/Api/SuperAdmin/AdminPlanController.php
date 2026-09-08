<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\PlanCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPlanController extends Controller
{
    public function __construct(private PlanCatalogService $catalog) {}

    public function index(): JsonResponse
    {
        $plans = Plan::query()
            ->facility()
            ->orderBy('sort_order')
            ->withCount('versions')
            ->get()
            ->map(fn (Plan $plan) => [...$plan->comparisonMeta(), 'id' => $plan->id, 'is_active' => $plan->is_active, 'is_default' => $plan->is_default, 'version' => $plan->version, 'version_count' => $plan->versions_count, 'features' => $plan->features ?? (object) []]);

        return response()->json(['data' => $plans]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePlan($request);

        try {
            $plan = $this->catalog->create($data, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $plan->refresh(), 'message' => 'Plan created.'], 201);
    }

    public function update(Request $request, Plan $plan): JsonResponse
    {
        if (! $plan->isFacilityScope()) {
            return response()->json(['error' => 'Legacy doctor plans are managed by the marketplace finance settings.'], 422);
        }

        $data = $this->validatePlan($request, forUpdate: true);

        try {
            $plan = $this->catalog->update($plan, $data, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $plan->refresh(), 'message' => 'Plan updated. A new version was recorded.']);
    }

    public function setActive(Request $request, Plan $plan): JsonResponse
    {
        $validated = $request->validate(['active' => 'required|boolean', 'reason' => 'sometimes|nullable|string|max:1000']);

        try {
            $this->catalog->setActive($plan, (bool) $validated['active'], $request->user(), $validated['reason'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $plan->id, 'is_active' => $plan->fresh()->is_active], 'message' => 'Plan status updated.']);
    }

    public function versions(Plan $plan): JsonResponse
    {
        $versions = $this->catalog->versionHistory($plan)->map(fn ($v) => [
            'id' => $v->id,
            'version' => $v->version,
            'snapshot' => $v->snapshot,
            'created_by' => $v->creator?->name,
            'created_at' => $v->created_at?->toDateTimeString(),
        ]);

        return response()->json(['data' => $versions]);
    }

    private function validatePlan(Request $request, bool $forUpdate = false): array
    {
        $rules = [
            'name' => $forUpdate ? 'sometimes|string|max:191' : 'required|string|max:191',
            'slug' => $forUpdate ? 'sometimes|string|max:191|unique:plans,slug' : 'required|string|max:191|unique:plans,slug',
            'description' => 'sometimes|nullable|string|max:2000',
            'monthly_price' => 'sometimes|numeric|min:0',
            'annual_price' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|max:3',
            'annual_discount_percent' => 'sometimes|numeric|min:0|max:100',
            'trial_days' => 'sometimes|nullable|integer|min:0',
            'support_level' => 'sometimes|string|in:standard,priority,dedicated',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
            'max_doctors' => 'sometimes|nullable|integer|min:0',
            'max_staff' => 'sometimes|nullable|integer|min:0',
            'max_locations' => 'sometimes|nullable|integer|min:0',
            'max_monthly_bookings' => 'sometimes|nullable|integer|min:0',
            'max_sms' => 'sometimes|nullable|integer|min:0',
            'max_storage_mb' => 'sometimes|nullable|integer|min:0',
            'max_admin_users' => 'sometimes|nullable|integer|min:0',
            'features' => 'sometimes|array',
        ];

        // Empty string for an optional limit means "unlimited" for admin UX.
        $validated = $request->validate($rules);
        foreach ([
            'max_doctors', 'max_staff', 'max_locations', 'max_monthly_bookings',
            'max_sms', 'max_storage_mb', 'max_admin_users', 'trial_days',
        ] as $field) {
            if (array_key_exists($field, $validated) && ($validated[$field] === '' || $validated[$field] === null)) {
                $validated[$field] = null;
            }
        }

        return $validated;
    }
}
