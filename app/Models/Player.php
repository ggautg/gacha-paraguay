<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Modelo del jugador anónimo identificado por la cookie del navegador.
 *
 * Centraliza las reglas de la economía del juego: saldo, total de tiradas, contador
 * de pity y disponibilidad de la bonificación. No depende del sistema de autenticación
 * de Laravel porque el progreso se asocia a un dispositivo, no a una cuenta.
 */
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

    /**
     * Convierte saldos y contadores a enteros, y la marca de bonificación a fecha.
     *
     * El cast datetime es imprescindible para comparar el cooldown con now() y para
     * serializar bonusReadyAt() como ISO 8601 sin conversiones manuales en los presentadores.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'coins' => 'integer',
            'total_pulls' => 'integer',
            'pity' => 'integer',
            'bonus_claimed_at' => 'datetime',
        ];
    }

    /**
     * Relación con las entradas de colección del jugador.
     *
     * Se usa tanto para la colección como para los cálculos de progreso y para detectar
     * duplicados al resolver una tirada.
     */
    public function collection(): HasMany
    {
        return $this->hasMany(CollectionEntry::class);
    }

    /**
     * Relación con el historial de tiradas del jugador, ordenable por id reciente.
     */
    public function pulls(): HasMany
    {
        return $this->hasMany(Pull::class);
    }

    /**
     * Calcula cuándo vuelve a estar disponible la bonificación de monedas.
     *
     * Si nunca se reclamó, devuelve "ahora" para que el botón aparezca habilitado
     * desde el primer ingreso sin necesidad de guardar una fecha artificial en la base.
     */
    public function bonusReadyAt(): Carbon
    {
        if (! $this->bonus_claimed_at) {
            return now();
        }

        return $this->bonus_claimed_at->copy()->addHours(config('gachapon.economy.bonus_cooldown_hours'));
    }

    /**
     * Determina si ya transcurrió el cooldown de la bonificación.
     *
     * Se deriva de bonus_claimed_at en lugar de almacenar un booleano: así el estado
     * no puede desincronizarse con el reloj ni quedar atascado en "reclamada" tras
     * un reinicio del proceso.
     */
    public function canClaimBonus(): bool
    {
        if (! $this->bonus_claimed_at) {
            return true;
        }

        return now()->greaterThanOrEqualTo($this->bonusReadyAt());
    }

    /**
     * Lee de la configuración el porcentaje de reembolso según la rareza.
     *
     * La rareza 3★ reintegra el 100 % para que el jugador no pierda monedas al repetir
     * una figura máxima; el 0 por defecto evita reembolsos fantasma si se agrega una
     * rareza nueva sin configurar su porcentaje.
     */
    public function duplicateRefundPercent(int $rarity): int
    {
        return (int) (config("gachapon.economy.duplicate_refund_percent.{$rarity}") ?? 0);
    }

    /**
     * Convierte el porcentaje de reembolso en una cantidad entera de monedas.
     *
     * Toma como base el precio de la tirada simple para que la devolución por
     * duplicado sea la misma en tiradas individuales y múltiples.
     */
    public function duplicateRefund(int $rarity): int
    {
        $price = (int) config('gachapon.economy.price_single');

        return (int) round($price * $this->duplicateRefundPercent($rarity) / 100);
    }
}
