<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbac\RevokeUserPermissionRequest;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermissionRevoke;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserPermissionRevokeController extends Controller
{
    public function store(RevokeUserPermissionRequest $request, User $user): JsonResponse
    {
        $permission = $request->filled('permission_id')
            ? Permission::findOrFail($request->integer('permission_id'))
            : Permission::where('code', $request->string('permission_code')->toString())->firstOrFail();

        $revoke = UserPermissionRevoke::updateOrCreate(
            [
                'user_id' => $user->id,
                'permission_id' => $permission->id,
                'scope_type' => $request->input('scope_type', 'system'),
                'scope_id' => $request->input('scope_id'),
            ],
            [
                'revoked_by' => $request->user()->id,
                'reason' => $request->input('reason'),
            ]
        );

        return ApiResponse::success('User permission revoked successfully', $revoke->load('permission'), 201);
    }

    public function destroy(Request $request, User $user, Permission $permission): JsonResponse
    {
        $query = UserPermissionRevoke::where('user_id', $user->id)
            ->where('permission_id', $permission->id);

        if ($request->filled('scope_type')) {
            $query->where('scope_type', $request->string('scope_type')->toString());
        }

        if ($request->filled('scope_id')) {
            $query->where('scope_id', $request->string('scope_id')->toString());
        }

        $deleted = $query->delete();

        return ApiResponse::success('User permission revoke removed successfully', [
            'deleted' => $deleted,
        ]);
    }
}
