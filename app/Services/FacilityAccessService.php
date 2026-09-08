<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Central scope-guard for facility-flavored roles.
 *
 * Implements the "golden security rule": every facility-scoped request must
 * answer "is this facility one the actor is authorized to manage?" before any
 * protected code runs. A non-super facility admin can only ever operate on
 * facilities listed in their own `facility_admin` pivot membership, regardless
 * of IDs supplied in the URL or query string.
 *
 * `super-admin` and `platform-admin` are treated as all-scoped: they may
 * address any facility explicitly (or the first, when unqualified). Everyone
 * else is restricted to their own managed facilities.
 */
class FacilityAccessService
{
    /**
     * Roles that have a facility-scoped workspace.
     */
    private const FACILITY_WORKSPACE_ROLES = ['facility-admin', 'facility-staff', 'platform-admin'];

    /**
     * Golden-rule predicate: may $user manage $facility?
     */
    public function canManage(User $user, Facility $facility): bool
    {
        if ($user->isSuperAdmin() || $user->hasRole('platform-admin')) {
            return true;
        }
        if (! $user->hasAnyRole(self::FACILITY_WORKSPACE_ROLES)) {
            return false;
        }

        return $user->facilities()->where('facilities.id', $facility->id)->exists();
    }

    /**
     * All facility IDs the user may manage, for scope-listing queries.
     *
     * Returns null for unrestricted (global) roles — callers treat null as "no
     * facility filter". Returns an array of ids for workspace roles, and an
     * empty array for everyone else (nothing in scope).
     */
    public function managedIds(User $user): ?array
    {
        if ($user->isSuperAdmin() || $user->hasRole('platform-admin')) {
            return null;
        }
        if (! $user->hasAnyRole(self::FACILITY_WORKSPACE_ROLES)) {
            return [];
        }

        return $user->facilities()->pluck('facilities.id')->all();
    }

    /**
     * Resolve the facility a request addresses, enforcing scope.
     *
     * Falls back deterministically:
     *  - super-admin / platform admin: explicit facility_id, else the first.
     *  - all other workspace roles: explicit facility_id IF within their scope,
     *    else their first managed facility.
     *
     * Returns null when the actor has no facility workspace OR the explicit
     * facility_id is outside their scope (so callers can return 403).
     */
    public function resolve(Request $request): ?Facility
    {
        $user = $request->user();
        if (! $user || ! $user->hasAnyRole(self::FACILITY_WORKSPACE_ROLES)) {
            return null;
        }

        $id = $request->query('facility_id') ?? $request->input('facility_id');

        if ($user->isSuperAdmin() || $user->hasRole('platform-admin')) {
            return $id ? Facility::find($id) : Facility::first();
        }

        if ($id) {
            return $user->facilities()->where('facilities.id', $id)->first();
        }

        return $user->facilities()->first();
    }

    /**
     * Resolve an explicitly-addressed facility by ID, enforcing scope.
     *
     * Unlike resolve(), this requires a concrete facility target — no fallback
     * to "first managed facility". Surfaces that create or mutate facility-
     * scoped records must commit to one facility, and a facility outside the
     * actor's scope (or an absent one) resolves to null for callers to 403 on.
     */
    public function resolveExplicit(Request $request, int $facilityId): ?Facility
    {
        $user = $request->user();
        if (! $user || $facilityId <= 0) {
            return null;
        }
        if ($user->isSuperAdmin() || $user->hasRole('platform-admin')) {
            return Facility::find($facilityId);
        }
        if (! $user->hasAnyRole(self::FACILITY_WORKSPACE_ROLES)) {
            return null;
        }

        return $user->facilities()->where('facilities.id', $facilityId)->first();
    }

    /**
     * Combined helper used by workspace controllers: resolve AND verify scope.
     *
     * Equivalent to calling resolve() then canManage(); returning null lets the
     * caller emit a 403 while keeping the reasoning in one place.
     *
     * `$roles` narrows which non-global workspace roles may pass. Surfaces that
     * are admin-only decisions (e.g. billing) pass a stricter list such as
     * ['facility-admin'] to keep facility-staff out. Global operators
     * (super-admin and platform-admin) always pass the narrowing.
     */
    public function authorizedFacility(Request $request, array $roles = self::FACILITY_WORKSPACE_ROLES): ?Facility
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }
        if (! $user->isSuperAdmin() && ! $user->hasRole('platform-admin') && ! $user->hasAnyRole($roles)) {
            return null;
        }
        $facility = $this->resolve($request);
        if (! $facility || ! $this->canManage($user, $facility)) {
            return null;
        }

        return $facility;
    }
}
