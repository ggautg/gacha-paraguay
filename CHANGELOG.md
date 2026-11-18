# Registro de cambios

Este archivo explica **qué se cambió y por qué** en la revisión integral del sistema. Para el mapa técnico de archivos y flujos, mirá [`docs/ARQUITECTURA.md`](docs/ARQUITECTURA.md).

## Revisión integral (10 frentes)

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
- Sin cambios de esquema: solo se ajustaron tres filas de texto para reflejar las correcciones del plantel.
- No hizo falta instalar dependencias: Composer 2.10.2 y npm 11.20.0 ya estaban disponibles con `vendor/` y `node_modules/` instalados.

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
- props de Inertia diferidas en `HandleInertiaRequests`.

**No se cambió ninguna línea de lógica** en `app/`: solo comentarios y docblocks. `php -l` pasó en los 15 archivos.

### 10. npm / Composer

No hizo falta bajar nada: las dependencias ya estaban instaladas y funcionando. Igualmente se ejecutaron `npm run build` y `php artisan test` al final de la revisión.

## Corrección previa incluida

- **Santiago Peña**: 3★ con el rol «Expresidente · destituido en 2026», tanto en `config/gachapon.php` como en la base de datos. README actualizado a 18 legendarias / 33 épicas / 33 comunes.

## Verificación final

- `npm run build`: OK (639 módulos, sin errores).
- `php artisan test --ansi`: 10 pruebas, 55 aserciones, OK.
- `php -l` en los 15 archivos de `app/`: OK.
- `git diff --check`: sin errores de whitespace.
- Servidor de desarrollo levantado y cerrado a pedido; MySQL permanece disponible.
