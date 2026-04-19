# Vouch — Backend

API REST del proyecto Vouch, una plataforma social de críticas ponderadas para videojuegos.

---

## Stack

| Tecnología | Versión | Rol |
|---|---|---|
| PHP | 8.5.5 | Lenguaje |
| Laravel | 10.10 | Framework |
| MySQL | — | Base de datos (vía Laragon) |
| Laravel Sanctum | 3.3 | Autenticación por tokens |
| GuzzleHTTP | 7.x | Cliente HTTP |
| spatie/laravel-translatable | 6.x | Nombres traducibles en base de datos (JSON) |
| PHPUnit | 10.1 | Testing |

---

## Arquitectura general

```
app/
├── Console/Commands/
│   ├── IgdbImportTopCommand.php
│   ├── RecalculateScoresCommand.php
│   └── ResetContentCommand.php
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── IgdbController.php
│   │   └── Admin/
│   │       ├── GenreController.php
│   │       ├── CategoryController.php
│   │       ├── PlatformController.php
│   │       ├── ProductController.php
│   │       ├── ReviewController.php
│   │       └── UserController.php
│   └── Middleware/
│       ├── AdminMiddleware.php
│       └── CheckBanned.php
├── Models/
│   ├── User.php
│   ├── Genre.php
│   ├── Category.php
│   ├── Platform.php
│   ├── Product.php
│   ├── GameDetail.php
│   ├── Review.php
│   ├── ReviewScore.php
│   └── ProductScore.php
└── Services/
    ├── ScoringService.php
    ├── IgdbService.php
    └── ProductImportService.php
routes/
└── api.php
database/
├── migrations/
└── seeders/
```

---

## Configuración local

### Requisitos
- Laragon con PHP 8.5.5 y MySQL activos
- Composer 2.5.7

### Variables de entorno (`.env`)
```env
APP_URL=http://proyectovouchbe.local

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=proyectovouch
DB_USERNAME=root
DB_PASSWORD=

GOOGLE_CLIENT_ID=tu_google_client_id

TWITCH_CLIENT_ID=tu_twitch_client_id
TWITCH_CLIENT_SECRET=tu_twitch_client_secret
```

### Instalación
```bash
composer install
php artisan key:generate
php artisan migrate --seed
```

---

## Características implementadas

### Auth — Google OAuth + Sanctum

**Librería:** Laravel HTTP Client (nativa), Laravel Sanctum 3.3

**Flujo:**
```
Frontend obtiene credential de Google (JWT)
  → POST /api/auth/google { credential }
    → Backend verifica el token contra Google tokeninfo API
      → Crea o actualiza el usuario en base de datos
        → Emite token Sanctum
          → Devuelve { token, user }
```

**Decisión de arquitectura:** Se eligió el flujo frontend-iniciado (Google Identity Services) en lugar del flujo de redirección tradicional porque no requiere redirect URIs configurados, el token nunca viaja en la URL, y es el estándar actual de Google.

**Verificación del token:** Se llama a `https://oauth2.googleapis.com/tokeninfo?id_token={credential}` desde el backend para verificar la autenticidad del token y validar que el `aud` coincide con `GOOGLE_CLIENT_ID`. Sin librerías externas adicionales.

**Gestión de usuarios:** Si el usuario ya existe por email o google_id, se actualiza. Si no, se crea. Al hacer login se revocan todos los tokens anteriores y se emite uno nuevo.

**Endpoints:**
```
POST /api/auth/google       Body: { credential }  → { token, user }
GET  /api/user              Header: Authorization: Bearer {token}
```

---

### Middleware de seguridad

**`AdminMiddleware`** (`app/Http/Middleware/AdminMiddleware.php`)
Verifica que el usuario autenticado tenga `role === 'admin'`. Devuelve 403 si no.
Registrado como `admin` en `bootstrap/app.php`.

**`CheckBanned`** (`app/Http/Middleware/CheckBanned.php`)
Verifica que el usuario no tenga `banned_at` seteado. Devuelve 403 con mensaje de suspensión. Aplicado a todas las rutas protegidas con alias `not.banned`.

---

### Motor de puntuación (`ScoringService`)

El núcleo diferenciador de la plataforma. Calcula puntuaciones ponderadas por categoría según los géneros del producto.

**Fórmula:**
```
weighted_score = floor( Σ(score × weight) / Σ(weights) × 10 ) / 10
```

Los scores de categoría son enteros 0–10. Los pesos se definen en `Genre_x_Category.weight` (decimal 0.00–1.00). El resultado final es un decimal `0.0–10.0` truncado a 1 decimal (nunca se redondea).

**Algoritmo multi-género (MAX weight):**

Un producto puede tener múltiples géneros. Para construir el mapa de pesos de una reseña:
1. Se recorren todos los géneros del producto y sus categorías asignadas.
2. Para cada categoría se conserva el **peso máximo** encontrado entre todos los géneros.
3. El mapa resultante se ordena descendentemente por peso y se recortan las **top 15 categorías**.

Esto evita que géneros secundarios diluyan el peso de un criterio ya cubierto por el género principal.

**Escala de letras:**

| Score | Letra | Score | Letra |
|---|---|---|---|
| 10.0 | **S** | 7.1–7.9 | C+ |
| 9.1–9.9 | A+ | 7.0 | C |
| 9.0 | A | 6.1–6.9 | D+ |
| 8.1–8.9 | B+ | 6.0 | D |
| 8.0 | B | 5.1–5.9 | E+ |
| — | — | 5.0 | E |
| — | — | 0.0–4.9 | F |

La escala usa **truncado**, no redondeo: 9.9 → A+ (no S), 8.9 → B+ (no A).

**Fix de precisión flotante:** Antes del truncado se aplica `round($raw, 10)` para eliminar ruido de coma flotante. Sin esto, un promedio matemáticamente exacto de 10.0 podía resultar en `9.999999...` y degradar a A+.

**Triple nota por producto:**

| Score | Quién contribuye | Dónde se guarda |
|---|---|---|
| `global_score` | Solo usuarios con role `user` | `ProductScores` |
| `pro_score` | Solo usuarios con role `critic` | `ProductScores` |
| `trust_score` | Usuarios a los que sigues | Calculado en tiempo real |

El Trust Score no se cachea porque es personal para cada usuario. Global y Pro se recalculan y cachean en `ProductScores` al publicar cada crítica y al banear/desbanear reseñas. Las reseñas baneadas se excluyen del cálculo.

---

### Integración IGDB (`IgdbService` + `ProductImportService`)

**Autenticación:** Twitch OAuth con Client Credentials. El token se cachea durante 50 días para evitar requests repetidos.

**`IgdbService`** — métodos principales:
- `search(string $query)` — busca juegos por nombre
- `find(int $igdbId)` — obtiene un juego por ID
- `topByGenre(int $igdbGenreId, int $limit)` — top juegos de un género
- `coverUrl(array $cover, string $size)` — construye URL de portada (formato `t_cover_big`)

**`ProductImportService`** — método `importGame(array $igdbGame)`:
- Crea o actualiza `Product` + `GameDetail`
- Resuelve compañías (developer/publisher) desde el campo `involved_companies`
- Asigna plataformas: crea `Platform` si no existe, determina tipo (console/pc/streaming) por nombre, guarda `release_year` en la pivot `Product_x_Platform`
- **Sincroniza géneros** desde `igdbGame['genres']` cruzando `igdb_genre_id` con los géneros existentes en BD
- **Detecta URL de Steam** automáticamente desde `external_games` (category === 1 = Steam); construye `https://store.steampowered.com/app/{uid}/` y la guarda en `purchase_url` del pivot PC
- Genera slug único con sufijo numérico si hay colisión

**Queries IGDB incluyen:** `external_games.uid`, `external_games.category` para detectar la URL de Steam durante el import.

**Endpoints admin:**
```
GET  /api/admin/igdb/search?q={query}   → IgdbGame[]
POST /api/admin/igdb/import             Body: { igdb_id: number } → Product
```

**Comando artisan:**
```bash
php artisan igdb:import-top --limit=10
```
Itera todos los géneros que tengan `igdb_genre_id` seteado e importa los N juegos más populares de cada uno.

---

### Panel de administración (Admin CRUD)

Todos los endpoints están bajo `/api/admin` y requieren autenticación Sanctum + role `admin` + no estar baneado.

#### Géneros (`/api/admin/genres`)
- CRUD completo (index, store, update, destroy)
- `PUT /api/admin/genres/{id}/categories` — sincroniza las categorías asignadas al género con sus pesos. Recibe array `[{ id, weight }]` y reemplaza todas las asignaciones existentes.
- Cada género puede tener múltiples categorías con pesos independientes.
- El campo `name` es JSON multilingüe (ver *Nombres traducibles*). El slug se deriva automáticamente del nombre en inglés (`name.en`).
- Búsqueda por `?search=` aplica `JSON_EXTRACT` sobre los 5 idiomas. Ordenar por `name` usa `JSON_UNQUOTE(JSON_EXTRACT(name, '$.en'))`.

#### Categorías (`/api/admin/categories`)
- CRUD completo. Las categorías son los criterios de evaluación (Gameplay, Historia, Gráficos, etc.).
- El campo `name` es JSON multilingüe (ver *Nombres traducibles*). El slug se deriva de `name.en`.

#### Plataformas (`/api/admin/platforms`)
- CRUD completo. Tipo: `console | pc | streaming`.

#### Productos (`/api/admin/products`)
- Index paginado con carga de `genres`, `gameDetails`, `platforms` y `score`
- Filtros: `?search=`, `?type=`, `?genre_id=`. Orden por `id`, `title`, `type`
- Creación y edición: `genre_ids[]` (array, many-to-many), campos de `GameDetails` embebidos
- Slug auto-generado desde el título; sufijo numérico si hay colisión
- Cover image: URL externa

**`PUT /api/admin/products/{id}/purchase-links`**

Actualiza el campo `purchase_url` en la pivot `Product_x_Platform` para cada plataforma asociada al producto. Body:
```json
{
  "platforms": [
    { "platform_id": 1, "purchase_url": "https://store.steampowered.com/app/292030/" },
    { "platform_id": 3, "purchase_url": null }
  ]
}
```
Solo actualiza plataformas ya vinculadas al producto (`updateExistingPivot`). No crea ni elimina asociaciones.

#### Reseñas (`/api/admin/reviews`)
- Index paginado con filtro `?banned=1`
- `POST /api/admin/reviews/{id}/ban` — banea una reseña, recalcula scores
- `DELETE /api/admin/reviews/{id}/ban` — desbanea, recalcula scores

#### Usuarios (`/api/admin/users`)
- Index paginado con filtros `?banned=1&role={role}`
- `GET /api/admin/users/{id}` — detalle con reseñas
- `POST /api/admin/users/{id}/ban` — banea usuario + revoca todos sus tokens
- `DELETE /api/admin/users/{id}/ban` — desbanea usuario
- `PATCH /api/admin/users/{id}/role` — cambia role (`user | critic | admin`)

---

## Base de datos

### Convención de nombres
- Tablas regulares: PascalCase plural (`Genres`, `Products`)
- Tablas cruzadas: `A_x_B` (`Genre_x_Category`, `Product_x_Platform`)
- Excepción: `Follows` (auto-referencial Users–Users)
- Campos: snake_case minúscula

### Esquema completo

```
Users
  id, name, email, password (nullable), google_id (hidden)
  avatar, role (user|critic|admin), badges (JSON)
  banned_at (timestamp nullable), ban_reason (string nullable)
  timestamps

Genres                      Categories
  id, name (JSON), slug       id, name (JSON), slug, timestamps
  igdb_genre_id (nullable)
       └──── Genre_x_Category ────┘
               genre_id, category_id
               weight (decimal 0.00–1.00), timestamps

Platforms
  id, name, slug, type (console|pc|streaming), timestamps

Products
  id, type (game|movie|series)
  title, slug, description, cover_image, timestamps
       │
       ├── Product_x_Genre (cruzada)
       │     product_id, genre_id, timestamps
       │
       ├── Product_x_Platform (cruzada)
       │     product_id, platform_id
       │     release_year, purchase_url
       │
       ├── GameDetails
       │     product_id (PK), igdb_id, developer, publisher
       │
       ├── ProductScores
       │     product_id (PK), global_score (int), pro_score (int)
       │     updated_at
       │
       └── Reviews ───────────────────── Users
             id, user_id, product_id
             body (varchar 2200, nullable)
             weighted_score (int 0–100), letter_grade
             banned_at (timestamp nullable), ban_reason (string nullable)
             timestamps · unique(user_id, product_id)
               │
               └── Review_x_Category (cruzada)
                     review_id, category_id
                     score (tinyInt 0–10)

Follows (cruzada Users–Users)
  follower_id → users.id
  followed_id → users.id
  created_at
```

### Nombres traducibles (`spatie/laravel-translatable`)

Los campos `name` de `Genres` y `Categories` son columnas JSON. Cada valor almacena un objeto con los 5 idiomas soportados:

```json
{ "en": "Gameplay", "es": "Jugabilidad", "fr": "Jouabilité", "pt": "Jogabilidade", "it": "Giocabilità" }
```

Los modelos `Genre` y `Category` usan el trait `HasTranslations` con `$translatable = ['name']`. El método `toArray()` está sobreescrito para devolver siempre el objeto completo con los 5 idiomas en las respuestas JSON de la API.

Los slugs se derivan siempre del nombre en inglés: `Str::slug($data['name']['en'])`. Esto garantiza consistencia independientemente del idioma de la interfaz.

---

### Seeders

```bash
php artisan db:seed
```

Siembra los 22 géneros de IGDB con su `igdb_genre_id`, 6 categorías de evaluación y las asignaciones con pesos por género. Todos los nombres incluyen los 5 idiomas (en, es, fr, pt, it).

**Géneros disponibles (22):**

| Slug | IGDB ID | Slug | IGDB ID |
|---|---|---|---|
| `point-and-click` | 2 | `turn-based-strategy` | 11 |
| `fighting` | 4 | `tactical` | 12 |
| `shooter` | 5 | `hack-and-slash` | 25 |
| `music` | 7 | `quiz-trivia` | 26 |
| `platform` | 8 | `pinball` | 30 |
| `puzzle` | 9 | `adventure` | 31 |
| `racing` | 10 | `indie` | 32 |
| `rts` | 11 | `arcade` | 33 |
| `rpg` | 12 | `visual-novel` | 34 |
| `simulator` | 13 | `card-board` | 35 |
| `sport` | 14 | `strategy` | 15 |

**Categorías disponibles:** `gameplay`, `story`, `graphics`, `sound`, `duration`, `feel`.

Los pesos por categoría están definidos en `GenreCategorySeeder` para los 22 géneros. Ejemplo de pesos:

| Género | Distribución |
|---|---|
| `rpg` | Story 0.30, Gameplay 0.25, Graphics 0.15, Sound 0.15, Duration 0.15 |
| `shooter` | Gameplay 0.40, Graphics 0.20, Feel 0.20, Sound 0.10, Story 0.10 |
| `sport` | Gameplay 0.40, Duration 0.25, Graphics 0.20, Sound 0.10, Story 0.05 |

---

## Comandos Artisan personalizados

### `igdb:import-top`
Importa los juegos más valorados de IGDB para cada género que tenga `igdb_genre_id` configurado. Los géneros del juego se asignan automáticamente desde los metadatos de IGDB.

```bash
php artisan igdb:import-top --limit=10
```

| Opción | Default | Descripción |
|---|---|---|
| `--limit` | `10` | Número de juegos a importar por género |

---

### `scores:recalculate`
Recalcula el `weighted_score` y `letter_grade` de todas las reseñas existentes y actualiza `ProductScores`. Útil tras corregir la fórmula de cálculo o cambiar los pesos de categorías.

```bash
php artisan scores:recalculate
```

Flujo:
1. Carga todas las reseñas con sus scores y la jerarquía `product → genres → categories`
2. Aplica `ScoringService::calculateWeightedScore()` y `calculateLetterGrade()` a cada una
3. Persiste los nuevos valores en `Reviews`
4. Recalcula `global_score` y `pro_score` en `ProductScores` para cada producto afectado

Muestra una barra de progreso durante la ejecución.

---

### `db:reset-content`
Limpia todas las tablas de contenido (productos, géneros, categorías, plataformas, reseñas y sus tablas cruzadas) preservando los usuarios. Tras la limpieza re-ejecuta `CategorySeeder`, `GenreSeeder` y `GenreCategorySeeder`.

```bash
php artisan db:reset-content
```

| Opción | Descripción |
|---|---|
| `--no-seed` | Solo limpia las tablas, omite los seeders |

Flujo habitual para reiniciar datos desde cero:
```bash
php artisan db:reset-content
php artisan igdb:import-top --limit=10
```

---

### `scores:snapshot`
Guarda un snapshot diario de los scores de cada producto en `ProductScoreHistory`. Solo crea un registro nuevo si el score cambió respecto al último snapshot guardado. Productos sin ningún score (sin reseñas) se omiten.

```bash
php artisan scores:snapshot
```

Ejecutado automáticamente cada día a las 03:00 vía el scheduler de Laravel (`Kernel.php`). Para activarlo en producción es necesario tener el cron de Laravel configurado:

```bash
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

**Tabla `ProductScoreHistory`:**

| Campo | Tipo | Descripción |
|---|---|---|
| `product_id` | FK | Producto |
| `global_score` | tinyInt nullable | Score de usuarios |
| `pro_score` | tinyInt nullable | Score de críticos |
| `snapshot_date` | date | Fecha del snapshot (unique por producto) |

---

### Perfil de usuario (`UserProfileController`)

**Endpoints:**
```
GET    /api/user/profile           (auth + not.banned) → datos del perfil del usuario
PATCH  /api/user/profile           (auth + not.banned) → actualiza nombre (máx 25 chars), avatar, bio, social_links, show_email, reviews_public, card_big_bg, card_mid_bg, card_mini_bg
GET    /api/user/profile/card      (auth + not.banned) → datos de la card pública del usuario autenticado
GET    /api/public/card/{user}     (público, sin auth) → datos de la card pública de cualquier usuario por ID
```

`cardData()` devuelve: `id`, `name`, `avatar`, `email` (solo si `show_email`), `badges`, `social_links` (solo entradas compartidas, mapa `red → url`), `reviews_count`, `followers_count`, últimas 5 reseñas de juegos con `product.title`, `product.slug`, `product.cover_image`, `letter_grade`, más `card_big_bg`, `card_mid_bg`, `card_mini_bg`.

**Campos adicionales en `Users`** (migración `2026_04_15_000004`):
- `card_big_bg` (string nullable) — URL de fondo para la Big Card (720×430)
- `card_mid_bg` (string nullable) — URL de fondo para la Mid Card (480×480)
- `card_mini_bg` (string nullable) — URL de fondo para la Mini Card (360×160)

**Decisión:** Los fondos de las cards son URLs externas (no se alojan imágenes). Se validan como `nullable|url|max:500`. El campo `avatar` y los campos `social_links.*.url` también se validan con la regla `url` para prevenir la inyección de valores arbitrarios.

---

### Sistema de seguimiento (`FollowController`)

**Endpoints:**
```
POST   /api/user/follow/{user}    (auth + not.banned) → seguir a un usuario
DELETE /api/user/follow/{user}    (auth + not.banned) → dejar de seguir a un usuario
```

**Decisión de arquitectura:** Se usa `syncWithoutDetaching` para `follow` (idempotente, no falla si ya sigue). Para `unfollow` se usa `detach`. Auto-seguirse devuelve 422.

La tabla `Follows` es auto-referencial (`follower_id`, `followed_id` → `users.id`). Las relaciones en `User` son `following()` y `followers()` via `belongsToMany(User::class, 'Follows', ...)`.

---

### Reseñas del usuario (`UserReviewController`)

**Endpoints:**
```
GET  /api/user/reviews/games   (auth + not.banned) → reseñas propias de juegos, paginadas 24, búsqueda ?search=
```

Devuelve paginación con `data[]` donde cada item incluye `letter_grade`, `weighted_score` y `product` (`title`, `slug`, `cover_image`). Ordenado por `created_at` desc.

---

### Cards públicas compartibles (Frontend)

Tres tamaños de card, pensadas para embeberse o compartirse en redes. El CSS vive **dentro del `<div>` de la card** (via `<style>`) con prefijos de clase únicos para ser portables fuera de la app:

| Card | Tamaño | Prefijo CSS | Ruta standalone |
|---|---|---|---|
| Big Card | 720×430 px | `.vfc-*` | `/card/big/:id` |
| Mid Card | 480×480 px | `.vsc-*` | `/card/mid/:id` |
| Mini Card | 360×160 px | `.vmc-*` | `/card/mini/:id` |

Las rutas standalone (`/card/big/:id`, `/card/mid/:id`, `/card/mini/:id`) son páginas públicas (sin auth) que muestran únicamente la card centrada sobre fondo `#111`. Usan `PublicCardController@show` en el backend.

Desde "Mi perfil público" el usuario puede:
- Editar la URL de fondo de cada card (guardado único con "Guardar fondos")
- Copiar el enlace de cada card con botón "Copiar enlace" (feedback visual 2s)
- Ver el botón "Fiarme" (deshabilitado en el propio perfil con texto "Es tu perfil")

Los "Ver perfil" de Mid y Mini Card apuntan a `/u/:id` (usando ID en lugar de nombre para evitar colisiones y cambios de nick).

---

### Perfil público responsive (`/u/:id`)

**Ruta:** `GET /u/:id` → `PublicProfileComponent`

Página pública sin header ni breadcrumb que muestra el perfil de cualquier usuario en diseño responsive, basado en la estética de Big Card pero adaptado a cualquier tamaño de pantalla.

**Estructura:**
- Fondo `#111` de pantalla completa
- Card centrada con `max-width: 620px`
- Soporte para imagen de fondo (si el usuario la configuró)
- Avatar, nombre, badges, estadísticas (reseñas + seguidores), últimas 3 reseñas con portadas y nota, email (si público), links sociales
- Responsive: en móvil (`≤480px`) el avatar y los paddings se reducen

**Comportamiento:**
- El nombre de usuario en las reseñas del detalle de producto (`/product/:type/:slug`) es un `<a [routerLink]="['/u', r.user.id]">` que navega a esta página
- Las cards Mid y Mini redirigen al perfil del usuario con "Ver perfil completo →" / "Ver perfil →" apuntando a `/u/:id`
- `RenderMode.Server` para SSR + OG tags (igual que las cards)

---

## Roadmap

### Fase 1 — Core (completada)
- [x] Autenticación Google OAuth + Sanctum
- [x] Esquema de base de datos v2 (Products genérico + GameDetails)
- [x] Modelos y relaciones Eloquent
- [x] Motor de puntuación ponderada (ScoringService)
- [x] Integración IGDB API (IgdbService + ProductImportService)
- [x] Panel de administración completo (CRUD Admin)
- [x] Sistema de roles y baneos (usuarios + reseñas)
- [x] Nombres de géneros y categorías traducibles en BD (Spatie Translatable, JSON, 5 idiomas)
- [x] Relación many-to-many Products ↔ Genres (`Product_x_Genre`)
- [x] Algoritmo MAX weight para productos multi-género (top 15 categorías)
- [x] 22 géneros IGDB con pesos completos en todos los idiomas
- [x] Auto-detección de URL Steam desde `external_games` en import IGDB
- [x] Links de compra editables por plataforma (`PUT /products/{id}/purchase-links`)
- [x] Comando `db:reset-content` para reinicio limpio de datos

### Fase 2 — API pública
- [x] `GET /api/products/relevant` — top 6 productos con score ≥ 80 (B-), ordenados por `release_year` desc. Devuelve `slug`, `type` y `score_type` (global/pro) según cuál sea mayor.
- [x] `GET /api/products/{type}/{slug}` — detalle completo: géneros, game_details, plataformas con `purchase_url`, scores con letter_grade. Si el request incluye token Sanctum válido, añade `user_review` con la nota del usuario. Devuelve 404 si el type no coincide.
- [x] `GET /api/games?search=&page=1` — lista paginada de juegos (12 por página), ordenados por `MAX(release_date)` desc. Búsqueda por título con `LIKE`. Responde `{ data, current_page, last_page, total }`.
- [x] `GET /api/products/{id}/review-form` — datos del producto + categorías únicas de sus géneros para construir el formulario de crítica.
- [x] `POST /api/reviews` (auth + not.banned) — crea una review con scores por categoría. Calcula `weighted_score` y `letter_grade` vía `ScoringService`. Recalcula `ProductScores`. Devuelve 422 si el usuario ya tiene review del producto.
- [x] `GET /api/reviews/{review}/edit-form` (auth + not.banned) — devuelve los datos del producto + categorías + scores actuales + body de la review para pre-rellenar el formulario de edición. Valida que el usuario sea el autor.
- [x] `PUT /api/reviews/{review}` (auth + not.banned) — actualiza scores y body de una review existente. Recalcula `weighted_score`, `letter_grade` y `ProductScores`. Valida autoría.
- [x] `GET /api/products/{id}/reviews` — lista paginada de reseñas públicas de un producto (6 por página). Excluye reseñas baneadas y de usuarios con `reviews_public = false` o baneados. Devuelve `user.id`, `user.name`, `user.avatar`, `letter_grade`, `body`, `created_at`.
- [x] Trust Score en tiempo real (Follows del usuario autenticado) — `ScoringService::calculateTrustScore()`, se muestra en el detalle de producto solo si el usuario sigue a alguien que ha reseñado el juego; borde discontinuo morado para diferenciarlo

### Fase 3 — Capa social
- [x] Sistema de seguimiento (Follows) — ver sección *Sistema de seguimiento*
- [x] Perfil de usuario con sección pública (cards compartibles) — ver sección *Perfil público*
- [x] Sistema de badges — ver sección *Badges y logros*
- [x] Sistema de encuestas — ver sección *Sistema de encuestas*

### Fase 4 — Optimización
- [x] Job diario de snapshot histórico de ProductScores (`scores:snapshot`)

### Fase 5 — Pre-producción
- [ ] Página 404 personalizada en el frontend
- [ ] Rate limiting en endpoints sensibles más allá del login
- [ ] Tests PHPUnit — cubrir al menos `ScoringService` y endpoints críticos
- [ ] SSR en páginas públicas (detalle de producto, cards) para indexación por buscadores
- [ ] Opción "solo verificados" en encuestas y avisos (filtrar audiencia por badge `verificado`)
- [ ] Método de petición formal de badge verificado (formulario/flujo para que el usuario lo solicite)
- [ ] Automatizar links a tiendas de compra (Steam, PS Store, Xbox, etc. desde metadatos de IGDB)
- [ ] Nota de mis seguidores — igual que Trust Score pero calculado desde seguidores en vez de seguidos
- [ ] Creación de estilos propios — identidad visual de la plataforma (tipografía, paleta, personalidad)
- [ ] Investigar AdSense / Carbon Ads para monetización

### Fase 6 — Post-producción
- [ ] Laravel Queues para recálculo asíncrono de puntuaciones
- [ ] Widget de crítico / infografía embebible — componente que el crítico pueda embeber en su blog o imagen dinámica generada con Laravel + Spatie Browsershot resumiendo su nota en un gráfico para redes sociales
- [ ] Sistema de widget para directos — indica la última nota puesta y la nota del juego en el que está emitiendo en vivo
- [ ] Juegos recomendados al usuario según análisis de sus propias notas (ML o scoring heurístico)
- [ ] Investigar publicidad nativa y cómo implementarla
- [ ] Investigar comisión de transacción de Patreon para modelo de soporte/suscripción
- [ ] Investigar Docker para migración de infraestructura futura

---

## Badges y logros

Sistema de logros automáticos basado en hitos de actividad. Los badges se almacenan en `users.badges` (JSON array de slugs). El servicio central es `BadgeService` (`app/Services/BadgeService.php`).

### Badges manuales (admin)

| Badge | Asignación |
|---|---|
| `verificado` | Admin desde `/api/admin/users/{id}/badge/verify` |

### Badges automáticos — reseñas

| Badge | Hito |
|---|---|
| `critico-rapido` | Primera reseña de un producto sin reseñas previas |
| `critico-novel` | 10 reseñas |
| `critico-junior` | 20 reseñas |
| `critico-senior` | 50 reseñas |
| `critico-maestro` | 100 reseñas |
| `el-critico` | 200 reseñas |

### Badges automáticos — seguidores

| Badge | Hito |
|---|---|
| `critico-amigo` | 10 seguidores |
| `critico-solicitado` | 100 seguidores |
| `critico-fiable` | 1 000 seguidores |
| `critico-famoso` | 3 000 seguidores |
| `critico-influyente` | 6 000 seguidores |

**Sistema de reclamación (claim-based):** Los badges ya no se asignan automáticamente. El flujo es:
1. `GET /api/user/badges` — devuelve el progreso de cada badge (`current`, `threshold`, `awarded`, `claimable`).
2. `POST /api/user/badges/{slug}/claim` — el backend revalida los requisitos antes de otorgar el badge. Si el usuario no cumple los criterios o el badge ya fue otorgado, devuelve 422. Solo los badges cuyo `claimable = true` en el paso 1 pueden reclamarse.

`BadgeService::getProgress()` calcula el estado en tiempo real. `claim()` llama a `getProgress()` internamente para validar antes de otorgar, por lo que no es posible auto-asignarse un badge sin cumplir el requisito real.

**Admin — verificado:** `POST /api/admin/users/{user}/badge/verify` otorga el badge; `DELETE` lo revoca. El panel admin muestra un botón `✓` por fila que se activa visualmente cuando el usuario ya está verificado.

**Admin — verificado:** `POST /api/admin/users/{user}/badge/verify` otorga el badge; `DELETE` lo revoca. El panel admin muestra un botón `✓` por fila que se activa visualmente cuando el usuario ya está verificado.

---

## Seguridad y performance (audit 2026-04-16)

### Seguridad

**Rate limiting en `POST /api/auth/google`**
Añadido `throttle:10,1` al endpoint de login con Google: máximo 10 intentos por minuto por IP. Previene ataques de fuerza bruta contra el flujo OAuth.

**Validación de URLs en `UserProfileController::update()`**
Los campos `avatar`, `card_big_bg`, `card_mid_bg`, `card_mini_bg` y `social_links.*.url` ahora usan la regla `url` de Laravel en lugar de `string`. Esto rechaza valores arbitrarios que no sean URLs válidas.

### Performance

**Eliminación de N+1 queries**
`PublicCardController::show()` y `UserProfileController::cardData()` ejecutaban dos queries separadas para contar reseñas y seguidores. Reemplazado por `$user->loadCount([...])` que resuelve ambos conteos en una sola query:
```php
$user->loadCount([
    'reviews as reviews_count' => fn($q) => $q->whereNull('banned_at'),
    'followers as followers_count',
]);
```

**Índices de base de datos** (migración `2026_04_16_000002`)
Añadidos índices en las columnas más consultadas en WHERE/ORDER BY:
- `reviews.banned_at` — filtrado de reseñas activas vs. baneadas
- `reviews.product_id` — relación reviews ↔ product
- `Product_x_Platform.release_date` — ordenado en `GET /api/games`

**`ChangeDetectionStrategy.OnPush`** (Frontend)
Aplicado a los 26 componentes Angular de la aplicación. Angular solo re-evalúa el árbol de vistas cuando cambia una referencia de `@Input`, se emite un evento, o un `signal` lo fuerza. Con signals (que ya usa la app) esto es totalmente seguro y elimina ciclos de detección innecesarios.

**`takeUntilDestroyed`** (Frontend)
`GameListComponent` migraba el patrón manual `Subject` + `takeUntil(destroy$)` + `ngOnDestroy`. Reemplazado por `takeUntilDestroyed(destroyRef)` desde `@angular/core/rxjs-interop`, eliminando el boilerplate. `HeaderComponent` también añade `takeUntilDestroyed()` a la suscripción de eventos del router (llamado sin args en el constructor, que es contexto de inyección).

---

## Cards compartibles — i18n completa (2026-04-17)

Todas las cadenas de texto en las tres cards públicas (Big, Mid, Mini) y en la sección "Mi perfil público" ahora usan el sistema de internacionalización de la app.

**Qué se tradujo:**
- Etiquetas `reseñas` / `seguidores` / `Mis últimas reseñas` / `Ver perfil completo →` en `MidCardPageComponent` — antes hardcodeadas en español.
- Botones de seguir/dejar de seguir (`fiarme`, `following`, `fiarme_self`) y botón de copiar enlace en `UserProfileCardComponent` (Big Card y perfil público).
- Todos los nombres de badges en los tres contextos donde aparecen: `UserProfileCardComponent`, `MidCardPageComponent` y las cards inline de `UserPublicProfileComponent`.

**Cómo funciona:**
- Los badge slugs (`critico-senior`, `el-critico`, etc.) se traducen con la clave dinámica `'badges.' + slug | transloco`, apuntando a la sección `badges` de cada archivo i18n.
- Los 5 idiomas del proyecto (es, en, fr, pt, it) tienen la sección `badges` completa con los 11 slugs.
- `MidCardPageComponent` e `UserPublicProfileComponent` eliminaron su mapa hardcodeado `BADGE_LABELS` y ahora delegan en transloco. El método `badgeLabel()` fue eliminado de ambos componentes.

---

## Sistema de encuestas (2026-04-18)

Permite a los administradores crear encuestas temporales con opciones de respuesta. Los usuarios registrados que no hayan respondido ven un icono en el header mientras la encuesta está activa.

### Modelos

- `Survey` — `title`, `question`, `starts_at`, `ends_at`
- `SurveyOption` — `survey_id`, `text`
- `SurveyResponse` — `survey_id`, `user_id`, `survey_option_id` — unique(`survey_id`, `user_id`)

### Backend

**Admin (requiere role admin):**
```
GET    /api/admin/surveys              → lista con estado (upcoming|active|ended) y conteo de respuestas
POST   /api/admin/surveys              → crea encuesta + opciones
GET    /api/admin/surveys/{id}         → detalle
PUT    /api/admin/surveys/{id}         → actualiza (borra y recrea opciones)
DELETE /api/admin/surveys/{id}         → elimina
```

**Usuarios autenticados:**
```
GET    /api/surveys/active             → encuesta activa que el usuario no ha respondido aún (null si no hay)
POST   /api/surveys/{survey}/respond   → registra la respuesta (422 si ya respondió o la encuesta no está activa)
```

`SurveyController::active()` filtra con `whereDoesntHave('responses', fn($q) => $q->where('user_id', $user->id))` para devolver solo encuestas sin respuesta del usuario. El estado (`upcoming|active|ended`) se calcula en tiempo real comparando las fechas.

### Frontend

**Admin — `/admin/surveys`:** CRUD completo con formulario de opciones dinámico (N opciones, añadir/quitar). Pastillas de estado coloreadas (activa=verde, próxima=amarillo, finalizada=gris).

**Header:** Al hacer login se carga la encuesta activa vía `toObservable(this.authService.currentUser)`. Si existe, aparece un icono con animación CSS (`@keyframes survey-shake`) que vibra al hover. Al clicar, se abre el `DialogComponent` con las opciones como radio buttons. Al enviar se muestra un mensaje de agradecimiento 2 segundos y se cierra automáticamente ocultando el icono.

**Migración necesaria:**
```bash
php artisan migrate
```

Crea las tablas `surveys`, `survey_options`, `survey_responses`.

### i18n

Claves añadidas en los 5 idiomas (es, en, fr, pt, it):
- `admin.nav.surveys` — enlace en el nav del panel admin
- `admin.surveys.*` — textos del CRUD de encuestas
- `survey.*` — icono del header, botón de envío y mensaje de agradecimiento

### Mejoras posteriores (2026-04-18)

- **Multilingüe:** `title`, `question` y `text` de opciones pasaron a columnas JSON gestionadas con `spatie/laravel-translatable`. Se requieren los 5 idiomas (es, en, fr, pt, it) antes de guardar. Estado `missing_translations` si algún campo no está completo.
- **Tooltip de selección:** En el header ya no se abre la encuesta directamente, sino un tooltip con el listado de encuestas activas. El usuario elige cuál responder.
- **Resultados:** Botón "Resultados" en el admin abre un diálogo con barras de progreso por opción mostrando cantidad y porcentaje de respuestas. Implementado con `withCount('responses')` en `SurveyOption`.
- **Timezone fix:** `datetime-local` enviaba la hora local como UTC. Se añadieron helpers `localToUTC()` / `utcToLocal()` en el componente para conversión bidireccional.

---

## Sistema de avisos (2026-04-18)

Permite a los administradores publicar avisos informativos con título y cuerpo multilingüe. A diferencia de las encuestas, el icono en el header no desaparece al cerrar el modal, permitiendo releer el aviso.

### Modelo

- `Announcement` — `title` (JSON, 5 langs), `body` (JSON, 5 langs), `starts_at`, `ends_at`

### Backend

**Admin (requiere role admin):**
```
GET    /api/admin/announcements          → lista con estado calculado
POST   /api/admin/announcements          → crea aviso
GET    /api/admin/announcements/{id}     → detalle
PUT    /api/admin/announcements/{id}     → actualiza
DELETE /api/admin/announcements/{id}     → elimina
```

**Usuarios autenticados:**
```
GET    /api/announcements/active         → avisos activos con todas las traducciones completas
```

El estado (`upcoming|active|ended|missing_translations`) se calcula en tiempo real. Solo se devuelven en el endpoint público los avisos con todas las traducciones completas.

### Frontend

**Admin — `/admin/announcements`:** CRUD con tabs de idioma y barra de progreso de traducciones. Reutiliza las clases CSS del módulo de encuestas.

**Header:** Icono de megáfono separado del icono de encuesta. Abre un tooltip con el listado de avisos activos. Al clicar en uno se abre un `DialogComponent` con título y cuerpo. Cerrar el modal **no** elimina el aviso de la lista — el icono permanece visible para relectura.

**Migración necesaria:**
```bash
php artisan migrate
```

Crea la tabla `announcements`.

### i18n

Claves añadidas en los 5 idiomas:
- `admin.nav.announcements` — enlace en el nav del panel admin
- `admin.announcements.*` — textos del CRUD de avisos
- `announcement.icon_title` — tooltip del icono en el header
- `admin.surveys.results_btn` / `admin.surveys.results_total` — botón y título de resultados de encuesta

---

## Refactoring de duplicación (2026-04-18)

Auditoría y eliminación de código duplicado o mal factorizado, sin cambios de comportamiento.

### Backend

**`app/Http/Controllers/Admin/Concerns/ParsesIndexRequest.php`** (trait)
Extrae la resolución de `sort_by`, `sort_dir` y `per_page` que se repetía en los 6 controllers admin de listado (`GenreController`, `CategoryController`, `PlatformController`, `ProductController`, `ReviewController`, `UserController`). Cada controller hace `use ParsesIndexRequest` y llama a `$this->paginationParams($request, [...])`.

**`app/Models/Concerns/HasTranslatableSearch.php`** (trait)
Scope `searchTranslatable($query, $search, $column)` con `JSON_UNQUOTE(JSON_EXTRACT(...))` para buscar en los 5 idiomas de columnas JSON. Compartido por `Genre` y `Category`. Elimina la duplicación del bloque `orWhereRaw` que existía en ambos controllers admin.

**`app/Models/Concerns/HasPublishStatus.php`** (trait)
Método `status()` que devuelve `upcoming|active|ended|missing_translations`. Compartido por `Survey` y `Announcement`. Antes era un método privado duplicado en `SurveyController` y `AnnouncementController`.

**`Product::uniqueCategories()`**
Método en el modelo `Product` que deduplica categorías provenientes de múltiples géneros. Usaban el mismo foreach anidado: `ProductController::reviewForm()` y `ReviewController::editForm()`. Ahora ambos delegan en `$product->uniqueCategories()`.

**`User::cardData()`**
Método en el modelo `User` con las ~30 líneas de last_reviews + loadCount + sharedSocials + response array. Estaba duplicado verbatim en `UserProfileController::cardData()` y `PublicCardController::show()`. Ahora:
- `UserProfileController::cardData()` → `return response()->json($request->user()->cardData())`
- `PublicCardController::show()` → `array_merge($user->cardData(), ['is_following' => ...])`

### Frontend

**`src/app/core/constants/langs.ts`**
Constantes `LANGS` (array) y `LANG_LOCALES` (array con label de display) que se repetían inline en 6+ componentes. `LANGS` lo usan surveys y announcements; `LANG_LOCALES` lo usan los 4 CRUD de entidades traducibles (géneros, categorías, surveys, announcements).

**`src/app/core/utils/datetime.utils.ts`**
Funciones `utcToLocal()` y `localToUTC()` extraídas de `AdminSurveysComponent` y `AdminAnnouncementsComponent` donde estaban duplicadas como métodos privados.

**`src/app/core/utils/localized-value.ts`**
Función `localizedValue(record, lang)` con fallback `record[lang] || record['es'] || Object.values(record)[0] || ''`. Usada por `HeaderComponent` (aviso activo), `AdminSurveysComponent` y `AdminAnnouncementsComponent`.

**`src/app/features/admin/admin-table.base.ts`** (clase abstracta)
~40 líneas de estado y métodos de tabla compartidos (signals de página, sort, perPage, confirmDialog; métodos setSort, sortIcon, setPerPage, goTo, pages, openConfirm, confirmAction, closeConfirm) que se repetían en los 6 componentes admin de listado. Clase abstracta sin `@Component`, extendida con `extends AdminTableBase<T>`. El componente de productos sobreescribe el sort por defecto con `override sortBy = signal('title')`.

**Eliminado: `src/app/features/admin/pipes/localized-name.pipe.ts`**
Duplicado exacto de `src/app/shared/pipes/localized-name.pipe.ts`. Los 3 componentes admin que lo importaban (géneros, categorías, productos) actualizaron su import a la ruta shared.
