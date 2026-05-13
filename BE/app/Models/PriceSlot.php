<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceSlot extends Model
{
    use HasUuids;

    protected $fillable = [
        'cluster_id',
        'start_time',
        'end_time',
        'price',
        'apply_to_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'apply_to_days' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(VenueCluster::class, 'cluster_id');
    }
}
