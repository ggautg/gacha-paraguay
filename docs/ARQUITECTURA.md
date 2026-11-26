# Arquitectura del proyecto

Guía para entender **dónde vive cada cosa y por qué**. Los cambios puntuales están en [`CHANGELOG.md`](../CHANGELOG.md) y las reglas para agentes en [`AGENTS.md`](../AGENTS.md).

## Visión general

```
Navegador
   │  Inertia + Vue 3 (SPA)
   ▼
Laravel 13  ── controllers ── GachaService ── MySQL 8
   │                         │
   │                         └─ GachaPresenter (datos para Vue)
   └─ middleware EnsurePlayer (cookie UUID)
```

No hay API REST separada: Inertia sirve componentes Vue con props desde Laravel y las acciones (`/tirar`, `/bonificacion`) son POST que redirigen de vuelta (patrón POST/Redirect/GET). Las reglas económicas y el importe del sueldo mínimo salen de `config/gachapon.php` y llegan al navegador en la prop compartida `gacha`.

## Capa backend (`app/`)

Todos los archivos tienen docblock de clase y de métodos públicos.

### Controladores

| Archivo | Función principal | Por qué está ahí |
|---|---|---|
| `Http/Controllers/GachaController.php` | `index()`, `pull()`, `bonus()` | Valida la entrada HTTP (`count` solo 1 o `multi_pull`) y delega azar/economía al servicio. Guarda el resultado de la tirada en el flash de sesión para que la animación sobreviva a la redirección (PRG). `bonus()` abre transacción y vuelve a leer al jugador con `lockForUpdate()` antes de acreditar: dos peticiones simultáneas no pueden cobrar la bonificación dos veces. |
| `Http/Controllers/AlbumController.php` | `index()` | Entrega el catálogo completo. Usa `keyBy('character_id')` para cruzar rápido las entradas de colección con los personajes. |
| `Http/Controllers/Controller.php` | Clase base | Punto común para futuros controladores; documentada para no dejar un archivo mudo. |

### Servicio y presentador

- **`Services/GachaService.php`** — núcleo del juego.
  - `pull(Player $player, int $count): array`: abre transacción, vuelve a leer al jugador con `lockForUpdate()`, cobra el precio, sortea, descuenta/acenta monedas, actualiza `pity` y guarda cada `Pull`.
  - `rollRarity()`: aplica la garantía (si `pity + 1 >= config('gachapon.pity')`, fuerza 3★) y luego probabilidad acumulada con `random_int()`.
  - `pickCharacter()`: elige dentro del pozo de la rareza sin repetir IDs en la misma tirada; si el pozo está vacío, cae al catálogo completo para no abortar.
  - El bloqueo pesimista evita que dos peticiones concurrentes gasten el mismo saldo. Los reembolsos se acumulan y se suman al saldo final.

- **`Support/GachaPresenter.php`** — serialización para Inertia.
  - `player()`: estado del jugador, progreso general y por rareza. Las cantidades se obtienen con consultas agregadas en SQL (`COUNT` + `GROUP BY`) en vez de cargar 84 modelos en memoria.
  - `toCard()`: forma de cada figura. Oculta imagen y extracto cuando no está descubierta (anti-spoiler); nombre, rol y rareza sí se publican porque forman parte del catálogo visible.
  - `reveal()`: resultado de tirada; reutiliza `toCard()` y añade `refund` / `refund_percent`.

- **`Support/Salary.php`** — formateo de sueldos mínimos y guaraníes a partir de `config('gachapon.salary.amount')`. El mismo importe se expone al navegador en `gacha.salary.amount` y `app.js` lo inyecta en `lib/rarity.js`; así servidor y cliente no duplican el número del decreto.

### Modelos

| Modelo | Responsabilidad |
|---|---|
| `Character` | Catálogo: nombre, rol, época, categoría, rareza, imagen y enlace a Wikipedia. |
| `Player` | Jugador anónimo: monedas, `pity`, total de tiradas, bonificación y relaciones. Define `duplicateRefund()` y `duplicateRefundPercent()`. |
| `CollectionEntry` | Cuántas copias tiene un jugador de cada figura. Clave única `(player_id, character_id)`. |
| `Pull` | Historial de tiradas; alimenta «Últimas tiradas». |
| `User` | Modelo base de Laravel, documentado para completar el inventario. |

### Middleware y providers

- **`EnsurePlayer.php`**: si no hay cookie `gacha_player`, crea un UUID, un `Player` con el saldo inicial y lo deja en `request->attributes`. Sin registro de usuario.
- **`HandleInertiaRequests.php`**: comparte `player`, `gacha` y `flash` con todas las vistas. `player` y `flash.message` son closures para resolverse en cada respuesta; no se usa `Inertia::lazy()` porque ambas páginas los necesitan en la carga inicial.
- **`AppServiceProvider.php`**: configuración global de la app.

### Comando de importación

`Console/Commands/ImportCharacters.php` lee `config/gachapon.php`, consulta la API de MediaWiki en lotes de 20, descarga retratos a `storage/app/public/characters/`, recorta extractos a ~560 caracteres y respeta pausas/HTTP 429. Opciones: `--missing`, `--no-images`, `--only=slug`, `--fresh`.

## Capa frontend (`resources/js`)

### Páginas

- **`pages/Home.vue`**
  - `pull(count)`: bloquea con `pulling`, hace `router.post('/tirar', { count })` y libera en `onFinish`.
  - `pullAgain()`: repite la misma cantidad sin cerrar el revelado.
  - `claimBonus()`: POST a `/bonificacion` con `useForm`; el estado «Reclamando…» y la cuenta regresiva viven en `BonusClaim.vue`.
  - `rates`: arma probabilidades + progreso por rareza y se los pasa a `RarityRates.vue`.
  - La garantía (tiradas restantes) se calcula dentro de `RarityRates.vue`.
  - Un `watch` en `page.props.lastPull` abre el revelado cuando llega la respuesta.
  - La máquina, los botones, la bonificación, los paneles y el historial son componentes; la página solo coordina.

- **`pages/Album.vue`**
  - `search`, `filter`, `onlyOwned`: filtros en cliente (el plantel es chico), presentados por `CollectionFilters.vue` con `v-model`.
  - `visible`: resultado de aplicar los tres filtros.
  - `hasFilters` / `clearFilters()`: el botón de limpieza solo está activo cuando hace falta.
  - `completion`: porcentaje que dibuja `CollectionProgress.vue` con un anillo SVG y `stroke-dashoffset`.

### Layout

- **`Layouts/AppLayout.vue`**: franja de bandera, header (logo, nav, progreso, saldo), `<slot>`, pie, selector de temas y `ToastNotice`.
  - `isActive(href)`: resalta la ruta actual con `aria-current`.
  - El toast ya no vive aquí: `ToastNotice.vue` escucha `flash.message`, maneja el temporizador y el cierre.

### Componentes compartidos

| Componente | Propósito / decisión |
|---|---|
| `CharacterPortrait.vue` | Unifica `img` + fallback. Escucha `@error` porque una URL de Wikimedia puede morir después de importada; `watch` en `image` reinicia el intento al cambiar de personaje (importante en el modal). |
| `RarityBadge.vue` | Una sola pastilla de rareza para tarjeta, modal y revelado. Los colores vienen de `lib/rarity.js` → variables CSS, así sigue el tema automáticamente. |
| `CloseButton.vue` | Área táctil y `aria-label` iguales en los tres overlays. El SVG es `aria-hidden` porque el accesible ya está en el botón. |
| `EmptyState.vue` | Estado vacío con slot `action`. Evita que cada pantalla invente su propio «?». |
| `ProgressBar.vue` | `role="progressbar"` + `aria-valuenow`. El ancho es dinámico; el color, token del tema. |
| `PanelHeader.vue` | Título + pista + slot de acción. Extraído de tres paneles idénticos. |
| `StatCard.vue` | Tarjetas de «Colección», «Tiradas» y «Legendarios» con sufijo y acento opcional. |
| `CoinChip.vue` | Saldo en sueldos mínimos con tooltip en guaraníes. |
| `PortraitFallback.vue` | Silueta con iniciales para figuras sin imagen. |
| `RarityStars.vue` | Estrellas SVG de 1 a 3. |
| `CharacterCard.vue` | Tarjeta de grilla: marco por rareza, anti-spoiler, contador de copias y evento `select`. |
| `CharacterModal.vue` | Ficha detallada. Mantiene `visible` aparte de la prop para que la transición de salida se dibuje; el extracto solo se muestra si está descubierto. |
| `PullReveal.vue` | Revelado a pantalla completa. Bloquea cierre mientras `busy`, muestra resumen económico y permite repetir. |
| `ThemePicker.vue` | Selector flotante de nueve paletas; cierra con clic externo o Escape. |
| `GachaMachine.vue` | Dibujo y estado accesible de la máquina. Solo se agita mientras hay una tirada en curso; su CSS vive junto al componente. |
| `PullControls.vue` | Botones ×1/×10, precio, `title`, `aria-busy`, error de saldo y nota del sueldo mínimo. Emite `pull`. |
| `BonusClaim.vue` | Cuenta regresiva en vivo y botón de bonificación. Limpia su `setInterval` al desmontar. Emite `claim`. |
| `RarityRates.vue` | Tasas, barras por rareza y medidor de puntos de la garantía. |
| `ShowcasePanel.vue` | Últimas figuras descubiertas y saldo; usa el mismo `toCard()` anti-spoiler del servidor. |
| `RecentPullsPanel.vue` | Historial breve de tiradas con estado vacío. |
| `ToastNotice.vue` | Aviso global con `role="status"`, cierre manual y temporizador cancelado al desmontar. |
| `CollectionFilters.vue` | Filtros por rareza, búsqueda y «Solo descubiertos» con eventos `update:*`. |
| `CollectionProgress.vue` | Anillo de finalización con degradado del tema activo. |

### Composable

`composables/useDismissableOverlay.js` centraliza:

- cierre con **Escape** solo cuando el overlay está activo;
- bloqueo del scroll del documento con `overflow: hidden`, guardando el valor previo;
- **focus trap**: guarda el elemento que abrió el diálogo, enfoca su primer control, confina `Tab` entre los controles visibles y devuelve el foco al cerrar o desmontar;
- limpieza de listeners y del scroll al desmontar.

Lo usan `PullReveal` y `CharacterModal`, que pasan la raíz del diálogo en `container` y le dan `tabindex="-1"` como destino de foco vacío. Antes cada uno repetía los listeners, podía dejar el scroll trabado si se cerraba a mitad de una petición y anunciaba `aria-modal` sin confinar el foco. `ThemePicker` no usa el trap: es un popover, no un diálogo modal.

### Librerías

- **`lib/rarity.js`**: `RARITY_ORDER`, `formatCoins`, `formatGuaranies`, `formatNumber`, `rarityMeta` (color, degradado, etiqueta y blurb por rareza).
- **`lib/themes.js`**: metadatos de las nueve paletas, persistencia en `localStorage` (`gacha-theme`), `applyTheme()` (atributo `data-theme`, `theme-color` y favicon) e `initTheme()` (lee la preferencia guardada antes de mostrar la interfaz desde Blade).

### Entrada

`app.js` crea la app Inertia, resuelve páginas con `import.meta.glob`, inicializa el tema, copia `gacha.salary.amount` a `lib/rarity.js` con `setSueldoMinimo()` y configura la barra de navegación con `rgb(var(--accent-rgb))`.

## Estilos (`resources/css/app.css`)

- Tokens de Tailwind (`night`, `bone`, `gold`, rarezas, bandera).
- Nueve bloques `[data-theme='…']` que pisan esos tokens.
- Utilidades semánticas: `surface`, `surface-2`, `surface-3`, `line-soft`, `line-strong`, `portrait-ink`, `panel`, `chip`, `btn-*`, `gold-text`, `flag-stripe`, `nav-glow`, `coin-glow`.
- Animaciones: `float-soft` (6 px / 9 s), `sparkle` (4,8 s), `reveal-card`, `shine-sweep` y la sacudida de la máquina (en `GachaMachine.vue`). El movimiento decorativo es lento y de bajo contraste.
- `@media (prefers-reduced-motion: reduce)` desactiva animaciones para quien lo pide el sistema.

### Entrada de estilos y clases retiradas

- Se eliminó la clase `.btn-red` y su variante `prefers-reduced-motion`: no tenía usos y endurecía el mantenimiento de los botones.
- Los textos de error usan `text-flag-red`, un color definido por los tokens; no existe una clase `text-danger` en este proyecto.

## Base de datos y configuración

- MySQL 8.0.35, base `gacha_paraguay` (utf8mb4). La aplicación usa `gacha_app`, con `SELECT/INSERT/UPDATE/DELETE` solo en `gacha_paraguay.*` y `gacha_paraguay_test.*`, sin `GRANT OPTION`; su credencial vive únicamente en `.env` (ignorado por Git). `gacha_migrate` es un usuario aparte, con DDL, reservado para `php artisan migrate` y documentado en el README. Las migraciones ya aplicadas no se reescriben.
- `SESSION_DRIVER=database`, `CACHE_STORE=database` y `QUEUE_CONNECTION=database` exigen las tablas `sessions`, `cache` y `jobs`. La tabla `sessions` antes no tenía migración: se agregó `0001_01_01_000003_create_sessions_table.php` con guard para bases ya existentes.
- `config/gachapon.php` es la fuente de verdad del plantel (84 figuras), economía (precios, bonificación, reembolsos), salario, probabilidades, `pity` y tamaño de la tirada múltiple. El navegador recibe esos valores en la prop compartida `gacha`.
- Si se cambia un nombre o rol en la configuración, hay que reflejarlo en la tabla `characters` (el comando de importación o una actualización puntual). En esta revisión se sincronizaron Víctor Pecci, Keraná y Luisón.

## Flujo completo de una tirada

1. El usuario pulsa «Tirar ×1» o «Tirar ×10». `Home.pull()` bloquea los botones y mueve la palanca.
2. `POST /tirar` con `count`. `GachaController::pull()` valida que sea 1 o `multi_pull`.
3. `GachaService::pull()` abre transacción, bloquea al jugador, cobra, sortea rareza (con garantía), elige personajes, registra copias y reembolsos, actualiza `pity` y guarda el historial.
4. El controlador redirige con `with('pull', $result)`.
5. Inertia actualiza `page.props.lastPull`; el `watch` de `Home` abre `PullReveal`.
6. El usuario puede cerrar o «Tirar de vuelta»; en ese caso el overlay sigue abierto mostrando «Tirando…» hasta la nueva respuesta.

## Flujo de la bonificación

1. `BonusClaim.vue` calcula `seconds` comparando `bonus_ready_at` con un reloj que se actualiza cada segundo; el temporizador se limpia al desmontar.
2. Cuando llega a 0, el botón se habilita y emite `claim`; `Home.vue` hace `POST /bonificacion`.
3. `GachaController::bonus()` bloquea la fila con `lockForUpdate()`, vuelve a comprobar `canClaimBonus()` dentro de la transacción, acredita el importe y graba `bonus_claimed_at`. Si no corresponde, lanza `ValidationException` y no se acredita nada.
4. El `flash.message` lo escucha `ToastNotice.vue`, que lo muestra como toast accesible con cierre manual y temporizador cancelable.

## Pruebas y mantenimiento

```bash
php artisan test          # 10 pruebas / 55 aserciones
npm run build             # 649 módulos, compilación de producción
npm audit                 # 0 vulnerabilidades
php artisan gacha:import  # actualizar catálogo desde Wikipedia
git diff --check          # sin errores de whitespace
```

Los archivos PHP de `app/` están documentados para que una persona nueva pueda entender el porqué de cada decisión sin leer el historial de Git. Las reglas de trabajo y los invariantes del sistema están en [`AGENTS.md`](../AGENTS.md).
