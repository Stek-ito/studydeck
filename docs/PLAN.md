# StudyDeck: aprender Laravel desde Symfony construyendo una app real

Inicio del plan: 2026-10-17 · Reorientado a StudyDeck: 2026-10-28 · Ritmo part-time
Idioma: código y commits en inglés; este plan, la documentación y las clases en español.

**StudyDeck** es una app de Laravel que convierte PDFs en fichas de estudio con la API de Claude y las repasa con repetición espaciada (Leitner). Es un proyecto personal y de portfolio para entrevistas: toca Eloquent, colas, integración con IA, testing y control de costes.

Cada fase del plan de Laravel se practica construyendo una pieza de StudyDeck, en vez de ejercicios sueltos. Roberto escribe el código que le sirve para aprender; Claude revisa y genera solo el boilerplate.

## Equivalencias Symfony → Laravel

| Symfony | Laravel | Nota |
|---|---|---|
| Service Container + autowiring | Service Container | Laravel resuelve más "mágicamente", menos `services.yaml` |
| Doctrine ORM (Data Mapper) | Eloquent (Active Record) | Cambio de paradigma real, no solo sintaxis |
| Twig | Blade | Blade compila a PHP puro, más simple |
| Symfony Console | Artisan | `php bin/console` → `php artisan` |
| Symfony Forms | Form Requests + Validator | Laravel no tiene "form builder", valida y ya |
| Security (Voters, Guards) | Gates & Policies, Sanctum/Passport | Conceptualmente similar |
| EventDispatcher | Events & Listeners | Casi calcado |
| Messenger | Queues (Jobs) | Muy similar en espíritu |
| Doctrine Migrations | Migrations | Sintaxis fluida en vez de SQL/annotations |
| Bundles | Packages (Composer + Service Providers) | Menos ceremonia que un bundle |
| DoctrineFixtures | Seeders + Factories | Factories usan Faker |
| `config/services.yaml` | Service Providers (`app/Providers`) | Configuración en PHP, no YAML |
| ParamConverter | Route Model Binding | Laravel lo hace implícito |

## Decisiones de arquitectura (versión barata, uso personal)

- **Versiones:** Laravel 13 (pide PHP 8.3 a 8.5) con **PHP 8.5**, la última estable. PHP 8.6 sale en noviembre de 2026; se puede actualizar después.
- **Base de datos:** MySQL local, la base de datos `studydeck` en `utf8mb4` que crea Roberto, con un usuario de MySQL propio (`studydeck`) que solo accede a `studydeck` y `studydeck_test`. Tablas en `InnoDB ROW_FORMAT=DYNAMIC`, declarado en `config/database.php` para no depender de la configuración del servidor. Las tablas siempre con migraciones.
- **Entorno:** dos caminos que conviven. WAMP en local para empezar rápido, y Docker en paralelo para aprender esa tecnología (ver "Pista Docker").
- **Colas:** driver `database`, sin Redis.
- **PDFs:** se extrae el texto en el servidor (`pdftotext`); el PDF se envía tal cual a Claude solo si está escaneado.
- **Claude API:** SDK oficial de PHP, salida estructurada con esquema JSON, Batch API (–50 %), hash del PDF para no procesarlo dos veces y registro de tokens gastados por documento. La clave de la API va solo en `.env`.
- **Modelo:** lo elige Roberto tras comparar Opus 5.5, Sonnet 5.5 y Haiku 5.5 con sus PDFs (fase final).
- **Móvil:** PWA primero; la API con Sanctum deja abierta una app nativa (KMP) más adelante.

## Estado de las fases

| Fase | Tema | Pieza de StudyDeck | Estado |
|---|---|---|---|
| 0 | Diagnóstico y entorno | Proyecto creado y arrancando | [x] 2026-11-04 |
| 1 | Routing, controladores, middleware | API de mazos y fichas en memoria | [x] 2026-11-10 |
| 2 | Eloquent ORM | Modelo de datos y seeders | [ ] |
| 3 | Blade, validación, auth | Pantalla de estudio, subida de PDFs, login | [ ] |
| 4 | Artisan, Events, Jobs/Queues | Generación de fichas con Claude en background | [ ] |
| 5 | Testing | Tests del flujo completo con Claude simulado | [ ] |
| 6 | Ecosistema y "Laravel way" | Livewire, PWA, API con Sanctum | [ ] |
| D | Pista Docker (en paralelo, desde la fase 1) | StudyDeck entero corriendo en contenedores | [ ] |
| Final | Consolidación | Medir costes reales, elegir modelo, desplegar | [ ] |

## Fase 0 — Diagnóstico y entorno
Proyecto en `C:\Roberto\StudyDeck`. Antes de trabajar, en cada terminal nueva:
`$env:Path = "C:\wamp64\bin\php\php8.5.12.1;" + $env:Path` (la consola global se queda en PHP 8.3 por ToysTNT).

1. [x] Versiones de PHP: 8.3 global, 8.5 solo en la terminal de StudyDeck.
2. [x] Composer actualizado a 2.11.1.
3. [x] Bases de datos `studydeck` y `studydeck_test` creadas en MySQL (`utf8mb4_unicode_ci`).
4. [x] Proyecto creado con `composer create-project laravel/laravel .` → Laravel 13.39.0.
5. [x] Estructura comparada con `symfony/skeleton`; conceptos: `env()`, `.env` frente a Symfony, middleware, autenticación frente a autorización.
6. [x] MySQL con usuario propio (`studydeck`), motor `InnoDB ROW_FORMAT=DYNAMIC` y `php artisan migrate` (9 tablas).
7. [x] `php artisan serve` (bienvenida, `/up` y sesión guardada en MySQL) y primer commit `4fff5f8` en la rama `main`.

Guía detallada de la fase: [fase-0-entorno.md](fase-0-entorno.md). Problemas encontrados y su solución: [troubleshooting.md](troubleshooting.md).

## Fase 1 — Routing, controladores, middleware
- Rutas en `routes/web.php` y `routes/api.php`; resource controllers (`Route::resource`).
- Middleware = listeners de `kernel.request`/`kernel.response`, con pipeline explícito.
- Route Model Binding (≈ ParamConverter sin configurar).

**Práctica:** `DeckController` y `CardController` que devuelven JSON a partir de un array en memoria. Un middleware sencillo que mida el tiempo de respuesta.

1. [x] Teoría del routing: `routes/`, anatomía de una ruta, grupos `web` (sesión, CSRF) y `api` (sin estado, prefijo `/api`).
2. [x] `routes/api.php` creado a mano y registrado en `bootstrap/app.php`.
3. [x] `DeckController` con `index` y `show`.
4. [x] Restricciones de ruta (`where`) y 404 con `abort()`: un único formato de error `{"message"}`.
5. [x] Recurso anidado `/api/decks/{deck}/cards` con `CardController` y rutas agrupadas con `prefix`.
6. [x] Middleware `MeasureResponseTime` (cabecera `X-Response-Time`) en el grupo `api`.
7. [x] Repositorio en GitHub (`Stek-ito/studydeck`, por SSH) y un commit por paso desde ahora.

Guía detallada de la fase: [fase-1-routing.md](fase-1-routing.md). Problema de GitHub con dos cuentas: [troubleshooting.md](troubleshooting.md), apartado 8.

## Fase 2 — Eloquent ORM (la más difícil)
Doctrine es Data Mapper, Eloquent es Active Record: el objeto se guarda a sí mismo (`$deck->save()`), no hay `EntityManager`.
- Migrations, `$fillable`, casts, relaciones, query scopes, factories y seeders.
- Eager loading con `with()` desde el principio para evitar el N+1.

Guía detallada y pasos de la fase: [fase-2-eloquent.md](fase-2-eloquent.md).

**Práctica:** las tablas de StudyDeck:
- `documents`: PDF subido, `sha256`, estado (`pending`/`processing`/`done`/`failed`), páginas y tokens gastados.
- `decks` y `cards`: término, definición, `is_key`, página de origen. Relaciones `User hasMany Deck`, `Deck hasMany Card`, `Document hasOne Deck`.
- `card_reviews`: caja de Leitner, próxima fecha, explicación propia y duda.
- Scope `dueToday()` en `Card`.
- Seeder que cargue unas fichas de ejemplo.

## Fase 3 — Blade, validación, auth
- Blade: layouts y componentes (≈ `{% extends %}`/`{% block %}`).
- Form Requests (`make:request`) para la validación.
- Auth con Breeze; Gates y Policies (≈ Voters).

**Práctica:**
- Pantalla de estudio: girar la ficha, "Lo sé" / "Repasar", filtro "Hoy".
- La lógica de Leitner en un servicio propio (`LeitnerScheduler`) con intervalos 1, 3, 7, 15, 30 y 60 días.
- Formulario de subida con `StoreDocumentRequest` (`mimes:pdf`, tamaño y páginas máximas).
- `DeckPolicy`: cada usuario solo ve y edita sus mazos.

## Fase 4 — Artisan, Events, Jobs/Queues
- Commands ≈ Symfony Console; Events & Listeners ≈ EventDispatcher; Queues ≈ Messenger.

**Práctica:** el corazón de StudyDeck.
- Job `GenerateCardsFromDocument`: extrae el texto, lo trocea por capítulos, llama a Claude con salida estructurada `{cards: [{term, definition, key, page}]}` y guarda el mazo.
- El cliente de Claude detrás de una interfaz propia (`FlashcardGenerator`) registrada en un Service Provider, para poder cambiarla en los tests.
- Idempotencia por `sha256`, reintentos con backoff y `failed_jobs`.
- Evento `DeckGenerated` con un listener que registre los tokens y el coste.
- Command `studydeck:import-html` que importe las 470 fichas de la página de entrevista (`entrevista-backend/fichas-backend.html`).

## Fase 5 — Testing (en paralelo desde la fase 2)
- PHPUnit con `RefreshDatabase`, `actingAs()` y HTTP testing.
- `Queue::fake()`, `Storage::fake()`, `Event::fake()` y un `FakeFlashcardGenerator` para no llamar a la API real.
- Tests unitarios de `LeitnerScheduler`.
- Pest opcional.

## Fase 6 — Ecosistema y "Laravel way"
- Livewire para la pantalla de estudio, sin recargar la página.
- PWA: manifest y service worker para instalarla en el móvil y estudiar sin conexión.
- Sanctum: API autenticada para una futura app móvil.
- Herramientas a conocer: Horizon, Telescope (≈ profiler), Pulse.

## Pista Docker (en paralelo)
Objetivo: entender Docker escribiendo la configuración a mano, no solo usarla. Al final StudyDeck arranca con un `docker compose up` en cualquier máquina.

- **D1 · Conceptos:** imagen, contenedor, capa, volumen, red, puertos. `docker run`, `ps`, `logs`, `exec`. Práctica: levantar un MySQL 8.4 suelto en el puerto 3308 (en WAMP, el 3306 es MySQL y el 3307 MariaDB).
- **D2 · Dockerfile propio:** imagen basada en `php:8.5-fpm` con las extensiones de Laravel (`pdo_mysql`, `intl`, `zip`…), Composer y `poppler-utils` (para `pdftotext`). Caché de capas y `.dockerignore`.
- **D3 · Docker Compose:** servicios `app` (PHP-FPM), `web` (nginx), `db` (MySQL), `queue` (`php artisan queue:work`) y, opcional, `scheduler`. Volúmenes para los datos de MySQL, variables de entorno, `depends_on` con healthchecks.
- **D4 · Comparar con Laravel Sail:** qué hace Sail por ti y por qué hemos montado lo nuestro antes.
- **D5 · Producción y CI:** build multi-stage (imagen pequeña sin herramientas de desarrollo), usuario no root y la imagen en un pipeline de GitHub Actions que pase los tests.

Notas para Windows: Docker Desktop usa WSL2; el código montado desde `C:\` va lento, así que en esta pista el proyecto se clona dentro del sistema de ficheros de WSL.

## Final — Consolidación
- Medir 2 o 3 PDFs reales con el endpoint de conteo de tokens y comparar calidad y coste con Opus 5.5, Sonnet 5.5 y Haiku 5.5. Roberto elige el modelo.
- Pasar la generación a la Batch API.
- Desplegar en un VPS pequeño con la imagen de Docker (opcional) y documentar en el README las decisiones de diseño frente a cómo se haría en Symfony. Ese README es lo que se cuenta en una entrevista.

## Recursos
1. Laracasts "Laravel from Scratch".
2. Documentación oficial de Laravel.
3. Documentación oficial de Docker ("Get started") y las imágenes oficiales de PHP y MySQL en Docker Hub.
4. Documentación de la API de Claude (PDF, salida estructurada, Batch API, conteo de tokens).
