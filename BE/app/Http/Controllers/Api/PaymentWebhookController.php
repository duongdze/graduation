<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly NotificationService $notificationService
    ) {}

    /**
     * MVP Payment Gateway Callback.
     *
     * NOTE: This is a simplified MVP callback handler.
     * In production, implement signature verification for each gateway.
     */
    public function callback(Request $request, string $gateway): JsonResponse
    {
        $request->validate([
            'gateway_txn_id' => ['required', 'string'],
            'payment_id' => ['sometimes', 'uuid'],
            'booking_id' => ['sometimes', 'uuid'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:success,failed'],
        ]);

        if (! $this->hasValidSignature($request)) {
            return ApiResponse::error('Invalid webhook signature.', [], 401);
        }

        $gatewayTxnId = $request->input('gateway_txn_id');

        // Idempotency check: already processed?
        $existingPayment = Payment::where('gateway_txn_id', $gatewayTxnId)->first();
        if ($existingPayment && $existingPayment->status !== 'pending') {
            return ApiResponse::success('Transaction already processed.', [
                'payment_id' => $existingPayment->id,
                'status' => $existingPayment->status,
            ]);
        }

        // Find the payment record
        $payment = $existingPayment;
        if (! $payment) {
            if ($request->filled('payment_id')) {
                $payment = Payment::findOrFail($request->input('payment_id'));
            } elseif ($request->filled('booking_id')) {
                $payment = Payment::where('booking_id', $request->input('booking_id'))
                    ->where('status', 'pending')
                    ->latest()
                    ->firstOrFail();
            } else {
                return ApiResponse::error('Payment not found. Provide payment_id or booking_id.', [], 404);
            }
        }

        // Validate amount matches
        if (abs((float) $payment->amount - (float) $request->input('amount')) > 0.01) {
            Log::warning('Payment webhook amount mismatch', [
                'payment_id' => $payment->id,
                'expected' => $payment->amount,
                'received' => $request->input('amount'),
                'gateway' => $gateway,
            ]);

            return ApiResponse::error('Amount mismatch.', [], 422);
        }

        DB::transaction(function () use ($request, $payment, $gateway, $gatewayTxnId) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'pending') {
                return; // Already processed (race condition check)
            }

            $payment->update([
                'gateway_txn_id' => $gatewayTxnId,
                'gateway_response' => $request->all(),
                'status' => $request->input('status') === 'success' ? 'success' : 'failed',
                'paid_at' => $request->input('status') === 'success' ? now() : null,
            ]);

            if ($request->input('status') === 'success') {
                $booking = $payment->booking;
                if ($booking && $booking->status === 'pending_payment') {
                    $booking->update(['status' => 'paid']);
                }

                // Notify customer
                if ($booking?->customer_id) {
                    app(NotificationService::class)->createForUser(
                        $booking->customer_id,
                        'payment_success',
                        'Thanh toán thành công',
                        "Đơn đặt sân {$booking->booking_code} đã thanh toán thành công.",
                        'Booking',
                        $booking->id
                    );
                }

                if ($booking?->cluster?->owner_id && $booking->cluster->owner_id !== $booking->customer_id) {
                    app(NotificationService::class)->createForUser(
                        $booking->cluster->owner_id,
                        'booking_paid',
                        'Booking paid',
                        "Booking {$booking->booking_code} has been paid.",
                        'Booking',
                        $booking->id,
                        ['payment_id' => $payment->id, 'gateway' => $gateway]
                    );
                }
            }
        });

        return ApiResponse::success('Webhook processed successfully.', [
            'payment_id' => $payment->id,
            'status' => $payment->fresh()->status,
        ]);
    }

    private function hasValidSignature(Request $request): bool
    {
        $secret = config('services.payment_webhook.secret');
        if (! $secret) {
            return true;
        }

        $signature = $request->header('X-Webhook-Signature');
        if (! $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Retry a failed/pending payment.
     */
    public function retry(Request $request, Payment $payment): JsonResponse
    {
        if (! in_array($payment->status, ['pending', 'failed'])) {
            return ApiResponse::error('Only pending or failed payments can be retried.', [], 422);
        }

        // Check if booking is still valid
        $booking = $payment->booking;
        if (! $booking || in_array($booking->status, ['cancelled', 'expired', 'completed'])) {
            return ApiResponse::error('The associated booking is no longer valid for payment.', [], 422);
        }

        // Create a new payment attempt
        $newPayment = DB::transaction(function () use ($request, $payment, $booking) {
            return Payment::create([
                'booking_id' => $booking->id,
                'amount' => $payment->amount,
                'method' => $payment->method,
                'status' => 'pending',
            ]);
        });

        return ApiResponse::success('Payment retry created. Complete the payment.', [
            'payment' => $newPayment,
            'checkout' => $this->paymentService->checkoutPayload($newPayment),
        ], 201);
    }
}
