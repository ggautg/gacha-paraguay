# Auditoría integral — Gacha Guaraní

## Resumen ejecutivo

| Campo | Valor |
|---|---|
| Proyecto | Gacha Guaraní (`main`, commit `6f19f8b`) |
| Fecha de auditoría | 26 de noviembre de 2026 |
| Alcance | **Repositorio + MySQL local en vivo** (re-auditoría del 26/11). Instaladas `vendor/` y `node_modules/`; `.env` local creado y `APP_KEY` generada. Producción sigue **NO VERIFICADA** |
| Carga asumida | Baja/media, un servidor Laravel + MySQL, decenas a cientos de jugadores simultáneos |
| Calificación global | **C** — arquitectura sana y controles correctos verificados en vivo, pero con brechas operativas y de dependencias que corregir antes de producción |
| Hallazgos totales | 35 |

---

## Índice

- [Calificación global](#calificación-global)
- [Verificación en vivo](#verificación-en-vivo)
- [Conteo por severidad](#conteo-por-severidad)
- [Top 10 riesgos](#top-10-riesgos)
- [Resumen para gerencia](#resumen-para-gerencia)
- [Quick wins](#quick-wins)
- [Mapa del entregable](#mapa-del-entregable)
- [Remediaciones posteriores](#remediaciones-posteriores)
- [Límites del alcance](#límites-del-alcance)

---

## Calificación global

**C.** El sistema tiene decisiones de diseño acertadas: azar criptográfico con `random_int()`, transacciones con `lockForUpdate()` para tiradas y bonificación, anti-spoiler centralizado, PRG para el revelado, ausencia de SQL inyectable desde el usuario y ausencia de `v-html`/secretos en el bundle.

Baja la nota la combinación de tres factores:

1. Dependencias PHP desactualizadas con **14 avisos de seguridad** (`composer audit --locked`), incluido Laravel `v13.38.0` afectado por `CVE-2026-128083`.
2. La plantilla de entorno y la documentación promueven **MySQL como `root`** y no existe una configuración de producción verificable.
3. No hay **rate limiting**, cabeceras de seguridad, backups documentados ni CI que ejecute pruebas/auditorías.

Si se corrigen los ítems inmediatos del plan, la calificación puede subir a **B** sin cambiar la arquitectura.

---

## Verificación en vivo

La re-auditoría levantó MySQL 8.0.35 (servicio `MySQL80` ya activo), creó un `.env` local ignorado por Git e instaló dependencias. Evidencia nueva y relevante:

| Comprobación | Resultado | Impacto en el informe |
|---|---|---|
| `composer audit --locked` | 14 advisories / 6 paquetes | DEP-01 se mantiene en **Alta** |
| `php artisan test` con `DB_CONNECTION=mysql` | 10 pruebas / 55 aserciones en `gacha_paraguay_test` | QA-01 deja de ser «no ejecutada» |
| Dos `POST /tirar` simultáneos con saldo exacto | `coins=0`, `total_pulls=1`, `pulls=1` | `lockForUpdate()` **funciona** en MySQL real |
| `curl -I` a la app local | Sin CSP, HSTS, `nosniff` ni `frame-ancestors` | CFG-03 confirmado en HTTP |
| `Set-Cookie` local | `httponly; samesite=lax`, sin `Secure` (HTTP local) | CFG-04 confirmado como configuración de despliegue |
| `POST /tirar` sin token CSRF | `419` | Control CSRF confirmado |
| `GET /coleccion` | `"image":null,"extract":null` en figuras no obtenidas | Anti-spoiler confirmado por HTTP |
| `EXPLAIN` en `collection_entries` | `Using filesort` | DB-02 confirmado |
| `SHOW GRANTS` | `root` con `ALL PRIVILEGES ... WITH GRANT OPTION` en `*.*` | CFG-02 confirmado en el entorno local |
| `storage/app/public/characters` | 0 archivos, 72 rutas referenciadas, sin `public/storage` | Nuevo hallazgo **OPS-04** |
| `npm run build` | 649 módulos, sin errores | Build verificado |

---

## Conteo por severidad

| Severidad | Cantidad | IDs |
|---|---:|---|
| Crítica | 0 | — |
| Alta | 3 | CFG-01, CFG-02, DEP-01 |
| Media | 13 | CFG-03, CFG-04, BE-01, BE-02, API-01, DB-01, FE-01, QA-01, QA-02, OPS-01, OPS-02, OPS-04, COMP-01 |
| Baja | 12 | CFG-05, BE-03, BE-04, DB-02, DB-03, DB-04, FE-02, FE-03, DEP-02, QA-03, QA-04, OPS-03 |
| Informativa | 7 | CFG-06, BE-05, BE-06, API-02, API-03, DB-05, DEP-03 |
| **Total** | **35** | — |

No se registró ningún hallazgo **Crítico**: no hay ejecución remota de código, inyección SQL explotable desde el usuario, secretos versionados ni fuga masiva de datos verificada.

---

## Top 10 riesgos

| # | ID | Riesgo | Severidad | Por qué importa |
|---:|---|---|---|---|
| 1 | DEP-01 | 14 avisos en dependencias PHP, incluido Laravel < 13.40.0 | Alta | Vulnerabilidades conocidas con parche disponible; el framework está por debajo de la versión corregida |
| 2 | CFG-02 | MySQL como `root` en `.env.example` y README | Alta | Un fallo futuro o fuga de configuración entrega control total de la base |
| 3 | CFG-01 | `APP_DEBUG=true`, `APP_KEY` vacío y sin plantilla de producción | Alta | Si se despliega como está, errores detallados y sesiones/cifrado quedan expuestos |
| 4 | API-01 | Sin `throttle` en `/tirar`, `/bonificacion` ni en creación de jugadores | Media | Abuso automatizado, flood de filas y denegación de servicio con bajo costo |
| 5 | CFG-03 | Sin CSP, HSTS, `X-Frame-Options` ni `nosniff` | Media | Defensa en profundidad nula ante XSS, clickjacking y MIME sniffing |
| 6 | QA-01 | Pruebas con SQLite: `lockForUpdate()` no se ejerce | Media | La corrección de concurrencia más importante del proyecto no está cubierta |
| 7 | OPS-02 | Sin backups probados, RTO/RPO ni alertas | Media | Una pérdida de datos o caída podría pasar desapercibida o ser irreversible |
| 8 | DB-01 | `pulls`, `players`, `sessions`, `cache` y `jobs` sin purga | Media | Crecimiento ilimitado y retención de identificadores sin política |
| 9 | BE-02 | `gacha:import --fresh` confirma por defecto `true` | Media | Riesgo de borrar catálogo y progreso en ejecución no interactiva |
| 10 | OPS-04 | Retratos referenciados sin archivos ni `storage:link` en el entorno auditado | Media | 72 fichas apuntan a `/storage/...` que responde 403; la colección se ve incompleta |

---

## Resumen para gerencia

En lenguaje no técnico: **el juego funciona y está bien pensado**, pero hoy está más cerca de un prototipo bien hecho que de un servicio listo para producción abierta al público.

Las cosas buenas: no se guardan secretos en el repositorio, el saldo **no se pudo gastar dos veces** en una prueba real con dos peticiones simultáneas, el azar usa generación segura y las figuras no descubiertas no filtran imagen ni biografía (verificado en la respuesta HTTP). El código está documentado y las 10 pruebas pasan contra MySQL.

Las cosas que conviene arreglar antes de lanzar: actualizar las librerías PHP, dejar de usar la cuenta administradora de MySQL, apagar los mensajes de error detallados en producción, poner límites a cuántas peticiones puede hacer una misma IP, agregar cabeceras de seguridad, tener copias de respaldo probadas y desplegar los retratos y el enlace de `storage`. Ninguna exige reescribir el juego; son ajustes de configuración y de proceso.

El gasto estimado de los ítems inmediatos es de **1 a 3 días de trabajo** para una persona con experiencia en Laravel/MySQL.

---

## Quick wins

Marcados en el [plan de remediación](08-plan-de-remediacion.md) con **⚡**:

- `composer update` a versiones parcheadas y `composer audit` en CI.
- `.env.production.example` con `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`.
- Usuario MySQL `gacha_app` con privilegios mínimos.
- Middleware de cabeceras de seguridad.
- `throttle` en las tres rutas públicas.
- Borrar el test placeholder y el seeder que crea `Test User`.
- Ejecutar `php artisan storage:link` y verificar que las 72 fichas con retrato respondan 200 (OPS-04).

---

## Mapa del entregable

| Archivo | Contenido |
|---|---|
| [01-arquitectura.md](01-arquitectura.md) | Stack, estructura, servicios, despliegue, diagrama Mermaid y flujos |
| [02-seguridad.md](02-seguridad.md) | Hallazgos de seguridad de todas las capas + mapa OWASP |
| [03-backend.md](03-backend.md) | Backend Laravel: lógica, concurrencia, comando de importación |
| [04-base-de-datos.md](04-base-de-datos.md) | Esquema, índices, integridad, crecimiento y migraciones |
| [05-frontend.md](05-frontend.md) | Vue/Inertia: serialización, XSS, accesibilidad y rendimiento |
| [06-dependencias-devops.md](06-dependencias-devops.md) | Dependencias, pruebas, CI/CD, observabilidad y continuidad |
| [07-matriz-de-riesgos.md](07-matriz-de-riesgos.md) | Tabla priorizada probabilidad/impacto/esfuerzo |
| [08-plan-de-remediacion.md](08-plan-de-remediacion.md) | Roadmap 0-7 días, 1 mes y 1-3 meses |
| [09-buenas-practicas-encontradas.md](09-buenas-practicas-encontradas.md) | Controles correctos que conviene conservar |
| [10-anexos.md](10-anexos.md) | Comandos ejecutados, salidas, supuestos y exclusiones |

---

## Remediaciones posteriores

Después de cerrar el informe se implementaron dos de los hallazgos; **la matriz, el plan y los conteos de arriba conservan el estado original de la auditoría**:

| ID | Estado actual | Evidencia |
|---|---|---|
| CFG-02 | **Remediado** | La app corre con `gacha_app` (solo DML en las bases del proyecto, sin `GRANT OPTION`); `.env.example` y el README documentan el usuario de migraciones separado. `SHOW GRANTS`, `migrate:status` y una escritura revertida en transacción, OK. |
| FE-01 | **Remediado** | `useDismissableOverlay` guarda el foco previo, enfoca el primer control, atrapa `Tab`/`Shift+Tab` y restaura el foco; comprobado con un DOM falso y build correcto. Falta prueba en navegador real. |

Quedan **33 hallazgos abiertos** del total original de 35.

---

## Límites del alcance

Ya **verificado en esta re-auditoría**: servicios y puertos MySQL, grants del usuario local, esquema y volúmenes de `gacha_paraguay`, migraciones, `EXPLAIN`, variables del servidor, `route:list`, `about`, `test` sobre MySQL, `npm run build`, headers HTTP y cookies de la app local.

Sigue marcado como **NO VERIFICADO** y no se asumió como seguro:

- Valores reales de `.env` en producción (`APP_DEBUG`, `APP_KEY`, `SESSION_SECURE_COOKIE`, credenciales).
- Despliegue real, usuario MySQL de producción, TLS, PHP-FPM, `php.ini`, WAF y CDN.
- Backups efectivos, RTO/RPO y alertas en el servidor de producción.
- Retratos en producción: el hallazgo OPS-04 se verificó **solo en este workspace**.
- Tráfico real, latencia, tasas de error y comportamiento bajo carga alta.
