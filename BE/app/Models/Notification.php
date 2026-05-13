<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'reference_type',
        'reference_id',
        'data',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    /** User who receives this notification */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
