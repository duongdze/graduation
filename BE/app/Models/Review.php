<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Review extends Model
{
    use HasUuids;

    protected $fillable = [
        'booking_id',
        'customer_id',
        'cluster_id',
        'rating',
        'comment',
        'reply_content',
        'replied_at',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'replied_at' => 'datetime',
        ];
    }

    /** Booking that was reviewed */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** Customer who wrote the review */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** Venue cluster being reviewed */
    public function cluster(): BelongsTo
    {
        return $this->belongsTo(VenueCluster::class, 'cluster_id');
    }

    /** Review photos */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /** Reports against this review */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }
}
