<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Player;
use App\Services\GachaService;
use App\Support\Salary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
     * Acredita la bonificación respetando el cooldown del jugador.
     *
     * La fila se bloquea con lockForUpdate() y la elegibilidad se vuelve a
     * comprobar dentro de la transacción: sin ese bloqueo, dos peticiones
     * simultáneas leerían "bonificación lista" y acreditarían el doble.
     * canClaimBonus() se deriva de bonus_claimed_at, así que basta con grabar
     * esa marca en el mismo bloqueo para que un doble clic no cobre dos veces.
     */
    public function bonus(Request $request): RedirectResponse
    {
        $coins = (int) config('gachapon.economy.bonus_coins');

        DB::transaction(function () use ($request, $coins) {
            $player = Player::query()
                ->lockForUpdate()
                ->findOrFail($this->player($request)->getKey());

            if (! $player->canClaimBonus()) {
                throw ValidationException::withMessages([
                    'bonus' => 'La bonificación todavía no está lista.',
                ]);
            }

            $player->increment('coins', $coins);
            $player->bonus_claimed_at = now();
            $player->save();
        });

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
