<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FavoriteVenue extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'venue_cluster_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function venueCluster(): BelongsTo
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }
}
