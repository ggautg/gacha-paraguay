<?php

namespace App\Http\Middleware;

use App\Models\Player;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlayer
{
    /**
     * Identifica al jugador con una cookie de larga duración y lo crea si no existe.
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
