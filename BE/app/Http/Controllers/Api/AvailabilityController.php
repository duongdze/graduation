<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\AvailabilityRequest;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Services\AvailabilityService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    public function __construct(private readonly AvailabilityService $availabilityService) {}

    public function courtSlots(AvailabilityRequest $request, VenueCourt $venueCourt): JsonResponse
    {
        $slots = $this->availabilityService->slotsForCourt(
            $venueCourt,
            $request->validated('date'),
            $request->validated('duration_minutes')
        );

        return ApiResponse::success('Fetched available slots successfully', $slots);
    }

    public function clusterSlots(AvailabilityRequest $request, VenueCluster $venueCluster): JsonResponse
    {
        $slots = $this->availabilityService->slotsForCluster(
            $venueCluster,
            $request->validated('date'),
            $request->validated('duration_minutes')
        );

        return ApiResponse::success('Fetched cluster available slots successfully', $slots);
    }
}
