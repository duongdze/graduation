<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CommunityPost extends Model
{
    use HasUuids;

    protected $fillable = [
        'author_id',
        'venue_cluster_id',
        'content',
        'status',
        'view_count',
        'like_count',
        'comment_count',
    ];

    protected $appends = [
        'is_venue_owner',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function venueCluster(): BelongsTo
    {
        return $this->belongsTo(VenueCluster::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(CommunityPostLike::class, 'post_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CommunityPostComment::class, 'post_id');
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function getIsVenueOwnerAttribute(): bool
    {
        if ($this->venue_cluster_id !== null) {
            return true;
        }

        return VenueCluster::where('owner_id', $this->author_id)->exists();
    }
}
