<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CollectionEntry;
use App\Models\Player;
use App\Models\Pull;
use App\Support\GachaPresenter;
use App\Support\Salary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio que resuelve la lógica del gacha: azar, economía y progreso de colección.
 *
 * Toda la operación transcurre en una transacción con bloqueo pesimista del jugador,
 * de modo que dos tiradas concurrentes no puedan gastar dos veces el mismo saldo ni
 * duplicar el contador de pity. Las decisiones de presentación se delegan en
 * GachaPresenter; aquí solo se produce el resultado.
 */
class GachaService
{
    /**
     * Ejecuta una tirada simple o múltiple y devuelve el resultado revelado.
     *
     * lockForUpdate() bloquea la fila del jugador durante la transacción: sin ese
     * bloqueo, dos peticiones simultáneas leerían el mismo saldo y podrían gastarlo
     * dos veces. El precio se determina según el count solicitado, pero se valida
     * contra el saldo ya bloqueado dentro de la transacción.
     *
     * @return array{results: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function pull(Player $player, int $count): array
    {
        $multi = (int) config('gachapon.multi_pull');
        $count = $count === $multi ? $multi : 1;

        // Defensa en profundidad: cualquier valor inesperado se cobra como tirada simple.
        $price = $count === 1
            ? (int) config('gachapon.economy.price_single')
            : (int) config('gachapon.economy.price_multi');

        return DB::transaction(function () use ($player, $count, $price) {
            // Releemos al jugador dentro de la transacción para operar sobre la fila bloqueada.
            $player = Player::query()->lockForUpdate()->findOrFail($player->getKey());

            if ($player->coins < $price) {
                throw ValidationException::withMessages([
                    'coins' => 'No te alcanza el sueldo: esta tirada cuesta '.Salary::format($price).' ('.Salary::guaranies($price).'). Reclamá la bonificación o volvé más tarde.',
                ]);
            }

            // Una sola consulta alimenta todos los sorteos de la tirada múltiple.
            /** @var Collection<int, Collection<int, Character>> $pools */
            $pools = Character::query()->get()->groupBy('rarity');

            if ($pools->isEmpty()) {
                throw ValidationException::withMessages([
                    'coins' => 'El catálogo está vacío. Ejecutá `php artisan gacha:import`.',
                ]);
            }

            $coins = $player->coins - $price;
            $usedIds = [];
            $results = [];
            $newCount = 0;
            $refundTotal = 0;

            for ($i = 0; $i < $count; $i++) {
                $rolledRarity = $this->rollRarity($player);
                $character = $this->pickCharacter($pools, $rolledRarity, $usedIds);

                // Evita que la misma tirada múltiple repita personajes.
                $usedIds[] = $character->id;

                $rarity = (int) $character->rarity;

                $entry = CollectionEntry::query()->firstOrNew([
                    'player_id' => $player->id,
                    'character_id' => $character->id,
                ]);

                $isNew = ! $entry->exists;
                $refund = 0;
                $refundPercent = 0;

                if ($isNew) {
                    $entry->quantity = 1;
                    $entry->save();
                    $newCount++;
                } else {
                    $entry->increment('quantity');
                    // Duplicado: se devuelve parte del precio según la rareza (3★, el 100 %).
                    $refund = $player->duplicateRefund($rarity);
                    $refundPercent = $player->duplicateRefundPercent($rarity);
                    $refundTotal += $refund;
                }

                $entry->refresh();

                // Obtener 3★ reinicia la racha: la próxima garantía se cuenta desde cero.
                $player->pity = $rarity === 3 ? 0 : $player->pity + 1;

                Pull::query()->create([
                    'player_id' => $player->id,
                    'character_id' => $character->id,
                    'rarity' => $rarity,
                    'is_new' => $isNew,
                ]);

                $results[] = GachaPresenter::reveal($character, $entry, $isNew, $refund, $refundPercent);
            }

            // El saldo final descuenta el precio y suma los reembolsos acumulados.
            $player->coins = $coins + $refundTotal;
            $player->total_pulls += $count;
            $player->save();

            return [
                'results' => $results,
                'summary' => [
                    'spent' => $price,
                    'refund' => $refundTotal,
                    'new' => $newCount,
                    'coins' => $player->coins,
                    'pity' => $player->pity,
                    'pity_limit' => (int) config('gachapon.pity'),
                ],
            ];
        });
    }

    /**
     * Aplica la regla de pity antes de sortear con probabilidades acumuladas.
     *
     * Forzar 3★ al alcanzar el límite garantiza una racha máxima de tiradas sin
     * premio alto, algo que la sola probabilidad no asegura. random_int() se prefiere
     * a rand() porque el azar tiene impacto económico y conviene que sea impredecible.
     */
    private function rollRarity(Player $player): int
    {
        if ($player->pity + 1 >= (int) config('gachapon.pity')) {
            return 3;
        }

        $roll = random_int(1, 100);
        $cumulative = 0;

        foreach (config('gachapon.rates') as $rarity => $rate) {
            $cumulative += (int) $rate;

            if ($roll <= $cumulative) {
                return (int) $rarity;
            }
        }

        return 1;
    }

    /**
     * Elige un personaje del pozo de la rareza sorteada sin repetir los ya usados.
     *
     * Si el pozo de esa rareza está vacío se cae a todo el catálogo para no abortar
     * la tirada; si no queda nadie sin usar, se vuelve a permitir el pozo completo.
     *
     * @param  Collection<int, Collection<int, Character>>  $pools
     * @param  array<int, int>  $usedIds
     */
    private function pickCharacter(Collection $pools, int $rarity, array $usedIds): Character
    {
        $pool = $pools->get($rarity);

        if (! $pool || $pool->isEmpty()) {
            $pool = $pools->flatten(1);
        }

        // Evitamos repetir personajes dentro de la misma tirada múltiple.
        $available = $pool->reject(fn (Character $character) => in_array($character->id, $usedIds, true));

        if ($available->isEmpty()) {
            $available = $pool;
        }

        return $available->values()[random_int(0, $available->count() - 1)];
    }
}
