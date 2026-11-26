# Base de datos (MySQL 8)

## Índice

- [Esquema verificado](#esquema-verificado)
- [Índices e integridad](#índices-e-integridad)
- [Hallazgos](#hallazgos)
- [Crecimiento y retención](#crecimiento-y-retención)
- [Límites de verificación](#límites-de-verificación)
- [Buenas prácticas](#buenas-prácticas)

---

## Esquema verificado

Fuente: `database/migrations/2026_10_23_000001_create_gachapon_tables.php:11-56`, más migraciones de framework y la migración de reescalado `2026_10_26_000000_switch_currency_to_sueldo_minimo.php`.

| Tabla | Columnas clave | Índices / restricciones | Propósito |
|---|---|---|---|
| `characters` | `id`, `slug` unique, `name`, `wiki_title`, `wiki_url`, `image_path?`, `extract?`, `role?`, `era?`, `category?`, `rarity` default 1, `timestamps` | PK, `UNIQUE(slug)`, `INDEX(rarity)` | Catálogo de 84 figuras |
| `players` | `id`, `token` UUID unique, `name`, `coins` unsignedBigInteger default 9132000, `total_pulls`, `pity`, `bonus_claimed_at?`, `timestamps` | PK, `UNIQUE(token)` | Jugador anónimo y economía |
| `collection_entries` | `id`, `player_id`, `character_id`, `quantity` default 1, `timestamps` | PK, FK cascade a `players`/`characters`, `UNIQUE(player_id, character_id)` | Copias por jugador |
| `pulls` | `id`, `player_id`, `character_id`, `rarity`, `is_new`, `created_at`, `updated_at` | PK, FK cascade, `INDEX(player_id, created_at)` | Historial de auditoría |
| `sessions` | `id`, `user_id?`, `ip_address?`, `user_agent?`, `payload`, `last_activity` | PK, `INDEX(last_activity)`, `INDEX(user_id)` | Sesiones (`SESSION_DRIVER=database`) |
| `cache`, `cache_locks` | `key`, `value`/`owner`, `expiration` | PK, `INDEX(expiration)` | Caché y locks |
| `jobs`, `job_batches`, `failed_jobs` | — | ver migraciones de framework | Cola configurada pero sin jobs propios |

`Pull` declara `const UPDATED_AT = null` (`app/Models/Pull.php:17-18`), coherente con la idea de evento inmutable.

---

## Índices e integridad

- `UNIQUE(player_id, character_id)` evita duplicar filas de colección; `GachaService` usa `firstOrNew()` sobre esa llave (`GachaService.php:80-83`).
- `UNIQUE(token)` protege la identidad anónima (`EnsurePlayer.php:38-44`).
- `INDEX(player_id, created_at)` en `pulls` cubre `latest('id')` con filtro por jugador (`GachaController.php:38-42`); el orden por `id` no coincide exactamente con el prefijo del índice, pero el filtro por `player_id` sí.
- `INDEX(rarity)` en `characters` apoya la agrupación por rareza (`GachaPresenter.php:31-34`).
- Foreign keys con `cascadeOnDelete()` mantienen consistencia, con la salvedad de DB-03.
- `strict => true` en `config/database.php:60`: MySQL rechaza valores inválidos en vez de recortarlos silenciosamente.

---

## Hallazgos

<a id="db-01"></a>
### [DB-01] Crecimiento ilimitado y sin política de purga

- **Severidad:** Media
- **Capa:** Base de datos / Operaciones
- **Referencia:** CWE-770 (Allocation of Resources Without Limits), GDPR Art. 5(1)(e)
- **Ubicación:** `database/migrations/2026_10_23_000001_create_gachapon_tables.php:26-56`, `database/migrations/0001_01_01_000003_create_sessions_table.php:23-30`, `app/Console/Commands/ImportCharacters.php:87-97`
- **Descripción:** no hay comando, scheduled task ni política para purgar `pulls`, `players` inactivos, `sessions` expiradas, `cache` ni `jobs`. `--fresh` borra todo, pero no es una política de retención: es un reinicio manual y destructivo.
- **Evidencia:**
  ```php
  // create_gachapon_tables.php:47-56
  Schema::create('pulls', function (Blueprint $table) {
      $table->id();
      $table->foreignId('player_id')->constrained()->cascadeOnDelete();
      $table->foreignId('character_id')->constrained()->cascadeOnDelete();
      $table->unsignedTinyInteger('rarity');
      $table->boolean('is_new')->default(false);
      $table->timestamps();
      $table->index(['player_id', 'created_at']);
  });
  ```
  ```text
  git grep for "prune|purge|deleteOlderThan|schedule(" -> no maintenance command found
  ```
- **Impacto / Por qué importa:** cada tirada múltiple inserta 10 filas en `pulls` y cada visita sin cookie crea un `player`. Con el tiempo la tabla de historial crece sin límite, las copias de seguridad tardan más y los identificadores de jugadores inactivos se conservan indefinidamente (ver COMP-01).
- **Escenario de explotación o fallo:** un bot sin rate limit (API-01) crea miles de jugadores y tiradas; `pulls` pasa de 100 mil a millones de filas en horas y el espacio en disco se agota.
- **Recomendación:** definir retención (por ejemplo, 6-12 meses para `pulls`, 30 días para `sessions`, 24 meses para jugadores inactivos) y programar limpieza por lotes con `chunkById()`. Publicar la política en el aviso de privacidad.
- **Ejemplo de corrección:**
  ```php
  // after: app/Console/Commands/PruneGachaData.php (excerpt)
  $cutoff = now()->subMonths(12);

  Pull::query()
      ->where('created_at', '<', $cutoff)
      ->chunkById(1000, fn ($pulls) => $pulls->each->delete());

  Player::query()
      ->where('last_activity_hint', '<', $cutoff) // or derived from latest pull
      ->chunkById(500, fn ($players) => $players->each->delete());
  ```
  ```php
  // after: routes/console.php or scheduler
  Schedule::command('gacha:prune')->dailyAt('03:00');
  ```
- **Esfuerzo estimado:** M
- **Estado de verificación:** Verificado en repo; volumen local medido (`players=4`, `characters=84`, `collection_entries=5`, `pulls=5`, `sessions=4`), tamaño y tasa de crecimiento **NO VERIFICADOS**.

<a id="db-02"></a>
### [DB-02] Falta índice para el orden por `collection_entries.updated_at`

- **Severidad:** Baja
- **Capa:** Base de datos / Rendimiento
- **Referencia:** CWE-1049 (Excessive Data Query)
- **Ubicación:** `database/migrations/2026_10_23_000001_create_gachapon_tables.php:37-45`, `app/Http/Controllers/GachaController.php:53`
- **Descripción:** la home ordena las entradas de colección por `updated_at` (`latest('updated_at')`), pero la tabla solo tiene índice en la llave primaria y en `(player_id, character_id)`. MySQL debe ordenar en memoria las filas del jugador.
- **Evidencia:**
  ```php
  // GachaController.php:53
  $entries = $player->collection()->latest('updated_at')->get()->keyBy('character_id');
  ```
  ```php
  // create_gachapon_tables.php:44
  $table->unique(['player_id', 'character_id']);
  ```
- **Impacto / Por qué importa:** con 84 entradas por jugador el sort es trivial. Se registra porque el patrón se replica si la colección crece; agregar el índice es barato y evita un `filesort`.
- **Escenario de explotación o fallo:** un jugador con miles de copias acumuladas provoca un `filesort` en cada carga de la home.
- **Recomendación:** crear una **migración nueva** (nunca editar la aplicada) con `INDEX(player_id, updated_at)` y verificar con `EXPLAIN` en MySQL real.
- **Ejemplo de corrección:**
  ```php
  // after: database/migrations/YYYY_MM_DD_XXXXXX_add_index_to_collection_entries.php
  Schema::table('collection_entries', function (Blueprint $table): void {
      $table->index(['player_id', 'updated_at']);
  });
  ```
- **Esfuerzo estimado:** S
- **Estado de verificación:** Verificado en código y en MySQL 8.0.35: `SHOW INDEX` confirma la ausencia del índice y `EXPLAIN ... ORDER BY updated_at DESC` devuelve `Using filesort`.

<a id="db-03"></a>
### [DB-03] Borrado en cascada elimina el historial de auditoría del jugador

- **Severidad:** Baja
- **Capa:** Base de datos / Integridad
- **Referencia:** CWE-732 (Incorrect Permission Assignment / destructive cascade), GDPR Art. 5(1)(e)
- **Ubicación:** `database/migrations/2026_10_23_000001_create_gachapon_tables.php:39-40,49-50`
- **Descripción:** `pulls.player_id` y `collection_entries.player_id` usan `cascadeOnDelete()`. Al eliminar un `Player` (por purga, prueba o soporte) se eliminan también todas sus tiradas, sin rastro de rarezas ni reembolsos.
- **Evidencia:**
  ```php
  // create_gachapon_tables.php:49-50
  $table->foreignId('player_id')->constrained()->cascadeOnDelete();
  $table->foreignId('character_id')->constrained()->cascadeOnDelete();
  ```
- **Impacto / Por qué importa:** se pierde la capacidad de auditar probabilidades o reclamaciones («mi tirada fue mal contada») después de una baja. Choca con la utilidad declarada del historial en el docblock de `Pull.php:9-13`.
- **Escenario de explotación o fallo:** se aplica la política de retención de DB-01 y se borra un jugador inactivo; con él desaparecen sus 500 tiradas, sin posibilidad de reconstruir el azar.
- **Recomendación:** si se quiere conservar auditoría, hacer `player_id` nullable con `nullOnDelete()` y anonimizar en vez de borrar. Si se prioriza el derecho de supresión, documentar explícitamente que el historial se elimina junto con la identidad.
- **Ejemplo de corrección:**
  ```php
  // before
  $table->foreignId('player_id')->constrained()->cascadeOnDelete();

  // after (new migration, keep audit rows anonymous)
  $table->foreignId('player_id')->nullable()->constrained()->nullOnDelete();
  ```
- **Esfuerzo estimado:** M
- **Estado de verificación:** Verificado en esquema; decisión legal **NO VERIFICADA**.

<a id="db-04"></a>
### [DB-04] Migración de reescalado sin transacción ni guarda de idempotencia

- **Severidad:** Baja
- **Capa:** Base de datos / Migraciones
- **Referencia:** CWE-362 (Race Condition) / CWE-460 (Improper Cleanup on Thrown Exception)
- **Ubicación:** `database/migrations/2026_10_26_000000_switch_currency_to_sueldo_minimo.php:15-24`
- **Descripción:** la migración cambia el default y multiplica todos los saldos con `DB::raw()` en dos sentencias separadas, sin transacción. Si la segunda falla, queda un esquema con default nuevo y saldos antiguos. Tampoco hay una marca que impida reaplicar el reescalado manualmente.
- **Evidencia:**
  ```php
  // switch_currency_to_sueldo_minimo.php:15-24
  Schema::table('players', function (Blueprint $table) {
      $table->unsignedBigInteger('coins')->default(9132000)->change();
  });

  DB::table('players')->update([
      'coins' => DB::raw('coins * 30440'),
  ]);
  ```
- **Impacto / Por qué importa:** el problema ya no se puede reproducir en una BD donde la migración se aplicó, pero el patrón se puede repetir en futuras migraciones económicas: un fallo a mitad deja economía inconsistente sin rollback.
- **Escenario de explotación o fallo:** en una instalación nueva, la sentencia `UPDATE` falla por timeout; la migración queda a medias y los jugadores arrancan con saldo de la escala vieja.
- **Recomendación:** envolver futuras migraciones de datos en `DB::transaction()` y registrar una marca de idempotencia en una tabla de control. No editar esta migración ya aplicada (regla de `AGENTS.md:12`).
- **Ejemplo de corrección:**
  ```php
  // after: pattern for future data migrations
  DB::transaction(function (): void {
      Schema::table('players', fn (Blueprint $table) => $table->unsignedBigInteger('coins')->default(9132000)->change());

      DB::table('players')->update(['coins' => DB::raw('coins * 30440')]);

      DB::table('migration_flags')->insertOrIgnore(['key' => 'currency_rescaled_2026_10_26']);
  });
  ```
- **Esfuerzo estimado:** S
- **Estado de verificación:** Verificado en repo; historial de ejecución real **NO VERIFICADO**.

<a id="db-05"></a>
### [DB-05] Charset y collation se heredan de la conexión, no del esquema

- **Severidad:** Informativa
- **Capa:** Base de datos
- **Referencia:** CWE-16 (Configuration)
- **Ubicación:** `config/database.php:56-57`, `database/migrations/2026_10_23_000001_create_gachapon_tables.php:11-56`
- **Descripción:** las migraciones no declaran `charset`/`collation`; dependen de `DB_CHARSET` y `DB_COLLATION` y de cómo se haya creado la base. El README pide `utf8mb4` / `utf8mb4_unicode_ci`, pero no hay validación automática.
- **Evidencia:**
  ```php
  // config/database.php:56-57
  'charset' => env('DB_CHARSET', 'utf8mb4'),
  'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
  ```
  ```php
  // create_gachapon_tables.php:11-12
  Schema::create('characters', function (Blueprint $table) {
      $table->id();
  ```
- **Impacto / Por qué importa:** una base creada con `latin1` puede almacenar los nombres con tilde y ñ del plantel de forma incorrecta o perder comparaciones. Hoy los defaults son correctos, pero no están blindados.
- **Escenario de explotación o fallo:** se apunta `.env` a una BD preexistente con otro charset; las búsquedas por `slug` con acentos dejan de coincidir.
- **Recomendación:** documentar y verificar la creación de la BD con `utf8mb4_unicode_ci`, o declarar charset/collation en cada tabla nueva. Agregar un check de deploy que consulte `SHOW VARIABLES LIKE 'character_set_database'`.
- **Ejemplo de corrección:**
  ```sql
  -- before (undocumented default)
  CREATE DATABASE gacha_paraguay;

  -- after
  CREATE DATABASE gacha_paraguay
      CHARACTER SET utf8mb4
      COLLATE utf8mb4_unicode_ci;
  ```
- **Esfuerzo estimado:** S
- **Estado de verificación:** Verificado en vivo: `gacha_paraguay` usa `utf8mb4` / `utf8mb4_unicode_ci`, mientras el servidor reporta `collation_database=utf8mb4_0900_ai_ci`. La BD de producción sigue **NO VERIFICADA**.

---

## Crecimiento y retención

Estimación cualitativa bajo la carga asumida (baja/media):

| Tabla | Crecimiento por acción | Riesgo a 12 meses | Acción recomendada |
|---|---|---|---|
| `pulls` | 1 fila por tirada (10 por tirada múltiple) | Alto si hay bot o mucha actividad | Purga mensual con retención definida |
| `players` | 1 fila por visitante sin cookie | Medio | Retención de inactivos + COMP-01 |
| `collection_entries` | Máximo 84 por jugador | Medio | Purga junto con `players` |
| `sessions` | 1 fila por sesión | Medio | Limpieza de expiradas (Laravel lo hace al escribir, no programáticamente) |
| `cache`, `cache_locks` | Según uso | Bajo | Revisar expiraciones |
| `jobs`, `failed_jobs` | Sin jobs propios hoy | Informativo | Solo si se agrega cola |

---

## Verificación en vivo

Servidor MySQL **8.0.35** conectado en `127.0.0.1:3306`.

```text
$ SELECT SCHEMA_NAME, DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
  FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='gacha_paraguay';
gacha_paraguay  utf8mb4  utf8mb4_unicode_ci

$ SELECT VERSION();
8.0.35

$ SELECT COUNT(*) FROM ...
players=4  characters=84  collection_entries=5  pulls=5  sessions=4  users=0
```

| Comprobación | Resultado | Hallazgo |
|---|---|---|
| `SHOW GRANTS FOR 'root'@'localhost'` | `ALL PRIVILEGES ON *.* ... WITH GRANT OPTION` | CFG-02 confirmado |
| `SHOW INDEX FROM collection_entries` | Unique `(player_id, character_id)`, FK `character_id`; sin índice en `updated_at` | DB-02 |
| `EXPLAIN SELECT ... ORDER BY updated_at DESC LIMIT 6` | `type=ref`, `key=collection_entries_player_id_character_id_unique`, `Extra=Using filesort` | DB-02 confirmado |
| `migrations` en `gacha_paraguay` | 6 filas, batches 1 y 2 | DB-04 |
| Migración en `gacha_paraguay_test` | 6 migraciones aplicadas desde cero | QA-01 |
| `SHOW VARIABLES` | `log_bin=ON`, `slow_query_log=ON`, `long_query_time=10`, `max_connections=151`, `local_infile=OFF`, `sql_mode` con `STRICT_TRANS_TABLES` | CFG-06 / buenas prácticas |
| `information_schema.EVENTS` para `gacha_paraguay` | Sin eventos de backup | OPS-02 |

## Límites de verificación

- Los datos y grants corresponden al **MySQL local del workspace**, no a producción.
- No se midió latencia, ni se ejecutó `EXPLAIN ANALYZE` con volumen real.
- No se verificó TLS entre la app y MySQL.
- No se encontraron backups ni tareas programadas; su existencia real en otro servidor es **NO VERIFICADA**.

---

## Buenas prácticas

- Llaves únicas bien elegidas: `players.token`, `characters.slug`, `collection_entries(player_id, character_id)`.
- Foreign keys presentes con `strict => true`.
- Índice compuesto en `pulls(player_id, created_at)` para el historial por jugador.
- `sessions` creada explícitamente porque `SESSION_DRIVER=database` la exige (`0001_01_01_000003_create_sessions_table.php:7-14`).
- `Pull::UPDATED_AT = null` evita columnas engañosas en un evento inmutable (`Pull.php:17-18`).
- Migración de cambio de moneda creada como archivo nuevo, sin reescribir la original (cumple `AGENTS.md:12`).
