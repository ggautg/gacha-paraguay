<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo del historial de tiradas: registro de auditoría del gacha.
 *
 * Cada fila registra el personaje obtenido, su rareza y si fue una novedad para
 * el jugador, de modo que el historial sirva tanto a la UI como a la depuración
 * de las probabilidades sin reconstruir el azar a partir de la colección final.
 */
class Pull extends Model
{
    // Una tirada es un evento inmutable: se crea, nunca se edita.
    const UPDATED_AT = null;

    protected $fillable = [
        'player_id',
        'character_id',
        'rarity',
        'is_new',
    ];

    /**
     * Fuerza rarity a entero e is_new a booleano para filtros y respuestas JSON limpias.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rarity' => 'integer',
            'is_new' => 'boolean',
        ];
    }

    /**
     * Relación con el personaje obtenido; permite mostrar nombre e imagen en el historial.
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * Relación con el jugador que realizó la tirada.
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
