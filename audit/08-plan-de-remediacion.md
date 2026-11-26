# Plan de remediación

## Índice

- [Principios](#principios)
- [Resumen por horizonte](#resumen-por-horizonte)
- [Fase 1 — 0 a 7 días (P0/P1)](#fase-1--0-a-7-días-p0p1)
- [Fase 2 — 1 mes (P2)](#fase-2--1-mes-p2)
- [Fase 3 — 1 a 3 meses (P3 / informativos)](#fase-3--1-a-3-meses-p3--informativos)
- [Criterios de aceptación](#criterios-de-aceptación)
- [Rollback y seguridad operativa](#rollback-y-seguridad-operativa)

> ⚡ = quick win: cambio pequeño, alto retorno, sin reescribir la lógica del juego.

---

## Principios

1. **No tocar código por fuera del entregable de auditoría.** Este plan describe cambios; su ejecución requiere una tarea de implementación separada.
2. **Cumplir `AGENTS.md`:** español en documentación, `.env` nunca versionado, lógica de UI en `Components/`, tokens CSS, docblock del **por qué**, y `CHANGELOG.md` + `docs/ARQUITECTURA.md` en cada cambio.
3. **Si cambia el esquema, crear migración nueva.** Nunca editar `2026_10_23_000001_create_gachapon_tables.php` ni `2026_10_26_000000_switch_currency_to_sueldo_minimo.php`.
4. **Validar antes de cerrar:** `php artisan test`, `npm run build`, `php -l`, `git diff --check`, `git status --short`.
5. **Medir con MySQL real:** las pruebas de concurrencia y los `EXPLAIN` deben ejecutarse contra MySQL, no SQLite.

---

## Resumen por horizonte

| Horizonte | Objetivo | IDs cubiertos | Esfuerzo agregado |
|---|---|---|---|
| **0-7 días** | Cerrar brechas explotables y dejar gates automáticos | DEP-01, CFG-01, CFG-02, CFG-03, CFG-04, CFG-05, BE-02, API-01, DB-01, QA-01, QA-03, QA-04, OPS-01, OPS-02, OPS-04 | 1-3 días-persona |
| **1 mes** | Calidad, accesibilidad, privacidad y observabilidad | BE-01, BE-03, BE-04, BE-06, DB-02, DB-04, FE-01, FE-02, FE-03, QA-02, COMP-01, DEP-02, OPS-03 | 3-5 días-persona |
| **1-3 meses** | Reproducibilidad y decisiones de largo plazo | CFG-06, BE-05, API-02, API-03, DB-03, DB-05, DEP-03 | 2-4 días-persona |

---

## Fase 1 — 0 a 7 días (P0/P1)

| # | ID | Acción | ⚡ | Esfuerzo | Responsable sugerido |
|---:|---|---|:---:|---|---|
| 1 | DEP-01 | `composer update` a versiones parcheadas; `composer audit --locked` debe quedar en 0; verificar `php artisan test` y `npm run build` | ⚡ | S/M | Backend |
| 2 | CFG-01 | Crear `.env.production.example` con `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY=base64:...`, `APP_URL=https://...`, `LOG_LEVEL=warning`; check de deploy que rechace debug en producción | ⚡ | S | DevOps |
| 3 | CFG-02 | Crear `gacha_app` con `SELECT, INSERT, UPDATE, DELETE` solo en `gacha_paraguay.*`; usuario separado para migraciones; rotar credencial actual | ⚡ | S/M | DBA |
| 4 | BE-02 | Cambiar `$this->confirm(..., true)` por `false`; exigir `--force` en modo no interactivo | ⚡ | S | Backend |
| 5 | API-01 | `throttle:30,1` en `/tirar`, `throttle:10,1` en `/bonificacion` y límite en creación de jugadores de `EnsurePlayer` | ⚡ | S/M | Backend |
| 6 | CFG-03 | Middleware `SecurityHeaders` con `nosniff`, `Referrer-Policy`, `frame-ancestors 'self'`; HSTS solo tras confirmar HTTPS; CSP con nonce para el script inline del tema |  | M | Backend/DevOps |
| 7 | CFG-04 | `SESSION_SECURE_COOKIE=true` en producción y parámetros explícitos en `Cookie::queue()` |  | S | Backend |
| 8 | QA-01 | Base MySQL de pruebas en `phpunit.xml`; prueba de doble gasto y doble bonificación con dos conexiones |  | M | QA/Backend |
| 9 | OPS-01 | Workflow de CI: `composer install`, `composer audit --locked`, `php -l`, `php artisan test`, `npm ci`, `npm audit`, `npm run build` |  | M | DevOps |
| 10 | OPS-02 | `mysqldump` diario + `storage/characters` con retención de 30 días; **restore drill** documentado; alerta de fallo de backup |  | M | DevOps |
| 11 | DB-01 | Comando `gacha:prune` por lotes y schedule diario; retención acordada con COMP-01 |  | M | Backend/DBA |
| 12 | CFG-05 | `LOG_STACK=daily`, `LOG_LEVEL=warning`, `LOG_DAILY_DAYS=30` en plantilla de producción | ⚡ | S | DevOps |
| 13 | QA-03 / QA-04 | Borrar `tests/Unit/ExampleTest.php` y el `User` del seeder; o reemplazarlos por pruebas/seeders con valor | ⚡ | S | Backend |
| 14 | OPS-04 | `php artisan storage:link` + importar/verificar los retratos; check de deploy que confirme `Storage::disk('public')->exists()` sobre una muestra del catálogo | ⚡ | S/M | DevOps |

**Orden sugerido dentro de la fase:** 1 → 9 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 10 → 11 → 12 → 13 → 14. Instalar primero el gate de CI evita que los cambios siguientes se regresen.

**Verificación de cierre de fase:**

```bash
composer audit --locked          # expected: 0 advisories
php artisan test                 # expected: green on MySQL
npm run build                    # expected: green
php -l app/Http/Controllers/GachaController.php
git diff --check
git status --short
```

---

## Fase 2 — 1 mes (P2)

| # | ID | Acción | Esfuerzo | Criterio de aceptación |
|---:|---|---|---|---|
| 15 | FE-01 | Focus trap + restauración de foco en `useDismissableOverlay` (o `<dialog>` nativo) | M | Tab no sale del modal; foco vuelve al botón que lo abrió |
| 16 | QA-02 | Pruebas HTTP de `/tirar` (válida, count inválido, saldo, flash) y aserciones `image = null`, `extract = null` en `/coleccion` | S/M | Suite cubre CSRF, PRG y anti-spoiler completo |
| 17 | COMP-01 | Aviso de privacidad, retención formal y endpoint de exportación/borrado del progreso | M | Usuario puede eliminar `Player`, `CollectionEntry` y `Pull` |
| 18 | BE-01 | Rotación y revocación de `gacha_player`; acortar vigencia | M | Existe flujo «reiniciar progreso» y token nuevo |
| 19 | DB-02 | Migración nueva con `INDEX(player_id, updated_at)` | S | `EXPLAIN` no muestra `filesort` en la consulta de la vitrina |
| 20 | BE-03 | Validar MIME/tamaño del retrato con `getimagesizefromstring()` | S/M | Se rechazan cuerpos no-imagen y mayores al límite |
| 21 | BE-06 | Comando `gacha:config:check` en CI | S | Falla si tasas no suman 100 o precios no son consistentes |
| 22 | DB-04 | Aplicar patrón de transacción + flag de idempotencia a migraciones de datos futuras | S | Documentado en `docs/ARQUITECTURA.md` |
| 23 | OPS-03 | Endpoint `/health/ready` con DB + storage y throttle | S | Monitor alerta si la BD no responde aunque `/up` esté verde |
| 24 | FE-02 | Auto-hospedar Cinzel/Inter o condicionar Google Fonts al consentimiento | S/M | Cero peticiones a terceros antes de consentir |
| 25 | FE-03 | Pausar el intervalo cuando `seconds === 0` o la pestaña esté oculta | S | Sin re-renders después de reclamar la bonificación |
| 26 | DEP-02 | `minimum-stability: stable` | S | `composer validate` y lockfile sin paquetes dev |
| 27 | BE-04 | Cachear pozos de rareza con invalidación en `gacha:import` (solo si el catálogo crece) | S | Una consulta al catálogo por hora, no por tirada |

---

## Fase 3 — 1 a 3 meses (P3 / informativos)

| # | ID | Acción | Esfuerzo |
|---:|---|---|---|
| 28 | CFG-06 | Versionar Nginx/Apache, PHP-FPM, `php.ini`, `TRUSTED_PROXIES` y CORS explícito si aparece API | M |
| 29 | BE-05 | Eliminar `User`, `UserFactory`, `DatabaseSeeder` y `inspire`, o documentar por qué se conservan | S |
| 30 | API-02 | Si se publica API, crear `/api/v1` con OpenAPI; si no, documentar que no existe API pública | M |
| 31 | API-03 | Decidir si `/up` es público o se restringe en WAF/balanceador; documentarlo | S |
| 32 | DB-03 | Decidir entre `nullOnDelete()` para conservar auditoría o borrado total por derecho de supresión | M |
| 33 | DB-05 | Declarar charset/collation en la creación de la BD y verificarlos en cada despliegue (local ya es `utf8mb4`) | S |
| 34 | DEP-03 | Quitar `bootstrap.js`/Axios si no se usan; agregar ESLint si se incorpora más lógica JS | S |

---

## Criterios de aceptación

Globales, aplicables a todas las fases:

- [ ] `composer audit --locked` sin advisories.
- [ ] `npm audit --audit-level=low` sin vulnerabilidades.
- [ ] `php artisan test` verde sobre **MySQL**, con la prueba de concurrencia incluida.
- [ ] `npm run build` verde.
- [ ] `php -l` sobre los 15 archivos de `app/` sin errores.
- [ ] `git diff --check` sin salida y `git status --short` mostrando solo los archivos previstos.
- [ ] `CHANGELOG.md` y `docs/ARQUITECTURA.md` actualizados en cada cambio de código.
- [ ] Ningún `.env`, credencial ni log versionado.
- [ ] Nuevas migraciones, nunca edición de migraciones aplicadas.

Por hallazgo:

| ID | Criterio específico |
|---|---|
| DEP-01 | `laravel/framework >= 13.40.0` y 0 advisories |
| CFG-01 | `APP_DEBUG=false` en producción; `APP_KEY` generada |
| CFG-02 | `SHOW GRANTS FOR 'gacha_app'` sin `DROP`, sin permisos en otras bases |
| CFG-03 | `curl -I` muestra `nosniff` y `frame-ancestors 'self'` |
| CFG-04 | `Set-Cookie` de `gacha_player` con `Secure; HttpOnly; SameSite=Lax` |
| API-01 | 31ª petición a `/tirar` en un minuto responde 429 |
| BE-02 | `gacha:import --fresh --no-interaction` sin `--force` termina con FAILURE |
| QA-01 | La prueba de doble gasto corre sobre MySQL y falla si se quita `lockForUpdate()` |
| OPS-02 | Restore drill: la copia se restaura en BD temporal y la app arranca |
| OPS-04 | `php artisan storage:link` presente y `curl -I /storage/characters/...` responde `200 image/jpeg` |
| FE-01 | Navegación con Tab confinada al diálogo |
| COMP-01 | Existe enlace de privacidad y flujo de borrado |

---

## Rollback y seguridad operativa

| Cambio | Riesgo de rollback | Procedimiento |
|---|---|---|
| `composer update` | Medio | Conservar `composer.lock` anterior en tag; `composer install` con ese lock |
| `.env.production.example` | Bajo | Es archivo nuevo; no reemplazar `.env` real sin ventana de mantenimiento |
| Usuario MySQL `gacha_app` | Medio | Probar grants en staging; mantener usuario actual hasta verificar migraciones |
| `SecurityHeaders` / CSP | Medio | Desplegar CSP en modo `Content-Security-Policy-Report-Only` primero; revisar consola |
| `throttle` | Bajo | Ajustar límites por config, sin tocar rutas |
| Migración DB-02 | Bajo | `down()` con `dropIndex` en la misma migración nueva |
| Purga DB-01 | **Alto** | Ejecutar primero en modo `--dry-run`; nunca borrar sin backup verificado (OPS-02) |
| `storage:link` + retratos OPS-04 | Bajo | El symlink se puede recrear; conservar `storage/app/public/characters` antes de reimportar |
| `--force` en `--fresh` | Bajo | Solo afecta automatizaciones; documentar en README |

**Prohibido durante la remediación:** versionar `.env`, editar migraciones ya aplicadas, ejecutar `gacha:import --fresh` sin backup, desactivar CSRF para «arreglar» pruebas, o fijar `APP_DEBUG=true` en producción para depurar.
