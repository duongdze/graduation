<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'reference_type',
        'reference_id',
        'title',
        'created_by',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /** User who created this conversation */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Users participating in this conversation */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot('last_read_at', 'joined_at');
    }

    /** Participant records */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /** Messages in this conversation */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
