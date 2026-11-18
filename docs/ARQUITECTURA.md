# Arquitectura del proyecto

Guía para entender **dónde vive cada cosa y por qué**. Los cambios puntuales están en [`CHANGELOG.md`](../CHANGELOG.md).

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

No hay API REST separada: Inertia sirve componentes Vue con props desde Laravel y las acciones (`/tirar`, `/bonificacion`) son POST que redirigen de vuelta.

## Capa backend (`app/`)

Todos los archivos tienen docblock de clase y de métodos públicos.

### Controladores

| Archivo | Función principal | Por qué está ahí |
|---|---|---|
| `Http/Controllers/GachaController.php` | `index()`, `pull()`, `bonus()` | Valida la entrada HTTP (`count` solo 1 o `multi_pull`) y delega azar/economía al servicio. Guarda el resultado de la tirada en el flash de sesión para que la animación sobreviva a la redirección (PRG). |
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

- **`Support/Salary.php`** — formateo de sueldos mínimos y guaraníes; se usa tanto en controladores como en la home.

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
- **`HandleInertiaRequests.php`**: comparte `player` y `flash` con todas las vistas. Usa `Inertia::lazy()`/`optional()` donde corresponde para no serializar de más.
- **`AppServiceProvider.php`**: configuración global de la app.

### Comando de importación

`Console/Commands/ImportCharacters.php` lee `config/gachapon.php`, consulta la API de MediaWiki en lotes de 20, descarga retratos a `storage/app/public/characters/`, recorta extractos a ~560 caracteres y respeta pausas/HTTP 429. Opciones: `--missing`, `--no-images`, `--only=slug`, `--fresh`.

## Capa frontend (`resources/js`)

### Páginas

- **`pages/Home.vue`**
  - `pull(count)`: bloquea con `pulling`, hace `router.post('/tirar', { count })` y libera en `onFinish`.
  - `pullAgain()`: repite la misma cantidad sin cerrar el revelado.
  - `claimBonus()`: POST a `/bonificacion` si ya pasó el cooldown; muestra «Reclamando…».
  - `bonusSeconds` / `bonusLabel`: cuenta regresiva en vivo con un `setInterval` que se limpia al desmontar.
  - `rates`: armado de probabilidades + progreso por rareza.
  - `nextLegendary`: tiradas restantes para la garantía.
  - Un `watch` en `page.props.lastPull` abre el revelado cuando llega la respuesta.

- **`pages/Album.vue`**
  - `search`, `filter`, `onlyOwned`: filtros en cliente (el plantel es chico).
  - `visible`: resultado de aplicar los tres filtros.
  - `hasFilters` / `clearFilters()`: el botón de limpieza solo está activo cuando hace falta.
  - `completion` + `dashOffset`: anillo SVG de progreso.

### Layout

- **`Layouts/AppLayout.vue`**: franja de bandera, header (logo, nav, progreso, saldo), `<slot>`, pie, selector de temas y toast.
  - `isActive(href)`: resalta la ruta actual con `aria-current`.
  - `dismissToast()`: cierra el aviso y cancela el temporizador.

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

### Composable

`composables/useDismissableOverlay.js` centraliza:

- cierre con **Escape** solo cuando el overlay está activo;
- bloqueo del scroll del documento con `overflow: hidden`, guardando el valor previo;
- limpieza de listeners y del scroll al desmontar.

Lo usan `PullReveal` y `CharacterModal`. Antes cada uno repetía los listeners y podía dejar el scroll trabado si se cerraba a mitad de una petición.

### Librerías

- **`lib/rarity.js`**: `RARITY_ORDER`, `formatCoins`, `formatGuaranies`, `formatNumber`, `rarityMeta` (color, degradado, etiqueta y blurb por rareza).
- **`lib/themes.js`**: metadatos de las nueve paletas, persistencia en `localStorage` (`gacha-theme`), `applyTheme()` (atributo `data-theme`, `theme-color` y favicon) e `initTheme()` (lee la preferencia guardada antes de mostrar la interfaz desde Blade).

### Entrada

`app.js` crea la app Inertia, resuelve páginas con `import.meta.glob`, inicializa el tema y configura la barra de navegación con `rgb(var(--accent-rgb))`.

## Estilos (`resources/css/app.css`)

- Tokens de Tailwind (`night`, `bone`, `gold`, rarezas, bandera).
- Nueve bloques `[data-theme='…']` que pisan esos tokens.
- Utilidades semánticas: `surface`, `surface-2`, `surface-3`, `line-soft`, `line-strong`, `portrait-ink`, `panel`, `chip`, `btn-*`, `gold-text`, `flag-stripe`, `nav-glow`, `coin-glow`.
- Animaciones: `float-soft`, `sparkle`, `reveal-card`, `shine-sweep` y la sacudida de la máquina (en `Home.vue`).
- `@media (prefers-reduced-motion: reduce)` desactiva animaciones para quien lo pide el sistema.

## Base de datos y configuración

- MySQL 8, base `gacha_paraguay` (utf8mb4). El entorno local usa usuario `root` con la credencial definida en `.env`.
- `config/gachapon.php` es la fuente de verdad del plantel (84 figuras), economía (precios, bonificación, reembolsos), salario, probabilidades, `pity` y tamaño de la tirada múltiple.
- Si se cambia un nombre o rol en la configuración, hay que reflejarlo en la tabla `characters` (el comando de importación o una actualización puntual). En esta revisión se sincronizaron Víctor Pecci, Keraná y Luisón.

## Flujo completo de una tirada

1. El usuario pulsa «Tirar ×1» o «Tirar ×10». `Home.pull()` bloquea los botones y mueve la palanca.
2. `POST /tirar` con `count`. `GachaController::pull()` valida que sea 1 o `multi_pull`.
3. `GachaService::pull()` abre transacción, bloquea al jugador, cobra, sortea rareza (con garantía), elige personajes, registra copias y reembolsos, actualiza `pity` y guarda el historial.
4. El controlador redirige con `with('pull', $result)`.
5. Inertia actualiza `page.props.lastPull`; el `watch` de `Home` abre `PullReveal`.
6. El usuario puede cerrar o «Tirar de vuelta»; en ese caso el overlay sigue abierto mostrando «Tirando…» hasta la nueva respuesta.

## Flujo de la bonificación

1. `bonusSeconds` se calcula comparando `bonus_ready_at` con un reloj que se actualiza cada segundo.
2. Cuando llega a 0, el botón se habilita y `claimBonus()` hace `POST /bonificacion`.
3. El controlador llama `canClaimBonus()` (basado en `bonus_claimed_at`), acredita `Salary::format(...)` y marca el cobro. Un doble clic no puede cobrar dos veces.
4. El `flash.message` llega al `AppLayout` y aparece como toast accesible con cierre manual.

## Pruebas y mantenimiento

```bash
php artisan test          # 10 pruebas del flujo completo
npm run build             # compilación de producción
php artisan gacha:import  # actualizar catálogo desde Wikipedia
```

Los archivos PHP de `app/` están documentados para que una persona nueva pueda entender el porqué de cada decisión sin leer el historial de Git.
