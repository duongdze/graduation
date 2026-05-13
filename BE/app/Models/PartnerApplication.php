<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PartnerApplication extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'business_name',
        'tax_code',
        'status',
        'reviewed_by',
        'reject_reason',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /** Applicant user */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Admin who reviewed */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Documents (license, ID cards) */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
