<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityPostLike extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'post_id',
        'user_id',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
