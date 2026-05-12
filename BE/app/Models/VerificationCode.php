<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationCode extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'identifier',
        'type',
        'code',
        'channel',
        'attempt_count',
        'max_attempts',
        'is_used',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_used' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Check if this code is still valid */
    public function isValid(): bool
    {
        return ! $this->is_used
            && $this->expires_at->isFuture()
            && $this->attempt_count < $this->max_attempts;
    }
}
