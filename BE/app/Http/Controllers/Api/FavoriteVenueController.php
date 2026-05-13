<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FavoriteVenue;
use App\Models\VenueCluster;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteVenueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $favorites = FavoriteVenue::query()
            ->with(['venueCluster.owner', 'venueCluster.courts.courtType'])
            ->where('user_id', $request->user()->id)
            ->latest('created_at')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched favorite venues successfully', $favorites);
    }

    public function store(Request $request, VenueCluster $venueCluster): JsonResponse
    {
        $favorite = FavoriteVenue::firstOrCreate([
            'user_id' => $request->user()->id,
            'venue_cluster_id' => $venueCluster->id,
        ]);

        return ApiResponse::success('Venue favorited successfully', $favorite->load('venueCluster'), 201);
    }

    public function destroy(Request $request, VenueCluster $venueCluster): JsonResponse
    {
        FavoriteVenue::where('user_id', $request->user()->id)
            ->where('venue_cluster_id', $venueCluster->id)
            ->delete();

        return ApiResponse::success('Venue unfavorited successfully');
    }
}
