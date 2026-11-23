<?php

namespace App\Http\Middleware;

use App\Models\Player;
use App\Support\GachaPresenter;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Middleware de Inertia que comparte con el frontend los datos globales del juego.
 *
 * Expone el jugador resuelto por EnsurePlayer junto con tasas, precios, pity y sueldo,
 * de modo que la UI no tenga que consultarlos por separado y las reglas económicas
 * tengan una sola fuente de verdad: config/gachapon.php.
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * Plantilla Blade raíz que se carga en la primera visita a la página.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determina la versión actual de los assets.
     *
     * Se delega en el padre para usar el manifiesto de Vite: así un cambio de assets
     * fuerza una recarga completa sin tener que mantener una versión a mano.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define las props que se comparten por defecto en todas las respuestas Inertia.
     *
     * El jugador se envía como closure para que Inertia lo resuelva en cada
     * respuesta y siempre refleje el saldo vigente. No se usa Inertia::lazy()
     * porque las dos páginas lo necesitan en la carga inicial. Los valores de
     * gachapon salen de config para que el frontend aplique exactamente las
     * mismas reglas que el servidor.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var Player|null $player */
        $player = $request->attributes->get('player');

        return [
            ...parent::share($request),
            'player' => fn () => $player ? GachaPresenter::player($player) : null,
            'gacha' => [
                'rates' => config('gachapon.rates'),
                'price_single' => (int) config('gachapon.economy.price_single'),
                'price_multi' => (int) config('gachapon.economy.price_multi'),
                'multi_pull' => (int) config('gachapon.multi_pull'),
                'pity' => (int) config('gachapon.pity'),
                'salary' => [
                    'amount' => (int) config('gachapon.salary.amount'),
                    'daily' => (int) config('gachapon.salary.daily'),
                    'decree' => config('gachapon.salary.decree'),
                    'since' => config('gachapon.salary.since'),
                ],
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
            ],
        ];
    }
}
