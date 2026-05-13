<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Booking extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'booking_code',
        'customer_id',
        'court_id',
        'cluster_id',
        'booking_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'base_price',
        'total_price',
        'source',
        'status',
        'cancel_reason',
        'cancelled_by',
        'cancelled_at',
        'walk_in_name',
        'walk_in_phone',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'base_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    // ── Core Relationships ─────────────────────────────────

    /** Customer who booked (null for walk-in) */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** Court booked */
    public function court(): BelongsTo
    {
        return $this->belongsTo(VenueCourt::class, 'court_id');
    }

    /** Venue cluster */
    public function cluster(): BelongsTo
    {
        return $this->belongsTo(VenueCluster::class, 'cluster_id');
    }

    /** User who created this booking (customer or staff) */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** User who cancelled */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // ── Payment & Finance ──────────────────────────────────

    /** Payment attempts for this booking */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Refunds for this booking */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /** Fee ledger entry (created on completion) */
    public function feeLedger(): HasOne
    {
        return $this->hasOne(VenueFeeLedger::class);
    }

    // ── Slot Lock ──────────────────────────────────────────

    /** Slot locks associated with this booking */
    public function slotLocks(): HasMany
    {
        return $this->hasMany(SlotLock::class);
    }

    // ── Feedback ───────────────────────────────────────────

    /** Review for this booking (1:1) */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /** Complaints for this booking */
    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    // ── Recruitment ────────────────────────────────────────

    /** Player posts linked to this booking */
    public function playerPosts(): HasMany
    {
        return $this->hasMany(PlayerPost::class);
    }
}
