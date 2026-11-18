<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de la tabla que registra cuántas copias posee un jugador de un personaje.
 *
 * quantity permite acumular duplicados en una sola fila en lugar de repetir filas;
 * el reembolso de esos duplicados se calcula aparte, en GachaService, según la
 * rareza del personaje.
 */
class CollectionEntry extends Model
{
    protected $fillable = [
        'player_id',
        'character_id',
        'quantity',
    ];

    /**
     * Fuerza quantity a entero para que increment('quantity') y el JSON sean
     * predecibles y no aparezcan cantidades como cadenas en el frontend.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /**
     * Relación con el jugador dueño de esta entrada de la colección.
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * Relación con el personaje coleccionado.
     *
     * Da acceso a su rareza, necesaria para calcular el reembolso por duplicado.
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
