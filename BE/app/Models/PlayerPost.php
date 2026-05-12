<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PlayerPost extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'author_id',
        'title',
        'description',
        'sport_type',
        'court_type_id',
        'venue_cluster_id',
        'booking_id',
        'play_date',
        'start_time',
        'end_time',
        'location_name',
        'latitude',
        'longitude',
        'needed_players',
        'max_players',
        'current_players',
        'skill_level',
        'gender_preference',
        'cost_per_player',
        'is_auto_approve',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'play_date' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'cost_per_player' => 'decimal:2',
            'is_auto_approve' => 'boolean',
        ];
    }

    /** Post author */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Optional court type preference */
    public function courtType(): BelongsTo
    {
        return $this->belongsTo(CourtType::class);
    }

    /** Optional linked venue */
    public function venueCluster(): BelongsTo
    {
        return $this->belongsTo(VenueCluster::class);
    }

    /** Optional linked booking */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** Participants who joined/requested */
    public function participants(): HasMany
    {
        return $this->hasMany(PlayerPostParticipant::class, 'post_id');
    }

    /** Player ratings given in context of this post */
    public function playerRatings(): HasMany
    {
        return $this->hasMany(PlayerRating::class, 'post_id');
    }

    /** Post images */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /** Reports against this post */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }
}
