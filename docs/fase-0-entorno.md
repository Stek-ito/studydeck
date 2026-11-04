# Fase 0: Diagnóstico y entorno

Guía de lo que se hizo, paso a paso, para poder repetirlo en otro proyecto o en otra máquina.

Si algo falla, mira primero [troubleshooting.md](troubleshooting.md).

## Resultado

| Pieza | Versión / valor |
|---|---|
| Laravel | 13.39.0 |
| PHP | 8.5.12 (solo en la terminal de StudyDeck; la global es 8.3) |
| Composer | 2.11.1 |
| MySQL | 8.0.27 (WAMP), `127.0.0.1:3306` |
| Bases de datos | `studydeck` (app) y `studydeck_test` (tests), `utf8mb4_unicode_ci` |
| Usuario de MySQL | `studydeck@127.0.0.1`, con permisos solo sobre esas dos bases de datos |
| Motor de tablas | InnoDB, `ROW_FORMAT=DYNAMIC` |
| Ruta del proyecto | `C:\Roberto\StudyDeck` |

---

## Al empezar cada sesión de trabajo

En una terminal **nueva**:
```powershell
$env:Path = "C:\wamp64\bin\php\php8.5.12.1;" + $env:Path
cd C:\Roberto\StudyDeck
php -v   # ✅ 8.5.x
```
Comprueba también que WAMP está arrancado (icono verde).

---

## Paso 1: Versiones de PHP

**Qué:** PHP 8.3 por defecto (para ToysTNT) y PHP 8.5 solo en la terminal de StudyDeck.

**Por qué:** Windows usa el primer `php.exe` que encuentra en el `PATH`. Si se cambia la versión global, se cambia para todos los proyectos.

1. WAMP → clic derecho → Herramientas → "Cambiar versión de PHP CLI" → **8.3.36.1**.
2. Cerrar todas las terminales y abrir una nueva → `php -v` → ✅ 8.3.36.
3. En esa terminal: `$env:Path = "C:\wamp64\bin\php\php8.5.12.1;" + $env:Path` → `php -v` → ✅ 8.5.12.
4. En otra terminal → `php -v` → ✅ sigue en 8.3.36 (el cambio solo afecta a una terminal).

**Extensiones necesarias para Laravel** en el `php.ini` de la 8.5 (activas si la línea no empieza por `;`): `openssl`, `pdo_mysql`, `mbstring`, `fileinfo`, `intl`, `zip`, `curl`. Para comprobarlo: `php -m`.

---

## Paso 2: Actualizar Composer

**Por qué:** Composer 2.5.5 (2023) es anterior a PHP 8.5 y daba avisos "Deprecated".

1. Terminal **como administrador** (Composer vive en `C:\ProgramData`).
2. `composer self-update`.
3. En una terminal normal → `composer -V` → ✅ `Composer version 2.11.1` y `PHP version 8.3.36`.
4. Con el PATH de la 8.5 → `composer -V` → ✅ el mismo Composer con `PHP version 8.5.12`.

**Idea clave:** hay un solo Composer, que usa el PHP del `PATH` de cada terminal.

---

## Paso 3: Bases de datos

**Por qué dos:** los tests (`RefreshDatabase`) borran y recrean las tablas. Si usaran la base de datos de la app, perderías los datos.

En phpMyAdmin (`http://localhost/phpmyadmin`, usuario `root`, sin contraseña, servidor **MySQL**, no MariaDB), pestaña SQL:
```sql
CREATE DATABASE studydeck CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE studydeck_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SHOW DATABASES LIKE 'studydeck%';   -- ✅ aparecen las dos
```
- `utf8mb4`: UTF-8 completo (tildes, ñ, emojis). El `utf8` de MySQL está incompleto; no usarlo nunca.
- `utf8mb4_unicode_ci`: forma de comparar y ordenar textos; `ci` = *case insensitive*. Es la que usa Laravel por defecto.

En Symfony sería `php bin/console doctrine:database:create`.

---

## Paso 4: Crear el proyecto

```powershell
cd C:\Roberto\StudyDeck          # carpeta vacía
composer create-project laravel/laravel .
php artisan --version            # ✅ Laravel Framework 13.x
```
- El `.` del final = "instalar aquí". Sin él, se crea una subcarpeta (ver troubleshooting, problema 7).
- No usamos `laravel new` ni *starter kits* (React/Vue/Livewire con login hecho), para ver el esqueleto limpio y montar la autenticación a mano en la fase 3.
- Equivale a `composer create-project symfony/skeleton`.

Lo que hace `create-project`, en orden:
1. Descarga el esqueleto `laravel/laravel`.
2. `composer install`: `laravel/framework` y sus dependencias, muchas de ellas componentes de Symfony (HttpFoundation, Console, Routing…).
3. Scripts `post-create-project-cmd`:
   - copia `.env.example` → `.env`;
   - `php artisan key:generate` crea la `APP_KEY` (≈ `APP_SECRET` de Symfony);
   - crea `database/database.sqlite` y migra sobre SQLite (Laravel usa SQLite por defecto). Lo cambiamos en el paso 6.

**Ficheros para IA que trae Laravel 13:** `CLAUDE.md` y `AGENTS.md` con instrucciones para instalar **Laravel Boost**. El `CLAUDE.md` se sustituyó por el del proyecto; Boost se valorará en la fase 6.

---

## Paso 5: Estructura comparada con Symfony

| Laravel | Symfony | Para qué sirve |
|---|---|---|
| `app/` | `src/` | Tu código (namespace `App\` en los dos) |
| `app/Http/Controllers/` | `src/Controller/` | Controladores |
| `app/Models/` | `src/Entity/` + `src/Repository/` | Modelos Eloquent: entidad **y** repositorio a la vez (Active Record) |
| `app/Providers/AppServiceProvider.php` | `config/services.yaml` | Registrar y configurar servicios, en PHP |
| `bootstrap/app.php` | `src/Kernel.php` + parte de `config/packages/` | Arranque: rutas, middleware y gestión de errores |
| `config/*.php` | `config/packages/*.yaml` | Configuración: cada fichero devuelve un array de PHP |
| `routes/web.php` | `config/routes.yaml` o `#[Route]` | Rutas en código |
| `routes/console.php` | `src/Command/` | Comandos rápidos escritos como closures |
| `resources/views/` | `templates/` | Plantillas Blade (≈ Twig) |
| `resources/css`, `resources/js` | `assets/` | Frontend, compilado con Vite |
| `database/migrations/` | `migrations/` | Migraciones |
| `database/factories/`, `database/seeders/` | `src/DataFixtures/` | Datos de prueba |
| `storage/` | `var/` | Logs, caché, sesiones y ficheros subidos |
| `public/index.php` | `public/index.php` | *Front controller* |
| `tests/Feature`, `tests/Unit` | `tests/` | Tests de integración y unitarios |
| `artisan` | `bin/console` | Consola |

Las rutas de API (`routes/api.php`) no vienen por defecto: se añaden con `php artisan install:api` (fase 1).

### Conceptos aprendidos

**Un paquete frente a "instala lo que necesites".** El `composer.json` solo pide `php`, `laravel/framework` y `laravel/tinker`. `laravel/framework` trae todo de serie (ORM, colas, correo, caché, validación). Symfony parte de un esqueleto mínimo y se amplía con bundles y recetas de Flex.

**`env()` solo dentro de `config/`.** `env('DB_CONNECTION', 'sqlite')` significa "el valor del `.env`; si no existe, `'sqlite'`". En el código de la app se usa `config('database.default')`, nunca `env()`. El motivo: en producción, `php artisan config:cache` junta toda la configuración en un fichero y Laravel deja de leer el `.env` (parecido a la compilación del contenedor de Symfony en `var/cache`).

**El `.env` funciona al revés que en Symfony:**

| | Symfony | Laravel |
|---|---|---|
| Se sube a git | `.env` (valores por defecto) | `.env.example` (plantilla) |
| No se sube | `.env.local` (tus valores) | `.env` (tus valores y secretos) |

**Middleware = capas alrededor del controlador** (como una cebolla). Cada capa puede:
1. mirar o modificar la petición;
2. cortar el paso y responder ella misma;
3. modificar la respuesta a la vuelta.

En Symfony se hace con dos listeners (`kernel.request` antes y `kernel.response` después). En Laravel es una sola clase, y lo que separa el antes del después es `$next`:
```php
public function handle(Request $request, Closure $next): Response
{
    // antes del controlador (≈ kernel.request)
    $response = $next($request);   // sigue hacia el controlador
    // después: la respuesta ya existe (≈ kernel.response)
    return $response;
}
```
Si se devuelve una respuesta sin llamar a `$next`, el controlador no se ejecuta.

Se registran en `bootstrap/app.php` → `->withMiddleware(...)`:

| Forma | Afecta a | Equivalente en Symfony |
|---|---|---|
| `$middleware->append(...)` | Todas las peticiones | Listener de `kernel.request` |
| `$middleware->web(append: ...)` | Solo el grupo `web` (sesión, cookies, CSRF) | Listener limitado a un firewall |
| `$middleware->alias([...])` + `Route::middleware('...')` | Solo las rutas elegidas | `access_control` en `security.yaml` |

**Autenticación frente a autorización:**

| Pregunta | Concepto | Laravel | Symfony |
|---|---|---|---|
| ¿Quién eres? ¿Has iniciado sesión? | Autenticación | Middleware `auth` | Firewall + `access_control` |
| ¿Puedes ver **este** mazo? | Autorización | Policy (`DeckPolicy`) | Voter |

`auth` va solo en las rutas privadas. Si fuera global, también bloquearía la página de login y habría un bucle de redirecciones.

---

## Paso 6: Conectar con MySQL y migrar

**Por qué es imprescindible:** el `.env` trae `SESSION_DRIVER=database`, `CACHE_STORE=database` y `QUEUE_CONNECTION=database`. Sesiones, caché y colas viven en tablas; sin ellas, la app no arranca.

### 6.1. Usuario propio de MySQL (mínimo privilegio)
Con `root`, StudyDeck podría tocar las bases de datos de ToysTNT. En phpMyAdmin, como `root`:
```sql
CREATE USER 'studydeck'@'127.0.0.1' IDENTIFIED BY '<contraseña>';
GRANT ALL PRIVILEGES ON studydeck.* TO 'studydeck'@'127.0.0.1';
GRANT ALL PRIVILEGES ON studydeck_test.* TO 'studydeck'@'127.0.0.1';
```

### 6.2. `.env`
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=studydeck
DB_USERNAME=studydeck
DB_PASSWORD=<contraseña>
```
Y borrar el SQLite que creó el instalador: `Remove-Item database\database.sqlite`.

### 6.3. Comprobar la conexión antes de migrar
```bash
php artisan db:show   # ✅ MySQL 8.0.27, Database studydeck, Username studydeck, Tables 0
```

### 6.4. Motor InnoDB con formato DYNAMIC
El MySQL de WAMP crea las tablas en MyISAM y con formato de fila COMPACT, y las migraciones fallan (troubleshooting, problemas 1 y 2). En `config/database.php`, conexión `'mysql'`:
```php
'engine' => 'InnoDB ROW_FORMAT=DYNAMIC',   // antes: null
```

### 6.5. Migrar
```bash
php artisan migrate          # o migrate:fresh si una migración quedó a medias
php artisan migrate:status   # ✅ las 3 en "Ran"
```

| Migración | Tablas |
|---|---|
| `0001_01_01_000000_create_users_table` | `users`, `password_reset_tokens`, `sessions` |
| `0001_01_01_000001_create_cache_table` | `cache`, `cache_locks` |
| `0001_01_01_000002_create_jobs_table` | `jobs`, `job_batches`, `failed_jobs` |

Además, la tabla `migrations` registra qué migraciones se han ejecutado (≈ `doctrine_migration_versions`). Total: 9 tablas.

Comprobar el motor en phpMyAdmin:
```sql
SELECT table_name, engine, row_format FROM information_schema.tables WHERE table_schema = 'studydeck';
-- ✅ todas InnoDB / Dynamic
```

---

## Paso 7: Arrancar y primer commit

*Pendiente.*
