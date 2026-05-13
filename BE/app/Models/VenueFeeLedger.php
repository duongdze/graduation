<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueFeeLedger extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'booking_id',
        'cluster_id',
        'booking_total',
        'fee_percent',
        'fee_amount',
        'status',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'booking_total' => 'decimal:2',
            'fee_percent' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'reconciled_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(VenueCluster::class, 'cluster_id');
    }
}
