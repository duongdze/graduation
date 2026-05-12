<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $table = 'media';

    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'collection',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'sort_order',
    ];

    /** The parent model (User, VenueCluster, Review, etc.) */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }
}
