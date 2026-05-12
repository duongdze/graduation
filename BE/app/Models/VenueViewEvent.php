<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueViewEvent extends Model
{
    use HasUuids;

    const CREATED_AT = 'viewed_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'cluster_id',
        'user_id',
        'ip_address',
        'user_agent',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(VenueCluster::class, 'cluster_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
