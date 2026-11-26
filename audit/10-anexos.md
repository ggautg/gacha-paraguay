# Anexos

## Índice

- [Propósito de este anexo](#propósito-de-este-anexo)
- [Alcance acordado](#alcance-acordado)
- [Estado del workspace](#estado-del-workspace)
- [Inventario del entregable](#inventario-del-entregable)
- [Comandos ejecutados](#comandos-ejecutados)
- [Comandos no ejecutados y motivo](#comandos-no-ejecutados-y-motivo)
- [Estado de verificación por hallazgo](#estado-de-verificación-por-hallazgo)
- [Evidencia clave por capa](#evidencia-clave-por-capa)
- [Exclusiones](#exclusiones)
- [Glosario](#glosario)

---

## Propósito de este anexo

Registrar **qué se miró, qué se ejecutó, qué no se pudo verificar y por qué**, para que otro auditor pueda reproducir el trabajo sin repetir las decisiones de alcance. Todo hallazgo del entregable cita `archivo:línea` y fragmento de código; cuando no fue posible, se marcó `NO VERIFICADO` en el propio hallazgo.

---

## Alcance acordado

| Decisión | Valor elegido | Consecuencia |
|---|---|---|
| Alcance externo | Repo + MySQL local + app servida en `127.0.0.1` (re-auditoría del 26/11) | Producción, TLS, headers reales, backups y permisos de sistema siguen `NO VERIFICADO` |
| Carga asumida | **Baja/media, 1 servidor** | Decenas a cientos de jugadores simultáneos; no se evaluó scaling horizontal ni miles de RPS |
| Modo de trabajo | Lectura de código + ejecución local; escritura limitada a `audit/*.md` | Código, migraciones y `.env.example` intactos; `vendor/`, `node_modules/`, `public/build/` y `.env` locales ignorados por Git; datos mutados solo en `gacha_paraguay_test` |
| Idioma | Español en explicaciones; identificadores y comentarios de código en inglés en los ejemplos | Coherente con `AGENTS.md:5` |

---

## Estado del workspace

Verificado al inicio y al cierre de la auditoría:

```text
$ git log -1 --oneline
6f19f8b Modulariza la interfaz y asegura bonificaciones

$ git rev-list --count HEAD
5

$ git status --short        # al inicio
(no output)

$ git status --short        # al cierre
?? audit/
```

Al inicio de la primera auditoría no existían `vendor/`, `node_modules/` ni `.env`; `.env.example` se usó solo como evidencia de defaults. En la re-auditoría se pidieron dependencias, se creó un `.env` local con la credencial entregada por chat y se generó `APP_KEY`. Ninguno de esos artefactos está versionado; la contraseña solo vive en `.env` y no se reproduce en este entregable. `package-lock.json` se restauró a su versión original después de que `npm install` modificara el campo `name`.

---

## Inventario del entregable

| Archivo | Contenido | Hallazgos |
|---|---|---|
| [00-resumen-ejecutivo.md](00-resumen-ejecutivo.md) | Calificación C, 35 hallazgos, evidencia en vivo, top 10, quick wins | — |
| [01-arquitectura.md](01-arquitectura.md) | Stack, estructura, servicios, diagrama Mermaid, flujos, rutas | — |
| [02-seguridad.md](02-seguridad.md) | Superficie, mapa OWASP, configuración, API, cumplimiento | CFG-01..06, API-01..03, COMP-01 |
| [03-backend.md](03-backend.md) | Servicio, controladores, middleware, importador | BE-01..06 |
| [04-base-de-datos.md](04-base-de-datos.md) | Esquema, índices, integridad, crecimiento | DB-01..05 |
| [05-frontend.md](05-frontend.md) | Vue/Inertia, XSS, anti-spoiler, accesibilidad, rendimiento | FE-01..03 |
| [06-dependencias-devops.md](06-dependencias-devops.md) | Versiones, dependencias, pruebas, CI, observabilidad | DEP-01..03, QA-01..04, OPS-01..04 |
| [07-matriz-de-riesgos.md](07-matriz-de-riesgos.md) | Probabilidad × impacto, exposición, prioridad P0-P3 | Los 35 |
| [08-plan-de-remediacion.md](08-plan-de-remediacion.md) | Roadmap 0-7 días / 1 mes / 1-3 meses, criterios y rollback | Los 35 |
| [09-buenas-practicas-encontradas.md](09-buenas-practicas-encontradas.md) | Controles correctos a conservar | — |
| [10-anexos.md](10-anexos.md) | Este documento | — |

---

## Comandos ejecutados

| Comando | Salida relevante | Uso en el informe |
|---|---|---|
| `php -v` | PHP 8.3.36 | Stack |
| `composer --version` | 2.10.2 | Stack |
| `node -v` / `npm --version` | v26.13.0 / 11.20.0 | Stack, DEP-01 |
| `mysql --version` | 8.0.35 (cliente) | Stack, DB-05 |
| `git log -1 --oneline` | `6f19f8b Modulariza la interfaz y asegura bonificaciones` | Encabezado |
| `git rev-list --count HEAD` | `5` | Encabezado |
| `git status --short` | Limpio al inicio; `?? audit/` al cierre | Alcance |
| `git diff --check` | Sin salida | Validación |
| `git grep` de secretos / `.env` | Sin credenciales ni objetos `.env` en historial | CFG-01, buenas prácticas |
| `git grep` de `DB::raw`, `whereRaw`, `selectRaw` | Solo dos `selectRaw` con columnas fijas (`GachaPresenter.php:32,38`) | Seguridad SQL |
| `git grep` de `v-html`, `{!! !!}`, `innerHTML`, `document.write` | Sin coincidencias | FE / XSS |
| `git grep` de `eval`, `exec`, `shell_exec`, `unserialize` | Sin coincidencias | Seguridad |
| `git grep` de `$request->all()` | Sin coincidencias | Validación de entrada |
| `git ls-files \| Select-String -Pattern "\.github\|Dockerfile\|docker-compose\|..."` | Sin coincidencias de CI/infra; sí `CHANGELOG.md`, `docs/ARQUITECTURA.md`, `public/.htaccess` | OPS-01, CFG-06 |
| `php -l` sobre 15 archivos de `app/` | Sin errores | Verificación estática |
| `composer validate --no-check-publish --no-check-lock` | `./composer.json is valid` | DEP-02 |
| `composer audit` (sin `vendor/`) | `No installed packages found...` | Motivo de usar `--locked` |
| `composer audit --locked --format=plain` | 14 advisories / 6 paquetes | DEP-01 |
| `npm audit --package-lock-only --audit-level=low` | `found 0 vulnerabilities` | DEP-03 / buenas prácticas |
| `node -e` leyendo `composer.lock` | Versiones de Laravel y Symfony | DEP-01 |
| `composer install --no-interaction --prefer-dist --no-progress` | Dependencias PHP instaladas; autoload y `package:discover` correctos | Habilitó la re-auditoría |
| `npm install --no-audit --no-fund` | 110 paquetes; aviso de script de `esbuild` no aprobado | Build; DEP-03 |
| `git restore package-lock.json` | Lockfile devuelto a su contenido versionado | Integridad del repo |
| `npm run build` | 649 módulos, manifest generado | Frontend |
| `php artisan key:generate --force` | `APP_KEY` escrita solo en `.env` local | CFG-01 |
| `php artisan about --only=environment` | Laravel 13.38.0 / PHP 8.3.36 / `local` / debug activo | CFG-01, stack |
| `php artisan route:list --json --no-ansi` | 9 rutas; sin `throttle` en `/tirar` y `/bonificacion` | API-01, inventario de rutas |
| `php artisan migrate --force` + `migrate:status` en `gacha_paraguay_test` | 6 migraciones `Ran`; `gacha_paraguay` solo lectura | QA-01, DB-04 |
| `$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='gacha_paraguay_test'; php artisan test` | 10 pruebas / 55 aserciones | QA-01 |
| Dos `POST /tirar` simultáneos con CSRF y saldo exacto | `302/302`; `coins=0`, `total_pulls=1`, `pulls=1` | Concurrencia real, QA-01 |
| `curl -D -`, `curl -w '%{http_code}'` sobre `/`, `/coleccion`, `/up`, `/storage/...`, `/tirar` | Headers, anti-spoiler, `200`, `403`, `419` y `302` | CFG-03, CFG-04, FE-02, QA-02, OPS-03, OPS-04 |
| `SHOW GRANTS`, `SHOW VARIABLES`, `SHOW INDEX`, `EXPLAIN`, `COUNT(*)`, `information_schema.EVENTS` | MySQL 8.0.35 en vivo; grants, charset, `filesort`, volúmenes y sin eventos | CFG-02, CFG-06, DB-02, DB-05, OPS-02 |
| `Get-ScheduledTask` | Sin tareas programadas de `gacha` ni de backup | OPS-02 |
| Lectura de `bootstrap/app.php`, `routes/web.php`, controladores, servicios, modelos, migraciones, pruebas, Vue, CSS y configuración | Evidencia `archivo:línea` en cada hallazgo | Todas las capas |

---

## Comandos no ejecutados y motivo

Estos comandos fallaron en la primera pasada por falta de dependencias y **ya se ejecutaron en la re-auditoría**: `php artisan route:list --json`, `php artisan about`, `php artisan test` (sobre MySQL), `npm run build`, `composer install`, `npm install`, `curl`, `EXPLAIN`, `SHOW GRANTS` y `SHOW VARIABLES`.

| Comando | Motivo |
|---|---|
| `php artisan db:seed` | Escribiría filas de prueba; innecesario para la auditoría |
| `php artisan gacha:import` | Haría red contra Wikimedia y escribiría `storage/` y BD; los retratos en producción siguen `NO VERIFICADO` |
| `npm run dev` | Servidor de desarrollo permanente; no aplica a una comprobación puntual |
| `composer update` | Cambiaría `composer.lock`; DEP-01 solo documenta la necesidad, no aplica el parche |
| Pint / PHPStan / Larastan | No están instalados; no se agregaron herramientas nuevas |
| `curl` a un dominio de producción, `SHOW GRANTS` en producción, restore drill de backup | Sin acceso a despliegue real; OPS-02, CFG-02 y CFG-04 siguen parcialmente `NO VERIFICADOS` |
| Prueba de carga con cientos de jugadores | Fuera de la carga baja/media acordada; no se midió latencia ni RPS |

---

## Estado de verificación por hallazgo

Leyenda: **V** = verificado en repo o en el entorno local en vivo; **P** = verificado en local pero con parte dependiente de producción no verificada; **NV** = no verificado. Tras la re-auditoría ningún hallazgo queda totalmente **NV**: los que antes lo estaban pasaron a **P** al comprobarse la parte local.

| ID | Estado | Detalle |
|---|---|---|
| CFG-01 | P | Defaults de `.env.example` y `.env` local con debug activo verificados; producción NV |
| CFG-02 | Remediado | App con `gacha_app` (solo DML, sin `GRANT OPTION`); `SHOW GRANTS`, `migrate:status` y escritura revertida OK; producción NV |
| CFG-03 | V | Sin middleware de headers y `curl -D -` confirma su ausencia en HTTP |
| CFG-04 | P | `Set-Cookie` local con `httponly; samesite=lax`; `Secure` y HTTPS de producción NV |
| CFG-05 | V | Canal `single` y nivel `debug` en configs |
| CFG-06 | P | Variables MySQL verificadas en vivo; infraestructura versionada y CORS de producción NV |
| BE-01 | P | Código y cookie local verificados; rotación/revocación ausentes y navegador real NV |
| BE-02 | V | Confirmación y borrado en el comando |
| BE-03 | P | Código verificado; contenido real de Wikimedia NV |
| BE-04 | P | Consulta verificada; impacto sin medir |
| BE-05 | V | Artefactos de scaffold presentes |
| BE-06 | V | Ausencia de validación de invariantes en repo |
| API-01 | V | `route:list` confirma rutas sin `throttle` |
| API-02 | V | Nueve rutas registradas, sin prefijo `api` |
| API-03 | V | `health: '/up'` en `bootstrap/app.php:13`; `curl` devolvió `200` |
| DB-01 | P | Esquema, ausencia de purga y volumen local medidos; crecimiento real NV |
| DB-02 | V | `SHOW INDEX` y `EXPLAIN` confirman `Using filesort` |
| DB-03 | V | `cascadeOnDelete()` en migración |
| DB-04 | P | Código verificado y 6 migraciones aplicadas en test; historial de ejecución en producción NV |
| DB-05 | P | `gacha_paraguay` local usa `utf8mb4` / `utf8mb4_unicode_ci`; producción NV |
| FE-01 | Remediado | Focus trap verificado con DOM falso y build; prueba en navegador/lector NV |
| FE-02 | P | Tags externos verificados también en la respuesta HTTP; consentimiento legal NV |
| FE-03 | V | `setInterval` incondicional en el componente |
| DEP-01 | P | `composer audit --locked` ejecutado; explotabilidad en producción NV |
| DEP-02 | V | `composer.json:84-85` |
| DEP-03 | V | `bootstrap.js` sin imports desde `app.js` |
| QA-01 | V | Suite ejecutada en MySQL (10/55) y doble gasto descartado con dos POST reales; falta automatizarlo |
| QA-02 | V | Cobertura revisada; `419`, `302` e `image/extract: null` confirmados manualmente por HTTP |
| QA-03 | V | Prueba placeholder |
| QA-04 | V | Seeder y factory |
| OPS-01 | V | Sin `.github/` versionado |
| OPS-02 | P | Ausencia verificada en repo, tareas de Windows y eventos MySQL; backups reales NV |
| OPS-03 | V | Health check del framework, `curl .../up` = `200` |
| OPS-04 | P | 72 rutas referenciadas, 0 archivos, sin `public/storage` y `403` en local; producción NV |
| COMP-01 | P | Cookie de 2 años y ausencia de aviso verificadas; requisito legal local NV |

**Totales al cierre de la auditoría:** 19 V, 16 P, 0 NV. Las 16 marcas **P** concentran exactamente lo que depende del despliegue real: producción, HTTPS, backups, legal y volumen de tráfico. Después se marcaron **CFG-02 y FE-01 como Remediado** (ver [00-resumen-ejecutivo.md](00-resumen-ejecutivo.md#remediaciones-posteriores)); el resto conserva el estado original.

---

## Evidencia clave por capa

| Capa | Archivo:línea | Fragmento |
|---|---|---|
| Config | `.env.example:1-5` | `APP_ENV=local`, `APP_KEY=`, `APP_DEBUG=true` |
| Config | `bootstrap/app.php:16-19` | Solo `EnsurePlayer` + `HandleInertiaRequests` en el grupo `web` |
| Seguridad | `routes/web.php:8-9` | `POST /tirar` y `POST /bonificacion` sin `throttle` |
| Identidad | `EnsurePlayer.php:46` | `Cookie::queue('gacha_player', $token, 60 * 24 * 365 * 2)` |
| Backend | `GachaService.php:47` | `Player::query()->lockForUpdate()->findOrFail(...)` |
| Backend | `GachaService.php:148` | `random_int(1, 100)` |
| Backend | `GachaController.php:78` | `Rule::in([1, (int) config('gachapon.multi_pull')])` |
| Importador | `ImportCharacters.php:83` | `$this->confirm(..., true)` |
| Importador | `ImportCharacters.php:369` | `Storage::disk('public')->put($path, $response->body())` |
| Anti-spoiler | `Character.php:95-96` | `'image' => $owned ? ... : null`, `'extract' => $owned ? ... : null` |
| Presentador | `GachaPresenter.php:61-64` | Progreso global y por rareza |
| BD | `2026_10_23_000001_create_gachapon_tables.php:44` | `UNIQUE(['player_id', 'character_id'])` |
| BD | `2026_10_23_000001_create_gachapon_tables.php:55` | `INDEX(['player_id', 'created_at'])` |
| Pruebas | `phpunit.xml:26-27` | `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` |
| Pruebas | `GachaTest.php:81` | Invocación directa a `GachaService`, sin HTTP |
| Frontend | `Home.vue:54-59` | `router.post('/tirar', ...)` con guard de `pulling` |
| Frontend | `app.blade.php:27-29` | Fuentes desde `fonts.googleapis.com` / `fonts.gstatic.com` |
| Frontend | `app.css:619-626` | `@media (prefers-reduced-motion: reduce)` |
| Dependencias | `composer.json:84-85` | `minimum-stability: dev`, `prefer-stable: true` |

---

## Exclusiones

- Infraestructura real (servidor web, PHP-FPM, `php.ini`, WAF, CDN, TLS).
- Datos reales de `gacha_paraguay`, volúmenes de tablas y rendimiento medido.
- Usuarios, contraseñas y grants reales de MySQL.
- Permisos de filesystem en `storage/`, `bootstrap/cache/` y enlace `public/storage`.
- Logs de producción, tráfico, tasas de error y alertas efectivas.
- Licencias de contenido externo más allá de lo declarado en `README.md:183-187` (CC BY-SA 4.0 para Wikipedia/Wikimedia y MIT para el código).
- Asesoría jurídica sobre LGPD/GDPR aplicables al proyecto.

---

## Glosario

| Término | Significado en este informe |
|---|---|
| **Anti-spoiler** | Política que oculta imagen y biografía de figuras no descubiertas |
| **PRG** | POST / Redirect / GET: la mutación redirige y el resultado viaja en flash de sesión |
| **`pity`** | Contador que garantiza un legendario tras 12 tiradas sin uno |
| **SM** | Sueldo mínimo; unidad monetaria del juego (₲ 3.044.000) |
| **`lockForUpdate()`** | Bloqueo pesimista de fila en MySQL dentro de una transacción |
| **Exposición** | Combinación cualitativa de probabilidad e impacto usada en la matriz |
| **V / P / NV** | Verificado / Verificado parcialmente / No verificado |
| **Quick win (⚡)** | Cambio pequeño con alto retorno y bajo riesgo |
| **OWASP / CWE** | Catálogos de riesgos y debilidades usados como referencia, no como sustituto del hallazgo |
