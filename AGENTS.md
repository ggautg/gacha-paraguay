# AGENTS.md

Guía breve para agentes que trabajen en **Gacha Guaraní**. El detalle de cada cambio está en [`CHANGELOG.md`](CHANGELOG.md) y el mapa técnico en [`docs/ARQUITECTURA.md`](docs/ARQUITECTURA.md).

## Reglas del proyecto

- Responder, comentar y documentar **en español** (voseo neutro, sin mojibake).
- No versionar `.env` ni credenciales. La base local ya quedó configurada con la contraseña indicada por el usuario; se guarda solo en `.env`.
- Leer `config/gachapon.php`, `GachaService.php` y `GachaPresenter.php` antes de tocar economía, rarezas o anti-spoiler.
- Toda lógica de interfaz nueva va en `resources/js/Components/`; las páginas solo coordinan datos.
- Usar tokens CSS (`--accent-*`, `--surface-*`, `--border-*`) en vez de colores fijos y respetar `prefers-reduced-motion`.
- Todo archivo PHP bajo `app/` y todo componente Vue lleva un docblock que explique **por qué** existe, no qué hace línea por línea.
- Cambiar `CHANGELOG.md` y `docs/ARQUITECTURA.md` en cada cambio. Si cambia el esquema, crear una migración nueva: nunca editar una ya aplicada.

## Arranque local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
npm run build
php artisan serve
```

- Base: MySQL 8, `gacha_paraguay`, usuario `root`. La contraseña se toma de `.env` (no se comparte en archivos versionados).
- Si hay que importar el catálogo: `php artisan gacha:import` (o `--missing` para reintentar retratos).
- Desarrollo con recarga: `npm run dev` en otra terminal.

## Validación obligatoria antes de terminar

```bash
php artisan test
npm run build
php -l app/Http/Controllers/GachaController.php
git diff --check
git status --short
```

Estado verificado en la última revisión: 10 pruebas / 55 aserciones y build de 649 módulos, sin errores.

## Mapa rápido

| Necesidad | Archivo |
|---|---|
| Plantel, precios, tasas, `pity`, sueldo | `config/gachapon.php` |
| Azar, transacción, reembolsos | `app/Services/GachaService.php` |
| Forma de los datos para Vue y anti-spoiler | `app/Support/GachaPresenter.php` |
| Identidad anónima por cookie | `app/Http/Middleware/EnsurePlayer.php` |
| Props compartidas (`player`, `gacha`, `flash`) | `app/Http/Middleware/HandleInertiaRequests.php` |
| Máquina y controles | `resources/js/Components/GachaMachine.vue`, `PullControls.vue` |
| Bonificación y count-down | `resources/js/Components/BonusClaim.vue` |
| Revelado | `resources/js/Components/PullReveal.vue` |
| Filtros y progreso de colección | `resources/js/Components/CollectionFilters.vue`, `CollectionProgress.vue` |
| Temas (9 paletas) | `resources/js/lib/themes.js`, `resources/css/app.css` |
| Pruebas | `tests/Feature/GachaTest.php` |

## Decisiones que no se deben romper

- **PRG:** `/tirar` y `/bonificacion` son POST que redirigen; el revelado viaja en el flash de sesión.
- **Concurrencia:** tiradas y bonificación bloquean la fila del jugador con `lockForUpdate()` dentro de una transacción.
- **Anti-spoiler:** imagen y extracto se ocultan hasta descubrir la figura; nombre, rol y rareza son catálogo visible.
- **Moneda:** el saldo se mide en sueldos mínimos (SM). El importe sale de `config/gachapon.php` y se pasa al navegador en `gacha.salary.amount`; no duplicar el número en JS.
- **Sesiones:** `SESSION_DRIVER=database` exige la tabla `sessions`, creada por `0001_01_01_000003_create_sessions_table.php`.
