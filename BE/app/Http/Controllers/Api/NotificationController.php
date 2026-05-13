<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Role;
use App\Services\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->when($request->filled('is_read'), fn ($query) => $query->where('is_read', $request->boolean('is_read')))
            ->latest('created_at')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched notifications successfully', $notifications);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_ids' => ['required_without:role', 'array'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
            'role' => ['required_without:user_ids', 'string', 'exists:roles,name'],
            'type' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'reference_type' => ['nullable', 'string', 'max:50'],
            'reference_id' => ['nullable', 'uuid'],
            'data' => ['nullable', 'array'],
        ]);

        $userIds = $request->input('user_ids', []);
        if ($request->filled('role')) {
            $role = Role::where('name', $request->input('role'))->firstOrFail();
            $userIds = $role->users()->pluck('users.id')->all();
        }

        $count = $this->notificationService->createForUsers(
            $userIds,
            $request->input('type'),
            $request->input('title'),
            $request->input('body'),
            $request->input('reference_type'),
            $request->input('reference_id'),
            $request->input('data', [])
        );

        return ApiResponse::success('Notifications created successfully', [
            'created' => $count,
        ], 201);
    }

    public function read(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403, 'This action is unauthorized.');

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return ApiResponse::success('Notification marked as read successfully', $notification->fresh());
    }

    public function readAll(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return ApiResponse::success('Notifications marked as read successfully', [
            'updated' => $count,
        ]);
    }
}
