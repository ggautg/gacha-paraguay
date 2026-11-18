<?php

namespace App\Support;

use App\Models\Character;
use App\Models\CollectionEntry;
use App\Models\Player;

/**
 * Presentador que convierte los modelos del dominio en los arrays planos que consume Inertia.
 *
 * Aquí se concentran los cálculos derivados (progreso por rareza, cooldown, revelado)
 * para que controladores y servicios no repitan la misma serialización.
 */
class GachaPresenter
{
    /**
     * Estado completo del jugador y de su colección.
     *
     * refresh() asegura que se vea el saldo actualizado por GachaService aunque el
     * objeto Player provenga de la request. Los totales por rareza se agregan en SQL
     * en lugar de recorrer la colección en PHP, para no cargar todas las filas en memoria.
     */
    public static function player(Player $player): array
    {
        $player->refresh();

        $total = Character::query()->count();

        // Dos consultas agregadas: una para el catálogo y otra para lo ya obtenido.
        $rarityTotals = Character::query()
            ->selectRaw('rarity, COUNT(*) as aggregate')
            ->groupBy('rarity')
            ->pluck('aggregate', 'rarity');

        $rarityOwned = $player->collection()
            ->join('characters', 'characters.id', '=', 'collection_entries.character_id')
            ->selectRaw('characters.rarity as rarity, COUNT(*) as aggregate')
            ->groupBy('characters.rarity')
            ->pluck('aggregate', 'rarity');

        $owned = $player->collection()->count();

        $byRarity = [];
        foreach ([3, 2, 1] as $rarity) {
            $byRarity[$rarity] = [
                'owned' => (int) ($rarityOwned[$rarity] ?? 0),
                'total' => (int) ($rarityTotals[$rarity] ?? 0),
            ];
        }

        return [
            'coins' => $player->coins,
            'total_pulls' => $player->total_pulls,
            'pity' => $player->pity,
            'pity_limit' => (int) config('gachapon.pity'),
            'bonus_ready_at' => $player->bonusReadyAt()->toIso8601String(),
            'bonus_claimable' => $player->canClaimBonus(),
            'bonus_coins' => (int) config('gachapon.economy.bonus_coins'),
            'stats' => [
                'total' => $total,
                'owned' => $owned,
                'completion' => $total > 0 ? round($owned / $total * 100, 1) : 0,
                'by_rarity' => $byRarity,
            ],
        ];
    }

    /**
     * Resultado de una tirada, listo para la animación de revelado.
     *
     * Reutiliza toCard() para mantener una sola política anti-spoiler y añade los
     * datos del reembolso. El porcentaje viaja al frontend para que pueda mostrar
     * cuánto se devolvió sin tener que conocer la regla de la economía.
     */
    public static function reveal(Character $character, ?CollectionEntry $entry, bool $isNew, int $refund, int $refundPercent = 0): array
    {
        return [
            ...$character->toCard($entry),
            'is_new' => $isNew,
            'refund' => $refund,
            'refund_percent' => $refundPercent,
        ];
    }
}
