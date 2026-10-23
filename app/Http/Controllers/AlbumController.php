<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Player;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlbumController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Player $player */
        $player = $request->attributes->get('player');

        $entries = $player->collection()->get()->keyBy('character_id');

        $characters = Character::query()
            ->orderByDesc('rarity')
            ->orderBy('name')
            ->get()
            ->map(fn (Character $character) => $character->toCard($entries->get($character->id)));

        return Inertia::render('Album', [
            'characters' => $characters,
        ]);
    }
}
