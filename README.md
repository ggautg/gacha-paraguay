# Gacha Guaraní 🇵🇾

Un gachapón web de figuras históricas del Paraguay. Cada tirada revela un retrato y una biografía reales, tomados de la **Wikipedia en español**, con una rareza de 1 a 3 estrellas.

Construido con **Laravel 13 + Inertia 3 + Vue 3 + Tailwind CSS 4**.

---

## ¿Cómo funciona?

- **51 figuras curadas** del plantel: 11 legendarias (3★), 22 épicas (2★) y 18 comunes (1★).
- **Sin registro**: el jugador se identifica con una cookie `gacha_player` (UUID, 2 años) y se crea automáticamente en la primera visita.
- **Monedas guaraníes (₲)**:
  - Saldo inicial: **₲ 1.500**.
  - Tirada simple: **₲ 100**.
  - Tirada múltiple (×10): **₲ 900**.
  - Bonificación: **+₲ 500** cada **8 horas**.
  - Duplicados devuelven parte de la tirada: **₲ 20 / 60 / 250** según rareza.
- **Probabilidades por tirada**: común **65 %**, épico **27 %**, legendario **8 %**.
- **Pity**: si pasan **12 tiradas** sin un legendario, la siguiente lo garantiza.
- **Álbum**: la colección muestra las 51 figuras; las no descubiertas aparecen como siluetas. Los duplicados aumentan la cantidad y se pueden filtrar por rareza.

## Stack

| Capa | Tecnología |
| --- | --- |
| Backend | Laravel 13.38 (PHP 8.3+) |
| SPA | Inertia.js 3 + Vue 3.5 |
| Estilos | Tailwind CSS 4 + CSS propio (tema museo oscuro/oro) |
| Base de datos | SQLite (por defecto) |
| Fuente de datos | API de Wikipedia en español (MediaWiki) |
| Build | Vite 7 |

## Requisitos

- PHP 8.3 o superior con `pdo_sqlite`, `mbstring` y `openssl`.
- Composer 2.
- Node.js 20+ y npm.

## Instalación

```bash
git clone <tu-repo> gacha-paraguay
cd gacha-paraguay

composer install
cp .env.example .env
php artisan key:generate

# Base de datos SQLite
touch database/database.sqlite      # Windows: New-Item database/database.sqlite
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
php artisan gacha:import --fresh    # borra catálogo y progreso, y reinicia jugadores
```

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
  css/app.css                             # tema, máquina de cápsulas, animaciones
  js/pages/Home.vue                       # máquina, probabilidades, vitrina
  js/pages/Album.vue                      # grilla, filtros, buscador, modal
  js/Components/PullReveal.vue            # overlay de revelado
database/migrations/2026_10_23_000001_create_gachapon_tables.php
tests/Feature/GachaTest.php               # 8 pruebas del flujo completo
```

## Pruebas

```bash
php artisan test
```

Cubren render de la home y el álbum, cobro de monedas, precio múltiple, devolución por duplicado, pity, saldo insuficiente y bonificación diaria única.

## Créditos y licencia

- Retratos y extractos biográficos: **Wikipedia en español / Wikimedia Commons**, contenido bajo licencia [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/). Cada ficha enlaza a su artículo original.
- Código del proyecto: **MIT**.
- Hecho en Paraguay. 🇵🇾
