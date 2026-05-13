<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Report extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'reporter_id',
        'reportable_type',
        'reportable_id',
        'reason',
        'description',
        'status',
        'action_taken',
        'action_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    /** The reported entity (User, Review, PlayerPost, etc.) */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /** User who filed the report */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** Admin who reviewed the report */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
