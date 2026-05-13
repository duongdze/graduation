<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbac\SyncUserRolesRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UserRoleController extends Controller
{
    public function sync(SyncUserRolesRequest $request, User $user): JsonResponse
    {
        $resolvedRoles = collect($request->input('roles'))->map(function (array $item) {
            $role = isset($item['role_id'])
                ? Role::find($item['role_id'])
                : Role::where('name', $item['name'] ?? null)->first();

            if (! $role) {
                abort(422, 'Each role entry must include a valid role_id or name.');
            }

            return [
                'role' => $role,
                'scope_type' => $item['scope_type'] ?? 'system',
                'scope_id' => $item['scope_id'] ?? null,
            ];
        });

        if ($request->user()->is($user) && ! $resolvedRoles->contains(fn ($item) => $item['role']->name === 'super_admin')) {
            return ApiResponse::error('You cannot remove your own super_admin role.', [], 422);
        }

        DB::transaction(function () use ($request, $user, $resolvedRoles) {
            $user->userRoles()->delete();

            foreach ($resolvedRoles as $item) {
                $user->userRoles()->create([
                    'role_id' => $item['role']->id,
                    'scope_type' => $item['scope_type'],
                    'scope_id' => $item['scope_id'],
                    'granted_by' => $request->user()->id,
                ]);
            }
        });

        return ApiResponse::success('User roles synced successfully', [
            'user' => $user->fresh()->load('roles'),
        ]);
    }
}
