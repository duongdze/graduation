<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerPostParticipant extends Model
{
    protected $fillable = [
        'post_id',
        'user_id',
        'status',
        'message',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    /** The post being joined */
    public function post(): BelongsTo
    {
        return $this->belongsTo(PlayerPost::class, 'post_id');
    }

    /** The user requesting to join */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
