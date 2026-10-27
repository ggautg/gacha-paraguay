<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Player;
use App\Services\GachaService;
use App\Support\Salary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GachaController extends Controller
{
    public function index(Request $request): Response
    {
        $player = $this->player($request);

        $recent = $player->pulls()
            ->with('character')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn ($pull) => [
                'id' => $pull->id,
                'name' => $pull->character->name,
                'role' => $pull->character->role,
                'rarity' => $pull->rarity,
                'is_new' => $pull->is_new,
                'image' => $pull->character->imageUrl(),
                'at' => $pull->created_at?->diffForHumans(),
            ]);

        $entries = $player->collection()->latest('updated_at')->get()->keyBy('character_id');
        $showcase = Character::query()
            ->whereIn('id', $entries->take(6)->keys())
            ->get()
            ->sortBy(fn (Character $character) => $entries->keys()->search($character->id))
            ->values()
            ->map(fn (Character $character) => $character->toCard($entries->get($character->id)));

        return Inertia::render('Home', [
            'recent' => $recent,
            'showcase' => $showcase,
            'lastPull' => $request->session()->get('pull'),
        ]);
    }

    public function pull(Request $request, GachaService $gacha): RedirectResponse
    {
        $validated = $request->validate([
            'count' => ['required', 'integer', Rule::in([1, (int) config('gachapon.multi_pull')])],
        ]);

        $result = $gacha->pull($this->player($request), (int) $validated['count']);

        return redirect()
            ->route('home')
            ->with('pull', $result);
    }

    public function bonus(Request $request): RedirectResponse
    {
        $player = $this->player($request);

        if (! $player->canClaimBonus()) {
            return back()->withErrors([
                'bonus' => 'La bonificación todavía no está lista.',
            ]);
        }

        $coins = (int) config('gachapon.economy.bonus_coins');

        $player->coins += $coins;
        $player->bonus_claimed_at = now();
        $player->save();

        return back()->with('message', '¡Recibiste '.Salary::format($coins).' ('.Salary::guaranies($coins).')!');
    }

    private function player(Request $request): Player
    {
        return $request->attributes->get('player');
    }
}
