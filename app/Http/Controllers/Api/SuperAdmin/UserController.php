<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\AccountState;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $query = User::with(['roles:id,name,slug', 'roles.permissions:id,name,slug'])->when($request->search, fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))->orderByDesc('created_at');
        $users = $query->paginate($perPage);

        return response()->json(['data' => $users->getCollection()->map(fn ($u) => $this->present($u)), 'meta' => ['total' => $users->total(), 'per_page' => $perPage, 'current_page' => $users->currentPage()]]);
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'account_state' => $user->account_state?->value,
            'is_active' => $user->is_active,
            'roles' => $user->roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug])->values(),
            'effective_permissions' => $user->roles->flatMap(fn ($r) => $r->permissions)->unique('slug')->values()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug]),
        ];
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['roles:id,name,slug', 'doctor']);

        return response()->json(['data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'account_state' => $user->account_state?->value, 'account_state_label' => $user->account_state?->label(), 'is_active' => $user->is_active, 'is_verified' => $user->is_verified, 'created_at' => $user->created_at?->toIso8601String(), 'roles' => $user->roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug])->toArray(), 'doctor' => $user->doctor ? ['id' => $user->doctor->id, 'display_name' => $user->doctor->display_name] : null]]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), ['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:8|confirmed', 'role_ids' => 'required|array', 'role_ids.*' => 'integer|exists:roles,id']);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        $user = User::create(['name' => $request->name, 'email' => $request->email, 'password' => Hash::make($request->password), 'account_state' => AccountState::ACTIVE, 'is_active' => true]);
        $user->roles()->attach($request->role_ids);

        return response()->json(['data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'account_state' => $user->account_state?->value, 'roles' => $user->roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->toArray()]], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id || ($user->hasRole('super-admin') && $user->id === 1)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $v = Validator::make($request->all(), ['name' => 'sometimes|string|max:255', 'email' => 'sometimes|email|unique:users,email,'.$user->id]);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        $user->update($request->only(['name', 'email']));

        return response()->json(['data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]]);
    }

    public function updateRoles(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id || ($user->hasRole('super-admin') && $user->id === 1)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $v = Validator::make($request->all(), ['role_ids' => 'required|array', 'role_ids.*' => 'integer|exists:roles,id']);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        $user->roles()->sync($request->role_ids);

        return response()->json(['data' => ['id' => $user->id, 'name' => $user->name, 'roles' => $user->fresh()->roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug])->values()]]);
    }

    public function changeStatus(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id || ($user->hasRole('super-admin') && $user->id === 1)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $v = Validator::make($request->all(), ['status' => 'required|string|in:active,suspended,disabled,deactivated']);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        $map = ['active' => AccountState::ACTIVE, 'suspended' => AccountState::SUSPENDED, 'disabled' => AccountState::DISABLED, 'deactivated' => AccountState::DEACTIVATED];
        $user->update(['account_state' => $map[$request->status]]);

        return response()->json(['data' => ['id' => $user->id, 'account_state' => $user->fresh()->account_state?->value]]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id || ($user->hasRole('super-admin') && $user->id === 1)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $user->delete();

        return response()->json(['message' => 'User deleted']);
    }
}
