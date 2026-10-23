<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Character extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'wiki_title',
        'wiki_url',
        'image_path',
        'extract',
        'role',
        'era',
        'category',
        'rarity',
    ];

    protected function casts(): array
    {
        return [
            'rarity' => 'integer',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CollectionEntry::class);
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        // URL relativa para que funcione en cualquier host (localhost, 127.0.0.1, producción).
        return '/storage/'.ltrim($this->image_path, '/');
    }

    /**
     * Resumen del personaje listo para la colección.
     */
    public function toCard(?CollectionEntry $entry = null): array
    {
        $owned = $entry !== null;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'role' => $this->role,
            'era' => $this->era,
            'category' => $this->category,
            'rarity' => $this->rarity,
            'owned' => $owned,
            'quantity' => $entry?->quantity ?? 0,
            'image' => $owned ? $this->imageUrl() : null,
            'extract' => $owned ? $this->extract : null,
            'wiki_url' => $this->wiki_url,
        ];
    }
}
