<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Player extends Model
{
    protected $fillable = [
        'token',
        'name',
        'coins',
        'total_pulls',
        'pity',
        'bonus_claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'coins' => 'integer',
            'total_pulls' => 'integer',
            'pity' => 'integer',
            'bonus_claimed_at' => 'datetime',
        ];
    }

    public function collection(): HasMany
    {
        return $this->hasMany(CollectionEntry::class);
    }

    public function pulls(): HasMany
    {
        return $this->hasMany(Pull::class);
    }

    public function bonusReadyAt(): Carbon
    {
        if (! $this->bonus_claimed_at) {
            return now();
        }

        return $this->bonus_claimed_at->copy()->addHours(config('gachapon.economy.bonus_cooldown_hours'));
    }

    public function canClaimBonus(): bool
    {
        if (! $this->bonus_claimed_at) {
            return true;
        }

        return now()->greaterThanOrEqualTo($this->bonusReadyAt());
    }

    public function duplicateRefund(int $rarity): int
    {
        return (int) (config("gachapon.economy.duplicate_refund.{$rarity}") ?? 0);
    }
}
