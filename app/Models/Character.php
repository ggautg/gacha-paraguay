<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo de un personaje del catálogo, es decir, de una figura coleccionable.
 *
 * Guarda los datos importados de Wikipedia y expone toCard() como única forma de
 * consumir un personaje desde la UI: ahí se concentra la política anti-spoiler que
 * decide qué información se revela según el progreso del jugador.
 */
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

    /**
     * Fuerza rarity a entero para que la agrupación por rareza use claves numéricas.
     *
     * Así la agrupación por rareza y las comparaciones estrictas trabajan con el
     * mismo tipo que los valores de la configuración.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rarity' => 'integer',
        ];
    }

    /**
     * Relación inversa con las entradas de colección de este personaje.
     *
     * Permite consultar las copias existentes sin pasar por un jugador concreto,
     * útil para agregaciones globales del catálogo.
     */
    public function entries(): HasMany
    {
        return $this->hasMany(CollectionEntry::class);
    }

    /**
     * Devuelve la URL pública del retrato o null cuando el personaje no tiene imagen.
     *
     * Se construye una ruta relativa en lugar de usar asset() o Storage::url() para
     * que funcione igual en localhost, 127.0.0.1 y producción, sin depender del host
     * ni de un dominio configurado para el disco.
     */
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
     *
     * Oculta la imagen y el extracto cuando no hay entrada de colección: revelarlos
     * delataría de antemano a quién se puede obtener y rompería la sorpresa del gacha
     * (anti-spoiler). El nombre, el rol y la rareza sí se publican porque forman parte
     * del catálogo visible de la colección.
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
