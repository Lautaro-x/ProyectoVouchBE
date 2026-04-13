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
| PHPUnit | 10.1 | Testing |

---

## Arquitectura general

```
app/
├── Http/
│   └── Controllers/
│       └── AuthController.php
├── Models/
│   ├── User.php
│   ├── Genre.php
│   ├── Category.php
│   ├── Platform.php
│   ├── Product.php
│   ├── GameDetail.php
│   ├── Review.php
│   ├── ReviewScore.php
│   ├── ProductScore.php
├── Services/
│   └── ScoringService.php
routes/
└── api.php
database/
└── migrations/
config/
├── cors.php
└── services.php
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
```

### Instalación
```bash
composer install
php artisan key:generate
php artisan migrate
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

**Decisión de arquitectura:** Se eligió el flujo frontend-iniciado (Google Identity Services) en lugar del flujo de redirección tradicional porque:
- No requiere redirect URIs configurados
- El token nunca viaja en la URL
- Mejor UX (popup en lugar de redirección)
- Es el estándar actual de Google

**Verificación del token:** Se llama a `https://oauth2.googleapis.com/tokeninfo?id_token={credential}` desde el backend para verificar la autenticidad del token y validar que el `aud` (audience) coincide con nuestro `GOOGLE_CLIENT_ID`. Sin librerías externas adicionales.

**Gestión de usuarios:** Si el usuario ya existe por email o google_id, se actualiza. Si no, se crea. Al hacer login se revocan todos los tokens anteriores y se emite uno nuevo.

**Endpoint:**
```
POST /api/auth/google
Body: { credential: string }
Response: { token: string, user: User }
```

**Endpoint protegido (ejemplo):**
```
GET /api/user
Header: Authorization: Bearer {token}
```

---

## Base de datos

### Tabla `users`
| Campo | Tipo | Descripción |
|---|---|---|
| id | bigint | PK |
| google_id | string nullable unique | ID de cuenta Google |
| name | string | Nombre |
| email | string unique | Email |
| avatar | string nullable | URL de foto de perfil |
| password | string nullable | Nullable porque usamos OAuth |
| email_verified_at | timestamp nullable | — |
| remember_token | string nullable | — |
| created_at / updated_at | timestamp | — |

---

---

## Motor de puntuación (`ScoringService`)

El núcleo diferenciador de la plataforma. Calcula puntuaciones ponderadas por categoría según el género del producto.

### Fórmula de nota ponderada

```
nota = Σ(puntuación_categoría × peso_categoría) / Σ(pesos)
```

Los pesos se definen en `genre_category.weight` (decimal 0-1). Al dividir por la suma de pesos, la nota siempre queda en escala 0-100 independientemente de cuántas categorías tenga el género.

### Escala de letras

| Rango | Letra | Rango | Letra |
|---|---|---|---|
| 97-100 | A+ | 77-79 | C+ |
| 93-96 | A | 73-76 | C |
| 90-92 | A- | 70-72 | C- |
| 87-89 | B+ | 67-69 | D+ |
| 83-86 | B | 63-66 | D |
| 80-82 | B- | 60-62 | D- |
| — | — | 0-59 | F |

### Triple nota por producto

| Score | Quién contribuye | Dónde se guarda |
|---|---|---|
| `global_score` | Todos los usuarios | `product_scores` |
| `pro_score` | Usuarios con role `critic` o `admin` | `product_scores` |
| `trust_score` | Usuarios a los que sigues | Calculado en tiempo real |

El Trust Score no se cachea porque es personal para cada usuario. Global y Pro se recalculan y cachean en `product_scores` al publicar cada crítica.

---

## Base de datos

### Convención de nombres
- Tablas regulares: PascalCase plural (`Genres`, `Products`)
- Tablas cruzadas: `A_x_B` singular (`Genre_x_Category`, `Product_x_Platform`)
- Excepción: `Follows` (auto-referencial Users–Users)
- Campos: snake_case minúscula

### Esquema completo

```
Users
  id, name, email, password (nullable), google_id (hidden)
  avatar, role (user|critic|admin), badges (JSON), timestamps

Genres                      Categories
  id, name, slug              id, name, slug, timestamps
       └──── Genre_x_Category ────┘
               genre_id, category_id
               weight (decimal 0.00–1.00), timestamps

Platforms
  id, name, slug, type (console|pc|streaming), timestamps

Products
  id, type (game|movie|series), genre_id
  title, slug, description
  cover_image (URL externa; local si existe public/cover_images/{slug}.ext)
  timestamps
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
             body (varchar 255, nullable, sin links)
             weighted_score (int 0–100), letter_grade
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

### Fórmula de score

Los scores de categoría (0–10) se ponderan y escalan a 0–100:
```
weighted_score = round( Σ(score × weight) / Σ(weights) × 10 )
```

---

## Roadmap

### Fase 1 — Core (en progreso)
- [x] Autenticación Google OAuth
- [ ] Rediseño y creación de migraciones (esquema v2)
- [ ] Modelos y relaciones Eloquent
- [ ] Endpoints CRUD: Products, Genres, Categories, Platforms
- [ ] Endpoint de Reviews con cálculo de score ponderado
- [ ] Integración IGDB API (metadatos de juegos)

### Fase 2 — Capa social
- [ ] Sistema de Follow
- [ ] Trust Score (media de usuarios seguidos, tiempo real)
- [ ] Validación de críticas (útil / no útil)
- [ ] Niveles de usuario (Entusiasta / Experto)

### Fase 3 — Extensibilidad
- [ ] MovieDetails (director, imdb_id, duration)
- [ ] SeriesDetails (director, imdb_id, seasons)

### Fase 4 — Optimización
- [ ] Job diario de snapshot histórico de ProductScores (no trigger)
- [ ] Laravel Queues para recálculo asíncrono de puntuaciones
- [ ] Caché de puntuaciones (Redis)
- [ ] Scraper de Pro Scores (Metacritic/OpenCritic)

---

## TODO

- [ ] Definir y crear todas las migraciones del esquema v2
- [ ] Crear modelos: Platform, GameDetails, y actualizar Product
- [ ] Controllers: GenreController, CategoryController, ProductController, PlatformController, ReviewController
- [ ] Sistema de puntuación ponderada: Σ(score×weight)/Σ(weights) → letter grade
- [ ] Triple score por producto: Global (todos), Pro (critics/admin), Trust (seguidos, tiempo real)
- [ ] Endpoint Trust Score: calculado en tiempo real según follows del usuario autenticado
- [ ] Sistema de roles: user, critic, admin — Pro Score solo cuenta critics y admins
- [ ] Sistema de Follow entre usuarios
- [ ] IGDB API: integración para autocompletar metadatos de juegos
- [ ] Soporte multi-plataforma: consolas, PC, streaming con fecha de salida y link por plataforma
- [ ] Extensibilidad a películas y series (MovieDetails, SeriesDetails)
- [ ] Job diario: snapshot histórico de ProductScores por día
- [ ] Validación de críticas (útil / no útil)
- [ ] Niveles de usuario (Entusiasta / Experto)
- [ ] Laravel Queues + Redis para recálculo y caché de puntuaciones
- [ ] Scraper de Pro Scores (Metacritic / OpenCritic)
