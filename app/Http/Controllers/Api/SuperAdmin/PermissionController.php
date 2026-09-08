<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::orderBy('group')->orderBy('name')->get();
        $grouped = $permissions->groupBy('group')->map(fn ($group) => $group->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'description' => $p->description])->values())->toArray();

        return response()->json(['data' => ['groups' => $grouped, 'all' => $permissions->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'group' => $p->group])->toArray()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:120|unique:permissions,slug',
            'group' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }

        $permission = Permission::create(array_merge($v->validated(), ['guard_name' => 'web']));

        return response()->json(['data' => ['id' => $permission->id, 'name' => $permission->name, 'slug' => $permission->slug, 'group' => $permission->group, 'description' => $permission->description]], 201);
    }

    public function update(Request $request, Permission $permission): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'slug' => 'sometimes|string|max:120|unique:permissions,slug,'.$permission->id,
            'group' => 'sometimes|string|max:100',
            'description' => 'sometimes|nullable|string|max:500',
        ]);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }

        $permission->update($v->validated());

        return response()->json(['data' => ['id' => $permission->id, 'name' => $permission->name, 'slug' => $permission->slug, 'group' => $permission->group, 'description' => $permission->description]]);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        if ($permission->roles()->exists()) {
            return response()->json(['error' => 'Permission is assigned to one or more roles.'], 422);
        }

        $permission->delete();

        return response()->json(['message' => 'Permission deleted.']);
    }
}
