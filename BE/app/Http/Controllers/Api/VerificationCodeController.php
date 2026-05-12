<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailNotificationService;
use App\Services\VerificationCodeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationCodeController extends Controller
{
    public function __construct(
        private readonly VerificationCodeService $codeService,
        private readonly EmailNotificationService $emailService
    ) {}

    /**
     * Send a verification code (register, phone_verify).
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => ['required', 'string'], // email or phone
            'type' => ['required', 'string', 'in:register,phone_verify'],
            'channel' => ['sometimes', 'string', 'in:email,sms'],
        ]);

        $identifier = $request->input('identifier');
        $type = $request->input('type');
        $channel = $request->input('channel', 'email');

        $userId = $request->user()?->id;
        if ($type === 'register') {
            $userId = User::where('email', $identifier)->orWhere('phone', $identifier)->value('id');
        }

        $result = $this->codeService->generate($identifier, $type, $channel, $userId);

        $emailSent = $channel === 'email'
            ? $this->emailService->sendVerificationCode($identifier, $result['code'], $type)
            : false;

        $responseData = [
            'message' => 'Verification code sent.',
            'email_sent' => $emailSent,
            'sms_sent' => false,
        ];

        // In local/testing, return code for easy testing
        if (app()->environment('local', 'testing')) {
            $responseData['debug_code'] = $result['code'];
            $responseData['expires_at'] = $result['expires_at'];
        }

        return ApiResponse::success('Verification code sent.', $responseData);
    }

    /**
     * Verify a code.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => ['required', 'string'],
            'type' => ['required', 'string', 'in:register,reset_password,phone_verify'],
            'code' => ['required', 'string', 'size:6'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $this->codeService->verify(
            $request->input('identifier'),
            $request->input('type'),
            $request->input('code')
        );

        $responseData = null;

        // If verifying registration, activate user and return a login token.
        if ($request->input('type') === 'register') {
            $user = User::where('email', $request->input('identifier'))
                ->orWhere('phone', $request->input('identifier'))
                ->first();

            if ($user) {
                $updates = ['status' => 'active'];
                if (filter_var($request->input('identifier'), FILTER_VALIDATE_EMAIL)) {
                    $updates['email_verified_at'] = now();
                } else {
                    $updates['phone_verified_at'] = now();
                }

                $user->update($updates);

                $responseData = [
                    'user' => $user->fresh()->load('roles'),
                    'access_token' => $user->createToken($request->input('device_name', 'api-token'))->plainTextToken,
                    'token_type' => 'Bearer',
                ];
            }
        }

        return ApiResponse::success('Code verified successfully.', $responseData);
    }
}
