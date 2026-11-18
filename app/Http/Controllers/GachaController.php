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

/**
 * Controlador de la pantalla principal y de las acciones de juego: tirar y cobrar la bonificación.
 *
 * Valida la entrada HTTP, recupera el jugador que inyectó el middleware y delega el
 * azar y la economía en GachaService. Las reglas en sí no viven aquí para que el
 * mismo flujo pueda reutilizarse desde otro punto de entrada.
 */
class GachaController extends Controller
{
    /**
     * Muestra las últimas tiradas y una vitrina de los hallazgos más recientes.
     *
     * El orden de "actualizado más recientemente" se conserva en las claves de la
     * colección y se reaplica después del whereIn(), porque SQL no garantiza orden
     * para conjuntos de IDs. La vitrina pasa por toCard() para no filtrar datos de
     * personajes todavía no descubiertos.
     */
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

    /**
     * Valida el count permitido y delega la tirada al servicio de dominio.
     *
     * Restringir el valor a 1 o a multi_pull evita que un count manipulado cambie
     * el precio. El resultado viaja en el flash de sesión para que la animación de
     * revelado sobreviva a la redirección y no se repita al recargar (POST/Redirect/GET).
     */
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

    /**
     * Acredita la bonificación de monedas respetando el cooldown del jugador.
     *
     * canClaimBonus() decide en función de bonus_claimed_at y se persiste la marca
     * de tiempo apenas se cobra: así una recarga o un doble clic no pueden otorgar
     * la bonificación dos veces.
     */
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

    /**
     * Recupera el jugador que EnsurePlayer colocó en los atributos de la request.
     *
     * Centralizar esta lectura evita repetir la llave del atributo en cada acción
     * y hace explícito que la identidad es responsabilidad del middleware.
     */
    private function player(Request $request): Player
    {
        return $request->attributes->get('player');
    }
}
