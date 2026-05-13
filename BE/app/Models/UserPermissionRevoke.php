<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermissionRevoke extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'permission_id',
        'scope_type',
        'scope_id',
        'revoked_by',
        'reason',
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

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /** User who revoked this permission */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
