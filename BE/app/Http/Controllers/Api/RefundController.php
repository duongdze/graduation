<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StoreRefundRequest;
use App\Models\Booking;
use App\Models\Refund;
use App\Models\VenueCluster;
use App\Services\MediaService;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use App\Traits\AuthorizesVenueScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    use AuthorizesVenueScope;

    public function __construct(private readonly PaymentService $paymentService) {}

    public function index(Request $request): JsonResponse
    {
        $refunds = Refund::query()
            ->with(['booking', 'payment', 'processor', 'media'])
            ->when(! $this->isPlatformAdmin($request->user()), function ($query) use ($request) {
                $clusterIds = $this->refundableClusterIdsForUser($request->user());
                $query->whereHas('booking', fn ($booking) => $booking->whereIn('cluster_id', $clusterIds));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('booking_id'), fn ($query) => $query->where('booking_id', $request->string('booking_id')->toString()))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched refunds successfully', $refunds);
    }

    public function show(Request $request, Refund $refund): JsonResponse
    {
        $this->assertCanAccessRefund($request, $refund);

        return ApiResponse::success('Fetched refund successfully', $refund->load(['booking', 'payment', 'processor', 'media']));
    }

    public function store(StoreRefundRequest $request): JsonResponse
    {
        $booking = Booking::findOrFail($request->validated('booking_id'));
        abort_unless(
            $this->isPlatformAdmin($request->user())
            || in_array($booking->cluster_id, $this->refundableClusterIdsForUser($request->user()), true),
            403,
            'You cannot create a refund for this booking.'
        );

        $refund = $this->paymentService->createRefund($request->validated());

        return ApiResponse::success('Refund created successfully', $refund, 201);
    }

    public function approve(Request $request, Refund $refund): JsonResponse
    {
        $this->assertCanAccessRefund($request, $refund);
        $refund = $this->paymentService->approveRefund($refund, $request->user());

        return ApiResponse::success('Refund approved successfully', $refund);
    }

    public function reject(Request $request, Refund $refund): JsonResponse
    {
        $this->assertCanAccessRefund($request, $refund);
        $payload = $this->paymentService->rejectRefund($refund, $request->user());

        return ApiResponse::success('Refund rejected successfully', $payload);
    }

    public function uploadProof(Request $request, Refund $refund, MediaService $mediaService): JsonResponse
    {
        $this->assertCanAccessRefund($request, $refund);

        $request->validate([
            'file' => ['required', 'image', 'max:5120'],
            'collection' => ['nullable', 'string', 'in:refund_proof,proof'],
        ]);

        $media = $mediaService->store([
            'mediable_type' => 'refund',
            'mediable_id' => $refund->id,
            'collection' => $request->input('collection', 'refund_proof'),
            'sort_order' => 0,
        ], $request->file('file'));

        return ApiResponse::success('Refund proof uploaded successfully', [
            'refund' => $refund->fresh(['booking', 'payment', 'processor', 'media']),
            'media' => $media,
        ], 201);
    }

    private function assertCanAccessRefund(Request $request, Refund $refund): void
    {
        $refund->loadMissing('booking');
        abort_unless(
            $this->isPlatformAdmin($request->user())
            || in_array($refund->booking?->cluster_id, $this->refundableClusterIdsForUser($request->user()), true),
            403,
            'You cannot access this refund.'
        );
    }

    private function refundableClusterIdsForUser($user): array
    {
        return array_values(array_unique(array_merge(
            VenueCluster::where('owner_id', $user->id)->pluck('id')->all(),
            $this->venueScopeIds($user)
        )));
    }
}
