# Backend Laravel

## Índice

- [Modelo de ejecución](#modelo-de-ejecución)
- [Hallazgos](#hallazgos)
- [Verificación disponible](#verificación-disponible)
- [Buenas prácticas del backend](#buenas-prácticas-del-backend)

---

## Modelo de ejecución

El backend es un monolito Laravel sin API separada:

1. `EnsurePlayer` resuelve o crea al jugador antes de cada request del grupo `web`.
2. `HandleInertiaRequests` publica `player`, `gacha` y `flash` como props compartidas.
3. `GachaController` valida la entrada HTTP y delega en `GachaService`.
4. `GachaService` ejecuta azar, economía y persistencia dentro de una transacción con `lockForUpdate()`.
5. `GachaPresenter` y `Character::toCard()` dan forma a los datos y aplican anti-spoiler.

Las reglas económicas viven en `config/gachapon.php:686-710`; no están duplicadas en PHP ni en Vue.

---

## Hallazgos

<a id="be-01"></a>
### [BE-01] Identidad anónima persistente sin revocación ni rotación

- **Severidad:** Media
- **Capa:** Backend / Seguridad
- **Referencia:** OWASP A07 / CWE-613 (Insufficient Session Expiration), CWE-539 (Use of Persistent Cookies)
- **Ubicación:** `app/Http/Middleware/EnsurePlayer.php:31-47`, `bootstrap/app.php:16-19`
- **Descripción:** el identificador `gacha_player` se emite una vez con vigencia de dos años y nunca se rota ni se puede revocar. Si la cookie se copia a otro navegador, esa identidad rulea el saldo, el `pity` y la colección hasta que expire o se borre la fila manualmente.
- **Evidencia:**
  ```php
  // EnsurePlayer.php:31-35
  $token = $request->cookie('gacha_player');

  $player = $token
      ? Player::query()->where('token', $token)->first()
      : null;

  // EnsurePlayer.php:46
  Cookie::queue('gacha_player', $token, 60 * 24 * 365 * 2);
  ```
- **Impacto / Por qué importa:** es equivalente a una credencial de larga duración sin cierre de sesión. En equipos compartidos, un segundo usuario hereda todo el progreso; ante robo de cookie no hay revocación. Se agrava con CFG-04 (sin `secure` explícito).
- **Escenario de explotación o fallo:** un usuario abre el sitio en una PC pública; otra persona reutiliza el perfil del navegador y gasta los 3 SM iniciales. El dueño original no tiene forma de invalidar ese token desde la interfaz.
- **Recomendación:** acortar la vigencia, marcar `secure`/`httpOnly`/`sameSite` de forma explícita y ofrecer una acción «reinciar mi progreso» que rote el token y borre dependencias en la misma transacción. Si se agrega cuenta de usuario, rotar el token al iniciar sesión.
- **Ejemplo de corrección:**
  ```php
  // before
  Cookie::queue('gacha_player', $token, 60 * 24 * 365 * 2);

  // after
  Cookie::queue(
      'gacha_player',
      $token,
      60 * 24 * 30,
      secure: config('app.env') === 'production',
      httpOnly: true,
      sameSite: 'lax',
  );
  ```
  ```php
  // after: revocation endpoint (sketch)
  DB::transaction(function () use ($player): void {
      $player->collection()->delete();
      $player->pulls()->delete();
      $player->update([
          'token' => (string) Str::uuid(),
          'coins' => (int) config('gachapon.economy.starting_coins'),
          'total_pulls' => 0,
          'pity' => 0,
          'bonus_claimed_at' => null,
      ]);
  });
  ```
- **Esfuerzo estimado:** M
- **Estado de verificación:** Verificado en código y en vivo: la cookie generada por la app usa `httponly; samesite=lax` y es cifrada; el comportamiento en un navegador real y con HTTPS sigue **NO VERIFICADO**.

<a id="be-02"></a>
### [BE-02] `gacha:import --fresh` confirma por defecto `true`

- **Severidad:** Media
- **Capa:** Backend / Operaciones
- **Referencia:** CWE-1188 (Insecure Default Initialization), CWE-676 (Use of Potentially Dangerous Function)
- **Ubicación:** `app/Console/Commands/ImportCharacters.php:82-100`, `README.md:108`
- **Descripción:** la opción destructiva `--fresh` preconfigura la confirmación en `true`. En una ejecución no interactiva (`--no-interaction`, cron, CI o script) la respuesta por defecto acepta y borra tiradas, colecciones, saldos y retratos sin intervención humana.
- **Evidencia:**
  ```php
  // ImportCharacters.php:82-97
  if ($this->option('fresh')) {
      if (! $this->confirm('Se borrarán el catálogo, las colecciones y los saldos de todos los jugadores. ¿Continuar?', true)) {
          return self::FAILURE;
      }

      DB::transaction(function () {
          Pull::query()->delete();
          CollectionEntry::query()->delete();
          Character::query()->delete();
          Player::query()->update([...]);
      });
  }
  ```
- **Impacto / Por qué importa:** una sola opción mal pasada en automatización destruye todo el historial de auditoría y el progreso de los jugadores. El riesgo no es teórico: el README documenta el comando como una operación de uso corriente.
- **Escenario de explotación o fallo:** se programa `php artisan gacha:import --fresh --no-interaction` para «refrescar» el catálogo; la confirmación se acepta sola y se pierden todas las tiradas.
- **Recomendación:** cambiar el default a `false` y exigir además un flag explícito (`--force`) cuando no hay TTY. Documentar `--fresh` como operación de mantenimiento, no de rutina.
- **Ejemplo de corrección:**
  ```php
  // before
  if (! $this->confirm('...', true)) {
      return self::FAILURE;
  }

  // after
  if (! $this->confirm('...', false)) {
      return self::FAILURE;
  }
  ```
  ```php
  // after: hard guard for non-interactive runs
  if ($this->option('fresh') && ! $this->input->isInteractive() && ! $this->option('force')) {
      $this->error('--fresh requires --force in non-interactive mode.');

      return self::FAILURE;
  }
  ```
- **Esfuerzo estimado:** S
- **Estado de verificación:** Verificado.

<a id="be-03"></a>
### [BE-03] Retratos importados sin validación real de MIME ni límite de tamaño

- **Severidad:** Baja
- **Capa:** Backend / Seguridad
- **Referencia:** OWASP A08 / CWE-434 (Unrestricted Upload of File with Dangerous Type), CWE-400 (Uncontrolled Resource Consumption)
- **Ubicación:** `app/Console/Commands/ImportCharacters.php:315-372`
- **Descripción:** el comando acepta cualquier cuerpo de respuestauccessful mayor a 1.000 bytes y guarda el archivo con una extensión derivada de la URL. No verifica que los bytes sean una imagen ni impone un tamaño máximo.
- **Evidencia:**
  ```php
  // ImportCharacters.php:340-341
  if ($response->successful() && strlen($response->body()) > 1_000) {
      return $this->storeImage($response, $url, $slug);
  }

  // ImportCharacters.php:364-369
  $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
  $extension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';
  $path = "characters/{$slug}.{$extension}";
  Storage::disk('public')->put($path, $response->body());
  ```
- **Impacto / Por qué importa:** una respuesta HTML o SVG disfrazada de `.jpg` se sirve desde `/storage`; sin CFG-03 (`nosniff`) el navegador puede interpretarla como HTML. Además, una respuesta muy grande llena el disco. La URL sale de la API de Wikipedia, por lo que hoy es una fuente de confianza moderada, no un upload del usuario.
- **Escenario de explotación o fallo:** Wikimedia o un proxy devuelve una página de error de 50 KB con extensión `.jpg`; el archivo se guarda y se sirve con contenido no/imagen.
- **Recomendación:** validar los bytes con `getimagesizefromstring()` o `finfo`, limitar el cuerpo a un máximo razonable, derivar la extensión del MIME detectado y servir `/storage` con `X-Content-Type-Options: nosniff`.
- **Ejemplo de corrección:**
  ```php
  // before
  if ($response->successful() && strlen($response->body()) > 1_000) {
      return $this->storeImage($response, $url, $slug);
  }

  // after
  $body = $response->body();
  if ($response->successful()
      && strlen($body) > 1_000
      && strlen($body) <= 5_000_000
      && getimagesizefromstring($body) !== false) {
      return $this->storeImage($body, $slug);
  }
  ```
- **Esfuerzo estimado:** S/M
- **Estado de verificación:** Verificado en repo; contenido real devuelto por Wikipedia **NO VERIFICADO** (no se ejecutó la importación).

<a id="be-04"></a>
### [BE-04] Catálogo completo cargado en cada tirada

- **Severidad:** Baja
- **Capa:** Backend / Rendimiento
- **Referencia:** CWE-1049 (Excessive Data Query)
- **Ubicación:** `app/Services/GachaService.php:55-57`
- **Descripción:** `pull()` consulta las 84 filas de `characters` y las agrupa por rareza en cada petición, aunque la configuración del catálogo cambia solo durante una importación.
- **Evidencia:**
  ```php
  // GachaService.php:55-57
  // Una sola consulta alimenta todos los sorteos de la tirada múltiple.
  /** @var Collection<int, Collection<int, Character>> $pools */
  $pools = Character::query()->get()->groupBy('rarity');
  ```
- **Impacto / Por qué importa:** con 84 figuras el costo es insignificante y la decisión es defendible. Se registra para evitar que se replique si el catálogo crece o si se consulta por tirada dentro de un bucle. Bajo la carga asumida no es un problema operativo.
- **Escenario de explotación o fallo:** un pico de tiradas con miles de requests por minuto multiplica una consulta innecesaria; o un futuro catálogo de 10.000 personajes hace que cada tirada transfiera demasiadas filas.
- **Recomendación:** si el catálogo crece, memoizar los pozos en caché con TTL e invalidarlos al terminar `gacha:import`. No es prioritario ahora.
- **Ejemplo de corrección:**
  ```php
  // before
  $pools = Character::query()->get()->groupBy('rarity');

  // after
  $pools = Cache::remember('gacha.pools', now()->addHour(), fn () => Character::query()->get()->groupBy('rarity'));
  ```
  ```php
  // after: in ImportCharacters::handle(), after updateOrCreate loop
  Cache::forget('gacha.pools');
  ```
- **Esfuerzo estimado:** S
- **Estado de verificación:** Verificado; impacto real **NO MEDIDO** (sin perfiles ni tráfico).

<a id="be-05"></a>
### [BE-05] Código de andamiaje sin uso en el dominio del juego

- **Severidad:** Informativa
- **Capa:** Backend / Mantenibilidad
- **Referencia:** CWE-1164 (Irrelevant Code)
- **Ubicación:** `app/Models/User.php:13-19`, `app/Providers/AppServiceProvider.php:13-35`, `app/Http/Controllers/Controller.php:11-13`, `routes/console.php:6-7`
- **Descripción:** quedaron artefactos del scaffold inicial de Laravel: `User` (el docblock admite que no participa), `AppServiceProvider` vacío, `Controller` base vacío y el comando `inspire`. No generan riesgo directo, pero amplían la superficie que hay que leer y mantener.
- **Evidencia:**
  ```php
  // User.php:13-18
  /**
   * Modelo de usuario autenticable conservado del andamiaje inicial de Laravel.
   *
   * No participa en el flujo del juego: los jugadores se identifican con una cookie
   * mediante EnsurePlayer y el modelo Player.
   */
  ```
  ```php
  // AppServiceProvider.php:21-24
  public function register(): void
  {
      //
  }
  ```
- **Impacto / Por qué importa:** confusión para nuevos desarrolladores y dependencias innecesarias (`DatabaseSeeder` crea un `Test User`, ver QA-04). Si se conservan, deben estar documentados como decisiones deliberadas.
- **Escenario de explotación o fallo:** un desarrollador agrega lógica de autenticación en `User` creyendo que es la identidad del juego y rompe el flujo anónimo.
- **Recomendación:** eliminar `User`, `UserFactory`, `DatabaseSeeder` y el comando `inspire`, o dejar una nota explícita en `docs/ARQUITECTURA.md` de que se conservan solo como extensión futura.
- **Ejemplo de corrección:**
  ```text
  // before
  app/Models/User.php
  database/factories/UserFactory.php
  database/seeders/DatabaseSeeder.php
  routes/console.php -> Artisan::command('inspire', ...)

  // after
  (remove the four scaffold artifacts; keep routes/console.php only if a real command exists)
  ```
- **Esfuerzo estimado:** S
- **Estado de verificación:** Verificado.

<a id="be-06"></a>
### [BE-06] Reglas económicas sin comprobación de coherencia

- **Severidad:** Informativa
- **Capa:** Backend / Configuración
- **Referencia:** CWE-20 (Improper Input Validation) aplicado a configuración, no a usuario
- **Ubicación:** `config/gachapon.php:686-710`, `app/Services/GachaService.php:37-43,144-158`
- **Descripción:** el servicio confía en que `rates` suma 100, que `price_multi` es `price_single × multi_pull`, que `pity` es positivo y que cada rareza tiene pozo. No hay validación ni comando que verifique esas invariantes tras editar la configuración.
- **Evidencia:**
  ```php
  // config/gachapon.php
  'price_single' => 3044000,
  'price_multi' => 30440000,
  'rates' => [1 => 65, 2 => 27, 3 => 8],
  'pity' => 12,
  'multi_pull' => 10,
  ```
  ```php
  // GachaService.php:151-159
  foreach (config('gachapon.rates') as $rarity => $rate) {
      $cumulative += (int) $rate;
      if ($roll <= $cumulative) {
          return (int) $rarity;
      }
  }
  return 1;
  ```
- **Impacto / Por qué importa:** si alguien cambia una tasa sin ajustar el resto, el último rarity absorbe el sobrante o el fallback `return 1` distorsiona las probabilidades sin aviso. El sistema sigue funcionando, pero la economía publicada deja de ser la real.
- **Escenario de explotación o fallo:** se sube `rates[3]` de 8 a 15 sin bajar `rates[1]`; la suma da 107 y la probabilidad efectiva deja de coincidir con `RarityRates.vue`.
- **Recomendación:** agregar un comando `gacha:config:check` ejecutado en CI y antes de desplegar, que valide suma de tasas, positividad de `pity`, multiples de precios y existencia de al menos un personaje por rareza.
- **Ejemplo de corrección:**
  ```php
  // after: app/Console/Commands/CheckGachaConfig.php (excerpt)
  $rates = config('gachapon.rates');
  if (array_sum($rates) !== 100) {
      $this->error('Rates must add up to 100.');
      return self::FAILURE;
  }

  $single = (int) config('gachapon.economy.price_single');
  $multi = (int) config('gachapon.economy.price_multi');
  if ($multi !== $single * (int) config('gachapon.multi_pull')) {
      $this->error('price_multi must equal price_single * multi_pull.');
      return self::FAILURE;
  }
  ```
- **Esfuerzo estimado:** S
- **Estado de verificación:** Verificado; no se encontró validación equivalente en el repo.

---

## Verificación disponible

| Comando | Resultado |
|---|---|
| `php -l` sobre los 15 archivos de `app/` | Sin errores de sintaxis |
| `php artisan about --only=environment` | Laravel 13.38.0 / PHP 8.3.36 / entorno `local` |
| `php artisan route:list --json --no-ansi` | 9 rutas registradas; `/tirar` y `/bonificacion` sin `throttle` |
| `php artisan migrate --force` en `gacha_paraguay_test` | 6 migraciones aplicadas desde cero |
| `php artisan test` con `DB_CONNECTION=mysql` | 10 pruebas / 55 aserciones |
| Revisión de `DB::raw` / `whereRaw` / `selectRaw` | Solo dos `selectRaw` con columnas fijas en `GachaPresenter.php:32,38`; ninguno con input del usuario |
| Revisión de `$request->all()` | Sin coincidencias |
| Revisión de `eval`, `exec`, `shell_exec`, `unserialize` | Sin coincidencias |

### Concurrencia verificada en vivo

Se sirvió la app contra `gacha_paraguay_test`, se emitió una cookie `gacha_player` real y se ajustó ese jugador a `coins=3044000` (precio exacto de una tirada). Después se enviaron dos `POST /tirar` simultáneos con la misma sesión y tokens CSRF válidos:

```text
concurrent_http_codes=302/302
id  coins  total_pulls  pity  pulls
16  0      1            1     1
```

El resultado confirma que el segundo proceso vio el saldo ya debitado y fue rechazado: no hubo doble gasto ni doble registro. La debilidad que queda es de **cobertura automatizada**, no de comportamiento (QA-01).

### Hallazgo operativo detectado

Las 72 figuras con `image_path` apuntan a `/storage/characters/...`, pero `storage/app/public/characters` está vacío y no existe `public/storage`. `GET /storage/characters/kiveve.jpg` respondió `403` porque el disco `local` exige firma. Se documenta como **OPS-04** en [06-dependencias-devops.md](06-dependencias-devops.md#ops-04).

---

## Buenas prácticas del backend

- **Transacciones correctas:** `GachaService.php:45-47` y `GachaController.php:101-105` bloquean la fila del jugador con `lockForUpdate()` y revalidan saldo/cooldown dentro de la transacción.
- **Azar seguro:** `random_int()` en `GachaService.php:148,186`, con `pity` determinista en `GachaService.php:144-146`.
- **Validación de entrada:** `count` se restringe a `{1, multi_pull}` en `GachaController.php:77-79`; el servicio vuelve a normalizarlo en `GachaService.php:37-38`.
- **PRG:** las mutaciones redirigen y el resultado viaja en flash (`GachaController.php:83-85,117`), evitando reenvío al recargar.
- **Anti-spoiler centralizado:** `Character::toCard()` (`Character.php:81-98`) es el único productor de tarjetas; imagen y extracto se ocultan si no hay entrada de colección.
- **Ausencia de SQL dinámico del usuario:** todas las consultas usan Query Builder/Eloquent con parámetros fijos.
