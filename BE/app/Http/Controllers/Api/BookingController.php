<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CancelBookingRequest;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Requests\Booking\StoreCounterBookingRequest;
use App\Models\Booking;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Services\BookingService;
use App\Support\ApiResponse;
use App\Traits\AuthorizesVenueScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    use AuthorizesVenueScope;

    public function __construct(private readonly BookingService $bookingService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $bookings = Booking::query()
            ->with(['customer', 'court.courtType', 'cluster', 'payments'])
            ->when(! $this->isPlatformAdmin($user), function ($query) use ($user) {
                $clusterIds = $this->bookableClusterIdsForUser($user);
                $query->where(function ($scopeQuery) use ($user, $clusterIds) {
                    $scopeQuery->where('customer_id', $user->id)
                        ->orWhere('created_by', $user->id);

                    if ($clusterIds !== []) {
                        $scopeQuery->orWhereIn('cluster_id', $clusterIds);
                    }
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('court_id'), fn ($query) => $query->where('court_id', $request->string('court_id')->toString()))
            ->when($request->filled('cluster_id'), fn ($query) => $query->where('cluster_id', $request->string('cluster_id')->toString()))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->string('customer_id')->toString()))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('booking_date', $request->date('date')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched bookings successfully', $bookings);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        if ($request->input('source') === 'counter') {
            $this->assertCanManageCourt($request, VenueCourt::findOrFail($request->validated('court_id')));
        }

        $booking = $this->bookingService->create($request->validated(), $request->user());

        return ApiResponse::success('Booking created successfully', $booking, 201);
    }

    public function storeCounter(StoreCounterBookingRequest $request): JsonResponse
    {
        $this->assertCanManageCourt($request, VenueCourt::findOrFail($request->validated('court_id')));

        $payload = $this->bookingService->createCounterBooking($request->validated(), $request->user());

        return ApiResponse::success('Counter booking created and paid successfully', $payload, 201);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        $this->assertCanAccessBooking($request, $booking);

        return ApiResponse::success('Fetched booking successfully', $booking->load(['customer', 'court.courtType', 'cluster', 'payments', 'refunds', 'review']));
    }

    public function cancel(CancelBookingRequest $request, Booking $booking): JsonResponse
    {
        $booking = $this->bookingService->cancel($booking, $request->user(), $request->validated('cancel_reason'));

        return ApiResponse::success('Booking cancelled successfully', $booking);
    }

    public function confirm(Request $request, Booking $booking): JsonResponse
    {
        $this->assertCanOperateBooking($request, $booking);

        $booking = $this->bookingService->confirm($booking);

        return ApiResponse::success('Booking confirmed successfully', $booking);
    }

    public function checkIn(Request $request, Booking $booking): JsonResponse
    {
        $this->assertCanOperateBooking($request, $booking);

        $booking = $this->bookingService->checkIn($booking);

        return ApiResponse::success('Booking checked in successfully', $booking);
    }

    public function complete(Request $request, Booking $booking): JsonResponse
    {
        $this->assertCanOperateBooking($request, $booking);

        $booking = $this->bookingService->complete($booking);

        return ApiResponse::success('Booking completed successfully', $booking);
    }

    private function assertCanAccessBooking(Request $request, Booking $booking): void
    {
        $user = $request->user();

        abort_unless(
            $this->isPlatformAdmin($user)
            || $booking->customer_id === $user->id
            || $booking->created_by === $user->id
            || in_array($booking->cluster_id, $this->bookableClusterIdsForUser($user), true),
            403,
            'You cannot access this booking.'
        );
    }

    private function assertCanOperateBooking(Request $request, Booking $booking): void
    {
        $user = $request->user();

        abort_unless(
            $this->isPlatformAdmin($user)
            || $booking->created_by === $user->id
            || in_array($booking->cluster_id, $this->bookableClusterIdsForUser($user), true),
            403,
            'You cannot operate this booking.'
        );
    }

    private function bookableClusterIdsForUser($user): array
    {
        return array_values(array_unique(array_merge(
            VenueCluster::where('owner_id', $user->id)->pluck('id')->all(),
            $this->venueScopeIds($user)
        )));
    }
}
