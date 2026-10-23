<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pull extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'player_id',
        'character_id',
        'rarity',
        'is_new',
    ];

    protected function casts(): array
    {
        return [
            'rarity' => 'integer',
            'is_new' => 'boolean',
        ];
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
