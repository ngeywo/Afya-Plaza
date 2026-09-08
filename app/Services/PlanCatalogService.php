<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Admin management of the facility plan catalog (configurable plans) plus the
 * read model used by the public comparison matrix.
 */
class PlanCatalogService
{
    /**
     * Matrix rows rendered generically by the frontend (no hard-coded table).
     */
    public function comparison(): array
    {
        $plans = Plan::query()
            ->facility()
            ->active()
            ->orderBy('sort_order')
            ->get();

        return [
            'currency' => config('services.subscriptions.currency', 'KES'),
            'plans' => $plans->map(fn (Plan $plan) => [
                ...$plan->comparisonMeta(),
                'max_doctors' => $plan->max_doctors,
                'max_staff' => $plan->max_staff,
                'max_locations' => $plan->max_locations,
                'max_monthly_bookings' => $plan->max_monthly_bookings,
                'max_sms' => $plan->max_sms,
                'max_storage_mb' => $plan->max_storage_mb,
                'max_admin_users' => $plan->max_admin_users,
                'features' => $plan->featureList(),
            ])->values(),
        ];
    }

    public function create(array $data, ?User $actor = null): Plan
    {
        $data['scope'] = Plan::SCOPE_FACILITY;
        $data['version'] = 1;
        $data['features'] = $this->normalizeFeatures($data['features'] ?? []);
        $plan = Plan::create($data);

        $this->snapshotVersion($plan, $actor);
        $this->audit($actor?->id, 'plan.created', $plan, [], $plan->snapshotArray(), 'Created: '.$plan->name);

        return $plan;
    }

    public function update(Plan $plan, array $data, ?User $actor = null): Plan
    {
        if (($data['scope'] ?? null) && $data['scope'] !== Plan::SCOPE_FACILITY) {
            throw new \RuntimeException('Facility plan scope cannot be changed to doctor.');
        }

        unset($data['slug']);
        $before = $plan->snapshotArray();

        if (array_key_exists('features', $data)) {
            $data['features'] = $this->normalizeFeatures($data['features']);
        }

        $plan->fill($data);
        $plan->version = $plan->version + 1;
        $plan->save();

        $this->snapshotVersion($plan, $actor);
        $this->audit($actor?->id, 'plan.updated', $plan, $before, $plan->snapshotArray(), 'Version '.$plan->version);

        return $plan;
    }

    public function setActive(Plan $plan, bool $active, ?User $actor = null, ?string $reason = null): Plan
    {
        $plan->is_active = $active;
        $plan->save();
        $this->audit($actor?->id, $active ? 'plan.activated' : 'plan.deactivated', $plan, ['is_active' => ! $active], ['is_active' => $active], $reason);

        return $plan;
    }

    public function versionHistory(Plan $plan): Collection
    {
        return $plan->versions()->latest('version')->get();
    }

    protected function snapshotVersion(Plan $plan, ?User $actor): void
    {
        PlanVersion::create([
            'plan_id' => $plan->id,
            'version' => $plan->version,
            'snapshot' => $plan->snapshotArray(),
            'created_by' => $actor?->id,
        ]);
    }

    protected function normalizeFeatures(array $features): array
    {
        $normalized = [];
        foreach (Plan::FEATURES as $key => $label) {
            $normalized[$key] = (bool) ($features[$key] ?? false);
        }

        return $normalized;
    }

    protected function audit(?int $actorId, string $action, Plan $plan, array $before, array $after, ?string $reason): void
    {
        AuditLog::record(
            actorId: $actorId,
            action: $action,
            resourceType: Plan::class,
            resourceId: $plan->id,
            resourceLabel: $plan->name.' ('.$plan->slug.')',
            before: $before,
            after: $after,
            reason: $reason,
        );
    }
}
