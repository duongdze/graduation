<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with('roles')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched users successfully', $users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['status'] = $data['status'] ?? 'active';

        $user = User::create($data);

        return ApiResponse::success('User created successfully', $user->load('roles'), 201);
    }

    public function show(User $user): JsonResponse
    {
        return ApiResponse::success('Fetched user successfully', $user->load(['roles', 'permissionRevokes.permission']));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return ApiResponse::success('User updated successfully', $user->fresh('roles'));
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return ApiResponse::success('User deleted successfully');
    }

    public function lock(Request $request, User $user): JsonResponse
    {
        $user->update(['status' => 'locked']);
        $user->tokens()->delete();

        $this->auditLogService->log(
            $request->user()->id,
            'user.locked',
            'User',
            $user->id,
            null,
            ['status' => 'locked'],
            'user',
            $request
        );

        return ApiResponse::success('User locked successfully', $user->fresh());
    }

    public function unlock(Request $request, User $user): JsonResponse
    {
        $user->update(['status' => 'active']);

        $this->auditLogService->log(
            $request->user()->id,
            'user.unlocked',
            'User',
            $user->id,
            null,
            ['status' => 'active'],
            'user',
            $request
        );

        return ApiResponse::success('User unlocked successfully', $user->fresh());
    }
}
