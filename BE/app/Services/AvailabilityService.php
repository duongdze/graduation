<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PriceSlot;
use App\Models\SlotLock;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Carbon\Carbon;

class AvailabilityService
{
    public function slotsForCourt(VenueCourt $court, string $date, ?int $durationMinutes = null): array
    {
        $court->load('cluster.bookingConfig');

        $duration = $durationMinutes
            ?? $court->cluster?->bookingConfig?->min_duration_minutes
            ?? 60;

        $dayOfWeek = Carbon::parse($date)->dayOfWeek;
        $priceSlots = PriceSlot::query()
            ->where('cluster_id', $court->cluster_id)
            ->where('is_active', true)
            ->where(function ($query) use ($dayOfWeek) {
                $query->whereNull('apply_to_days')
                    ->orWhereJsonContains('apply_to_days', $dayOfWeek);
            })
            ->orderBy('start_time')
            ->get();

        $busyBookings = Booking::query()
            ->where('court_id', $court->id)
            ->whereDate('booking_date', $date)
            ->whereIn('status', ['pending_payment', 'paid', 'checked_in', 'completed'])
            ->get(['start_time', 'end_time']);

        $busyLocks = SlotLock::query()
            ->where('court_id', $court->id)
            ->whereDate('booking_date', $date)
            ->where('expires_at', '>', now())
            ->get(['start_time', 'end_time']);

        $slots = [];
        foreach ($priceSlots as $priceSlot) {
            $cursor = Carbon::parse($date.' '.$priceSlot->start_time);
            $rangeEnd = Carbon::parse($date.' '.$priceSlot->end_time);

            while ($cursor->copy()->addMinutes($duration)->lte($rangeEnd)) {
                $slotEnd = $cursor->copy()->addMinutes($duration);
                $startTime = $cursor->format('H:i:s');
                $endTime = $slotEnd->format('H:i:s');
                $busy = $this->overlapsAny($startTime, $endTime, $busyBookings)
                    || $this->overlapsAny($startTime, $endTime, $busyLocks);

                $slots[] = [
                    'date' => $date,
                    'court_id' => $court->id,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'duration_minutes' => $duration,
                    'price' => $priceSlot->price,
                    'is_available' => ! $busy,
                    'status' => $busy ? 'busy' : 'available',
                ];

                $cursor->addMinutes($duration);
            }
        }

        return $slots;
    }

    public function slotsForCluster(VenueCluster $cluster, string $date, ?int $durationMinutes = null): array
    {
        return $cluster->courts()
            ->where('status', 'active')
            ->with('courtType')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (VenueCourt $court) => [
                'court' => $court,
                'slots' => $this->slotsForCourt($court, $date, $durationMinutes),
            ])
            ->values()
            ->all();
    }

    private function overlapsAny(string $startTime, string $endTime, iterable $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($range->start_time < $endTime && $range->end_time > $startTime) {
                return true;
            }
        }

        return false;
    }
}
