<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Player;
use App\Services\GachaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GachaTest extends TestCase
{
    use RefreshDatabase;

    private function makePlayer(?int $coins = null, int $pity = 0): Player
    {
        return Player::query()->create([
            'token' => (string) Str::uuid(),
            'name' => 'Test',
            'coins' => $coins ?? (int) config('gachapon.economy.starting_coins'),
            'pity' => $pity,
        ]);
    }

    private function seedCharacters(): void
    {
        foreach ([1, 2, 3] as $rarity) {
            Character::query()->create([
                'slug' => "personaje-{$rarity}",
                'name' => "Personaje {$rarity}",
                'wiki_title' => "Personaje {$rarity}",
                'wiki_url' => "https://es.wikipedia.org/wiki/Personaje_{$rarity}",
                'role' => 'Rol de prueba',
                'era' => '1900 â€“ 1950',
                'category' => 'Historia',
                'rarity' => $rarity,
                'extract' => 'BiografÃ­a de prueba.',
            ]);
        }
    }

    public function test_home_page_renders_with_the_gacha_machine(): void
    {
        $this->seedCharacters();
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->where('player.coins', (int) config('gachapon.economy.starting_coins'))
                ->where('player.stats.total', 3)
                ->has('recent', 0)
            );

        $this->assertDatabaseCount('players', 1);
    }

    public function test_album_page_lists_the_whole_catalog(): void
    {
        $this->seedCharacters();
        $this->withoutVite();

        $this->get('/coleccion')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Album')
                ->has('characters', 3)
                ->where('characters.0.owned', false)
            );
    }

    public function test_pull_charges_coins_and_unlocks_a_character(): void
    {
        $this->seedCharacters();
        $player = $this->makePlayer();

        $result = app(GachaService::class)->pull($player, 1);

        $this->assertCount(1, $result['results']);
        $this->assertSame(
            (int) config('gachapon.economy.starting_coins') - (int) config('gachapon.economy.price_single'),
            $player->refresh()->coins
        );
        $this->assertSame(1, $player->total_pulls);
        $this->assertDatabaseCount('pulls', 1);
        $this->assertDatabaseCount('collection_entries', 1);
    }

    public function test_multi_pull_charges_the_multi_price(): void
    {
        $this->seedCharacters();
        $player = $this->makePlayer(coins: (int) config('gachapon.economy.price_multi'));

        $result = app(GachaService::class)->pull($player, 10);

        $this->assertCount(10, $result['results']);
        $this->assertSame((int) config('gachapon.economy.price_multi'), $result['summary']['spent']);
        $this->assertSame($result['summary']['refund'], $player->refresh()->coins);
        $this->assertSame(10, $player->total_pulls);
        $this->assertDatabaseCount('pulls', 10);
    }

    public function test_it_refunds_part_of_a_duplicate(): void
    {
        $this->seedCharacters();
        Character::query()->where('rarity', '!=', 1)->delete();
        $this->assertSame(1, Character::query()->count());

        $player = $this->makePlayer();

        app(GachaService::class)->pull($player, 1);
        $result = app(GachaService::class)->pull($player, 1);

        $refund = (int) round(
            (int) config('gachapon.economy.price_single') * (int) config('gachapon.economy.duplicate_refund_percent.1') / 100
        );

        $this->assertFalse($result['results'][0]['is_new']);
        $this->assertSame($refund, $result['results'][0]['refund']);
        $this->assertSame((int) config('gachapon.economy.duplicate_refund_percent.1'), $result['results'][0]['refund_percent']);
        $this->assertSame(
            (int) config('gachapon.economy.starting_coins')
                - 2 * (int) config('gachapon.economy.price_single')
                + $refund,
            $player->refresh()->coins
        );
        $this->assertSame(2, $player->collection()->first()->quantity);
    }

    public function test_pity_guarantees_a_legendary(): void
    {
        $this->seedCharacters();
        $player = $this->makePlayer(pity: 11);

        $result = app(GachaService::class)->pull($player, 1);

        $this->assertSame(3, $result['results'][0]['rarity']);
        $this->assertSame(0, $player->refresh()->pity);
    }

    public function test_it_rejects_pulls_without_enough_coins(): void
    {
        $this->seedCharacters();
        $player = $this->makePlayer(coins: (int) config('gachapon.economy.price_single') - 1);

        $this->expectException(ValidationException::class);

        app(GachaService::class)->pull($player, 1);
    }

    public function test_portrait_urls_are_host_agnostic(): void
    {
        $this->seedCharacters();
        $character = Character::query()->firstOrFail();
        $character->update(['image_path' => 'characters/personaje-1.jpg']);

        $this->assertSame('/storage/characters/personaje-1.jpg', $character->fresh()->imageUrl());
    }

    public function test_daily_bonus_adds_coins_once(): void
    {
        $this->seedCharacters();
        $player = $this->makePlayer();

        $this->withCookie('gacha_player', $player->token)
            ->post('/bonificacion')
            ->assertRedirect()
            ->assertSessionHas('message', '¡Recibiste 1 SM (₲ 3.044.000)!');

        $this->assertSame(
            (int) config('gachapon.economy.starting_coins') + (int) config('gachapon.economy.bonus_coins'),
            $player->refresh()->coins
        );
        $this->assertNotNull($player->bonus_claimed_at);

        $this->withCookie('gacha_player', $player->token)
            ->post('/bonificacion')
            ->assertSessionHasErrors('bonus');

        $this->assertSame(
            (int) config('gachapon.economy.starting_coins') + (int) config('gachapon.economy.bonus_coins'),
            $player->refresh()->coins
        );
    }
}
