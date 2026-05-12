<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlotLock extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'court_id',
        'booking_date',
        'start_time',
        'end_time',
        'locked_by',
        'booking_id',
        'lock_type',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'expires_at' => 'datetime',
        ];
    }

    /** Court this lock is for */
    public function court(): BelongsTo
    {
        return $this->belongsTo(VenueCourt::class, 'court_id');
    }

    /** Associated booking (if lock is confirmed) */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** User who locked this slot (when locked_by is a user UUID) */
    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
