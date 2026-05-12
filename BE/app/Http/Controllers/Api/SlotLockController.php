<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SlotLock;
use App\Models\VenueCourt;
use App\Support\ApiResponse;
use App\Traits\AuthorizesVenueScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SlotLockController extends Controller
{
    use AuthorizesVenueScope;

    /**
     * List manual slot locks for a court.
     */
    public function index(Request $request, VenueCourt $venueCourt): JsonResponse
    {
        $this->assertCanManageCourt($request, $venueCourt);

        $locks = SlotLock::where('court_id', $venueCourt->id)
            ->where('lock_type', 'manual')
            ->when($request->filled('booking_date'), fn ($q) => $q->where('booking_date', $request->input('booking_date')))
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->paginate($request->integer('per_page', 30));

        return ApiResponse::paginated('Fetched slot locks successfully', $locks);
    }

    /**
     * Create a manual slot lock.
     */
    public function store(Request $request, VenueCourt $venueCourt): JsonResponse
    {
        $this->assertCanManageCourt($request, $venueCourt);

        $request->validate([
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $lock = DB::transaction(function () use ($request, $venueCourt) {
            $date = $request->input('booking_date');
            $startTime = $request->input('start_time');
            $endTime = $request->input('end_time');

            // Check for overlapping active bookings
            $hasBooking = $venueCourt->bookings()
                ->where('booking_date', $date)
                ->whereNotIn('status', ['cancelled', 'expired'])
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->lockForUpdate()
                ->exists();

            if ($hasBooking) {
                throw ValidationException::withMessages([
                    'start_time' => ['This time slot overlaps with an active booking.'],
                ]);
            }

            // Check for overlapping slot locks
            $hasLock = SlotLock::where('court_id', $venueCourt->id)
                ->where('booking_date', $date)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->lockForUpdate()
                ->exists();

            if ($hasLock) {
                throw ValidationException::withMessages([
                    'start_time' => ['This time slot is already locked.'],
                ]);
            }

            return SlotLock::create([
                'court_id' => $venueCourt->id,
                'booking_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'locked_by' => $request->user()->id,
                'booking_id' => null,
                'lock_type' => 'manual',
                'expires_at' => now()->addYears(10),
            ]);
        });

        return ApiResponse::success('Slot locked successfully', $lock, 201);
    }

    /**
     * Remove a manual slot lock.
     */
    public function destroy(Request $request, VenueCourt $venueCourt, SlotLock $slotLock): JsonResponse
    {
        $this->assertCanManageCourt($request, $venueCourt);

        if ($slotLock->court_id !== $venueCourt->id) {
            return ApiResponse::error('Slot lock does not belong to this court.', [], 404);
        }

        if ($slotLock->lock_type !== 'manual') {
            return ApiResponse::error('Only manual locks can be deleted.', [], 422);
        }

        $slotLock->delete();

        return ApiResponse::success('Slot lock removed successfully');
    }
}
