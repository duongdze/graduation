<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbac\StorePermissionRequest;
use App\Http\Requests\Rbac\UpdatePermissionRequest;
use App\Models\Permission;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $permissions = Permission::query()
            ->when($request->filled('group_name'), fn ($query) => $query->where('group_name', $request->string('group_name')->toString()))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('group_name')
            ->orderBy('code')
            ->paginate($request->integer('per_page', 50));

        return ApiResponse::paginated('Fetched permissions successfully', $permissions);
    }

    public function show(Permission $permission): JsonResponse
    {
        return ApiResponse::success('Fetched permission successfully', $permission);
    }

    public function store(StorePermissionRequest $request): JsonResponse
    {
        $permission = Permission::create($request->validated());

        return ApiResponse::success('Permission created successfully', $permission, 201);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {
        $permission->update($request->validated());

        return ApiResponse::success('Permission updated successfully', $permission->fresh());
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return ApiResponse::success('Permission deleted successfully');
    }
}
