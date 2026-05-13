<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venue\UpsertVenueCourtRequest;
use App\Models\Booking;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Support\ApiResponse;
use App\Traits\AuthorizesVenueScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VenueCourtController extends Controller
{
    use AuthorizesVenueScope;

    public function index(Request $request): JsonResponse
    {
        $clusterIds = null;
        if ($request->filled('cluster_id')) {
            $cluster = VenueCluster::findOrFail($request->string('cluster_id')->toString());
            if (($request->user()->hasRole('venue_owner') || $this->venueScopeIds($request->user()) !== []) && ! $this->isPlatformAdmin($request->user())) {
                $this->assertCanManageCluster($request, $cluster);
            }
            $clusterIds = [$cluster->id];
        } elseif ($request->user()->hasRole('venue_owner') && ! $this->isPlatformAdmin($request->user())) {
            $clusterIds = VenueCluster::where('owner_id', $request->user()->id)->pluck('id')->all();
        }

        $courts = VenueCourt::query()
            ->with(['cluster', 'courtType'])
            ->when($clusterIds !== null, fn ($query) => $query->whereIn('cluster_id', $clusterIds))
            ->when($request->filled('cluster_id'), fn ($query) => $query->where('cluster_id', $request->string('cluster_id')->toString()))
            ->when($request->filled('court_type_id'), fn ($query) => $query->where('court_type_id', $request->integer('court_type_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy('sort_order')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched venue courts successfully', $courts);
    }

    public function store(UpsertVenueCourtRequest $request): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($request->validated('cluster_id'));
        $this->assertCanManageCluster($request, $cluster);

        $court = VenueCourt::create($request->validated());

        return ApiResponse::success('Venue court created successfully', $court->load(['cluster', 'courtType']), 201);
    }

    public function show(VenueCourt $venueCourt): JsonResponse
    {
        return ApiResponse::success('Fetched venue court successfully', $venueCourt->load(['cluster', 'courtType']));
    }

    public function update(UpsertVenueCourtRequest $request, VenueCourt $venueCourt): JsonResponse
    {
        $this->assertCanManageCourt($request, $venueCourt);
        if ($request->filled('cluster_id')) {
            $targetCluster = VenueCluster::findOrFail($request->validated('cluster_id'));
            $this->assertCanManageCluster($request, $targetCluster);
        }

        $venueCourt->update($request->validated());

        return ApiResponse::success('Venue court updated successfully', $venueCourt->fresh(['cluster', 'courtType']));
    }

    public function destroy(Request $request, VenueCourt $venueCourt): JsonResponse
    {
        $this->assertCanManageCourt($request, $venueCourt);

        $hasFutureBooking = Booking::where('court_id', $venueCourt->id)
            ->whereDate('booking_date', '>=', today())
            ->whereNotIn('status', ['cancelled', 'expired', 'completed'])
            ->exists();

        if ($hasFutureBooking) {
            throw ValidationException::withMessages([
                'court_id' => ['Cannot delete a court with upcoming active bookings.'],
            ]);
        }

        $venueCourt->delete();

        return ApiResponse::success('Venue court deleted successfully');
    }
}
