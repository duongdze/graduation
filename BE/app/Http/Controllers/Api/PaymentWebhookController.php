<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService
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

        $existingPayment = Payment::where('gateway_txn_id', $gatewayTxnId)->first();
        if ($existingPayment && $existingPayment->status !== 'pending') {
            return ApiResponse::success('Transaction already processed.', [
                'payment_id' => $existingPayment->id,
                'status' => $existingPayment->status,
            ]);
        }

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

        if (abs((float) $payment->amount - (float) $request->input('amount')) > 0.01) {
            Log::warning('Payment webhook amount mismatch', [
                'payment_id' => $payment->id,
                'expected' => $payment->amount,
                'received' => $request->input('amount'),
                'gateway' => $gateway,
            ]);

            return ApiResponse::error('Amount mismatch.', [], 422);
        }

        $processedPayment = $this->paymentService->processGatewayResult(
            $payment,
            $gatewayTxnId,
            $request->input('status'),
            array_merge($request->all(), ['gateway' => $gateway])
        );

        return ApiResponse::success('Webhook processed successfully.', [
            'payment_id' => $processedPayment->id,
            'status' => $processedPayment->status,
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
        if (! in_array($payment->status, ['pending', 'failed'], true)) {
            return ApiResponse::error('Only pending or failed payments can be retried.', [], 422);
        }

        if ($payment->status === 'pending') {
            return ApiResponse::success('Payment is still pending. Continue the existing payment attempt.', [
                'payment' => $payment,
                'checkout' => $this->paymentService->checkoutPayload($payment),
            ]);
        }

        $booking = $payment->booking;
        if (! $booking || $booking->status !== 'pending_payment') {
            return ApiResponse::error('The associated booking is no longer valid for payment.', [], 422);
        }

        $existingPending = Payment::where('booking_id', $booking->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($existingPending) {
            return ApiResponse::success('A pending payment attempt already exists.', [
                'payment' => $existingPending,
                'checkout' => $this->paymentService->checkoutPayload($existingPending),
            ]);
        }

        $newPayment = Payment::create([
            'booking_id' => $booking->id,
            'amount' => $payment->amount,
            'method' => $payment->method,
            'status' => 'pending',
        ]);

        return ApiResponse::success('Payment retry created. Complete the payment.', [
            'payment' => $newPayment,
            'checkout' => $this->paymentService->checkoutPayload($newPayment),
        ], 201);
    }
}
