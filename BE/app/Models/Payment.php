<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasUuids;

    protected $fillable = [
        'booking_id',
        'amount',
        'method',
        'gateway_txn_id',
        'gateway_response',
        'status',
        'paid_at',
    ];

    protected $hidden = [
        'gateway_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** Refunds issued against this payment */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
