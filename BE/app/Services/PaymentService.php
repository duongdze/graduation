<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingConfig;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly PaymentGatewayService $paymentGatewayService
    ) {}

    public function createPayment(array $data, User $actor): Payment
    {
        return DB::transaction(function () use ($data, $actor) {
            $booking = Booking::query()->whereKey($data['booking_id'])->lockForUpdate()->firstOrFail();

            $booking->loadMissing('cluster');
            $venueScopeIds = $actor->userRoles()
                ->where('scope_type', 'venue')
                ->whereNotNull('scope_id')
                ->pluck('scope_id')
                ->all();

            if (
                $booking->customer_id !== $actor->id
                && $booking->created_by !== $actor->id
                && $booking->cluster?->owner_id !== $actor->id
                && ! in_array($booking->cluster_id, $venueScopeIds, true)
                && ! $actor->hasPermission('payment.manage_all')
                && ! $actor->hasPermission('booking.manage_all')
            ) {
                throw ValidationException::withMessages([
                    'booking_id' => ['You cannot create a payment for this booking.'],
                ]);
            }

            if (! in_array($booking->status, ['pending_payment', 'paid'], true)) {
                throw ValidationException::withMessages([
                    'booking_id' => ['This booking cannot accept a payment.'],
                ]);
            }

            $payment = Payment::create([
                'booking_id' => $booking->id,
                'amount' => $data['amount'],
                'method' => $data['method'],
                'gateway_txn_id' => $data['gateway_txn_id'] ?? null,
                'gateway_response' => $data['gateway_response'] ?? null,
                'status' => 'pending',
            ]);

            return $payment->load('booking');
        });
    }

    public function markPaid(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($lockedPayment->booking_id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status === 'refunded') {
                throw ValidationException::withMessages([
                    'status' => ['Refunded payments cannot be marked as paid.'],
                ]);
            }

            if (! in_array($booking->status, ['pending_payment', 'paid'], true)) {
                throw ValidationException::withMessages([
                    'booking_id' => ['Booking is not in a payable state.'],
                ]);
            }

            $lockedPayment->update([
                'status' => 'success',
                'paid_at' => now(),
            ]);

            if ($booking->status === 'pending_payment') {
                $booking->update(['status' => 'paid']);
            }

            $this->notifyPaymentSuccess($lockedPayment, $booking);

            return $lockedPayment->fresh('booking');
        });
    }

    public function markFailed(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status === 'success') {
                throw ValidationException::withMessages([
                    'status' => ['Successful payments cannot be marked as failed.'],
                ]);
            }

            $lockedPayment->update(['status' => 'failed']);

            return $lockedPayment->fresh('booking');
        });
    }

    public function createRefund(array $data): Refund
    {
        return DB::transaction(function () use ($data) {
            $payment = Payment::query()->whereKey($data['payment_id'])->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($data['booking_id'])->lockForUpdate()->firstOrFail();

            if ($payment->booking_id !== $booking->id) {
                throw ValidationException::withMessages([
                    'payment_id' => ['Payment does not belong to this booking.'],
                ]);
            }

            if ($payment->status !== 'success') {
                throw ValidationException::withMessages([
                    'payment_id' => ['Only successful payments can be refunded.'],
                ]);
            }

            $amount = $data['amount'] ?? $this->calculateRefundAmount($booking, $payment);

            $existingAmount = Refund::where('payment_id', $payment->id)
                ->whereIn('status', ['pending', 'processing', 'completed'])
                ->sum('amount');

            if (((float) $existingAmount + (float) $amount) > (float) $payment->amount) {
                throw ValidationException::withMessages([
                    'amount' => ['Refund amount exceeds remaining refundable payment amount.'],
                ]);
            }

            return Refund::create([
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'reason' => $data['reason'] ?? null,
                'status' => 'pending',
            ])->load(['booking', 'payment']);
        });
    }

    public function approveRefund(Refund $refund, User $actor): Refund
    {
        return DB::transaction(function () use ($refund, $actor) {
            $lockedRefund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($lockedRefund->payment_id)->lockForUpdate()->firstOrFail();

            if ($lockedRefund->status === 'completed') {
                return $lockedRefund->load(['booking', 'payment']);
            }

            $lockedRefund->update([
                'status' => 'completed',
                'processed_by' => $actor->id,
                'processed_at' => now(),
            ]);

            $completedAmount = Refund::where('payment_id', $payment->id)
                ->where('status', 'completed')
                ->sum('amount');

            if ((float) $completedAmount >= (float) $payment->amount) {
                $payment->update(['status' => 'refunded']);
            }

            return $lockedRefund->fresh(['booking', 'payment']);
        });
    }

    public function rejectRefund(Refund $refund, User $actor): array
    {
        return DB::transaction(function () use ($refund, $actor) {
            $lockedRefund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($lockedRefund->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => ['Only pending refunds can be rejected.'],
                ]);
            }

            $payload = $lockedRefund->toArray();
            $payload['processed_by'] = $actor->id;
            $payload['processed_at'] = now()->toISOString();
            $lockedRefund->delete();

            return $payload;
        });
    }

    public function checkoutPayload(Payment $payment): array
    {
        return $this->paymentGatewayService->checkoutPayload($payment);
    }

    public function processGatewayResult(Payment $payment, string $gatewayTxnId, string $status, array $gatewayResponse = []): Payment
    {
        return DB::transaction(function () use ($payment, $gatewayTxnId, $status, $gatewayResponse) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($lockedPayment->booking_id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== 'pending') {
                return $lockedPayment->fresh('booking');
            }

            if ($status === 'success' && ! in_array($booking->status, ['pending_payment', 'paid'], true)) {
                throw ValidationException::withMessages([
                    'booking_id' => ['Booking is not in a payable state.'],
                ]);
            }

            $lockedPayment->update([
                'gateway_txn_id' => $gatewayTxnId,
                'gateway_response' => $gatewayResponse,
                'status' => $status === 'success' ? 'success' : 'failed',
                'paid_at' => $status === 'success' ? now() : null,
            ]);

            if ($status === 'success') {
                if ($booking->status === 'pending_payment') {
                    $booking->update(['status' => 'paid']);
                }

                $this->notifyPaymentSuccess($lockedPayment, $booking);
            }

            return $lockedPayment->fresh('booking');
        });
    }

    private function calculateRefundAmount(Booking $booking, Payment $payment): float
    {
        $config = BookingConfig::where('cluster_id', $booking->cluster_id)->first();
        $refundPercent = (float) ($config?->refund_percent ?? 100);

        return round(((float) $payment->amount) * $refundPercent / 100, 2);
    }

    private function notifyPaymentSuccess(Payment $payment, Booking $booking): void
    {
        if ($booking->customer_id) {
            $this->notificationService->createForUser(
                $booking->customer_id,
                'payment_success',
                'Payment successful',
                "Booking {$booking->booking_code} has been paid.",
                'Booking',
                $booking->id
            );
        }

        $booking->loadMissing('cluster');
        if ($booking->cluster?->owner_id && $booking->cluster->owner_id !== $booking->customer_id) {
            $this->notificationService->createForUser(
                $booking->cluster->owner_id,
                'booking_paid',
                'Booking paid',
                "Booking {$booking->booking_code} has been paid.",
                'Booking',
                $booking->id,
                ['payment_id' => $payment->id]
            );
        }
    }
}
