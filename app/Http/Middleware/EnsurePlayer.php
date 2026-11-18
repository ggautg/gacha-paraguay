<?php

namespace App\Http\Middleware;

use App\Models\Player;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que identifica al jugador de forma anónima con una cookie persistente.
 *
 * El juego no exige registro: una cookie UUID de dos años identifica al coleccionista
 * y crea un Player con el saldo inicial la primera vez. Dejar el modelo en los atributos
 * de la request evita que cada controlador repita la búsqueda por token.
 */
class EnsurePlayer
{
    /**
     * Identifica al jugador con una cookie de larga duración y lo crea si no existe.
     *
     * La vigencia de dos años permite que el progreso sobreviva a cierres del navegador.
     * Se usa un UUID en lugar de un ID incremental para no adivinar ni exponer cuántos
     * jugadores hay; si la cookie apunta a un registro borrado, se emite una identidad
     * nueva en vez de fallar la petición.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('gacha_player');

        $player = $token
            ? Player::query()->where('token', $token)->first()
            : null;

        if (! $player) {
            $token = (string) Str::uuid();

            $player = Player::query()->create([
                'token' => $token,
                'name' => 'Coleccionista',
                'coins' => (int) config('gachapon.economy.starting_coins'),
            ]);

            Cookie::queue('gacha_player', $token, 60 * 24 * 365 * 2);
        }

        $request->attributes->set('player', $player);

        return $next($request);
    }
}
