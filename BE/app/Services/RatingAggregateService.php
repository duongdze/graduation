<?php

namespace App\Services;

use App\Models\User;
use App\Models\VenueCluster;

class RatingAggregateService
{
    public function syncVenueRating(VenueCluster $cluster): void
    {
        $stats = $cluster->reviews()
            ->where('is_visible', true)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as rating_count')
            ->first();

        $cluster->update([
            'rating_avg' => round((float) ($stats->avg_rating ?? 0), 2),
            'rating_count' => (int) ($stats->rating_count ?? 0),
        ]);
    }

    public function syncPlayerRating(User $user): void
    {
        $stats = $user->receivedPlayerRatings()
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as rating_count')
            ->first();

        $user->update([
            'player_rating_avg' => round((float) ($stats->avg_rating ?? 0), 2),
            'player_rating_count' => (int) ($stats->rating_count ?? 0),
        ]);
    }
}
