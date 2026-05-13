<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'content',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /** Conversation this message belongs to */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** User who sent this message */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
