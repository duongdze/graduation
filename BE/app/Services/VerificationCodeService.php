<?php

namespace App\Services;

use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VerificationCodeService
{
    /**
     * Generate and store a verification code.
     *
     * @param  string  $identifier  Email or phone
     * @param  string  $type        register|reset_password|phone_verify
     * @param  string  $channel     email|sms
     * @return array{code: string, expires_at: string}
     */
    public function generate(string $identifier, string $type, string $channel = 'email', ?string $userId = null): array
    {
        // Invalidate any existing unused codes for same identifier + type
        VerificationCode::where('identifier', $identifier)
            ->where('type', $type)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        $plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(5);

        VerificationCode::create([
            'user_id' => $userId,
            'identifier' => $identifier,
            'type' => $type,
            'code' => Hash::make($plainCode),
            'channel' => $channel,
            'attempt_count' => 0,
            'max_attempts' => 5,
            'is_used' => false,
            'expires_at' => $expiresAt,
        ]);

        return [
            'code' => $plainCode,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Verify a code against stored record.
     *
     * @throws ValidationException
     */
    public function verify(string $identifier, string $type, string $inputCode): VerificationCode
    {
        $record = VerificationCode::where('identifier', $identifier)
            ->where('type', $type)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'code' => ['Verification code is invalid or expired.'],
            ]);
        }

        if ($record->attempt_count >= $record->max_attempts) {
            $record->update(['is_used' => true]);
            throw ValidationException::withMessages([
                'code' => ['Too many failed attempts. Please request a new code.'],
            ]);
        }

        if (! Hash::check($inputCode, $record->code)) {
            $record->increment('attempt_count');
            throw ValidationException::withMessages([
                'code' => ['Verification code is incorrect.'],
            ]);
        }

        $record->update(['is_used' => true]);

        return $record;
    }
}
