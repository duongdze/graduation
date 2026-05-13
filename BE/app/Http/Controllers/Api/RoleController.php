<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbac\StoreRoleRequest;
use App\Http\Requests\Rbac\SyncRolePermissionsRequest;
use App\Http\Requests\Rbac\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $roles = Role::query()
            ->withCount('permissions')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('display_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched roles successfully', $roles);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = Role::create($request->validated());

        return ApiResponse::success('Role created successfully', $role, 201);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        if ($role->is_system && $request->filled('name') && $request->string('name')->toString() !== $role->name) {
            return ApiResponse::error('System role name cannot be changed.', [], 422);
        }

        $role->update($request->validated());

        return ApiResponse::success('Role updated successfully', $role->fresh());
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system) {
            return ApiResponse::error('System roles cannot be deleted.', [], 422);
        }

        $role->delete();

        return ApiResponse::success('Role deleted successfully');
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $permissionIds = collect($request->input('permission_ids', []));

        if ($request->filled('permission_codes')) {
            $permissionIds = $permissionIds->merge(
                Permission::whereIn('code', $request->input('permission_codes'))->pluck('id')
            );
        }

        $role->permissions()->sync($permissionIds->unique()->values()->all());

        return ApiResponse::success('Role permissions synced successfully', [
            'role' => $role->fresh()->load('permissions'),
        ]);
    }
}
