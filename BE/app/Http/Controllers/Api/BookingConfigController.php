<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\UpdateBookingConfigRequest;
use App\Models\BookingConfig;
use App\Models\VenueCluster;
use App\Support\ApiResponse;
use App\Traits\AuthorizesVenueScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingConfigController extends Controller
{
    use AuthorizesVenueScope;

    public function index(Request $request): JsonResponse
    {
        $clusterIds = null;
        if ($request->user()->hasRole('venue_owner') && ! $this->isPlatformAdmin($request->user())) {
            $clusterIds = VenueCluster::where('owner_id', $request->user()->id)->pluck('id')->all();
        }

        $configs = BookingConfig::query()
            ->with('cluster')
            ->when($clusterIds !== null, fn ($query) => $query->whereIn('cluster_id', $clusterIds))
            ->paginate($request->integer('per_page', 50));

        return ApiResponse::paginated('Fetched booking configs successfully', $configs);
    }

    public function update(UpdateBookingConfigRequest $request, BookingConfig $bookingConfig): JsonResponse
    {
        $bookingConfig->loadMissing('cluster');
        $this->assertCanManageCluster($request, $bookingConfig->cluster);

        $bookingConfig->update($request->validated());

        return ApiResponse::success('Booking config updated successfully', $bookingConfig->fresh('cluster'));
    }
}
