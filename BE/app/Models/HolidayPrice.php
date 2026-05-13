<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HolidayPrice extends Model
{
    use HasUuids;

    protected $fillable = [
        'cluster_id',
        'holiday_date',
        'price',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'price' => 'decimal:2',
        ];
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(VenueCluster::class, 'cluster_id');
    }
}
