<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRole extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'role_id',
        'scope_type',
        'scope_id',
        'granted_by',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** User who granted this role */
    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
