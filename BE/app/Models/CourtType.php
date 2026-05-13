<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourtType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'player_count',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** Courts using this type */
    public function venueCourts(): HasMany
    {
        return $this->hasMany(VenueCourt::class);
    }

    /** Player posts requesting this court type */
    public function playerPosts(): HasMany
    {
        return $this->hasMany(PlayerPost::class);
    }
}
