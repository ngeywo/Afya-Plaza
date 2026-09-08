<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\AccountState;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AccountStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 23: Administrator account controls.
 *
 * Suspension/reactivation/disablement all require confirmation (reason) and
 * produce an audit record. Destructive actions fail closed.
 */
class AdminAccountController extends Controller
{
    public function __construct(protected AccountStateMachine $stateMachine) {}

    /**
     * GET /api/admin/accounts
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:200',
            'role' => 'nullable|in:patient,doctor,facility-admin,super-admin,platform-admin',
            'state' => 'nullable|in:'.implode(',', array_map(fn ($s) => $s->value, AccountState::cases())),
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 25);

        $query = User::query()
            ->with(['roles:id,slug', 'doctor:id,user_id,verification_status'])
            ->when($validated['search'] ?? null, fn ($q, $s) => $q->where(fn ($sub) => $sub->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->when($validated['state'] ?? null, fn ($q, $st) => $q->where('account_state', $st))
            ->when($validated['role'] ?? null, fn ($q, $r) => $q->whereHas('roles', fn ($sub) => $sub->where('slug', $r)));

        $paginated = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => collect($paginated->items())->map(function (User $u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'roles' => $u->roles->pluck('slug'),
                    'account_state' => $u->accountState()->value,
                    'account_state_label' => $u->accountState()->label(),
                    'is_active' => (bool) $u->is_active,
                    'email_verified_at' => $u->email_verified_at?->toIso8601String(),
                    'phone_verified_at' => $u->phone_verified_at?->toIso8601String(),
                    'provider_verification' => $u->doctor?->verification_status?->value,
                    'last_login_at' => $u->last_login_at?->toIso8601String(),
                    'created_at' => $u->created_at?->toIso8601String(),
                ];
            })->values(),
            'meta' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/admin/accounts/{user}/activity
     */
    public function activity(Request $request, User $user): JsonResponse
    {
        $logs = AuditLog::query()
            ->where('actor_id', $user->id)
            ->orWhere(function ($q) use ($user) {
                $q->where('resource_type', User::class)->where('resource_id', $user->id);
            })
            ->with('actor:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($log) => [
                'action' => $log->action,
                'resource_type' => $log->resource_type,
                'resource_label' => $log->resource_label,
                'before' => $log->before,
                'after' => $log->after,
                'reason' => $log->reason,
                'ip_address' => $log->ip_address,
                'actor' => $log->actor ? ['id' => $log->actor->id, 'name' => $log->actor->name] : null,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $logs]);
    }

    /**
     * POST /api/admin/accounts/{user}/suspend
     */
    public function suspend(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:255']);

        $before = $user->account_state->value;
        $user->update(['account_state' => AccountState::SUSPENDED, 'is_active' => false]);
        $user->tokens()->delete();

        AuditLog::record(
            $request->user()->id,
            'account.suspended',
            User::class,
            $user->id,
            $user->name,
            ['account_state' => $before],
            ['account_state' => AccountState::SUSPENDED->value],
            $validated['reason'],
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json(['message' => 'Account suspended.', 'data' => ['id' => $user->id, 'account_state' => $user->fresh()->account_state->value]]);
    }

    /**
     * POST /api/admin/accounts/{user}/reactivate
     */
    public function reactivate(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:255']);

        $before = $user->account_state->value;
        $target = AccountState::ACTIVE;

        $this->stateMachine->transition($user, $target);
        $user->update(['is_active' => true, 'account_rejection_reason' => null, 'deactivated_at' => null]);

        AuditLog::record(
            $request->user()->id,
            'account.reactivated',
            User::class,
            $user->id,
            $user->name,
            ['account_state' => $before],
            ['account_state' => $user->account_state->value],
            $validated['reason'],
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json(['message' => 'Account reactivated.', 'data' => ['id' => $user->id, 'account_state' => $user->account_state->value]]);
    }

    /**
     * POST /api/admin/accounts/{user}/disable
     */
    public function disable(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:255']);

        $before = $user->account_state->value;
        $user->update(['account_state' => AccountState::DISABLED, 'is_active' => false]);
        $user->tokens()->delete();

        AuditLog::record(
            $request->user()->id,
            'account.disabled',
            User::class,
            $user->id,
            $user->name,
            ['account_state' => $before],
            ['account_state' => AccountState::DISABLED->value],
            $validated['reason'],
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json(['message' => 'Account disabled.', 'data' => ['id' => $user->id, 'account_state' => $user->fresh()->account_state->value]]);
    }
}
