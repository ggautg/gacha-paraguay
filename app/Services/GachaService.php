<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CollectionEntry;
use App\Models\Player;
use App\Models\Pull;
use App\Support\GachaPresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GachaService
{
    /**
     * Ejecuta una tirada simple o múltiple y devuelve el resultado revelado.
     *
     * @return array{results: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function pull(Player $player, int $count): array
    {
        $multi = (int) config('gachapon.multi_pull');
        $count = $count === $multi ? $multi : 1;

        $price = $count === 1
            ? (int) config('gachapon.economy.price_single')
            : (int) config('gachapon.economy.price_multi');

        return DB::transaction(function () use ($player, $count, $price) {
            $player = Player::query()->lockForUpdate()->findOrFail($player->getKey());

            if ($player->coins < $price) {
                throw ValidationException::withMessages([
                    'coins' => 'No te alcanzan las monedas. Reclamá la bonificación o volvé más tarde.',
                ]);
            }

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

                $usedIds[] = $character->id;

                $rarity = (int) $character->rarity;

                $entry = CollectionEntry::query()->firstOrNew([
                    'player_id' => $player->id,
                    'character_id' => $character->id,
                ]);

                $isNew = ! $entry->exists;
                $refund = 0;

                if ($isNew) {
                    $entry->quantity = 1;
                    $entry->save();
                    $newCount++;
                } else {
                    $entry->increment('quantity');
                    $refund = $player->duplicateRefund($rarity);
                    $refundTotal += $refund;
                }

                $entry->refresh();

                $player->pity = $rarity === 3 ? 0 : $player->pity + 1;

                Pull::query()->create([
                    'player_id' => $player->id,
                    'character_id' => $character->id,
                    'rarity' => $rarity,
                    'is_new' => $isNew,
                ]);

                $results[] = GachaPresenter::reveal($character, $entry, $isNew, $refund);
            }

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
