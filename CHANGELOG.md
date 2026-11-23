# Registro de cambios

Este archivo explica **qué se cambió y por qué** en la revisión integral del sistema. Para el mapa técnico de archivos y flujos, mirá [`docs/ARQUITECTURA.md`](docs/ARQUITECTURA.md); para las reglas que deben respetar los agentes, [`AGENTS.md`](AGENTS.md).

La sección siguiente es el registro histórico de una revisión anterior. Los datos de entorno y de verificación que aparecían allí **no se dieron por válidos**: se reverificaron en esta sesión y se corrigen en la [Revisión de los 12 frentes](#revisión-de-los-12-frentes-noviembre-de-2026).

## Revisión integral anterior (10 frentes — registro histórico)

### 1. Coherencia de UI/UX

- **Terminología única.** Se unificaron los textos visibles:
  - «sello» → «figura» (tarjeta, modal, revelado, vitrina y colección).
  - «Invocando» / «Invocá» → «Tirando» / «Descubrí».
  - «Pity» → «Garantía».
  - «álbum» → «colección».
  - «Abrir el álbum» → «Abrir la colección».
  - «Default» → «Predeterminado».
  - «Top 10» → «N.º 10»; «Kerana» → «Keraná»; «El séptimo hijo de Tau y Kerana» → «El séptimo hijo de Taú y Keraná».
  Estos cambios también se sincronizaron en la base de datos (`characters`) para que el texto mostrado coincida con `config/gachapon.php`.
- **Encabezados repetidos.** El patrón `flex items-center justify-between` con un `eyebrow` se extrajo a `PanelHeader.vue` y se usa en «Probabilidades», «Tu vitrina» y «Últimas tiradas».
- **Estados vacíos.** Los tres «?» sueltos (vitrina, últimas tiradas, búsqueda sin resultados) ahora usan `EmptyState.vue`, con texto y acción opcional coherentes.
- **Navegación.** Los enlaces del header tienen `aria-current="page"` y `aria-label`, y el `<nav>` está identificado para lectores de pantalla.

### 2. Movimiento fácil para la vista

- Se mantuvieron las animaciones suaves existentes (máquina, chispas, revelado, anillo de progreso) y se reforzó el criterio de **movimiento con propósito**:
  - La palanca y el perno giran solo mientras `pulling !== null`.
  - `PullReveal` bloquea el cierre durante la petición para evitar parpadeos.
  - `Tirar de vuelta` ya no cierra y vuelve a abrir el revelado: el overlay permanece y su contenido se actualiza al llegar la respuesta.
  - `resources/css/app.css` ya respeta `prefers-reduced-motion`; se conservó y se verificó que los componentes nuevos no introducen animaciones infinitas nuevas.
- Las barras de progreso usan transiciones de 700 ms y colores del tema, sin saltos bruscos.

### 3. Español perfecto

- Auditoría completa de textos visibles en Vue, Blade, controladores, configuración y README.
- Sin mojibake (`Ã`, `Â`, `â€`) en los archivos revisados.
- `README.md`: «Garantía», «Colección», «Predeterminado», «script en línea», «antes de mostrar la interfaz», «datos preparados a mano», «probabilidades» y «carga de la home y la colección».
- `config/gachapon.php`: rol de Víctor Pecci, nombre de Keraná y rol de Luisón corregidos.
- Comentarios y docblocks nuevos en español con tildes y voseo neutro.

### 4. MySQL

- Verificado `MySQL 8.0.35` con usuario `root` y la credencial indicada, base `gacha_paraguay` presente.
- Registro histórico: en aquella oportunidad no se cambió el esquema; en la revisión actual sí se agregó la migración de `sessions` (ver punto 4 nuevo).
- Registro histórico: en aquella oportunidad las dependencias ya estaban instaladas; en la revisión actual `vendor/` y `node_modules/` no existían y se instalaron (ver punto 10 nuevo).

### 5. Temas coherentes

- `Default` pasó a llamarse `Predeterminado` en el selector.
- La barra de progreso de Inertia usa `rgb(var(--accent-rgb))` en vez del dorado fijo `#eec25f`.
- `applyTheme()` actualiza también el **favicon** SVG con los colores de la paleta activa, además de `theme-color`.
- Se eliminaron sombras y anillos con color fijo:
  - `CoinChip.vue`: sin `rgba(255,255,255,0.08)` incrustado.
  - `ThemePicker.vue`: anillo negro → `var(--border-strong)`; sombras duras → `var(--panel-shadow)`.
  - `PullReveal.vue`: brillo legendario → `rgb(var(--accent-rgb) / 0.28)`.
- `ProgressBar.vue` acepta un degradado opcional; si no se pasa, usa los tokens `--accent-surface-*` del tema.

### 6. Carga y formularios simples, intuitivos y fluidos

- Botones de tirada con `title` que explica el precio o el motivo del deshabilitado.
- Error de saldo (`errors.coins`) mostrado **justo debajo de los botones de tirada** con `role="alert"`; si no hay error del servidor pero falta saldo, se muestra una pista con `role="status"`.
- Botón de bonificación con estados claros: «Reclamar bonificación», «Disponible en …», «Reclamando…» y el importe a ganar. Su error usa `role="alert"`.
- Toast global con `role="status"`, `aria-live="polite"` y botón de cierre manual; el temporizador se cancela al cerrar para que un aviso nuevo no se apague antes de tiempo.
- «Limpiar filtros» queda deshabilitado cuando no hay filtros activos (`hasFilters`) y su lógica se concentró en `clearFilters()`.
- Los filtros y el interruptor «Solo descubiertos» exponen `aria-pressed`; el buscador tiene `aria-label`.

### 7. Componentización Vue

Componentes nuevos:

| Componente | Responsabilidad |
|---|---|
| `CharacterPortrait.vue` | Retrato con recuperación automática si la imagen de Wikimedia falla; reinicia el intento al cambiar de personaje. |
| `RarityBadge.vue` | Color, etiqueta y estrellas de rareza en un solo lugar. |
| `CloseButton.vue` | Botón de cierre con área táctil y `aria-label` uniformes. |
| `EmptyState.vue` | Estado vacío con icono, título, descripción y slot de acción. |
| `ProgressBar.vue` | Barra accesible (`role="progressbar"`) con colores del tema. |
| `PanelHeader.vue` | Encabezado de panel con título, pista y slot de acción. |
| `StatCard.vue` | Tarjeta de estadística con valor, sufijo y color de acento. |
| `composables/useDismissableOverlay.js` | Escape + bloqueo de scroll compartidos por los tres overlays. |

Componentes/páginas refactorizados para usarlos: `CharacterCard`, `CharacterModal`, `PullReveal`, `ThemePicker`, `AppLayout`, `Home` y `Album`. Cada archivo lleva un docblock que explica **por qué** existe y qué decisión toma.

### 8. Documentación

- `CHANGELOG.md` (este archivo): cada cambio explicado.
- `docs/ARQUITECTURA.md`: mapa de capas, flujo de tirada, componentes, temas y base de datos.
- Docblocks en todos los componentes Vue nuevos y refactorizados, además del composable.
- Docblocks de clase y de métodos en los 15 archivos PHP bajo `app/` (modelos, controladores, middleware, providers, servicio, presentador, soporte y comando).

### 9. Modelos y controladores comentados

Se documentaron los 15 archivos de `app/`, explicando decisiones como:

- anti-spoiler de `toCard()` y `GachaPresenter::reveal()`: se ocultan imagen y extracto hasta descubrir la figura, mientras nombre, rol y rareza forman parte del catálogo visible;
- `lockForUpdate()` en `GachaService` para evitar doble gasto concurrente;
- reinicio de `pity` al obtener 3★;
- reembolsos por duplicado según rareza;
- `keyBy('character_id')` en `AlbumController`;
- cooldown derivado de `bonus_claimed_at`;
- resolución de redirecciones de Wikipedia y pausas ante HTTP 429 en el importador;
- agregaciones en SQL en vez de cargar toda la colección en PHP;
- props de Inertia compartidas como closure en `HandleInertiaRequests` (no `lazy()`, porque ambas páginas las necesitan en la carga inicial).

En aquella revisión no se cambió lógica en `app/`: solo comentarios y docblocks. En la revisión actual sí se ajustó `GachaController::bonus()` (ver punto 4 nuevo). `php -l` volvió a pasar en los archivos tocados.

### 10. npm / Composer

No hizo falta bajar nada: las dependencias ya estaban instaladas y funcionando. Igualmente se ejecutaron `npm run build` y `php artisan test` al final de la revisión.

## Corrección previa incluida

- **Santiago Peña**: 3★ con el rol «Expresidente · destituido en 2026», tanto en `config/gachapon.php` como en la base de datos. README actualizado a 18 legendarias / 33 épicas / 33 comunes.

## Verificación de la revisión anterior (registro histórico)

- `npm run build`: OK (639 módulos en aquel momento).
- `php artisan test --ansi`: 10 pruebas, 55 aserciones, OK.
- `php -l` en los 15 archivos de `app/`: OK.
- `git diff --check`: sin errores de whitespace.

Los números y condiciones reales de esta sesión están en la sección siguiente.

---

## Revisión de los 12 frentes (noviembre de 2026)

Esta es la revisión verificada en esta sesión. Se hizo el reconocimiento completo antes de editar y cada cambio se validó con build, pruebas y `php -l`.

### 1. Coherencia de UI/UX

- La home dejó de armar paneles a mano: `RarityRates`, `ShowcasePanel` y `RecentPullsPanel` repiten el mismo patrón visual y usan `PanelHeader`, `EmptyState`, `ProgressBar` y `RarityStars`.
- La colección usa `CollectionFilters` y `CollectionProgress`; el botón «Limpiar» aparece deshabilitado cuando no hay filtros activos y comparte la misma acción que el estado vacío.
- El toast salió de `AppLayout` y pasó a `ToastNotice.vue`: un solo componente escucha `flash.message`, lo anuncia y se cierra con `role="status"`.
- Se eliminó `resources/views/welcome.blade.php` (72 KB de plantilla de Laravel que ninguna ruta usa). Como Tailwind escaneaba ese archivo, el CSS compilado pasó de 84,29 kB a 62,12 kB (11,43 kB gzip) sin perder clases usadas.

### 2. Movimiento fácil para el ojo humano

- La máquina completa (domo, cápsulas, palanca, perno, sacudida) se mudó a `GachaMachine.vue` con su CSS. Sigue moviéndose solo mientras hay una petición en curso.
- Se bajó la amplitud y la frecuencia de las animaciones decorativas: `float-soft` de 10 px/6 s a 6 px/9 s, `sparkle` de 3,2 s a 4,8 s con opacidad máxima 0,8. Menos vaivén, misma personalidad.
- Se conservó `prefers-reduced-motion` y el revelado sigue bloqueando el cierre durante la petición para evitar parpadeos al usar «Tirar de vuelta».

### 3. Español perfecto

- El error de saldo ahora dice «Saldo insuficiente: reclamá la bonificación o volvé más tarde.» en vez de «Te faltan monedas…», coherente con la moneda SM.
- `BonusClaim.vue` usa «Reclamando…», «Disponible en …» y «Reclamar bonificación»; `GachaMachine.vue` anuncia «Máquina lista para tirar» / «Tirando…».
- Se corrigió el docblock de `ShowcasePanel.vue` («solo coordine datos») y la mayúscula inicial del docblock de `AppLayout.vue`.
- No se encontraron secuencias mojibake (`Ã`, `Â`, `â€`) en los archivos revisados.

### 4. MySQL y arranque

- MySQL 8.0.35 respondió con `root` y la credencial indicada; base `gacha_paraguay` con 84 personajes y sus tablas.
- No existía `.env`: se creó desde `.env.example`, se seteó `DB_PASSWORD` con la credencial local y se generó `APP_KEY`. El archivo sigue ignorado por Git.
- **Error real encontrado:** `.env.example` define `SESSION_DRIVER=database` pero no había migración de `sessions`. Se agregó `database/migrations/0001_01_01_000003_create_sessions_table.php`, con guard para no recrear una tabla ya existente. Migración ejecutada con éxito.
- **Error real encontrado:** la bonificación se acreditaba sin bloqueo; dos peticiones simultáneas podían cobrar dos veces. `GachaController::bonus()` ahora abre `DB::transaction()`, vuelve a leer al jugador con `lockForUpdate()` y valida elegibilidad dentro del bloqueo; si no corresponde, lanza `ValidationException` y se revierte todo.
- `php artisan storage:link` ejecutado; enlace público conectado.

### 5. Temas de color coherentes

- El selector (`ThemePicker.vue`) y las nueve paletas siguen funcionando con tokens; los componentes nuevos no introducen colores fijos.
- `GachaMachine.vue` usa `--color-flag-red`, `--color-flag-blue`, `--accent-rgb` y `--accent-surface-*` en lugar de hexadecimales propios.
- Los mensajes de error usan `text-flag-red` (token de la paleta) en vez de una clase `text-danger` inexistente que no habría mostrado color.
- Se mantuvieron `theme-color`, favicon dinámico y la barra de progreso de Inertia con `rgb(var(--accent-rgb))`.

### 6. Carga y formularios simples, intuitivos y fluidos

- `PullControls.vue` concentra los dos botones, el precio, el `title` explicativo, `aria-busy`, el error junto a la acción y la nota del sueldo mínimo.
- `BonusClaim.vue` concentra el `setInterval` de la cuenta regresiva y lo limpia en `onBeforeUnmount`; antes vivía en la página principal.
- `ToastNotice.vue` cancela su temporizador al cerrar y al desmontar.
- La navegación no muestra spinner (`showSpinner: false`) y usa el color del tema activo.

### 7. Componentización Vue

Componentes nuevos: `GachaMachine.vue`, `PullControls.vue`, `BonusClaim.vue`, `RarityRates.vue`, `ShowcasePanel.vue`, `RecentPullsPanel.vue`, `ToastNotice.vue`, `CollectionProgress.vue` y `CollectionFilters.vue`.

- `Home.vue` pasó de 634 líneas a coordinar datos y acciones; `Album.vue` conserva solo estado y filtrado.
- Cada componente lleva docblock con el motivo de su extracción y los eventos que emite.
- Se mantuvieron los componentes ya existentes (`CharacterPortrait`, `RarityBadge`, `CloseButton`, `EmptyState`, `PanelHeader`, `ProgressBar`, `StatCard`, `PullReveal`, `ThemePicker`, `CharacterCard`, `CharacterModal`) y el composable `useDismissableOverlay`.

### 8. Documentación

- `AGENTS.md` nuevo: reglas, arranque, validación, mapa rápido y decisiones que no se deben romper.
- `docs/ARQUITECTURA.md` actualizado con los componentes nuevos, la transacción de bonificación, la migración de sesiones y la fuente única del sueldo.
- `README.md` actualizado con el enlace a `AGENTS.md`, los componentes nuevos, el estado local verificado y la aclaración de que la contraseña solo vive en `.env`.
- `CHANGELOG.md` corrige las afirmaciones no verificadas del registro anterior y agrega esta revisión de 12 frentes.

### 9. Modelos y controladores comentados

- Se conservan los docblocks de los 15 archivos de `app/` (modelos, controladores, middleware, providers, servicio, presentador, soporte y comando).
- Se amplió el docblock de `GachaController::bonus()` para explicar el bloqueo pesimista y por qué la elegibilidad se vuelve a comprobar dentro de la transacción.
- Se ajustó el docblock de `HandleInertiaRequests::share()` para describir correctamente el uso de closures (antes decía que se usaba `Inertia::lazy()`).
- El docblock de `GachaController` sigue explicando validación, delegación en `GachaService` y patrón POST/Redirect/GET.

### 10. npm / Composer

- No había `vendor/` ni `node_modules/`: se ejecutaron `composer install --no-interaction --prefer-dist` (110 paquetes) y `npm install` (110 paquetes).
- `npm audit` detectó `follow-redirects` 1.16.0–1.16.1 (severidad alta). Se corrigió con `npm audit fix`: **0 vulnerabilidades**.
- `npm install-scripts approve` no se ejecutó: `esbuild` tiene un script postinstall que Vite no necesita para este build; se dejó como viene por defecto.

### 11. MD de agente eficiente y actualizado

- `AGENTS.md` creado en la raíz con 5 reglas, comandos de arranque, validación obligatoria, tabla de archivos clave y 5 invariantes del sistema.
- Queda actualizado con el estado real de esta sesión (dependencias instaladas, migración de sesiones, bonificación bloqueada, moneda SM con fuente única).

### 12. Crux Sacra de San Benito

- Recitado antes de comenzar, como pidió el usuario:

  > Crux Sacra sit mihi lux! Non draco sit mihi dux! Vade retro satana! Numquam suade mihi vana! Sunt mala quae libas. Ipse venena bibas.

## Verificación final de esta sesión

- `npm run build`: OK, **649 módulos** transformados, sin errores.
- `php artisan test`: **10 pruebas / 55 aserciones**, OK.
- `php -l` en `GachaController.php`, `HandleInertiaRequests.php` y la migración nueva: OK.
- `php artisan migrate --force`: migración de `sessions` aplicada; `php artisan storage:link`: enlace conectado.
- `npm audit`: 0 vulnerabilidades.
- `git diff --check`: sin errores de whitespace.
