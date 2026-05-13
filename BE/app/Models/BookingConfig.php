<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingConfig extends Model
{
    /** PK is cluster_id (1:1), not auto-increment */
    protected $primaryKey = 'cluster_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'cluster_id',
        'min_duration_minutes',
        'max_duration_minutes',
        'cancel_before_hours',
        'refund_percent',
    ];

    /** The venue cluster this config belongs to */
    public function cluster(): BelongsTo
    {
        return $this->belongsTo(VenueCluster::class, 'cluster_id');
    }
}
