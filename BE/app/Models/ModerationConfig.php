<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationConfig extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
        'value_type',
        'description',
        'updated_by',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function castedValue(): mixed
    {
        return match ($this->value_type) {
            'integer' => (int) $this->value,
            'decimal' => (float) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            default => $this->value,
        };
    }
}
