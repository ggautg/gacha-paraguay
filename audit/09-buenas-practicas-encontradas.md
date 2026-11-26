# Buenas prácticas encontradas

## Índice

- [Arquitectura y diseño](#arquitectura-y-diseño)
- [Seguridad](#seguridad)
- [Concurrencia y backend](#concurrencia-y-backend)
- [Base de datos](#base-de-datos)
- [Frontend y accesibilidad](#frontend-y-accesibilidad)
- [Pruebas y documentación](#pruebas-y-documentación)
- [Cómo conservarlas](#cómo-conservarlas)

---

## Arquitectura y diseño

| Práctica | Evidencia | Por qué es valiosa |
|---|---|---|
| Una sola fuente de verdad para la economía | `config/gachapon.php:686-710`; `HandleInertiaRequests.php:62-74`; `app.js:24-25` | Precios, tasas, `pity` y sueldo se cambian en un archivo; JS no duplica el importe de 1 SM |
| Capas con responsabilidades claras | `GachaController.php:17-23` valida y delega; `GachaService.php:15-22` resuelve dominio; `GachaPresenter.php:9-14` da forma a datos | Un cambio en presentación no toca la transacción, y viceversa |
| Patrón PRG para mutaciones | `GachaController.php:83-85,117` redirigen con flash; `Home.vue:39-45` abre el revelado desde `lastPull` | Recargar la página no repite la tirada ni el cobro |
| Anti-spoiler centralizado | `Character.php:81-98`; `GachaPresenter.php:76-83` | Imagen y extracto se ocultan en un solo lugar; no hay primera fila descubierta por accidente en otra vista |
| Comando de importación con reintentos parciales | `ImportCharacters.php:28-32,106-139` (`--missing`, `--no-images`, `--only`) | No se descarga de nuevo lo ya obtenido; respeta límites de Wikimedia con pausas y backoff en 429 |
| Convenciones explícitas para agentes | `AGENTS.md:5-13` | Documenta idioma, tokens CSS, ubicación de componentes, docblocks y migraciones nuevas |

---

## Seguridad

| Práctica | Evidencia | Por qué es valiosa |
|---|---|---|
| CSRF del grupo `web` | `bootstrap/app.php:15-20` agrega middleware al grupo que ya trae `ValidateCsrfToken`; Inertia envía `X-XSRF-TOKEN` | Las rutas `/tirar` y `/bonificacion` no son llamables desde un sitio tercero |
| Sin secretos versionados | `git grep` sin coincidencias de claves; `.env` ignorado en `.gitignore`; historial sin objetos `.env` | No hay credencial filtrada en el repo ni en commits pasados |
| Sin SQL dinámico del usuario | `git grep` de `DB::raw`/`whereRaw`/`orderByRaw` solo encuentra dos `selectRaw` con columnas fijas en `GachaPresenter.php:32,38` | No hay inyección SQL explotable desde HTTP |
| Sin renderizado HTML dinámico | Sin `v-html`, `{!! !!}`, `innerHTML` ni `document.write` en `resources/` | El contenido de Wikipedia se interpola como texto, no como markup |
| Azar criptográfico | `GachaService.php:148,186` usa `random_int()` | El azar con impacto económico no depende de `rand()` |
| Identidad con UUID, no ID incremental | `EnsurePlayer.php:38-44`; `players.token` unique en la migración | No se adivina ni se cuenta cuántos jugadores hay |
| Script inline del tema validado | `app.blade.php:14-16` aplica `/^[a-z0-9-]+$/`; `themes.js:71-79` cae al tema por defecto | Un valor manipulado de `localStorage` no inyecta atributos arbitrarios |
| Storage local, no hotlink | `ImportCharacters.php:369`; `Character.php:63-71` sirve `/storage/...` | Las imágenes se controlan en el propio servidor; no dependen de Wikimedia en cada request |

---

## Concurrencia y backend

| Práctica | Evidencia | Por qué es valiosa |
|---|---|---|
| Bloqueo pesimista del jugador | `GachaService.php:45-47`; `GachaController.php:101-105` | Dos tiradas o dos bonificaciones simultáneas no gastan dos veces el mismo saldo ni cobran dos veces el cooldown |
| Relectura dentro de la transacción | Los servicios vuelven a buscar `Player` por clave primaria antes de operar | Evita operar sobre el objeto stale que inyectó el middleware |
| Revalidación de elegibilidad | `GachaController.php:106-110` comprueba `canClaimBonus()` dentro del bloqueo | El estado del botón en el cliente nunca se considera autoridad |
| Defensa en profundidad en el precio | `GachaService.php:37-43` normaliza `count` aunque el controlador ya validó | Un valor inesperado se cobra como tirada simple, nunca como múltiple gratis |
| `pity` determinista y reiniciable | `GachaService.php:103-104,142-160` | La garantía de 12 tiradas es verificable y no depende del azar acumulado |
| Reembolsos derivados de rareza | `Player.php:102-117`; `config/gachapon.php:692-696` | El porcentaje viaja al frontend `GachaPresenter.php:81-82` sin duplicar la regla |
| Eventos inmutables | `Pull.php:17-18` declara `UPDATED_AT = null` | El historial no pretende tener versiones editables |
| Importación transaccional | `ImportCharacters.php:87-97` borra catálogo y progreso en una transacción | `--fresh` no deja estados a medias (el problema es el default de confirmación, BE-02) |

---

## Base de datos

| Práctica | Evidencia | Por qué es valiosa |
|---|---|---|
| Llaves únicas bien modeladas | `players.token`, `characters.slug`, `collection_entries(player_id, character_id)` en la migración principal | La identidad, el catálogo y las copias no se duplican |
| FK con `cascadeOnDelete()` | `collection_entries` y `pulls` apuntan a `players`/`characters` | No quedan huérfanos al borrar un jugador (ver DB-03 para la contrapartida de auditoría) |
| Índice para historial por jugador | `pulls` con `INDEX(player_id, created_at)` | La consulta `latest('id')` filtra por jugador eficientemente |
| `strict => true` en MySQL | `config/database.php:60` | Fechas o números inválidos fallan en vez de guardarse recortados |
| `sessions` creada por migración dedicada | `0001_01_01_000003_create_sessions_table.php:7-14` | `SESSION_DRIVER=database` funciona en una instalación nueva sin crear tablas a mano |
| Cambio de moneda con migración nueva | `2026_10_26_000000_switch_currency_to_sueldo_minimo.php` | La migración original sigue siendo la historia fiel del esquema |

---

## Frontend y accesibilidad

| Práctica | Evidencia | Por qué es valiosa |
|---|---|---|
| Páginas coordinadoras, componentes reutilizables | `Home.vue:1-11`, `Album.vue:1-9`, `Components/` | La lógica de interfaz nueva tiene un lugar obvio, según `AGENTS.md:7` |
| Overlay lifecycle centralizado | `useDismissableOverlay.js:3-15,45-52` | Escape y scroll se limpian al desmontar; evita listeners colgados |
| Retratos con fallback | `CharacterPortrait.vue:32-44` | Una URL rota de Wikipedia no deja un hueco blanco |
| Tokens CSS y 9 paletas | `rarity.js:1-75`, `app.css`, `themes.js:1-65` | Los colores se cambian en un lugar; el tema se aplica antes del primer render |
| `prefers-reduced-motion` | `app.css:619-626` | Las animaciones de revelado se desactivan para usuarios sensibles al movimiento |
| Estados accesibles en avisos | `ToastNotice.vue:48` (`aria-live="polite"`), `role="dialog"` en modales | Las novedades se anuncian sin robar foco (falta solo el focus trap, FE-01) |
| Bloqueo de doble envío | `Home.vue:47-59,72-78`; `PullReveal.vue:40-44` | `busy` impide clics duplicados mientras la request está en vuelo |
| Filtros en cliente con plantel chico | `Album.vue:1-9,53-71` | Cambiar de rareza no provoca navegación ni consulta extra |

---

## Pruebas y documentación

| Práctica | Evidencia | Por qué es valiosa |
|---|---|---|
| 10 pruebas de feature | `tests/Feature/GachaTest.php:45-188` | Cubren home, colección, cobro, precio múltiple, duplicados, `pity`, saldo insuficiente, URL de retrato y bonificación única |
| Pruebas parametrizadas por config | `GachaTest.php:54-55,84-86,119-120` | Cambiar la economía en `config` no rompe los expectations hardcodeados |
| Aserción de mensaje de bonificación | `GachaTest.php:169-182` | Verifica PRG, flash y cooldown en la misma prueba |
| Lockfiles versionados | `composer.lock`, `package-lock.json` | Las versiones son reproducibles aunque falte `vendor/` en este workspace |
| `composer validate` correcto | Salida: `./composer.json is valid` | El manifiesto PHP es consistente |
| `npm audit` limpio | `found 0 vulnerabilities` | La cadena JS no tiene advisories conocidos al momento de la auditoría |
| Documentación viva | `README.md`, `CHANGELOG.md`, `docs/ARQUITECTURA.md`, `AGENTS.md` | hay contexto para operar y para que nuevos agentes no rompan decisiones previas |
| `php -l` limpio en `app/` | 15 archivos sin errores de sintaxis | No hay errores de parser en el código versionado |

---

## Cómo conservarlas

1. **Agregarlas como checks de revisión:** en el template de PR, una casilla «¿Se conserva PRG / anti-spoiler / fuente única de configuración?».
2. **No debilitarlas al remediar:** por ejemplo, `throttle` (API-01) debe agregarse **sin** quitar CSRF; el CSP (CFG-03) debe incluir el script inline del tema o migrarlo a nonce, no eliminar la validación de `app.blade.php:14`.
3. **Mover las pruebas a MySQL con cuidado:** QA-01 cambia el driver, pero las aserciones parametrizadas por `config` deben seguir igual.
4. **Mantener el docblock del por qué:** `AGENTS.md:10` lo exige; los archivos nuevos de remediación (`SecurityHeaders`, `PruneGachaData`, `CheckGachaConfig`) deben explicar por qué existen, no qué hacen línea por línea.
5. **Preservar la distinción de capas:** el middleware de headers no debe contener reglas económicas; el comando de purga no debe llamar a `GachaService`.
