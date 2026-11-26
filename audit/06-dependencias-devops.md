# Dependencias, pruebas, CI/CD y observabilidad

## Índice

- [Versiones verificadas](#versiones-verificadas)
- [Dependencias](#dependencias)
- [Pruebas](#pruebas)
- [CI/CD y despliegue](#cicd-y-despliegue)
- [Observabilidad y continuidad](#observabilidad-y-continuidad)
- [Comandos ejecutados](#comandos-ejecutados)

---

## Versiones verificadas

| Herramienta / paquete | Versión | Fuente |
|---|---|---|
| PHP local | 8.3.36 | `php -v` |
| Composer | 2.10.2 | `composer --version` |
| Node local | 26.13.0 | `node -v` (README pide 20+) |
| npm | 11.20.0 | `npm --version` |
| MySQL cliente y servidor | 8.0.35 | `mysql --version` y `SELECT VERSION()` en vivo; versión de producción **NO VERIFICADA** |
| laravel/framework | v13.38.0 | `composer.lock` |
| inertiajs/inertia-laravel | v3.5.2 | `composer.lock` |
| symfony/http-foundation | v7.4.21 | `composer.lock` |
| symfony/http-kernel | v7.4.21 | `composer.lock` |
| symfony/mailer / mime | v7.4.19 | `composer.lock` |
| symfony/routing | v7.4.21 | `composer.lock` |
| vue | 3.5.44 | `package-lock.json` |
| @inertiajs/vue3 | 3.9.1 | `package-lock.json` |
| vite | 7.3.7 | `package-lock.json` |
| tailwindcss | 4.3.3 | `package-lock.json` |
| PHPUnit / Pint / Pail | 12.5.40 / 1.32.1 / 1.2.7 | `composer.lock` |

---

## Dependencias

<a id="dep-01"></a>
### [DEP-01] 14 avisos de seguridad en dependencias PHP, incluido Laravel

- **Severidad:** Alta
- **Capa:** Dependencias / Seguridad
- **Referencia:** OWASP A06 (Vulnerable and Outdated Components) / CWE-1104 (Use of Unmaintained Third Party Components)
- **Ubicación:** `composer.lock`, `composer.json:8-21`; salida de `composer audit --locked`
- **Descripción:** el lockfile fija `laravel/framework v13.38.0` y componentes Symfony `v7.4.19/21`. `composer audit --locked` reporta **14 avisos en 6 paquetes**. Laravel está por debajo de `13.40.0`, versión corregida de `CVE-2026-128083`.
- **Evidencia:**
  ```text
  $ composer audit --locked --format=plain
  Found 14 security vulnerability advisories affecting 6 packages:

  laravel/framework      low      CVE-2026-128083  Task Output Injection - Concurrency Process
  symfony/http-foundation          CVE-2026-121619  private_ranges trusted proxies shortcut
  symfony/http-foundation          CVE-2026-133748  NoPrivateNetworkHttpClient custom subnets
  symfony/http-foundation          CVE-2026-135802  Generated URLs point to client-chosen host
  symfony/http-foundation          CVE-2026-135801  Path rules bypassed via script-name suffix
  symfony/http-foundation          CVE-2026-118514  HEAD bypass of access_control methods
  symfony/http-kernel              CVE-2026-133743  Inline fragments change asset() URLs
  symfony/http-kernel              CVE-2026-123339  Forwarded headers set host/scheme/port
  symfony/mailer                   CVE-2026-133751  require_tls bypass (MITM)
  symfony/mailer                   CVE-2026-133755  Reply injection after STARTTLS
  symfony/mime          medium   CVE-2026-45070    Email header injection
  symfony/routing                  CVE-2026-133746  UrlGenerator host override
  symfony/routing                  CVE-2026-133754  Host placeholders send URLs to other host
  symfony/routing                  CVE-2026-118516  Host placeholders bypass path access control
  ```
  ```php
  // composer.json:11
  "laravel/framework": "^13.0",
  ```
- **Impacto / Por qué importa:** varios advisories afectan la generación de URLs con host controlado por el cliente y cabeceras reenviadas — justo el tipo de fallo que NO se mitiga con el código actual porque no hay trusted proxies versionados (CFG-06). Mailer no se usa hoy (`.env.example` usa `log`), pero queda como superficie latente. Tener 14 avisos conocidos con parche disponible es el hallazgo más accionable de la auditoría.
- **Escenario de explotación o fallo:** detrás de un reverse proxy, una cabecera `Host` o `X-Forwarded-*` manipulada genera enlaces absolutos a un dominio del atacante en correos o respuestas; o un job con salida controlada inyecta contenido en la consola de ConcurrencyProcess.
- **Recomendación:** actualizar a versiones corregidas (`laravel/framework >= 13.40.0` y las versiones de Symfony indicadas en cada advisory), regenerar `composer.lock`, ejecutar la suite y añadir `composer audit --locked` como gate de CI. Revisar `TRUSTED_PROXIES` al desplegar (CFG-06).
- **Ejemplo de corrección:**
  ```bash
  # before
  composer install
  # no vulnerability gate

  # after
  composer update laravel/framework symfony/http-foundation symfony/http-kernel \
                 symfony/mailer symfony/mime symfony/routing
  composer audit --locked
  php artisan test
  ```
  ```yaml
  # after: CI gate
  - name: Audit PHP dependencies
    run: composer audit --locked
  ```
- **Esfuerzo estimado:** S/M
- **Estado de verificación:** Verificado con `composer audit --locked` tras instalar dependencias; impacto explotable en producción **NO VERIFICADO** (sin despliegue real).

<a id="dep-02"></a>
### [DEP-02] `minimum-stability: dev` en `composer.json`

- **Severidad:** Baja
- **Capa:** Dependencias
- **Referencia:** CWE-1104 / CWE-16 (Configuration)
- **Ubicación:** `composer.json:84-85`
- **Descripción:** el proyecto permite versiones `dev` aunque `prefer-stable: true` mitiga el efecto. Combinado con la ausencia de CI, un `composer update` descuidado podría incorporar una rama de desarrollo.
- **Evidencia:**
  ```json
  "minimum-stability": "dev",
  "prefer-stable": true
  ```
- **Impacto / Por qué importa:** introduce un camino para que dependencias inestables entren al lockfile. Hoy no se observa ningún paquete `-dev` en producción, así que es preventivo.
- **Escenario de explotación o fallo:** un desarrollador agrega un paquete sin restricción de versión y Composer resuelve una rama `dev-main`; el build pasa localmente pero falla en producción.
- **Recomendación:** fijar `"minimum-stability": "stable"` y eliminar `prefer-stable`, o documentar por qué se necesita `dev`.
- **Ejemplo de corrección:**
  ```json
  // before
  "minimum-stability": "dev",
  "prefer-stable": true

  // after
  "minimum-stability": "stable"
  ```
- **Esfuerzo estimado:** S
- **Estado de verificación:** Verificado.

<a id="dep-03"></a>
### [DEP-03] Axios expuesto en `window` sin uso y sin linting de JS

- **Severidad:** Informativa
- **Capa:** Dependencias / Frontend
- **Referencia:** CWE-1164 (Irrelevant Code)
- **Ubicación:** `resources/js/bootstrap.js:1-4`, `package.json:9-21`, `resources/js/app.js:1-37`
- **Descripción:** `bootstrap.js` asigna Axios a `window.axios` y configura una cabecera, pero `app.js` no importa ese archivo y el código usa `router`/`useForm` de Inertia. Tampoco hay ESLint ni TypeScript.
- **Evidencia:**
  ```js
  // bootstrap.js:1-4
  import axios from 'axios';
  window.axios = axios;
  window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  ```
  ```text
  git grep for "bootstrap.js|window.axios" -> only the file itself
  ```
- **Impacto / Por qué importa:** paquete y global innecesarios; `npm audit` reporta 0 vulnerabilidades, pero no hay análisis estático que detecte errores de props o imports muertos en Vue.
- **Escenario de explotación o fallo:** un desarrollador usa `window.axios` creyendo que es la vía oficial y saltea el manejo de errores de Inertia.
- **Recomendación:** eliminar `bootstrap.js` y la dependencia `axios` si no se planea usarlos; si se agrega linting, configurar ESLint con `vue-eslint-parser`.
- **Ejemplo de corrección:**
  ```text
  // before
  resources/js/bootstrap.js
  package.json -> "axios": "^1.11.0"

  // after
  (remove both; keep Inertia router as the only HTTP path)
  ```
- **Esfuerzo estimado:** S
- **Estado de verificación:** Verificado por lectura y búsqueda en el repo.

---

## Pruebas

<a id="qa-01"></a>
### [QA-01] Pruebas con SQLite: `lockForUpdate()` no se ejercita

- **Severidad:** Media
- **Capa:** Pruebas / Concurrencia
- **Referencia:** CWE-362 (Race Condition) / CWE-1126 (Declaration of Redundant Exception Condition)
- **Ubicación:** `phpunit.xml:26-27`, `app/Services/GachaService.php:45-47`, `app/Http/Controllers/GachaController.php:101-105`
- **Descripción:** la suite corre sobre SQLite en memoria. SQLite no implementa `SELECT ... FOR UPDATE`; el bloqueo pesimista que protege saldo y `pity` se ignora silenciosamente en las pruebas.
- **Evidencia:**
  ```xml
  <!-- phpunit.xml:26-27 -->
  <env name="DB_CONNECTION" value="sqlite"/>
  <env name="DB_DATABASE" value=":memory:"/>
  ```
  ```php
  // GachaService.php:45-47
  return DB::transaction(function () use ($player, $count, $price) {
      $player = Player::query()->lockForUpdate()->findOrFail($player->getKey());
  ```
- **Impacto / Por qué importa:** las 10 pruebas pasan aunque la concurrencia estuviera rota. La corrección más importante del sistema (doble gasto / doble bonificación) carece de cobertura real. `README.md:181` informa 10 pruebas / 55 aserciones, pero no dice que corren sobre SQLite.
- **Escenario de explotación o fallo:** alguien refactoriza `GachaService` y quita `lockForUpdate()`; CI sigue verde y en producción dos peticiones simultáneas cobran dos veces el mismo saldo.
- **Recomendación:** crear una base MySQL de pruebas y ejecutar la suite contra ella; agregar al menos una prueba de concurrencia con dos conexiones/hilos que intenten gastar el mismo saldo y la misma bonificación. Mantener SQLite solo para pruebas unitarias puras.
- **Ejemplo de corrección:**
  ```xml
  <!-- before -->
  <env name="DB_CONNECTION" value="sqlite"/>
  <env name="DB_DATABASE" value=":memory:"/>

  <!-- after -->
  <env name="DB_CONNECTION" value="mysql"/>
  <env name="DB_DATABASE" value="gacha_paraguay_test"/>
  ```
  ```php
  // after: concurrency smoke test (sketch)
  $player = $this->makePlayer(coins: (int) config('gachapon.economy.price_single'));
  $results = collect([1, 2])->map(fn () => Http::post('/tirar', ['count' => 1]));

  $this->assertSame(1, $player->refresh()->total_pulls);
  $this->assertDatabaseCount('pulls', 1);
  ```
- **Esfuerzo estimado:** M
- **Estado de verificación:** Configuración verificada y **ejecutada en vivo** sobre `gacha_paraguay_test` con `DB_CONNECTION=mysql`: 10 pruebas / 55 aserciones. Además, dos `POST /tirar` simultáneos con saldo exacto dejaron `coins=0`, `total_pulls=1` y `pulls=1`, confirmando que `lockForUpdate()` funciona. El hallazgo se mantiene por **falta de cobertura automatizada**, no por comportamiento observado.

<a id="qa-02"></a>
### [QA-02] Sin prueba HTTP de `/tirar` ni aserción fuerte de anti-spoiler

- **Severidad:** Media
- **Capa:** Pruebas
- **Referencia:** CWE-1120 (Excessive Code Complexity / missing negative test) / OWASP A01
- **Ubicación:** `tests/Feature/GachaTest.php:62-105`, `routes/web.php:8-9`
- **Descripción:** las pruebas de tirada invocan `GachaService` directamente; ninguna hace `post('/tirar')` con cookie. La prueba de colección solo verifica `characters.0.owned === false`, sin comprobar que `image` e `extract` sean `null`.
- **Evidencia:**
  ```php
  // GachaTest.php:81
  $result = app(GachaService::class)->pull($player, 1);
  ```
  ```php
  // GachaTest.php:67-73
  $this->get('/coleccion')
      ->assertOk()
      ->assertInertia(fn (Assert $page) => $page
          ->component('Album')
          ->has('characters', 3)
          ->where('characters.0.owned', false)
      );
  ```
- **Impacto / Por qué importa:** no se cubren validación de `count`, CSRF, flujo PRG, cookie de jugador ni el contrato completo de anti-spoiler. Una regresión que filtre el retrato de una figura no descubierta pasaría inadvertida.
- **Escenario de explotación o fallo:** alguien cambia `toCard()` para enviar siempre `image`; las pruebas siguen verdes y la colección revela todas las figuras antes de obtenerlas.
- **Recomendación:** agregar pruebas de HTTP para `/tirar` (válida, count inválido, saldo insuficiente, flash `pull`) y endurecer la prueba de `/coleccion` con `where('characters.0.image', null)` y `where('characters.0.extract', null)`.
- **Ejemplo de corrección:**
  ```php
  // after
  $this->withCookie('gacha_player', $player->token)
      ->post('/tirar', ['count' => 1])
      ->assertRedirect()
      ->assertSessionHas('pull.results');

  $this->get('/coleccion')
      ->assertInertia(fn (Assert $page) => $page
          ->where('characters.0.image', null)
          ->where('characters.0.extract', null)
      );
  ```
- **Esfuerzo estimado:** S/M
- **Estado de verificación:** Verificado por lectura de la suite y por comprobación manual en vivo: `POST /tirar` sin CSRF devolvió `419`, con CSRF devolvió `302`, y `GET /coleccion` envió `"image":null,"extract":null` para las figuras no obtenidas. La **prueba automatizada** sigue ausente.

<a id="qa-03"></a>
### [QA-03] Prueba unitaria placeholder sin valor

- **Severidad:** Baja
- **Capa:** Pruebas
- **Referencia:** CWE-1164 (Irrelevant Code)
- **Ubicación:** `tests/Unit/ExampleTest.php:7-15`
- **Descripción:** la única prueba unitaria verifica `assertTrue(true)`; no aporta cobertura de `Salary`, `rarity` o presentadores.
- **Evidencia:**
  ```php
  // ExampleTest.php:12-15
  public function test_that_true_is_true(): void
  {
      $this->assertTrue(true);
  }
  ```
- **Impacto / Por qué importa:** da una falsa sensación de cobertura unitaria y mantiene una dependencia de `PHPUnit\Framework\TestCase` que no ejercita código del proyecto.
- **Escenario de explotación o fallo:** un reporte de cobertura cuenta 1 prueba unitaria «verde» mientras `Salary::format()` cambia y nadie lo nota.
- **Recomendación:** eliminarla o reemplazarla por pruebas de `Salary::format()` y `GachaPresenter::reveal()`.
- **Ejemplo de corrección:**
  ```php
  // after
  public function test_salary_format_trims_decimal_zeros(): void
  {
      config()->set('gachapon.salary.amount', 3044000);

      $this->assertSame('1 SM', Salary::format(3044000));
      $this->assertSame('0,2 SM', Salary::format(608800));
  }
  ```
- **Esfuerzo estimado:** S
- **Estado de verificación:** Verificado.

<a id="qa-04"></a>
### [QA-04] Seeder crea `Test User` con contraseña conocida

- **Severidad:** Baja
- **Capa:** Pruebas / Datos
- **Referencia:** CWE-798 (Use of Hard-coded Credentials) en datos de ejemplo
- **Ubicación:** `database/seeders/DatabaseSeeder.php:20-23`, `database/factories/UserFactory.php:31`
- **Descripción:** el seeder por defecto crea un usuario con `email=test@example.com` y contraseña `password`. No se usa en el flujo del juego (BE-05), pero si se ejecuta en un entorno compartido deja una cuenta débil.
- **Evidencia:**
  ```php
  // DatabaseSeeder.php:20-23
  User::factory()->create([
      'name' => 'Test User',
      'email' => 'test@example.com',
  ]);
  ```
  ```php
  // UserFactory.php:31
  'password' => static::$password ??= Hash::make('password'),
  ```
- **Impacto / Por qué importa:** riesgo bajo porque `User` no participa en el juego y no hay rutas `auth`. Se vuelve relevante si se habilita autenticación sin revisar el seeder.
- **Escenario de explotación o fallo:** se despliega con `db:seed` en un entorno de staging accesible y alguien inicia sesión con `test@example.com` / `password`.
- **Recomendación:** eliminar el seeder junto con `User`/`UserFactory` (BE-05) o envolverlo en `if (app()->environment('local'))` y usar una contraseña aleatoria.
- **Ejemplo de corrección:**
  ```php
  // after
  public function run(): void
  {
      if (! app()->environment('local')) {
          return;
      }

      // Seed local demo data with Hash::make(Str::random(32)).
  }
  ```
- **Esfuerzo estimado:** S
- **Estado de verificación:** Verificado.

---

## CI/CD y despliegue

<a id="ops-01"></a>
### [OPS-01] Sin pipeline de CI que ejecute pruebas, build ni auditorías

- **Severidad:** Media
- **Capa:** DevOps
- **Referencia:** CWE-1164 / OWASP A05 (Security Misconfiguration)
- **Ubicación:** `composer.json:34-50`, `package.json:5-8`, `README.md:170-181`; ausencia de `.github/` en `git ls-files`
- **Descripción:** existen scripts sueltos (`composer test`, `npm run build`, `npm audit`), pero no hay workflow que los ejecute en cada push/PR. El README documenta el estado «verificado en la última revisión» como texto manual.
- **Evidencia:**
  ```text
  $ git ls-files | Select-String -Pattern "\.github|Dockerfile|docker-compose|\.nvmrc"
  (no matches)
  ```
  ```json
  // composer.json:47-50
  "test": [
      "@php artisan config:clear --ansi",
      "@php artisan test"
  ],
  ```
- **Impacto / Por qué importa:** nada impide combinar un commit que rompe pruebas, baja el build o reintroduce una dependencia vulnerable. La verificación depende de que alguien recuerde ejecutar los comandos.
- **Escenario de explotación o fallo:** se mergea un cambio que actualiza `config/gachapon.php` con tasas que no suman 100 (BE-06); nadie lo detecta hasta que los jugadores reportan probabilidades raras.
- **Recomendación:** agregar un workflow de GitHub Actions (o equivalente) con matriz PHP 8.3/Node 20 que instale dependencias, corra lint, auditorías, pruebas MySQL y build. Publicar los resultados como check obligatorio.
- **Ejemplo de corrección:**
  ```yaml
  # after: .github/workflows/ci.yml
  name: CI
  on: [push, pull_request]

  jobs:
    php:
      runs-on: ubuntu-latest
      steps:
        - uses: actions/checkout@v4
        - uses: shivammathur/setup-php@v2
          with: { php-version: '8.3' }
        - run: composer install --no-interaction --prefer-dist
        - run: composer audit --locked
        - run: php artisan test
        - run: find app -name '*.php' -print0 | xargs -0 -n1 php -l

    js:
      runs-on: ubuntu-latest
      steps:
        - uses: actions/checkout@v4
        - uses: actions/setup-node@v4
          with: { node-version: '20' }
        - run: npm ci
        - run: npm audit --audit-level=low
        - run: npm run build
  ```
- **Esfuerzo estimado:** M
- **Estado de verificación:** Verificado; no se encontró configuración de CI versionada.

---

## Observabilidad y continuidad

<a id="ops-02"></a>
### [OPS-02] Sin backups probados, RTO/RPO definidos ni alertas

- **Severidad:** Media
- **Capa:** DevOps / Continuidad
- **Referencia:** CWE-1188 / ISO 27001 A.8.13 (Information backup)
- **Ubicación:** `README.md:65-99,181`, `config/logging.php:55-74`; ausencia de scripts de backup en `git ls-files`
- **Descripción:** la documentación cubre instalación local y dice que la BD quedó configurada en la máquina, pero no define cómo se respalda `gacha_paraguay`, los retratos de `storage/` ni `.env`. Tampoco hay alertas por error, discos llenos o caída de MySQL.
- **Evidencia:**
  ```text
  README.md:181 -> "MySQL 8.0.35 quedó disponible con la base gacha_paraguay...
  La contraseña local solo vive en .env."
  (no backup/restore script, no RTO/RPO, no alerting config in git)
  ```
  ```php
  // config/logging.php:55-74 -> default stack uses 'single' channel
  ```
- **Impacto / Por qué importa:** una pérdida de disco o un `DROP` accidental (agravado por CFG-02) puede ser irreversible. Sin alertas, la caída se descubre por un jugador, no por el operador.
- **Escenario de explotación o fallo:** se ejecuta `gacha:import --fresh` por error (BE-02); no existe copia de `pulls` ni de `collection_entries` para restaurar.
- **Recomendación:** definir RTO/RPO, programar `mysqldump` diario con retención, incluir `storage/app/public/characters` y una copia cifrada de `.env` fuera del servidor, hacer un restore drill mensual y configurar alertas de log/disco/health.
- **Ejemplo de corrección:**
  ```bash
  # after: /etc/cron.daily/gacha-backup (sketch)
  DAY="$(date +%F)"
  mysqldump -u gacha_app -p"$DB_PASSWORD" gacha_paraguay \
    | gzip > "/backup/mysql/gacha_${DAY}.sql.gz"
  tar -czf "/backup/storage/gacha_${DAY}.tar.gz" \
    /var/www/gacha/storage/app/public/characters
  find /backup -type f -mtime +30 -delete
  ```
- **Esfuerzo estimado:** M
- **Estado de verificación:** Ausencia verificada en repo, en `Get-ScheduledTask` (solo tareas de Windows, ninguna de `gacha`) y en `information_schema.EVENTS` de `gacha_paraguay` (sin eventos). Existen `log_bin=ON` y `slow_query_log=ON`, pero no hay copias ni restore drill. Backups en otro servidor siguen **NO VERIFICADOS**.

<a id="ops-03"></a>
### [OPS-03] Health check `/up` no comprueba dependencias

- **Severidad:** Baja
- **Capa:** DevOps / Observabilidad
- **Referencia:** CWE-754 (Improper Check for Unusual Conditions)
- **Ubicación:** `bootstrap/app.php:10-14`
- **Descripción:** Laravel registra `health: '/up'`, que responde 200 si el proceso PHP arranca. No consulta MySQL, caché ni storage, aunque la mayoría de las páginas dependen de la BD.
- **Evidencia:**
  ```php
  // bootstrap/app.php:10-14
  ->withRouting(
      web: __DIR__.'/../routes/web.php',
      commands: __DIR__.'/../routes/console.php',
      health: '/up',
  )
  ```
- **Impacto / Por qué importa:** el balanceador o monitor puede marcar «saludable» una instancia cuya BD está caída y seguir enviando tráfico que termina en 500.
- **Escenario de explotación o fallo:** MySQL se queda sin conexiones; `/up` sigue verde, las tiradas fallan y nadie recibe alerta hasta que un usuario se queja.
- **Recomendación:** mantener `/up` para el balanceador y agregar `/health/ready` que ejecute `DB::select('select 1')` y verifique `Storage::disk('public')`, con timeout corto. Conectar la alerta a ese endpoint (OPS-02).
- **Ejemplo de corrección:**
  ```php
  // after: routes/web.php
  Route::get('/health/ready', function () {
      DB::select('select 1');
      Storage::disk('public')->files('characters');

      return response()->json(['status' => 'ok']);
  })->middleware('throttle:10,1');
  ```
- **Esfuerzo estimado:** S
- **Estado de verificación:** Verificado en repo y en vivo: `curl .../up` devolvió `200` sin consultar dependencias.

<a id="ops-04"></a>
### [OPS-04] Retratos referenciados sin archivos ni enlace de storage en el entorno auditado

- **Severidad:** Media
- **Capa:** DevOps / Despliegue
- **Referencia:** CWE-1188 (Insecure Default Initialization of Resource) / OWASP A05 (Security Misconfiguration)
- **Ubicación:** `config/filesystems.php:41-48`, `app/Models/Character.php:63-71`; estado de `storage/app/public/characters` y `public/storage`
- **Descripción:** la BD de `gacha_paraguay` tiene 84 personajes, de los cuales 72 guardan `image_path` con valor `characters/*.jpg` y 12 con `NULL`. En este workspace `storage/app/public/characters` no contiene archivos y `public/storage` no existe. Las URLs `/storage/characters/...` que genera `imageUrl()` no tienen contenido servible; la ruta del framework `ServeFile` respondió `403` porque el disco `local` exige firma. La aplicación y el catálogo están correctos; falta el artefacto de despliegue.
- **Evidencia:**
  ```text
  $ SELECT image_path, COUNT(*) FROM characters GROUP BY image_path ORDER BY 2 DESC LIMIT 1;
  NULL                                  12
  characters/jose-gaspar-rodriguez-de-francia.jpg  1
  ...
  72 non-null paths / 84 characters

  $ Get-ChildItem storage/app/public/characters -File
  Count = 0

  $ Test-Path public/storage
  False

  $ curl -o NUL -w '%{http_code}' http://127.0.0.1:8000/storage/characters/kiveve.jpg
  403
  ```
  ```php
  // Character.php:69-70
  // URL relativa para que funcione en cualquier host (localhost, 127.0.0.1, producción).
  return '/storage/'.ltrim($this->image_path, '/');
  ```
- **Impacto / Por qué importa:** las fichas recién descubiertas muestran el fallback en vez del retrato real (`CharacterPortrait.vue:32-44`). La colección conserva nombres y rarezas, pero pierde parte de la experiencia visual. No es una fuga de datos ni un fallo de código.
- **Escenario de explotación o fallo:** se despliega el proyecto sin ejecutar `php artisan storage:link` ni importar imágenes; los jugadores ven 72 tarjetas con placeholder y reportan que «no carga nada».
- **Recomendación:** ejecutar `php artisan storage:link` y `php artisan gacha:import --missing` en el entorno de despliegue; verificar un muestreo de URLs con `curl -I` y agregar un check de deploy que confirme que `Storage::disk('public')->exists()` para varias rutas del catálogo. Documentar que las imágenes se sirven desde storage propio, no por hotlink.
- **Ejemplo de corrección:**
  ```bash
  # before
  # sin enlace público ni archivos en storage/app/public/characters

  # after
  php artisan storage:link
  php artisan gacha:import --missing
  curl -I http://localhost/storage/characters/kiveve.jpg   # expected: 200 image/jpeg
  ```
- **Esfuerzo estimado:** S/M
- **Estado de verificación:** Verificado en el workspace local; el estado de retratos en producción es **NO VERIFICADO**.

---

## Comandos ejecutados

| Comando | Resultado |
|---|---|
| `composer validate --no-check-publish --no-check-lock` | `./composer.json is valid` |
| `composer install --no-interaction --prefer-dist` | 110 paquetes instalados; autoload y `package:discover` correctos |
| `composer audit --locked --format=plain` | 14 advisories / 6 paquetes (ver DEP-01) |
| `npm install --no-audit --no-fund` | 110 paquetes; `package-lock.json` restaurado a su versión versionada |
| `npm audit --package-lock-only --audit-level=low` | `found 0 vulnerabilities` |
| `npm run build` | 649 módulos transformados, build correcto |
| `php artisan key:generate --force` | `APP_KEY` generada en `.env` local (ignorado) |
| `php artisan about --only=environment` | Laravel 13.38.0 / PHP 8.3.36 / `local` |
| `php artisan route:list --json --no-ansi` | 9 rutas; sin `throttle` en rutas mutables |
| `php artisan migrate --force` en `gacha_paraguay_test` | 6 migraciones aplicadas |
| `php artisan test` con `DB_CONNECTION=mysql` | 10 pruebas / 55 aserciones |
| Dos `POST /tirar` concurrentes con saldo exacto | `302/302`; `coins=0`, `total_pulls=1`, `pulls=1` |
| `curl` sobre `/`, `/coleccion`, `/up` y `/storage/...` | Headers, anti-spoiler, `200` y `403` respectivamente |
| `SHOW GRANTS`, `SHOW VARIABLES`, `EXPLAIN`, `COUNT(*)`, `SHOW INDEX` | Evidencia de MySQL 8.0.35 en vivo |
| `php -l` sobre 15 archivos de `app/` | Sin errores |
| `git diff --check` + `git status --short` | Sin diff de check; único cambio: `?? audit/` |

Pint y PHPStan no se ejecutaron: no estaban instalados y no se agregaron herramientas nuevas. Las verificaciones de producción (TLS, WAF, backups reales, usuarios de BD de producción) permanecen fuera del entorno auditado.
