<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailNotificationService;
use App\Services\VerificationCodeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordResetController extends Controller
{
    public function __construct(
        private readonly VerificationCodeService $codeService,
        private readonly EmailNotificationService $emailService
    ) {}

    /**
     * Send a password reset code to the user's email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->string('email')->lower()->toString())->first();

        if (! $user) {
            // Security: don't reveal whether email exists
            return ApiResponse::success('If the email exists, a reset code has been sent.');
        }

        $result = $this->codeService->generate(
            $user->email,
            'reset_password',
            'email',
            $user->id
        );

        $emailSent = $this->emailService->sendVerificationCode($user->email, $result['code'], 'reset_password');

        $responseData = [
            'message' => 'Reset code sent to your email.',
            'email_sent' => $emailSent,
        ];

        // In local/testing, return code for easy testing
        if (app()->environment('local', 'testing')) {
            $responseData['debug_code'] = $result['code'];
            $responseData['expires_at'] = $result['expires_at'];
        }

        return ApiResponse::success('If the email exists, a reset code has been sent.', $responseData);
    }

    /**
     * Reset password using verification code.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $email = $request->string('email')->lower()->toString();

        // Verify the code (throws ValidationException if invalid)
        $this->codeService->verify($email, 'reset_password', $request->input('code'));

        $user = User::where('email', $email)->firstOrFail();

        $user->update([
            'password' => Hash::make($request->input('password')),
        ]);

        // Revoke all tokens
        $user->tokens()->delete();

        return ApiResponse::success('Password has been reset successfully. Please login again.');
    }
}
