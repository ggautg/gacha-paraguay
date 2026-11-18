<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Models\CollectionEntry;
use App\Models\Player;
use App\Models\Pull;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Comando Artisan que puebla el catálogo de personajes desde Wikipedia.
 *
 * El plantel (nombres, roles, rarezas) vive en config/gachapon.php; este comando
 * solo resuelve el artículo y el retrato de cada entrada. Distingue la importación
 * normal de la curada —personajes sin artículo, con texto escrito a mano— y permite
 * reintentos parciales para no repetir trabajo de red ya realizado.
 */
class ImportCharacters extends Command
{
    protected $signature = 'gacha:import
                            {--fresh : Borra el catálogo y todo el progreso antes de importar}
                            {--missing : Reintenta solo los retratos que faltan}
                            {--no-images : Importa solo los datos de Wikipedia, sin retratos}
                            {--only= : Importa solo estos slugs, separados por coma}';

    protected $description = 'Importa los personajes históricos de Paraguay desde Wikipedia (nombre, retrato y biografía).';

    private const API = 'https://es.wikipedia.org/w/api.php';

    private const USER_AGENT = 'GachaGuarani/1.0 (proyecto local de demostración)';

    /**
     * Importa el plantel en lotes de 20 para no saturar la API de Wikipedia.
     *
     * Las combinaciones de opciones incompatibles se rechazan antes de tocar la base
     * de datos. Con --fresh, catálogo y progreso se reinician en una sola transacción
     * para no dejar estados a medias. Los retratos ya guardados se conservan siempre:
     * --missing limita la descarga a las fichas sin imagen, porque repetirla es lento
     * y consume cuota de Wikimedia sin aportar nada.
     */
    public function handle(): int
    {
        $roster = collect(config('gachapon.roster'));

        if ($roster->isEmpty()) {
            $this->error('El plantel en config/gachapon.php está vacío.');

            return self::FAILURE;
        }

        if ($this->option('fresh') && $this->option('only')) {
            $this->error('--fresh y --only no se pueden combinar.');

            return self::FAILURE;
        }

        if ($only = $this->option('only')) {
            $slugs = collect(explode(',', $only))
                ->map(fn (string $slug) => Str::slug(trim($slug)))
                ->filter()
                ->all();

            $roster = $roster
                ->filter(fn (array $spec) => in_array(Str::slug($spec['name']), $slugs, true))
                ->values();

            if ($roster->isEmpty()) {
                $this->error('Ningún personaje coincide con --only.');

                return self::FAILURE;
            }
        }

        if ($this->option('fresh')) {
            if (! $this->confirm('Se borrarán el catálogo, las colecciones y los saldos de todos los jugadores. ¿Continuar?', true)) {
                return self::FAILURE;
            }

            DB::transaction(function () {
                Pull::query()->delete();
                CollectionEntry::query()->delete();
                Character::query()->delete();
                Player::query()->update([
                    'coins' => (int) config('gachapon.economy.starting_coins'),
                    'total_pulls' => 0,
                    'pity' => 0,
                    'bonus_claimed_at' => null,
                ]);
            });

            Storage::disk('public')->delete(Storage::disk('public')->files('characters'));
            $this->info('Catálogo y progreso reiniciados.');
        }

        $imported = 0;
        $skipped = 0;
        $withoutImage = 0;
        $onlyMissing = (bool) $this->option('missing');

        foreach ($roster->chunk(20) as $chunk) {
            // Wikipedia acepta hasta 20 títulos por consulta, de ahí el tamaño del lote.
            $wikiTitles = $chunk->pluck('wiki')->filter()->values()->all();
            $pages = $wikiTitles === [] ? [] : $this->fetchPages($wikiTitles);

            foreach ($chunk as $spec) {
                if (blank($spec['wiki'] ?? null)) {
                    $this->importCurated($spec, $withoutImage);
                    $imported++;

                    continue;
                }

                $page = $this->resolvePage($pages, $spec['wiki']);

                if ($page === null) {
                    $this->warn("  · Sin artículo en Wikipedia: {$spec['wiki']}");
                    $skipped++;

                    continue;
                }

                $slug = Str::slug($spec['name']);
                $existing = Character::query()->where('slug', $slug)->first();

                $imagePath = $existing?->image_path;

                if (! $this->option('no-images') && (! $onlyMissing || ! $imagePath)) {
                    $imagePath = $this->downloadImage($page['thumbnail']['source'] ?? null, $slug) ?? $imagePath;
                    // Pausa entre retratos para no superar el límite de peticiones de Wikimedia.
                    usleep(400_000);
                }

                if (! $imagePath) {
                    $withoutImage++;
                }

                Character::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $spec['name'],
                        'wiki_title' => $page['title'],
                        'wiki_url' => 'https://es.wikipedia.org/wiki/'.str_replace(' ', '_', $page['title']),
                        'image_path' => $imagePath,
                        'extract' => $this->summarize($page['extract'] ?? ''),
                        'role' => $spec['role'],
                        'era' => $spec['era'],
                        'category' => $spec['category'],
                        'rarity' => (int) $spec['rarity'],
                    ]
                );

                $imported++;
                $this->line("  ✓ {$spec['name']} <fg=yellow>[{$spec['rarity']}★]</>");
            }

            if ($wikiTitles !== []) {
                // Respiro entre lotes de la API, además del aplicado a cada imagen.
                usleep(250_000);
            }
        }

        $this->newLine();
        $this->table(
            ['Importados', 'Sin artículo', 'Sin retrato', 'Total en catálogo'],
            [[$imported, $skipped, $withoutImage, Character::query()->count()]]
        );

        $this->newLine();
        $this->info('Listo. Ejecutá `php artisan storage:link` si todavía no lo hiciste.');

        return self::SUCCESS;
    }

    /**
     * Inserta o actualiza un personaje curado a mano (sin artículo de Wikipedia).
     *
     * Algunas entradas del plantel no tienen artículo resoluble, así que el texto y
     * el enlace biográficos se toman directamente de la configuración. Conserva el
     * retrato ya guardado para no perderlo en reimportaciones posteriores.
     *
     * @param  array<string, mixed>  $spec
     */
    private function importCurated(array $spec, int &$withoutImage): void
    {
        $slug = Str::slug($spec['name']);
        $existing = Character::query()->where('slug', $slug)->first();
        $imagePath = $existing?->image_path ?? ($spec['image'] ?? null);

        if (! $imagePath) {
            $withoutImage++;
        }

        Character::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $spec['name'],
                'wiki_title' => $spec['name'],
                'wiki_url' => $spec['link'] ?? '',
                'image_path' => $imagePath,
                'extract' => $spec['extract'] ?? null,
                'role' => $spec['role'],
                'era' => $spec['era'],
                'category' => $spec['category'],
                'rarity' => (int) $spec['rarity'],
            ]
        );

        $this->line("  ✓ {$spec['name']} <fg=yellow>[{$spec['rarity']}★]</> <fg=gray>(curado)</>");
    }

    /**
     * Consulta la API en lotes y reintenta con espera creciente ante fallos de red.
     *
     * Wikipedia normaliza títulos y aplica redirecciones, por eso se indexan las
     * páginas por título y se mapean de ida y vuelta: sin ese paso, los nombres del
     * plantel con mayúsculas o redirección caerían como "sin artículo".
     *
     * @param  array<int, string>  $titles
     * @return array<string, array<string, mixed>>
     */
    private function fetchPages(array $titles): array
    {
        $response = null;

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                    ->timeout(45)
                    ->get(self::API, [
                        'action' => 'query',
                        'format' => 'json',
                        'formatversion' => 2,
                        'prop' => 'pageimages|extracts',
                        'exintro' => 1,
                        'explaintext' => 1,
                        'piprop' => 'thumbnail',
                        'pithumbsize' => 900,
                        'redirects' => 1,
                        'titles' => implode('|', $titles),
                    ]);
            } catch (ConnectionException $exception) {
                $response = null;
                usleep(600_000 * $attempt);

                continue;
            }

            if ($response->successful()) {
                break;
            }

            $response = null;
            usleep(600_000 * $attempt);
        }

        if (! $response) {
            throw new RuntimeException('No se pudo contactar a la API de Wikipedia.');
        }

        $query = $response->json('query', []);
        // keyBy permite resolver cada título en O(1) en vez de buscarlo en la lista.
        $byTitle = collect($query['pages'] ?? [])->keyBy('title');

        $normalized = collect($query['normalized'] ?? [])->pluck('to', 'from');
        $redirects = collect($query['redirects'] ?? [])->pluck('to', 'from');

        $resolved = [];

        foreach ($titles as $title) {
            $candidate = $normalized[$title] ?? $title;
            $candidate = $redirects[$candidate] ?? $candidate;

            if ($byTitle->has($candidate)) {
                $resolved[$title] = $byTitle[$candidate];
            }
        }

        return $resolved;
    }

    /**
     * Devuelve la página resuelta o null cuando Wikipedia marca el artículo como ausente.
     *
     * Distinguir "no existe" de "sin extracto" evita crear fichas vacías en el catálogo.
     *
     * @param  array<string, array<string, mixed>>  $pages
     * @return array<string, mixed>|null
     */
    private function resolvePage(array $pages, string $title): ?array
    {
        $page = $pages[$title] ?? null;

        if (! $page || ($page['missing'] ?? false)) {
            return null;
        }

        return $page;
    }

    /**
     * Descarga el retrato con reintentos y respeta los límites de Wikimedia.
     *
     * La espera es mucho mayor después de un 429 porque el servicio limita por IP.
     * El Accept amplio permite recibir AVIF o WebP sin dependencias extra en el servidor;
     * una respuesta demasiado pequeña se descarta porque suele ser una página de error.
     */
    private function downloadImage(?string $url, string $slug): ?string
    {
        if (! $url) {
            return null;
        }

        $response = null;
        $status = null;

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                    'Referer' => 'https://es.wikipedia.org/',
                ])
                    ->timeout(20)
                    ->get($url);
            } catch (ConnectionException $exception) {
                $response = null;
                usleep(500_000 * $attempt);

                continue;
            }

            if ($response->successful() && strlen($response->body()) > 1_000) {
                return $this->storeImage($response, $url, $slug);
            }

            $status = $response->status();
            $response = null;

            // Wikimedia pide esperar bastante cuando devuelve 429.
            usleep($status === 429 ? 10_000_000 * $attempt : 500_000 * $attempt);
        }

        $this->warn("    No se pudo descargar el retrato de {$slug}".($status ? " (HTTP {$status})" : ''));

        return null;
    }

    /**
     * Guarda la imagen bajo characters/<slug> y normaliza la extensión del archivo.
     *
     * Se fuerza jpg como valor por defecto porque la URL de la miniatura no siempre
     * expone una extensión reconocible; así la ruta almacenada es predecible.
     */
    private function storeImage(Response $response, string $url, string $slug): string
    {
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $extension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';

        $path = "characters/{$slug}.{$extension}";

        Storage::disk('public')->put($path, $response->body());

        return $path;
    }

    /**
     * Recorta el extracto a 560 caracteres buscando un punto de corte natural.
     *
     * Quita las referencias numéricas "[1]" que Wikipedia inyecta y evita truncar a
     * mitad de frase. Se usan funciones mb_* para no partir caracteres con tilde o ñ.
     */
    private function summarize(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
        $text = preg_replace('/\[[0-9]+\]/u', '', $text) ?? '';

        if (mb_strlen($text) <= 560) {
            return $text;
        }

        $cut = mb_substr($text, 0, 560);
        $lastPeriod = mb_strrpos($cut, '.');

        if ($lastPeriod !== false && $lastPeriod > 320) {
            $cut = mb_substr($cut, 0, $lastPeriod + 1);
        }

        return trim($cut).'…';
    }
}
