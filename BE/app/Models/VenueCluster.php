<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class VenueCluster extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'description',
        'phone_contact',
        'address',
        'ward',
        'district',
        'city',
        'latitude',
        'longitude',
        'amenities',
        'status',
        'approved_by',
        'reject_reason',
        'lock_reason',
        'locked_at',
        'locked_by',
        'rating_avg',
        'rating_count',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'amenities' => 'array',
            'locked_at' => 'datetime',
            'rating_avg' => 'decimal:2',
        ];
    }

    // ── Owner / Admin ──────────────────────────────────────

    /** Venue owner */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Admin who approved/rejected this venue */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Admin or system actor who locked this venue */
    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    // ── Courts & Config ────────────────────────────────────

    /** Courts belonging to this venue */
    public function courts(): HasMany
    {
        return $this->hasMany(VenueCourt::class, 'cluster_id');
    }

    /** Booking configuration (1:1) */
    public function bookingConfig(): HasOne
    {
        return $this->hasOne(BookingConfig::class, 'cluster_id');
    }

    // ── Pricing ────────────────────────────────────────────

    /** Regular time-based price slots */
    public function priceSlots(): HasMany
    {
        return $this->hasMany(PriceSlot::class, 'cluster_id');
    }

    /** Holiday/special date pricing overrides */
    public function holidayPrices(): HasMany
    {
        return $this->hasMany(HolidayPrice::class, 'cluster_id');
    }

    // ── Booking & Finance ──────────────────────────────────

    /** All bookings for courts in this venue */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'cluster_id');
    }

    /** Fee ledger entries for this venue */
    public function feeLedgers(): HasMany
    {
        return $this->hasMany(VenueFeeLedger::class, 'cluster_id');
    }

    // ── Feedback ───────────────────────────────────────────

    /** Reviews for this venue */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'cluster_id');
    }

    /** Reports targeting this venue */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    /** Users who favorited this venue */
    public function favorites(): HasMany
    {
        return $this->hasMany(FavoriteVenue::class, 'venue_cluster_id');
    }

    // ── Media ──────────────────────────────────────────────

    /** Cover image, gallery photos */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    // ── Recruitment ────────────────────────────────────────

    /** Player posts linked to this venue */
    public function playerPosts(): HasMany
    {
        return $this->hasMany(PlayerPost::class, 'venue_cluster_id');
    }

    /** Community posts attached to this venue */
    public function communityPosts(): HasMany
    {
        return $this->hasMany(CommunityPost::class, 'venue_cluster_id');
    }
}
