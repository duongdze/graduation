<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingConfig;
use App\Models\HolidayPrice;
use App\Models\Payment;
use App\Models\PlatformFeeConfig;
use App\Models\PriceSlot;
use App\Models\Refund;
use App\Models\SlotLock;
use App\Models\User;
use App\Models\VenueCourt;
use App\Models\VenueFeeLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function create(array $data, User $actor): Booking
    {
        return DB::transaction(function () use ($data, $actor) {
            $date = Carbon::parse($data['booking_date'])->toDateString();
            $start = Carbon::parse($date.' '.$data['start_time']);
            $end = Carbon::parse($date.' '.$data['end_time']);

            if ($end->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages([
                    'booking_date' => ['Cannot create a booking in the past.'],
                ]);
            }

            $court = VenueCourt::query()->whereKey($data['court_id'])->lockForUpdate()->firstOrFail();
            $duration = $start->diffInMinutes($end);
            $startTime = $start->format('H:i:s');
            $endTime = $end->format('H:i:s');
            $this->assertDurationAllowed($court->cluster_id, $duration);

            $source = $data['source'] ?? 'online';
            $customerId = array_key_exists('customer_id', $data)
                ? $data['customer_id']
                : ($source === 'counter' ? null : $actor->id);

            if ($customerId !== null && $customerId !== $actor->id && $source !== 'counter' && ! $actor->hasPermission('booking.manage_all')) {
                throw ValidationException::withMessages([
                    'customer_id' => ['You cannot create a booking for another customer.'],
                ]);
            }

            $this->assertSlotIsFree($court->id, $date, $startTime, $endTime);

            [$basePrice, $totalPrice] = $this->resolvePrice($court, $date, $startTime, $endTime, $data);

            $booking = Booking::create([
                'booking_code' => $this->makeBookingCode(),
                'customer_id' => $customerId,
                'court_id' => $court->id,
                'cluster_id' => $court->cluster_id,
                'booking_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration_minutes' => $duration,
                'base_price' => $basePrice,
                'total_price' => $totalPrice,
                'source' => $source,
                'status' => 'pending_payment',
                'walk_in_name' => $data['walk_in_name'] ?? null,
                'walk_in_phone' => $data['walk_in_phone'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => $actor->id,
            ]);

            SlotLock::create([
                'court_id' => $court->id,
                'booking_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'locked_by' => $actor->id,
                'booking_id' => $booking->id,
                'lock_type' => 'auto',
                'expires_at' => now()->addMinutes(15),
            ]);

            return $booking->load(['customer', 'court', 'cluster']);
        });
    }

    public function createCounterBooking(array $data, User $actor): array
    {
        return DB::transaction(function () use ($data, $actor) {
            $booking = $this->create(array_merge($data, ['source' => 'counter']), $actor);

            $payment = Payment::create([
                'booking_id' => $booking->id,
                'amount' => $booking->total_price,
                'method' => 'cash',
                'status' => 'success',
                'paid_at' => now(),
            ]);

            $booking->update(['status' => 'paid']);

            return [
                'booking' => $booking->fresh(['customer', 'court', 'cluster', 'payments']),
                'payment' => $payment->fresh('booking'),
            ];
        });
    }

    public function cancel(Booking $booking, User $actor, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $reason) {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->customer_id !== $actor->id && $locked->created_by !== $actor->id && ! $actor->hasPermission('booking.manage_all')) {
                throw ValidationException::withMessages([
                    'booking_id' => ['You cannot cancel this booking.'],
                ]);
            }

            if (in_array($locked->status, ['cancelled', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['This booking cannot be cancelled.'],
                ]);
            }

            $this->assertCancellationAllowed($locked, $actor);

            $locked->update([
                'status' => 'cancelled',
                'cancel_reason' => $reason,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            SlotLock::where('booking_id', $locked->id)->delete();
            $this->createRefundRequestsForCancelledBooking($locked);

            return $locked->fresh(['customer', 'court', 'cluster']);
        });
    }

    public function confirm(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending_payment') {
                throw ValidationException::withMessages([
                    'status' => ['Only pending payment bookings can be confirmed.'],
                ]);
            }

            $locked->update(['status' => 'paid']);

            return $locked->fresh(['customer', 'court', 'cluster']);
        });
    }

    public function checkIn(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'paid') {
                throw ValidationException::withMessages([
                    'status' => ['Only paid bookings can be checked in.'],
                ]);
            }

            $locked->update(['status' => 'checked_in']);

            return $locked->fresh(['customer', 'court', 'cluster']);
        });
    }

    public function complete(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['paid', 'checked_in'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only paid or checked-in bookings can be completed.'],
                ]);
            }

            $locked->update(['status' => 'completed']);
            $this->createLedgerIfMissing($locked);

            return $locked->fresh(['customer', 'court', 'cluster', 'feeLedger']);
        });
    }

    private function assertSlotIsFree(string $courtId, string $date, string $startTime, string $endTime): void
    {
        $bookingConflict = Booking::query()
            ->where('court_id', $courtId)
            ->whereDate('booking_date', $date)
            ->whereIn('status', ['pending_payment', 'paid', 'checked_in', 'completed'])
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->lockForUpdate()
            ->exists();

        $lockConflict = SlotLock::query()
            ->where('court_id', $courtId)
            ->whereDate('booking_date', $date)
            ->where('expires_at', '>', now())
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->lockForUpdate()
            ->exists();

        if ($bookingConflict || $lockConflict) {
            throw ValidationException::withMessages([
                'slot' => ['This time slot is no longer available.'],
            ]);
        }
    }

    private function resolvePrice(VenueCourt $court, string $date, string $startTime, string $endTime, array $data): array
    {
        if (isset($data['base_price'], $data['total_price'])) {
            return [(float) $data['base_price'], (float) $data['total_price']];
        }

        $holidayPrice = HolidayPrice::where('cluster_id', $court->cluster_id)
            ->whereDate('holiday_date', $date)
            ->first();

        if ($holidayPrice) {
            return [(float) $holidayPrice->price, (float) $holidayPrice->price];
        }

        $dayOfWeek = Carbon::parse($date)->dayOfWeek;
        $priceSlot = PriceSlot::query()
            ->where('cluster_id', $court->cluster_id)
            ->where('is_active', true)
            ->where('start_time', '<=', $startTime)
            ->where('end_time', '>=', $endTime)
            ->where(function ($query) use ($dayOfWeek) {
                $query->whereNull('apply_to_days')
                    ->orWhereJsonContains('apply_to_days', $dayOfWeek);
            })
            ->orderBy('price')
            ->first();

        if (! $priceSlot) {
            throw ValidationException::withMessages([
                'price' => ['No active price slot matches this booking time.'],
            ]);
        }

        return [(float) $priceSlot->price, (float) $priceSlot->price];
    }

    private function assertDurationAllowed(string $clusterId, int $duration): void
    {
        $config = BookingConfig::where('cluster_id', $clusterId)->first();
        if (! $config) {
            return;
        }

        if ($duration < (int) $config->min_duration_minutes || $duration > (int) $config->max_duration_minutes) {
            throw ValidationException::withMessages([
                'duration_minutes' => ["Booking duration must be between {$config->min_duration_minutes} and {$config->max_duration_minutes} minutes."],
            ]);
        }
    }

    private function assertCancellationAllowed(Booking $booking, User $actor): void
    {
        if ($actor->hasPermission('booking.manage_all')) {
            return;
        }

        $config = BookingConfig::where('cluster_id', $booking->cluster_id)->first();
        $cancelBeforeHours = (int) ($config?->cancel_before_hours ?? 0);

        if ($cancelBeforeHours <= 0) {
            return;
        }

        $startsAt = Carbon::parse($booking->booking_date->toDateString().' '.$booking->start_time);
        if (now()->greaterThan($startsAt->copy()->subHours($cancelBeforeHours))) {
            throw ValidationException::withMessages([
                'booking_id' => ["Bookings must be cancelled at least {$cancelBeforeHours} hours before start time."],
            ]);
        }
    }

    private function createRefundRequestsForCancelledBooking(Booking $booking): void
    {
        $config = BookingConfig::where('cluster_id', $booking->cluster_id)->first();
        $refundPercent = (float) ($config?->refund_percent ?? 100);

        if ($refundPercent <= 0) {
            return;
        }

        $payments = Payment::where('booking_id', $booking->id)
            ->where('status', 'success')
            ->lockForUpdate()
            ->get();

        foreach ($payments as $payment) {
            $existingAmount = Refund::where('payment_id', $payment->id)
                ->whereIn('status', ['pending', 'processing', 'completed'])
                ->sum('amount');

            $targetAmount = round(((float) $payment->amount) * $refundPercent / 100, 2);
            $remainingAmount = round($targetAmount - (float) $existingAmount, 2);

            if ($remainingAmount <= 0) {
                continue;
            }

            Refund::create([
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'amount' => $remainingAmount,
                'reason' => $booking->cancel_reason ?: 'Booking cancelled',
                'status' => 'pending',
            ]);
        }
    }

    private function createLedgerIfMissing(Booking $booking): void
    {
        if (VenueFeeLedger::where('booking_id', $booking->id)->exists()) {
            return;
        }

        $config = PlatformFeeConfig::where('effective_from', '<=', now())
            ->orderByDesc('effective_from')
            ->first();

        $feePercent = (float) ($config?->fee_percent ?? 0);
        $feeAmount = round(((float) $booking->total_price) * $feePercent / 100, 2);

        VenueFeeLedger::create([
            'booking_id' => $booking->id,
            'cluster_id' => $booking->cluster_id,
            'booking_total' => $booking->total_price,
            'fee_percent' => $feePercent,
            'fee_amount' => $feeAmount,
            'status' => 'pending',
        ]);
    }

    private function makeBookingCode(): string
    {
        return 'BK'.now()->format('ymdHis').Str::upper(Str::random(4));
    }
}
