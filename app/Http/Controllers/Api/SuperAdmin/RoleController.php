<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::withCount('users')->with('permissions:id,name,slug,group')->orderBy('name')->get();

        return response()->json(['data' => $roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug, 'description' => $r->description, 'users_count' => $r->users_count, 'permissions' => $r->permissions->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug])->toArray()])]);
    }

    public function show(Role $role): JsonResponse
    {
        $role->load(['permissions:id,name,slug,group', 'users:id,name,email']);

        return response()->json(['data' => ['id' => $role->id, 'name' => $role->name, 'slug' => $role->slug, 'description' => $role->description, 'permissions' => $role->permissions->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'group' => $p->group])->toArray(), 'users' => $role->users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->toArray()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), ['name' => 'required|string|max:100|unique:roles,name', 'slug' => 'required|string|max:100|unique:roles,slug', 'description' => 'nullable|string|max:500', 'permission_ids' => 'nullable|array', 'permission_ids.*' => 'integer|exists:permissions,id']);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        $role = Role::create(['name' => $request->name, 'slug' => $request->slug, 'description' => $request->description, 'guard_name' => 'web']);
        if ($request->permission_ids) {
            $role->permissions()->attach($request->permission_ids);
        }

        return response()->json(['data' => ['id' => $role->id, 'name' => $role->name, 'slug' => $role->slug]], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        if (in_array($role->slug, ['super-admin', 'system-owner'])) {
            return response()->json(['error' => 'Cannot modify protected role'], 403);
        }
        $v = Validator::make($request->all(), ['name' => 'sometimes|string|max:100|unique:roles,name,'.$role->id, 'description' => 'nullable|string|max:500']);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        $role->update($request->only(['name', 'description']));

        return response()->json(['data' => ['id' => $role->id, 'name' => $role->name, 'description' => $role->description]]);
    }

    public function updatePermissions(Request $request, Role $role): JsonResponse
    {
        if (in_array($role->slug, ['super-admin', 'system-owner'])) {
            return response()->json(['error' => 'Cannot modify protected role'], 403);
        }
        $v = Validator::make($request->all(), ['permission_ids' => 'required|array', 'permission_ids.*' => 'integer|exists:permissions,id']);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        $role->permissions()->sync($request->permission_ids);

        return response()->json(['data' => ['id' => $role->id, 'permissions' => $role->fresh()->permissions->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug])->toArray()]]);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        if (in_array($role->slug, ['super-admin', 'system-owner'])) {
            return response()->json(['error' => 'Cannot delete protected role'], 403);
        }
        if ($role->users()->exists()) {
            return response()->json(['error' => 'Role has assigned users'], 422);
        }
        $role->delete();

        return response()->json(['message' => 'Role deleted']);
    }
}
