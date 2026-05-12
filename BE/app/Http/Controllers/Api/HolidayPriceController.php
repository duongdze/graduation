<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\UpsertHolidayPriceRequest;
use App\Models\HolidayPrice;
use App\Models\VenueCluster;
use App\Support\ApiResponse;
use App\Traits\AuthorizesVenueScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayPriceController extends Controller
{
    use AuthorizesVenueScope;

    public function index(Request $request): JsonResponse
    {
        $clusterIds = null;
        if ($request->filled('cluster_id') && ($request->user()->hasRole('venue_owner') || $this->venueScopeIds($request->user()) !== []) && ! $this->isPlatformAdmin($request->user())) {
            $cluster = VenueCluster::findOrFail($request->string('cluster_id')->toString());
            $this->assertCanManageCluster($request, $cluster);
            $clusterIds = [$cluster->id];
        } elseif ($request->user()->hasRole('venue_owner') && ! $this->isPlatformAdmin($request->user())) {
            $clusterIds = VenueCluster::where('owner_id', $request->user()->id)->pluck('id')->all();
        }

        $prices = HolidayPrice::query()
            ->with('cluster')
            ->when($clusterIds !== null, fn ($query) => $query->whereIn('cluster_id', $clusterIds))
            ->when($request->filled('cluster_id'), fn ($query) => $query->where('cluster_id', $request->string('cluster_id')->toString()))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('holiday_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('holiday_date', '<=', $request->date('to')))
            ->orderBy('holiday_date')
            ->paginate($request->integer('per_page', 50));

        return ApiResponse::paginated('Fetched holiday prices successfully', $prices);
    }

    public function show(HolidayPrice $holidayPrice): JsonResponse
    {
        return ApiResponse::success('Fetched holiday price successfully', $holidayPrice->load('cluster'));
    }

    public function store(UpsertHolidayPriceRequest $request): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($request->validated('cluster_id'));
        $this->assertCanManageCluster($request, $cluster);

        $price = HolidayPrice::updateOrCreate(
            [
                'cluster_id' => $request->validated('cluster_id'),
                'holiday_date' => $request->validated('holiday_date'),
            ],
            $request->validated()
        );

        return ApiResponse::success('Holiday price saved successfully', $price->load('cluster'), 201);
    }

    public function update(UpsertHolidayPriceRequest $request, HolidayPrice $holidayPrice): JsonResponse
    {
        $holidayPrice->loadMissing('cluster');
        $this->assertCanManageCluster($request, $holidayPrice->cluster);

        if ($request->filled('cluster_id')) {
            $this->assertCanManageCluster($request, VenueCluster::findOrFail($request->validated('cluster_id')));
        }

        $holidayPrice->update($request->validated());

        return ApiResponse::success('Holiday price updated successfully', $holidayPrice->fresh('cluster'));
    }

    public function destroy(Request $request, HolidayPrice $holidayPrice): JsonResponse
    {
        $holidayPrice->loadMissing('cluster');
        $this->assertCanManageCluster($request, $holidayPrice->cluster);

        $holidayPrice->delete();

        return ApiResponse::success('Holiday price deleted successfully');
    }
}
