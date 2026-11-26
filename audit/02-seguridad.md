# Seguridad (todas las capas)

## Índice

- [Superficie de ataque](#superficie-de-ataque)
- [Mapa OWASP / CWE](#mapa-owasp--cwe)
- [Hallazgos de configuración](#hallazgos-de-configuración)
- [Hallazgos de API y rutas](#hallazgos-de-api-y-rutas)
- [Hallazgos de cumplimiento y datos](#hallazgos-de-cumplimiento-y-datos)
- [Hallazgos de seguridad en otras capas](#hallazgos-de-seguridad-en-otras-capas)
- [Controles correctos verificados](#controles-correctos-verificados)

---

## Superficie de ataque

| Entrada | Superficie | Protección observada |
|---|---|---|
| Navegador anónimo | `GET /`, `GET /coleccion` | Middleware `web`, cookie cifrada por Laravel, props Inertia serializadas |
| Acciones de juego | `POST /tirar`, `POST /bonificacion` | CSRF del grupo `web`, validación de `count`, transacción + `lockForUpdate()` |
| Identidad | Cookie `gacha_player` | UUID v4, única en BD, cifrado por `EncryptCookies` del grupo `web` (cifrado confirmado en vivo: una cookie inyectada a mano no fue reconocida) |
| Importación | Comando `gacha:import` | Solo ejecutable por consola; URLs fijas en `config`, sin input HTTP |
| Archivos | `storage/app/public/characters` | Sirve por `/storage`; sin validación real de MIME (BE-03) |

No se encontró: inyección SQL desde input, `v-html`, `{!! !!}`, `eval`, `exec`, `unserialize`, secretos en repo, webhooks ni tokens en `localStorage`.

---

## Mapa OWASP / CWE

| OWASP Top 10 | Estado | Hallazgos relacionados |
|---|---|---|
| A01 Broken Access Control | Parcial | BE-01, API-01, CFG-02 |
| A02 Cryptographic Failures | Parcial | CFG-01 (`APP_KEY`), CFG-04 (cookies) |
| A03 Injection | Sin hallazgo verificado | — |
| A04 Insecure Design | Parcial | BE-01, API-01 |
| A05 Security Misconfiguration | **Presente** | CFG-01, CFG-03, CFG-06, DEP-01 |
| A06 Vulnerable and Outdated Components | **Presente** | DEP-01 |
| A07 Identification and Authentication Failures | Parcial | BE-01 (identidad anónima) |
| A08 Software and Data Integrity Failures | Parcial | DEP-01, BE-03 |
| A09 Logging & Monitoring Failures | Presente | CFG-05, OPS-02 |
| A10 Server Side Request Forgery | No verificado / condicionado | BE-03 (URL de retrato proviene de respuesta de Wikipedia) |

---

## Hallazgos de configuración

<a id="cfg-01"></a>
### [CFG-01] Defaults de entorno peligrosos si se copian a producción
- **Severidad:** Alta
- **Capa:** Config
- **Referencia:** OWASP A05 / CWE-1188 (Insecure Default Initialization)
- **Ubicación:** `.env.example:1-5`, `config/app.php:29,42`, `config/session.php:172`
- **Descripción:** la plantilla de entorno define un perfil local con debug activo, clave vacía y URL `http://localhost`; no existe una plantilla de producción ni un checklist de deploy que impida copiar esos valores.
- **Evidencia:**
  ```dotenv
  APP_NAME="Gacha Guaraní"
  APP_ENV=local
  APP_KEY=
  APP_DEBUG=true
  APP_URL=http://localhost
  ```
  ```php
  'debug' => (bool) env('APP_DEBUG', false),
  ```
- **Impacto / Por qué importa:** con `APP_DEBUG=true`, Ignition puede mostrar stack traces, SQL y variables de entorno ante cualquier error. Con `APP_KEY` vacío fallan cifrado de cookies y sesiones. Con `APP_URL` mal configurado, las URLs generadas y las imágenes apuntan a `localhost`.
- **Escenario de explotación o fallo:** un atacante pide una ruta inexistente o provoca un error de BD; la página de error revela rutas internas y configuración.
- **Recomendación:** crear `.env.production.example` con valores seguros; agregar al pipeline un paso que falle si `APP_DEBUG=true`, `APP_KEY` vacío o `APP_URL` no HTTPS.
- **Ejemplo de corrección:**
  ```dotenv
  # before (.env.example)
  APP_ENV=local
  APP_KEY=
  APP_DEBUG=true
  APP_URL=http://localhost

  # after (.env.production.example)
  APP_ENV=production
  APP_KEY=base64:...
  APP_DEBUG=false
  APP_URL=https://gacha.example.py
  SESSION_SECURE_COOKIE=true
  LOG_LEVEL=warning
  ```
- **Esfuerzo estimado:** S
- **Estado de verificación:** Verificado en repo y en el `.env` local (debug activo, `APP_KEY` generada durante la re-auditoría); `.env` de producción **NO VERIFICADO**.

<a id="cfg-02"></a>
### [CFG-02] MySQL como `root` promovido por plantilla y documentación
- **Severidad:** Alta
- **Capa:** Config / BD
- **Referencia:** OWASP A01 / CWE-250 (Execution with Unnecessary Privileges)
- **Ubicación:** `.env.example:23-28`, `README.md:76-78`, `AGENTS.md:28`, `config/database.php:53-54`
- **Descripción:** la instalación documentada y el valor por defecto usan el usuario administrador `root` para la conexión de la aplicación.
- **Evidencia:**
  ```dotenv
  DB_USERNAME=root
  DB_PASSWORD=
  ```
  ```php
  'username' => env('DB_USERNAME', 'root'),
  ```
- **Impacto / Por qué importa:** cualquier fallo futuro de inyección, fuga de `.env` o error humano entrega privilegios totales: lectura de otras bases, `DROP`, creación de usuarios. También permite migraciones destructivas desde el mismo usuario de runtime.
- **Escenario de explotación o fallo:** se filtra la credencial; el atacante conecta con `root` desde la IP de la app y ejecuta `DROP DATABASE gacha_paraguay`.
- **Recomendación:** crear `gacha_app` con `SELECT, INSERT, UPDATE, DELETE` solo en `gacha_paraguay.*`; usar un usuario distinto con DDL para migraciones; restringir host y exigir TLS si la BD no está en el mismo host.
- **Ejemplo de corrección:**
  ```sql
  -- before
  DB_USERNAME=root

  -- after
  CREATE USER 'gacha_app'@'127.0.0.1' IDENTIFIED BY '<secreto>';
  GRANT SELECT, INSERT, UPDATE, DELETE ON gacha_paraguay.* TO 'gacha_app'@'127.0.0.1';
  ```
- **Esfuerzo estimado:** S/M
- **Estado de verificación:** **Remediado** el 26/11: la app usa `gacha_app` con solo DML en las bases del proyecto, sin `GRANT OPTION`, y el README documenta `gacha_migrate` para DDL. `SHOW GRANTS`, `migrate:status` y una escritura revertida en transacción, OK. Producción sigue **NO VERIFICADA**.

<a id="cfg-03"></a>
### [CFG-03] Sin cabeceras de seguridad HTTP
- **Severidad:** Media
- **Capa:** Config
- **Referencia:** OWASP A05 / CWE-693 (Protection Mechanism Failure), CWE-1021 (Clickjacking)
- **Ubicación:** `bootstrap/app.php:15-20`, `public/.htaccess:1-25`
- **Descripción:** el grupo `web` solo agrega `EnsurePlayer` y `HandleInertiaRequests`; no hay middleware de cabeceras y el `.htaccess` solo contiene rewrite rules.
- **Evidencia:**
  ```php
  $middleware->web(append: [
      EnsurePlayer::class,
      HandleInertiaRequests::class,
  ]);
  ```
- **Impacto / Por qué importa:** no hay CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` ni HSTS. Un XSS futuro tiene menos barreras y el sitio puede ser enmarcado por terceros.
- **Escenario de explotación o fallo:** un sitio malicioso incruye la app en un iframe transparente y guía clics sobre «Tirar ×10».
- **Recomendación:** agregar middleware `SecurityHeaders`; HSTS solo después de confirmar HTTPS. El CSP debe permitir el script inline de `app.blade.php` mediante nonce o moverlo a un archivo.
- **Ejemplo de corrección:**
  ```php
  // before: bootstrap/app.php
  $middleware->web(append: [EnsurePlayer::class, HandleInertiaRequests::class]);

  // after
  $middleware->web(append: [
      EnsurePlayer::class,
      HandleInertiaRequests::class,
      SecurityHeaders::class,
  ]);
  ```
  ```php
  // SecurityHeaders.php
  return $next($request)->withHeaders([
      'X-Content-Type-Options' => 'nosniff',
      'Referrer-Policy' => 'strict-origin-when-cross-origin',
      'Content-Security-Policy' => "frame-ancestors 'self'",
      'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
  ]);
  ```
- **Esfuerzo estimado:** M
- **Estado de verificación:** Verificado.

<a id="cfg-04"></a>
### [CFG-04] Cookies sin atributos `secure` explícitos
- **Severidad:** Media
- **Capa:** Config / Backend
- **Referencia:** OWASP A05 / CWE-614 (Sensitive Cookie Without 'Secure' Attribute)
- **Ubicación:** `.env.example:30-34`, `config/session.php:172,185,202`, `app/Http/Middleware/EnsurePlayer.php:46`
- **Descripción:** la cookie de jugador se crea sin parámetros explícitos de seguridad y el entorno de ejemplo no define `SESSION_SECURE_COOKIE`.
- **Evidencia:**
  ```dotenv
  SESSION_ENCRYPT=false
  SESSION_PATH=/
  SESSION_DOMAIN=null
  ```
  ```php
  'secure' => env('SESSION_SECURE_COOKIE'),
  ```
  ```php
  Cookie::queue('gacha_player', $token, 60 * 24 * 365 * 2);
  ```
- **Impacto / Por qué importa:** si el sitio es alcanzable por HTTP, la identidad del jugador viaja sin cifrar en la capa de transporte. Robar `gacha_player` equivale a robar saldo, `pity` y colección.
- **Escenario de explotación o fallo:** la víctima abre un enlace `http://…`; un atacante en la misma red captura la cookie y la reutiliza en su navegador.
- **Recomendación:** fijar `SESSION_SECURE_COOKIE=true` en producción, redirigir HTTP→HTTPS en el servidor y hacer explícitos `secure`, `httpOnly` y `sameSite` al crear la cookie.
- **Ejemplo de corrección:**
  ```php
  // before
  Cookie::queue('gacha_player', $token, 60 * 24 * 365 * 2);

  // after
  Cookie::queue('gacha_player', $token, 60 * 24 * 365 * 2,
      secure: config('app.env') === 'production',
      httpOnly: true,
      sameSite: 'lax'
  );
  ```
- **Esfuerzo estimado:** S
- **Estado de verificación:** código verificado y atributos observados por HTTP (`httponly; samesite=lax`, sin `Secure` en HTTP local). El valor de `Secure` en producción sigue **NO VERIFICADO**.

<a id="cfg-05"></a>
### [CFG-05] Log `debug` en archivo único sin rotación
- **Severidad:** Baja
- **Capa:** Config / DevOps
- **Referencia:** OWASP A09 / CWE-779 (Logging of Excessive Data)
- **Ubicación:** `.env.example:18-21`, `config/logging.php:55-74`
- **Descripción:** el canal por defecto es `stack → single` con nivel `debug`; el canal `daily` existe pero no se usa.
- **Evidencia:**
  ```dotenv
  LOG_CHANNEL=stack
  LOG_STACK=single
  LOG_LEVEL=debug
  ```
  ```php
  'single' => [
      'level' => env('LOG_LEVEL', 'debug'),
  ],
  ```
- **Impacto / Por qué importa:** `storage/logs/laravel.log` crece sin límite; ante un error repetido puede llenar el disco y detener sesiones y backups.
- **Escenario de explotación o fallo:** la BD cae, cada request escribe stack traces; en horas el disco se agota.
- **Recomendación:** producción con `LOG_LEVEL=warning`, `LOG_STACK=daily`, `LOG_DAILY_DAYS=30` y centralización de logs. Nunca registrar cookies/tokens.
- **Ejemplo de corrección:**
  ```dotenv
  # before
  LOG_STACK=single
  LOG_LEVEL=debug

  # after
  LOG_STACK=daily
  LOG_LEVEL=warning
  LOG_DAILY_DAYS=30
  ```
- **Esfuerzo estimado:** S
- **Estado de verificación:** Verificado.

<a id="cfg-06"></a>
### [CFG-06] Infraestructura y CORS no versionados
- **Severidad:** Informativa
- **Capa:** Config / DevOps
- **Referencia:** OWASP A05 / CWE-16 (Configuration)
- **Ubicación:** `config/` (sin `cors.php`), `bootstrap/app.php:15-20`, `public/.htaccess:1-25`
- **Descripción:** no hay Dockerfile, Nginx, `docker-compose`, `.github/` ni configuración CORS explícita. En el entorno local se comprobó que la app responde sin cabeceras de seguridad; la configuración del servidor de producción no puede comprobarse.
- **Evidencia:** `Get-ChildItem config` devuelve `app, auth, cache, database, filesystems, gachapon, logging, mail, queue, services, session`. No aparece `cors.php`.
- **Impacto / Por qué importa:** TLS, PHP-FPM, `php.ini`, WAF y CORS no son reproducibles ni auditables. Hoy no hay API, pero un futuro endpoint podría quedar con una política laxa por defecto.
- **Escenario de explotación o fallo:** se agrega una API sin revisar defaults y un sitio tercero puede consumirla con credenciales del navegador.
- **Recomendación:** versionar la configuración del servidor; publicar `config/cors.php` con orígenes explícitos si se agrega API; verificar headers reales con `curl -I`.
- **Ejemplo de corrección:**
  ```php
  // config/cors.php (si se agrega API)
  'allowed_origins' => ['https://gacha.example.py'],
  'supports_credentials' => true,
  ```
- **Esfuerzo estimado:** M
- **Estado de verificación:** Ausencia de configuración versionada verificada y variables MySQL locales comprobadas; infraestructura, CORS y headers de producción **NO VERIFICADOS**.

---

## Hallazgos de API y rutas

<a id="api-01"></a>
### [API-01] Sin rate limiting en rutas públicas mutables
- **Severidad:** Media
- **Capa:** API
- **Referencia:** OWASP A04 / CWE-770 (Allocation of Resources Without Limits)
- **Ubicación:** `routes/web.php:7-10`, `bootstrap/app.php:15-20`, `app/Http/Middleware/EnsurePlayer.php:37-47`
- **Descripción:** las tres acciones públicas no tienen middleware `throttle` y una petición sin cookie crea un `Player` nuevo.
- **Evidencia:**
  ```php
  Route::post('/tirar', [GachaController::class, 'pull'])->name('pull');
  Route::post('/bonificacion', [GachaController::class, 'bonus'])->name('bonus');
  ```
  ```php
  if (! $player) {
      $token = (string) Str::uuid();
      $player = Player::query()->create([...]);
  }
  ```
- **Impacto / Por qué importa:** un script puede crear jugadores ilimitados, llenar `players` y `pulls`, y consumir CPU/transacciones sin autenticación. El costo para el atacante es casi cero.
- **Escenario de explotación o fallo:** bucle HTTP sin cookie contra `/tirar` y `/bonificacion` → miles de filas y bloqueos de fila en MySQL.
- **Recomendación:** aplicar `throttle` por IP y por jugador a las rutas mutables; limitar creación de jugadores; considerar CAPTCHA o prueba de trabajo para altas automatizadas.
- **Ejemplo de corrección:**
  ```php
  // before
  Route::post('/tirar', [GachaController::class, 'pull']);

  // after
  Route::post('/tirar', [GachaController::class, 'pull'])
      ->middleware('throttle:30,1');
  ```
- **Esfuerzo estimado:** S/M
- **Estado de verificación:** Verificado.

<a id="api-02"></a>
### [API-02] Sin versionado ni documentación de API
- **Severidad:** Informativa
- **Capa:** API
- **Referencia:** OWASP A05 / CWE-1059
- **Ubicación:** `routes/web.php:1-10`
- **Descripción:** no hay prefijo `/api/v1`, OpenAPI/Scribe ni contrato documentado. Es coherente con un monolito Inertia sin API pública.
- **Evidencia:** solo cuatro rutas `web`, todas sin prefijo de versión.
- **Impacto / Por qué importa:** si mañana se publica una API para móvil, no habrá contrato ni estrategia de compatibilidad.
- **Escenario de explotación o fallo:** un cambio en el payload de `pull` rompe clientes externos sin aviso.
- **Recomendación:** documentar que no hay API pública; si aparece, crear `/api/v1` con OpenAPI y versionado desde el inicio.
- **Ejemplo de corrección:**
  ```php
  // futuro
  Route::prefix('api/v1')->group(fn () => /* ... */);
  ```
- **Esfuerzo estimado:** M
- **Estado de verificación:** Verificado.

<a id="api-03"></a>
### [API-03] Health check `/up` expuesto públicamente
- **Severidad:** Informativa
- **Capa:** API
- **Referencia:** OWASP A05 / CWE-200
- **Ubicación:** `bootstrap/app.php:10-14`
- **Descripción:** Laravel registra `health: '/up'` por defecto.
- **Evidencia:**
  ```php
  ->withRouting(
      web: __DIR__.'/../routes/web.php',
      commands: __DIR__.'/../routes/console.php',
      health: '/up',
  )
  ```
- **Impacto / Por qué importa:** expone disponibilidad básica; no revela datos sensibles, pero debe considerarse en el monitor y en reglas de WAF.
- **Escenario de explotación o fallo:** un escáner descubre `/up` y confirma que el sistema está vivo.
- **Recomendación:** mantenerlo pero restringirlo a la IP del monitor o documentarlo como público intencional.
- **Ejemplo de corrección:**
  ```php
  'health' => '/up', // documentar como público o proteger en el balanceador
  ```
- **Esfuerzo estimado:** S
- **Estado de verificación:** Verificado.

---

## Hallazgos de cumplimiento y datos

<a id="comp-01"></a>
### [COMP-01] Identificador anónimo con retención de 2 años sin aviso ni borrado
- **Severidad:** Media
- **Capa:** Cumplimiento
- **Referencia:** OWASP A01 / GDPR Art. 5(1)(e) / LGPD Art. 15 — CWE-359 (Exposure of Private Personal Information)
- **Ubicación:** `app/Http/Middleware/EnsurePlayer.php:31-47`, `resources/views/app.blade.php:3-35`
- **Descripción:** la cookie `gacha_player` persiste dos años y crea un registro `Player` con progreso; no hay política de privacidad, consentimiento ni mecanismo de eliminación en la interfaz.
- **Evidencia:**
  ```php
  Cookie::queue('gacha_player', $token, 60 * 24 * 365 * 2);
  ```
  La plantilla Blade solo incluye metadatos, fuentes y licence de Wikipedia; no hay enlace a privacidad.
- **Impacto / Por qué importa:** un UUID persistente es un identificador en línea. Sin retención definida ni derecho de supresión, el sistema incumple buenas prácticas de protección de datos aunque no pida nombre ni correo.
- **Escenario de explotación o fallo:** un usuario pide que se borre su progreso; no existe flujo para localizar y eliminar su `Player`, `CollectionEntry` y `Pull`.
- **Recomendación:** publicar aviso de privacidad, definir retención, implementar endpoint autenticado por cookie para exportar/borrar datos y ampliar el crawler de limpieza.
- **Ejemplo de corrección:**
  ```php
  // ruta futura
  Route::delete('/mi-progreso', [PlayerController::class, 'destroy']);
  ```
- **Esfuerzo estimado:** M
- **Estado de verificación:** Verificado en repo; requisito legal local **NO VERIFICADO** (depende de asesoría jurídica).

---

## Hallazgos de seguridad en otras capas

Resumen de seguridad con detalle completo en el archivo de cada capa:

| ID | Título | Severidad | Detalle |
|---|---|---|---|
| BE-01 | Identidad anónima sin revocación ni rotación | Media | [03-backend.md](03-backend.md#be-01) |
| BE-02 | `--fresh` confirma por defecto `true` | Media | [03-backend.md](03-backend.md#be-02) |
| BE-03 | Imports sin MIME real ni tamaño máximo | Baja | [03-backend.md](03-backend.md#be-03) |
| DB-01 | Crecimiento ilimitado y sin purga | Media | [04-base-de-datos.md](04-base-de-datos.md#db-01) |
| FE-01 | Overlays sin focus trap | Media | [05-frontend.md](05-frontend.md#fe-01) |
| FE-02 | Google Fonts sin consentimiento ni CSP | Baja | [05-frontend.md](05-frontend.md#fe-02) |
| DEP-01 | 14 avisos de seguridad en dependencias PHP | Alta | [06-dependencias-devops.md](06-dependencias-devops.md#dep-01) |
| OPS-02 | Sin backups probados ni alertas | Media | [06-dependencias-devops.md](06-dependencias-devops.md#ops-02) |

---

## Controles correctos verificados

- **CSRF:** el grupo `web` incluye `ValidateCsrfToken`; Inertia envía `X-XSRF-TOKEN` automáticamente.
- **SQL:** no hay `DB::raw`, `whereRaw`, `orderByRaw` ni `selectRaw` con input del usuario. Los dos `selectRaw` existentes son agregaciones con columnas fijas (`GachaPresenter.php:32,38`).
- **Output:** no hay `{!! !!}` ni `v-html`; todo el texto dinámico se interpola con `{{ }}`.
- **Secretos:** no hay credenciales en el repo ni en el historial de Git.
- **Concurrencia:** tiradas y bonificación usan transacción + `lockForUpdate()`.
- **Azar:** `random_int()` en `GachaService.php:148,186` en vez de `rand()`.
- **Anti-spoiler:** `Character::toCard()` oculta imagen y extracto hasta descubrir la figura.

---

## Verificación en vivo (26 de noviembre de 2026)

Comprobaciones ejecutadas contra MySQL local y la app servida en `127.0.0.1`:

| ID | Evidencia en vivo | Resultado |
|---|---|---|
| CFG-01 | `.env` local creado desde `.env.example` con `APP_DEBUG=true` y `APP_KEY` generada | Defaults confirmados; producción **NO VERIFICADA** |
| CFG-02 | `SHOW GRANTS FOR 'root'@'localhost'` | `ALL PRIVILEGES ON *.* ... WITH GRANT OPTION` confirmado en el entorno local |
| CFG-03 | `curl -D -` sobre `/` | Sin `Content-Security-Policy`, `Strict-Transport-Security`, `X-Frame-Options`, `X-Content-Type-Options` ni `Referrer-Policy` |
| CFG-04 | `Set-Cookie` en `/` | `gacha_player` y la sesión con `httponly; samesite=lax`; sin `Secure` porque la prueba corre por HTTP local |
| CFG-06 | `SHOW VARIABLES` | `log_bin=ON`, `slow_query_log=ON`, `local_infile=OFF`, `sql_mode` estricto; infraestructura versionada sigue ausente |
| API-01 | `php artisan route:list --json --no-ansi` | `/tirar` y `/bonificacion` tienen solo middleware `web`, sin `throttle` |
| API-03 | `curl -o NUL -w '%{http_code}' .../up` | `200` público |
| COMP-01 | `SELECT COUNT(*)` y `Set-Cookie` | Al primer muestreo: 4 jugadores, 5 tiradas, 4 sesiones; cookie con `Max-Age=63072000` (2 años) |

```text
$ curl -D - -o NUL http://127.0.0.1:8000/
HTTP/1.1 200 OK
Cache-Control: no-cache, private
Content-Type: text/html; charset=utf-8
Set-Cookie: XSRF-TOKEN=<redact>; expires=...; Max-Age=7200; path=/; samesite=lax
Set-Cookie: gacha-guarani-session=<redact>; expires=...; Max-Age=7200; path=/; httponly; samesite=lax
Set-Cookie: gacha_player=<redact>; expires=...; Max-Age=63072000; path=/; httponly; samesite=lax
```

```text
$ curl -o NUL -w '%{http_code}' -X POST -d 'count=1' http://127.0.0.1:8000/tirar
419
```

El `419` confirma que el middleware CSRF rechaza peticiones sin token. Los valores de cookies se redactaron; no se registraron tokens ni credenciales.
