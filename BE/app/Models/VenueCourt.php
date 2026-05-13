<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VenueCourt extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'cluster_id',
        'court_type_id',
        'name',
        'status',
        'sort_order',
    ];

    /** Venue cluster this court belongs to */
    public function cluster(): BelongsTo
    {
        return $this->belongsTo(VenueCluster::class, 'cluster_id');
    }

    /** Court type (5v5, 7v7, badminton, etc.) */
    public function courtType(): BelongsTo
    {
        return $this->belongsTo(CourtType::class);
    }

    /** Bookings for this specific court */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'court_id');
    }

    /** Active slot locks for this court */
    public function slotLocks(): HasMany
    {
        return $this->hasMany(SlotLock::class, 'court_id');
    }
}
