<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Validation\ValidationException;

class PaymentGatewayService
{
    public function checkoutPayload(Payment $payment): array
    {
        $payment->loadMissing('booking');

        if ($payment->method === 'cash') {
            return [
                'provider' => 'cash',
                'payment_id' => $payment->id,
                'method' => 'cash',
                'amount' => (float) $payment->amount,
                'status' => $payment->status,
                'checkout_url' => null,
                'checkout_token' => null,
                'expires_at' => null,
            ];
        }

        $expiresAt = now()->addMinutes(15)->timestamp;
        $token = $this->makeToken($payment, $expiresAt);

        return [
            'provider' => 'local_mvp',
            'payment_id' => $payment->id,
            'booking_id' => $payment->booking_id,
            'method' => $payment->method,
            'amount' => (float) $payment->amount,
            'status' => $payment->status,
            'checkout_url' => url("/api/payments/{$payment->id}/checkout").'?'.http_build_query(['token' => $token]),
            'checkout_token' => $token,
            'expires_at' => now()->setTimestamp($expiresAt)->toIso8601String(),
        ];
    }

    public function assertValidToken(Payment $payment, ?string $token): void
    {
        if (! $token || ! str_contains($token, '|')) {
            throw ValidationException::withMessages([
                'token' => ['Checkout token is invalid.'],
            ]);
        }

        [$expiresAt, $signature] = explode('|', $token, 2);
        if (! ctype_digit($expiresAt) || (int) $expiresAt < now()->timestamp) {
            throw ValidationException::withMessages([
                'token' => ['Checkout token has expired.'],
            ]);
        }

        $expected = $this->signature($payment, (int) $expiresAt);
        if (! hash_equals($expected, $signature)) {
            throw ValidationException::withMessages([
                'token' => ['Checkout token is invalid.'],
            ]);
        }
    }

    private function makeToken(Payment $payment, int $expiresAt): string
    {
        return $expiresAt.'|'.$this->signature($payment, $expiresAt);
    }

    private function signature(Payment $payment, int $expiresAt): string
    {
        $payload = implode('|', [
            $payment->id,
            $payment->booking_id,
            $payment->amount,
            $payment->method,
            $expiresAt,
        ]);

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }
}
