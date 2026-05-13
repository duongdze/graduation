<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\EmailNotificationService;
use App\Services\VerificationCodeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private readonly VerificationCodeService $codeService,
        private readonly EmailNotificationService $emailService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $payload = DB::transaction(function () use ($request) {
            $user = User::create([
                'full_name' => $request->string('full_name')->toString(),
                'email' => $request->string('email')->lower()->toString(),
                'phone' => $request->input('phone'),
                'password' => Hash::make($request->string('password')->toString()),
                'status' => 'pending_verify',
                'bio' => $request->input('bio'),
                'preferred_sports' => $request->input('preferred_sports'),
                'preferred_position' => $request->input('preferred_position'),
            ]);

            $playerRole = Role::where('name', 'player')->first();
            if ($playerRole) {
                $user->userRoles()->firstOrCreate([
                    'role_id' => $playerRole->id,
                    'scope_type' => 'system',
                    'scope_id' => null,
                ]);
            }

            $verification = $this->codeService->generate($user->email, 'register', 'email', $user->id);
            $emailSent = $this->emailService->sendVerificationCode($user->email, $verification['code'], 'register');

            $data = [
                'user' => $this->userPayload($user),
                'verification' => [
                    'identifier' => $user->email,
                    'channel' => 'email',
                    'expires_at' => $verification['expires_at'],
                    'email_sent' => $emailSent,
                ],
            ];

            if (app()->environment('local', 'testing')) {
                $data['verification']['debug_code'] = $verification['code'];
            }

            return $data;
        });

        return ApiResponse::success('Registered successfully. Please verify your account.', $payload, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $identifier = $request->filled('identifier')
            ? $request->string('identifier')->lower()->toString()
            : $request->string('email')->lower()->toString();

        $user = User::where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return ApiResponse::error('Invalid credentials.', [
                'email' => ['The provided credentials are incorrect.'],
            ], 422);
        }

        if ($user->status === 'locked') {
            return ApiResponse::error('This account is locked.', [], 403);
        }

        if ($user->status === 'pending_verify') {
            return ApiResponse::error('Please verify your account before logging in.', [
                'identifier' => ['Account verification is required.'],
            ], 403);
        }

        $token = $user->createToken($request->input('device_name', 'api-token'))->plainTextToken;

        return ApiResponse::success('Logged in successfully', [
            'user' => $this->userPayload($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success('Logged out successfully');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success('Fetched profile successfully', [
            'user' => $this->userPayload($request->user()),
        ]);
    }

    private function userPayload(User $user): array
    {
        $user->load('roles');

        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'email_verified_at' => $user->email_verified_at,
            'phone_verified_at' => $user->phone_verified_at,
            'status' => $user->status,
            'lock_reason' => $user->lock_reason,
            'avatar_url' => $user->avatar_url,
            'bio' => $user->bio,
            'address' => $user->address,
            'ward' => $user->ward,
            'district' => $user->district,
            'city' => $user->city,
            'preferred_sports' => $user->preferred_sports,
            'preferred_position' => $user->preferred_position,
            'roles' => $user->roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'scope_type' => $role->pivot?->scope_type,
                'scope_id' => $role->pivot?->scope_id,
            ])->values(),
            'permissions' => $user->getAllPermissions()->pluck('code')->values(),
        ];
    }
}
