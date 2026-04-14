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
│   └── IgdbImportTopCommand.php
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

El núcleo diferenciador de la plataforma. Calcula puntuaciones ponderadas por categoría según el género del producto.

**Fórmula:**
```
weighted_score = round( Σ(score × weight) / Σ(weights) × 10 )
```

Los scores de categoría son enteros 0–10. Los pesos se definen en `Genre_x_Category.weight` (decimal 0.00–1.00). El resultado final es un entero 0–100.

**Escala de letras:**

| Rango | Letra | Rango | Letra |
|---|---|---|---|
| 97-100 | A+ | 77-79 | C+ |
| 93-96 | A | 73-76 | C |
| 90-92 | A- | 70-72 | C- |
| 87-89 | B+ | 67-69 | D+ |
| 83-86 | B | 63-66 | D |
| 80-82 | B- | 60-62 | D- |
| — | — | 0-59 | F |

**Triple nota por producto:**

| Score | Quién contribuye | Dónde se guarda |
|---|---|---|
| `global_score` | Todos los usuarios | `ProductScores` |
| `pro_score` | Usuarios con role `critic` o `admin` | `ProductScores` |
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
- Genera slug único con sufijo numérico si hay colisión

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
- Index paginado (15 por página) con carga de `gameDetail` y `platforms`
- Creación y edición con campos de `GameDetails` embebidos en el request
- Slug auto-generado desde el título; sufijo numérico si hay colisión
- Cover image: URL externa por defecto. Si existe `public/cover_images/{slug}.{ext}` (jpg/jpeg/png/webp), se devuelve la ruta local.

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
  id, type (game|movie|series), genre_id
  title, slug, description, cover_image, timestamps
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
             body (varchar 255, nullable)
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

Siembra géneros con su `igdb_genre_id`, 6 categorías de evaluación y las asignaciones con pesos por género. Todos los nombres incluyen los 5 idiomas.

| Género (slug) | Categorías y pesos |
|---|---|
| `rpg` | Story 0.30, Gameplay 0.25, Graphics 0.15, Sound 0.15, Duration 0.15 |
| `fps` | Gameplay 0.40, Graphics 0.20, Story 0.15, Sound 0.15, Duration 0.10 |
| `sport` | Gameplay 0.40, Graphics 0.20, Duration 0.25, Sound 0.10, Story 0.05 |

Categorías disponibles: `gameplay`, `story`, `graphics`, `sound`, `duration`, `feel`.

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

### Fase 2 — API pública
- [ ] Endpoints públicos: listado y detalle de productos con triple score
- [ ] Endpoint de reseñas: publicar, listar por producto
- [ ] Trust Score en tiempo real (Follows del usuario autenticado)
- [ ] Validación de críticas (útil / no útil)

### Fase 3 — Capa social
- [ ] Niveles de usuario (Entusiasta / Experto)
- [ ] Sistema de notificaciones

### Fase 4 — Extensibilidad
- [ ] MovieDetails (director, imdb_id, duration)
- [ ] SeriesDetails (director, imdb_id, seasons)

### Fase 5 — Optimización
- [ ] Job diario de snapshot histórico de ProductScores
- [ ] Laravel Queues para recálculo asíncrono de puntuaciones
- [ ] Caché de puntuaciones (Redis)
