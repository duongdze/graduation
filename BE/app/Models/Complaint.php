<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Complaint extends Model
{
    use HasUuids;

    protected $fillable = [
        'booking_id',
        'customer_id',
        'content',
        'status',
        'resolved_by',
        'resolve_note',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** Admin/staff who resolved */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** Evidence attachments */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
