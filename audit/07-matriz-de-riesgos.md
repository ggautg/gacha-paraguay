# Matriz de riesgos priorizada

## Índice

- [Metodología](#metodología)
- [Matriz completa](#matriz-completa)
- [Mapa de calor](#mapa-de-calor)
- [Top 5 por exposición](#top-5-por-exposición)
- [Notas sobre la priorización](#notas-sobre-la-priorización)

---

## Metodología

La severidad de cada hallazgo (**Crítica | Alta | Media | Baja | Informativa**) se mantuvo intacta respecto de las capas anteriores. Esta matriz agrega dos ejes:

- **Probabilidad:** chances de que el escenario ocurra en los próximos 6-12 meses con la postura actual del repo y la carga asumida (baja/media, 1 servidor). No es una probabilidad estadística; es una estimación cualitativa.
- **Impacto:** daño si ocurre (pérdida de dinero, datos, disponibilidad, cumplimiento o confianza).

**Exposición** = combinación de ambos ejes:

| Probabilidad \ Impacto | Alto | Medio | Bajo |
|---|---|---|---|
| **Alta** | Crítica | Alta | Media |
| **Media** | Alta | Media | Media |
| **Baja** | Media | Baja | Informativa |

**Prioridad operativa:**

| Prioridad | Significado | Horizonte del plan |
|---|---|---|
| **P0** | Exposición Crítica o Alta con parche conocido | 0-7 días |
| **P1** | Exposición Alta o Media con impacto de seguridad/operación | 0-7 / 1 mes |
| **P2** | Exposición Media sin explotación confirmada | 1 mes |
| **P3** | Exposición Baja o Informativa | 1-3 meses / backlog |

**NO VERIFICADO:** la probabilidad no incorpora datos de producción (tráfico, logs, backups reales, headers efectivos). Si el despliegue es público y sin proxy, CFG-01/CFG-04/API-01 suben de probabilidad.

---

## Matriz completa

| ID | Hallazgo | Severidad | Prob. | Impacto | Exposición | Esfuerzo | Prioridad | Archivo |
|---|---|---|---|---|---|---|---|---|
| CFG-01 | Defaults inseguros de `.env.example` | Alta | Media | Alto | Alta | S | P0 | [02-seguridad.md](#cfg-01) |
| CFG-02 | MySQL como `root` | Alta | Media | Alto | Alta | S/M | P0 | [02-seguridad.md](#cfg-02) |
| DEP-01 | 14 avisos en dependencias PHP | Alta | Alta | Alto | Crítica | S/M | P0 | [06-dependencias-devops.md](#dep-01) |
| CFG-03 | Sin cabeceras de seguridad HTTP | Media | Media | Medio | Media | M | P1 | [02-seguridad.md](#cfg-03) |
| CFG-04 | Cookies sin `secure` explícito | Media | Media | Medio | Media | S | P1 | [02-seguridad.md](#cfg-04) |
| BE-01 | Identidad sin revocación ni rotación | Media | Media | Medio | Media | M | P2 | [03-backend.md](#be-01) |
| BE-02 | `--fresh` confirma por defecto `true` | Media | Media | Alto | Alta | S | P1 | [03-backend.md](#be-02) |
| API-01 | Sin rate limiting en rutas públicas | Media | Alta | Medio | Alta | S/M | P1 | [02-seguridad.md](#api-01) |
| DB-01 | Crecimiento ilimitado sin purga | Media | Alta | Medio | Alta | M | P1 | [04-base-de-datos.md](#db-01) |
| FE-01 | Overlays sin focus trap | Media | Media | Medio | Media | M | P2 | [05-frontend.md](#fe-01) |
| QA-01 | Pruebas SQLite: `lockForUpdate()` no se ejerce | Media | Alta | Medio | Alta | M | P1 | [06-dependencias-devops.md](#qa-01) |
| QA-02 | Sin prueba HTTP de `/tirar` ni anti-spoiler fuerte | Media | Media | Medio | Media | S/M | P2 | [06-dependencias-devops.md](#qa-02) |
| OPS-01 | Sin CI de pruebas/build/auditorías | Media | Alta | Medio | Alta | M | P1 | [06-dependencias-devops.md](#ops-01) |
| OPS-02 | Sin backups probados ni alertas | Media | Media | Alto | Alta | M | P1 | [06-dependencias-devops.md](#ops-02) |
| OPS-04 | Retratos referenciados sin archivos ni `storage:link` | Media | Alta | Medio | Alta | S/M | P1 | [06-dependencias-devops.md](#ops-04) |
| COMP-01 | Identidad anónima con retención sin aviso | Media | Media | Medio | Media | M | P2 | [02-seguridad.md](#comp-01) |
| CFG-05 | Log `debug` en archivo único | Baja | Media | Bajo | Media | S | P2 | [02-seguridad.md](#cfg-05) |
| BE-03 | Retratos sin MIME real ni tamaño máximo | Baja | Baja | Medio | Baja | S/M | P3 | [03-backend.md](#be-03) |
| BE-04 | Catálogo completo por tirada | Baja | Media | Bajo | Media | S | P3 | [03-backend.md](#be-04) |
| DB-02 | Falta índice en `collection_entries.updated_at` | Baja | Media | Bajo | Media | S | P3 | [04-base-de-datos.md](#db-02) |
| DB-03 | Cascada borra historial de auditoría | Baja | Baja | Medio | Baja | M | P3 | [04-base-de-datos.md](#db-03) |
| DB-04 | Migración de reescalado sin transacción | Baja | Baja | Medio | Baja | S | P3 | [04-base-de-datos.md](#db-04) |
| FE-02 | Google Fonts sin consentimiento/CSP | Baja | Media | Bajo | Media | S/M | P2 | [05-frontend.md](#fe-02) |
| FE-03 | Intervalo de bonificación siempre activo | Baja | Alta | Bajo | Media | S | P3 | [05-frontend.md](#fe-03) |
| DEP-02 | `minimum-stability: dev` | Baja | Baja | Medio | Baja | S | P3 | [06-dependencias-devops.md](#dep-02) |
| QA-03 | Prueba unitaria placeholder | Baja | Alta | Bajo | Media | S | P3 | [06-dependencias-devops.md](#qa-03) |
| QA-04 | Seeder con contraseña `password` | Baja | Baja | Bajo | Informativa | S | P3 | [06-dependencias-devops.md](#qa-04) |
| OPS-03 | `/up` no comprueba dependencias | Baja | Media | Bajo | Media | S | P3 | [06-dependencias-devops.md](#ops-03) |
| CFG-06 | Infraestructura y CORS no versionados | Informativa | Baja | Medio | Baja | M | P3 | [02-seguridad.md](#cfg-06) |
| BE-05 | Código de scaffold sin uso | Informativa | Baja | Bajo | Informativa | S | P3 | [03-backend.md](#be-05) |
| BE-06 | Reglas económicas sin validación | Informativa | Media | Bajo | Media | S | P3 | [03-backend.md](#be-06) |
| API-02 | Sin versionado ni documentación de API | Informativa | Baja | Bajo | Informativa | M | P3 | [02-seguridad.md](#api-02) |
| API-03 | Health check `/up` expuesto | Informativa | Baja | Bajo | Informativa | S | P3 | [02-seguridad.md](#api-03) |
| DB-05 | Charset heredado de la conexión | Informativa | Baja | Bajo | Informativa | S | P3 | [04-base-de-datos.md](#db-05) |
| DEP-03 | Axios global sin uso y sin linting | Informativa | Media | Bajo | Media | S | P3 | [06-dependencias-devops.md](#dep-03) |

Los enlaces con anclas `#cfg-01`, `#dep-01`, etc., apuntan a los marcadores explícitos colocados antes de cada encabezado en los archivos correspondientes.

---

## Mapa de calor

| Probabilidad \ Impacto | Alto | Medio | Bajo |
|---|---|---|---|
| **Alta** | DEP-01 | API-01, DB-01, QA-01, OPS-01 | FE-03, QA-03 |
| **Media** | CFG-01, CFG-02, BE-02, OPS-02, OPS-04 | CFG-03, CFG-04, BE-01, FE-01, QA-02, COMP-01 | CFG-05, BE-04, DB-02, FE-02, BE-06, DEP-03, OPS-03 |
| **Baja** | — | BE-03, DB-03, DB-04, DEP-02, CFG-06 | QA-04, BE-05, API-02, API-03, DB-05 |

Conteo por celda: 1 / 5 / 2 / 4 / 6 / 7 / 0 / 5 / 5 = **35 hallazgos**.

---

## Top 5 por exposición

| # | ID | Exposición | Acción inmediata |
|---:|---|---|---|
| 1 | DEP-01 | Crítica | `composer update` a versiones parcheadas + `composer audit --locked` en CI |
| 2 | CFG-01 | Alta | `.env.production.example` con `APP_DEBUG=false`, `APP_KEY` generada y HTTPS |
| 3 | CFG-02 | Alta | Usuario MySQL `gacha_app` con privilegios mínimos |
| 4 | BE-02 | Alta | Default de confirmación `false` + `--force` para modo no interactivo |
| 5 | API-01 / QA-01 / DB-01 / OPS-01 / OPS-02 / OPS-04 | Alta | Rate limit, pruebas MySQL, purga, CI, backup restaurado y `storage:link` |

---

## Notas sobre la priorización

- **Ningún hallazgo es Crítico por severidad.** La única celda «Crítica» de exposición aparece por la combinación de probabilidad alta e impacto alto en DEP-01, porque hay parche disponible y el framework está por debajo de la versión corregida.
- **Los esfuerzos S/M son estimaciones.** Tras instalar dependencias se ejecutaron `composer audit`, `php artisan test` sobre MySQL, `npm run build` y pruebas HTTP; no se hizo un `composer update`, así que DEP-01 sigue abierto y su esfuerzo real puede subir si hay conflictos.
- **Concurrencia confirmada, prioridad de QA-01 sin cambios:** el doble gasto no se reproduce en MySQL real (`coins=0`, `total_pulls=1`, `pulls=1`), pero la suite corre sobre SQLite y no lo protege contra regresiones.
- **Lo que NO VERIFICADO no baja la prioridad:** CFG-01, CFG-04, OPS-02, OPS-04 y CFG-06 dependen del despliegue real. Se priorizan con la configuración y el entorno local observados; al obtener acceso a producción debe reevaluarse su probabilidad.
- **Las palancas de negocio** (ganancia de seguridad por día de trabajo) están en CFG-01, CFG-02, DEP-01 y API-01: todas son cambios de configuración o comandos, sin reescribir la lógica del juego.
