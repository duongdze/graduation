<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformFeeConfig extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'fee_percent',
        'max_fee_percent',
        'effective_from',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fee_percent' => 'decimal:2',
            'max_fee_percent' => 'decimal:2',
            'effective_from' => 'datetime',
        ];
    }

    /** Admin who set this fee config */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
