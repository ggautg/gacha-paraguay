# Gacha Guaraní 🇵🇾

Un gachapón web de figuras históricas del Paraguay. Cada tirada revela un retrato y una biografía reales, tomados de la **Wikipedia en español**, con una rareza de 1 a 3 estrellas.

Construido con **Laravel 13 + Inertia 3 + Vue 3 + Tailwind CSS 4**.

---

## ¿Cómo funciona?

- **63 figuras en el catálogo**: 14 legendarias (3★), 23 épicas (2★) y 26 comunes (1★).
- **Sin registro**: el jugador se identifica con una cookie `gacha_player` (UUID, 2 años) y se crea automáticamente en la primera visita.
- **Sueldos mínimos (SM)**, la moneda del juego:
  - **1 SM = ₲ 3.044.000** (sueldo mínimo legal vigente desde el 1 de julio de 2026, Decreto 6225/2026).
  - Saldo inicial: **3 SM** (₲ 9.132.000).
  - Tirada simple: **1 SM**.
  - Tirada múltiple (×10): **10 SM**.
  - Bonificación: **+1 SM** cada **8 horas**.
  - Duplicados devuelven un porcentaje de la tirada: **20 % / 60 % / 100 %** según rareza (común, épico, legendario).
- **Probabilidades por tirada**: común **65 %**, épico **27 %**, legendario **8 %**.
- **Pity**: si pasan **12 tiradas** sin un legendario, la siguiente lo garantiza.
- **Álbum**: la colección muestra las 63 figuras; las no descubiertas aparecen como siluetas. Los duplicados aumentan la cantidad y se pueden filtrar por rareza.
- **Tirar de vuelta**: el revelado incluye un botón para repetir la misma tirada (×1 o ×10) sin volver a la máquina; se deshabilita si no alcanza el saldo.

## Temas

La interfaz incluye un selector flotante (esquina inferior derecha) con nueve paletas:

| # | Tema | Inspiración |
| --- | --- | --- |
| 1 | **Default** | Noche guaraní, oro y azul |
| 2 | **Claro** | Papel, tinta y sol de mediodía |
| 3 | **Tereré** | Yerba fresca, guampa y monte |
| 4 | **Larissa Riquelme** | Rosa pasarela y oro |
| 5 | **Baila Conmigo** | Neón violeta de estudio |
| 6 | **Fútbol** | Albirroja de domingo |
| 7 | **Asado** | Brasa, carbón y humo |
| 8 | **Mandioca** | Tierra, raíz y almidón |
| 9 | **Emo 2007** | Negro, flequillo y rosa chicle |

Cada paleta redefine los tokens de color (`night`, `bone`, `gold`), el degradado del fondo, la selección de texto y el color de `theme-color`. El tema elegido se guarda en `localStorage` con la clave `gacha-theme` y se aplica antes del primer render con un script inline en `resources/views/app.blade.php` (sin parpadeo). Los metadatos de las paletas y la persistencia viven en `resources/js/lib/themes.js`.

## Stack

| Capa | Tecnología |
| --- | --- |
| Backend | Laravel 13.38 (PHP 8.3+) |
| SPA | Inertia.js 3 + Vue 3.5 |
| Estilos | Tailwind CSS 4 + CSS propio (9 paletas) |
| Base de datos | MySQL 8 (utf8mb4) |
| Fuente de datos | API de Wikipedia en español (MediaWiki) |
| Build | Vite 7 |

## Requisitos

- PHP 8.3 o superior con `pdo_mysql`, `mbstring` y `openssl`.
- MySQL 8 (o MariaDB 10.6+) en ejecución.
- Composer 2.
- Node.js 20+ y npm.

## Instalación

```bash
git clone <tu-repo> gacha-paraguay
cd gacha-paraguay

composer install
cp .env.example .env
php artisan key:generate

# Base de datos MySQL
mysql -u root -p -e "CREATE DATABASE gacha_paraguay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
# Configurá DB_DATABASE, DB_USERNAME y DB_PASSWORD en .env
php artisan migrate

# Enlace público para los retratos
php artisan storage:link

# Importar personajes desde Wikipedia (descarga retratos y biografías)
php artisan gacha:import

# Frontend
npm install
npm run build
```

Levantar el servidor:

```bash
php artisan serve
# http://127.0.0.1:8000
```

Para desarrollo con recarga en caliente: `npm run dev` en otra terminal (o `composer run dev`).

## Importación desde Wikipedia

```bash
php artisan gacha:import            # importa/actualiza todo el plantel
php artisan gacha:import --missing  # reintenta solo los retratos que faltan
php artisan gacha:import --no-images
php artisan gacha:import --only=menchi-barriocanal,carlos-martini  # solo esos slugs
php artisan gacha:import --fresh    # borra catálogo y progreso, y reinicia jugadores
```

Los personajes sin artículo en Wikipedia (por ejemplo, figuras de la farándula o de TikTok) se definen en el mismo plantel con `'wiki' => null` y sus datos curados (`extract`, `link`, `role`, `era`, `category`, `rarity`). El comando los inserta sin consultar la API y conserva el retrato si ya existía.

El comando:

1. Consulta la API de MediaWiki (`action=query`, `prop=pageimages|extracts`) en lotes de 20 títulos.
2. Descarga el retrato a `storage/app/public/characters/<slug>.<ext>` (nunca se enlaza en caliente).
3. Limpia y recorta el extracto biográfico a ~560 caracteres.
4. Guarda nombre, título wiki, URL, rol, época, categoría y rareza.

> Wikimedia puede responder **HTTP 429** si se hacen muchas descargas seguidas. El comando reintenta con espera progresiva; si un retrato no se puede bajar, la figura conserva el retrato anterior o usa una silueta con iniciales. Volvé a ejecutar `--missing` más tarde. Algunas biografías (por ejemplo Elvio Romero o Renée Ferrer) no tienen imagen en Wikipedia, así que mantienen la silueta de forma permanente.

El plantel, la economía y las probabilidades se editan en [`config/gachapon.php`](config/gachapon.php).

## Estructura relevante

```
app/
  Console/Commands/ImportCharacters.php   # importador de Wikipedia
  Http/Controllers/GachaController.php    # home, tiradas, bonificación
  Http/Controllers/AlbumController.php    # álbum de colección
  Http/Middleware/EnsurePlayer.php        # identidad por cookie
  Models/{Character,Player,CollectionEntry,Pull}.php
  Services/GachaService.php               # rareza, pity, duplicados, transacción
  Support/GachaPresenter.php              # forma de los datos para Vue
config/gachapon.php                       # plantel + economía + tasas
resources/
  css/app.css                             # tokens, 9 paletas, máquina y animaciones
  js/pages/Home.vue                       # máquina, probabilidades, vitrina
  js/pages/Album.vue                      # grilla, filtros, buscador, modal
  js/Components/PullReveal.vue            # overlay de revelado
  js/Components/ThemePicker.vue           # selector flotante de paletas
  js/lib/themes.js                        # metadatos y persistencia del tema
database/migrations/2026_10_23_000001_create_gachapon_tables.php
tests/Feature/GachaTest.php               # 10 pruebas del flujo completo
```

## Pruebas

```bash
php artisan test
```

Cubren render de la home y el álbum, cobro de sueldos, precio múltiple, devolución por duplicado, pity, saldo insuficiente y bonificación diaria única.

## Créditos y licencia

- Retratos y extractos biográficos: **Wikipedia en español / Wikimedia Commons**, contenido bajo licencia [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/). Cada ficha enlaza a su artículo original.
- Código del proyecto: **MIT**.
- Hecho en Paraguay. 🇵🇾
