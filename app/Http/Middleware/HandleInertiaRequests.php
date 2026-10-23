<?php

namespace App\Http\Middleware;

use App\Models\Player;
use App\Support\GachaPresenter;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
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
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
            ],
        ];
    }
}
