<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Player;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controlador de la colección: muestra el catálogo completo marcando lo que el jugador ya tiene.
 *
 * No filtra los personajes pendientes de descubrir: la colección debe exhibirlos como
 * bloqueados para que se perciba cuánto falta coleccionar. toCard() se encarga de
 * ocultar imagen y biografía de esos casos.
 */
class AlbumController extends Controller
{
    /**
     * Indexa la colección por character_id para resolver cada tarjeta en una búsqueda.
     *
     * keyBy('character_id') evita recorrer la colección por cada personaje del catálogo;
     * el acceso con get($character->id) mantiene el costo bajo incluso con el plantel completo.
     * El orden por rareza descendente y luego alfabético deja las figuras 3★ al principio.
     */
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
