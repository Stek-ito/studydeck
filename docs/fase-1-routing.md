# Fase 1: Routing, controladores y middleware

**Objetivo:** construir la primera pieza de StudyDeck, una API JSON de mazos (*decks*) y fichas (*cards*) con los datos en memoria, sin base de datos, y escribir el primer middleware propio.

Si algo falla, mira [troubleshooting.md](troubleshooting.md).

## Pasos

1. [x] Teoría: cómo funciona el routing en Laravel frente a Symfony
2. [x] Crear `routes/api.php` a mano y registrarlo en `bootstrap/app.php`
3. [x] Primer controlador: `DeckController` con `index` y `show`
4. [x] Parámetros de ruta, restricciones y errores 404
5. [x] Recurso anidado: `CardController` (`/api/decks/{deck}/cards`)
6. [ ] Middleware propio: `MeasureResponseTime`
7. [ ] Commit

---

## Paso 1: Cómo funciona el routing

### Dónde se definen las rutas

| | Symfony | Laravel |
|---|---|---|
| Dónde | Atributos `#[Route]` en el controlador, o `config/routes.yaml` | Ficheros de `routes/` (`web.php`, `api.php`…) |
| Cómo | Declarativo, junto al método | Código PHP: llamadas a la fachada `Route` |
| Ver todas | `php bin/console debug:router` | `php artisan route:list` |

En Laravel, las rutas **se separan del controlador**: un fichero central donde se ven todas de un vistazo.

```php
// Symfony
#[Route('/api/decks/{id}', name: 'deck_show', methods: ['GET'])]
public function show(int $id): JsonResponse { ... }

// Laravel (routes/api.php)
Route::get('/decks/{id}', [DeckController::class, 'show'])->name('decks.show');
```

### La anatomía de una ruta

```php
Route::get('/decks/{id}', [DeckController::class, 'show'])->name('decks.show');
//    │     │              │                                └─ nombre, para generar URLs: route('decks.show', 5)
//    │     │              └─ quién responde: [clase, método] o una closure
//    │     └─ la URL, con {parámetros}
//    └─ el método HTTP: get, post, put, patch, delete
```

El nombre equivale al `name:` de Symfony, y `route('decks.show', 5)` es como `$this->generateUrl('deck_show', ['id' => 5])`.

**Una ruta puede responder con una closure**, sin controlador:
```php
Route::get('/', function () {
    return view('welcome');
});
```
Sirve para cosas triviales. En cuanto hay lógica, va a un controlador.

**Si devuelves un array, Laravel lo convierte en JSON automáticamente** (con `Content-Type: application/json`). En Symfony tendrías que devolver `new JsonResponse($data)` o usar `$this->json($data)`.

### Grupos `web` y `api`

Cada fichero de rutas se carga con un **grupo de middleware** distinto:

| | `routes/web.php` → grupo `web` | `routes/api.php` → grupo `api` |
|---|---|---|
| Para qué | Páginas HTML para el navegador | API JSON para otros programas (app móvil, SPA) |
| Sesión y cookies | ✅ Sí | ❌ No (*stateless*) |
| Protección CSRF | ✅ Sí | ❌ No (no hay sesión que proteger) |
| Prefijo de URL | Ninguno | `/api` automático |
| Autenticación típica | Sesión (login) | Token (Sanctum, fase 6) |

En Symfony, el equivalente son dos **firewalls** en `security.yaml`: uno con `stateless: true` para `^/api` y otro con sesión para el resto.

### Por qué no existe `routes/api.php`

Desde Laravel 11, el esqueleto solo trae `web.php` y `console.php`. Muchas apps no necesitan API, así que se añade cuando hace falta:
- **`php artisan install:api`** crea `routes/api.php`, lo registra e instala **Sanctum** (autenticación por tokens) con su migración.
- **A mano:** se crea el fichero y se registra en `bootstrap/app.php`. Es lo que hacemos aquí, para ver cómo se conecta. Sanctum llegará en la fase 6.

### Rutas actuales (`php artisan route:list`)

La última columna de `route:list` dice **de dónde sale cada ruta**:

| Ruta | Origen |
|---|---|
| `GET /` | `routes/web.php`, línea 5 (la bienvenida). La única escrita en los ficheros de rutas. |
| `GET /up` | `health: '/up'` en `bootstrap/app.php`: Laravel registra la ruta `HealthCheck` por ti. |
| `GET` / `PUT storage/{path}` | `FilesystemServiceProvider`, dentro de `vendor/`: descarga y subida de ficheros del disco local con URLs temporales firmadas. |

**Los paquetes también registran rutas** desde su Service Provider, igual que un bundle de Symfony. Si aparece una ruta desconocida, `route:list` dice de dónde viene.

`GET|HEAD`: al definir un `GET`, también se acepta `HEAD` (la misma petición sin cuerpo).

### Por qué una app móvil usa `api` y no `web`

"Web" no significa "ordenador", significa **navegador**. El navegador del móvil usa las rutas `web` sin problema (así funcionará la PWA). La diferencia es **cómo se identifica el cliente**:

| | Navegador (`web`) | App nativa (`api`) |
|---|---|---|
| Identificación | **Cookie de sesión**, que el navegador guarda y envía solo | **Token** en una cabecera: `Authorization: Bearer ...` |
| CSRF | Necesario: el navegador envía las cookies automáticamente, incluso en peticiones provocadas por otra web | No hace falta: no hay cookies que se envíen solas |
| Respuesta | HTML normalmente | JSON |

Una app nativa no gestiona cookies ni tokens CSRF como un navegador. Un `POST` a una ruta `web` sin token CSRF responde **419**.

---

## Paso 2: Crear `routes/api.php` y registrarlo

**Por qué a mano:** un fichero de rutas solo existe para Laravel si `bootstrap/app.php` lo registra. `php artisan install:api` hace lo mismo y además instala Sanctum (fase 6).

**En Symfony:** una entrada en `config/routes.yaml` que importa un directorio con prefijo:
```yaml
api:
    resource: ../src/Controller/Api/
    type: attribute
    prefix: /api
```

### 2.1. `routes/api.php`
```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return ['status' => 'ok'];
});
```
`Route` es una **fachada** (*facade*): acceso estático a un servicio del contenedor, en este caso el router.

### 2.2. Registro en `bootstrap/app.php`
```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',      // nueva
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```
Esa línea carga el fichero, le aplica el grupo de middleware `api` y el prefijo `/api` (configurable con `apiPrefix:`).

### 2.3. Comprobaciones
```bash
php artisan route:list --path=api        # ✅ GET|HEAD api/ping ... routes/api.php:5
curl.exe -i http://127.0.0.1:8000/api/ping
curl.exe -i http://127.0.0.1:8000/
```
En PowerShell hay que escribir `curl.exe`, porque `curl` es un alias de `Invoke-WebRequest`. `-i` muestra las cabeceras.

| | `/api/ping` | `/` (web) |
|---|---|---|
| `Content-Type` | `application/json` (el array se convierte solo) | `text/html` |
| `Set-Cookie` | ❌ Ninguna | ✅ `laravel-session` y `XSRF-TOKEN` |

Es la teoría del paso 1 en la práctica: `web` tiene estado (sesión y CSRF) y `api` no.

---

## Paso 3: Primer controlador, `DeckController`

**Idea clave:** en Laravel **las rutas no van en el controlador**. El controlador solo tiene métodos; `routes/api.php` dice qué URL llama a qué método. El `{id}` de la ruta llega al parámetro `$id` del método por su nombre.

| | Symfony | Laravel |
|---|---|---|
| Crear | `make:controller` | `php artisan make:controller DeckController` |
| Clase base | `AbstractController` (`$this->json()`, `$this->render()`…) | `Controller`, **vacía**; se usan helpers globales: `response()`, `view()`, `abort()` |
| Ruta | `#[Route]` encima del método | `Route::get('/decks/{id}', [DeckController::class, 'show'])` en `routes/api.php` |
| JSON | `return $this->json($data);` | `return $data;` (`response()->json($data, 201)` si necesitas código o cabeceras) |

### 3.1. Controlador
```php
class DeckController extends Controller
{
    private const DECKS = [
        1 => ['id' => 1, 'name' => 'PHP', 'description' => '...'],
        2 => ['id' => 2, 'name' => 'Symfony', 'description' => '...'],
        3 => ['id' => 3, 'name' => 'Laravel', 'description' => '...'],
    ];

    public function index()
    {
        return array_values(self::DECKS);   // lista JSON [...], no objeto {"1":...}
    }

    public function show(int $id)
    {
        return self::DECKS[$id] ?? ['error' => 'Deck not found'];   // ⚠️ se corrige en el paso 4
    }
}
```

Errores típicos corregidos en la revisión:
- **Constante fuera de la clase:** `const DECKS` a nivel de fichero crea una constante del namespace (`App\Http\Controllers\DECKS`), no de la clase. Va dentro, como `private const`, y se usa con `self::DECKS`.
- **Sin `array_values`:** un array con claves `1, 2, 3` se serializa como objeto `{"1": ..., "2": ...}`, no como lista.
- **Parámetros sin tipo:** los parámetros de ruta llegan siempre como **string**. Con `int $id`, PHP convierte `"2"` en `2`.

### 3.2. Rutas
```php
use App\Http\Controllers\DeckController;

Route::get('/decks', [DeckController::class, 'index'])->name('decks.index');
Route::get('/decks/{id}', [DeckController::class, 'show'])->name('decks.show');
```

### 3.3. Comprobaciones
```bash
php artisan route:list --path=api   # ✅ decks.index › DeckController@index, decks.show › DeckController@show
curl.exe http://127.0.0.1:8000/api/decks     # ✅ lista: empieza por [
curl.exe http://127.0.0.1:8000/api/decks/2   # ✅ objeto: empieza por {
```

### 3.4. Los dos problemas que quedan para el paso 4

| Petición | Código obtenido | Qué pasa | Código correcto |
|---|---|---|---|
| `/api/decks/99` | **200** con `{"error":"Deck not found"}` | El cliente mira primero el código de estado: con 200 cree que ha ido bien | **404** |
| `/api/decks/abc` | **500** `TypeError: must be of type int, string given` | `"abc"` no se puede convertir a `int`; es un error del cliente, no del servidor | **404** (la ruta no debería ni coincidir) |

---

## Paso 4: Restricciones de ruta y errores 404

**Por qué importa el código de estado:** es lo primero que lee cualquier cliente (app móvil, `fetch`, cachés, monitores). Un 200 que dice "error" engaña al cliente; un 500 por un id mal escrito dice "ha fallado el servidor" cuando el error es del cliente, y dispara alertas.

### 4.1. Dos herramientas para dos problemas

| Problema | Herramienta | Symfony | Laravel |
|---|---|---|---|
| El formato del parámetro no es válido (`abc`) | **Restricción en la ruta**: si no coincide, 404 sin llegar al controlador | `requirements: ['id' => '\d+']` | `->where('id', '[0-9]+')` o `->whereNumber('id')` |
| El formato es válido pero el recurso no existe (`99`) | **Cortar desde el controlador** | `throw $this->createNotFoundException('...')` | `abort(404, '...')`, `abort_if(...)`, `abort_unless(...)` |

Otras restricciones: `whereAlpha`, `whereUuid`, `whereIn`. Global para todos los `{id}`: `Route::pattern('id', '[0-9]+')` en `AppServiceProvider::boot()`.

### 4.2. Código final
```php
// routes/api.php
Route::get('/decks/{id}', [DeckController::class, 'show'])
    ->name('decks.show')
    ->where('id', '[0-9]+');

// DeckController
public function show(int $id)
{
    if (!array_key_exists($id, self::DECKS)) {
        abort(404, 'Deck not found');
    }
    return self::DECKS[$id];
}
```

### 4.3. Lo aprendido por el camino (los intentos intermedios)

1. **Quitar el `??` sin poner nada → 500 `Undefined array key 99`.** En PHP normal es un *warning* y devuelve `null`; **Laravel convierte warnings y notices en excepciones**, así que un descuido falla de forma ruidosa en vez de devolver `null` en silencio (Symfony hace lo mismo con su `ErrorHandler` en debug).
2. **`return response()->json(['error' => ...], 404)` → funciona, pero dos formatos de error en la misma API** (`{"error"}` en el controlador y `{"message"}` en el router). Una API debe tener **un único formato de error**.
3. **`abort(404, ...)` → `{"message": "Deck not found"}`.** El error pasa por el **manejador de excepciones** (`withExceptions` en `bootstrap/app.php`), que formatea igual todos los errores: rutas, validación, autorización… Para cambiar el formato, un solo sitio. Regla: **los errores se lanzan, no se devuelven montados a mano** (igual que `createNotFoundException` + `ExceptionListener` en Symfony).
4. **Guard clause:** si el `if` sale con `return` o `abort`, sobra el `else`. El caso malo arriba, el camino feliz sin indentar.

### 4.4. Comprobaciones
```bash
curl.exe -i http://127.0.0.1:8000/api/decks/2     # ✅ 200, el mazo
curl.exe -i http://127.0.0.1:8000/api/decks/99    # ✅ 404 {"message": "Deck not found"}
curl.exe -i http://127.0.0.1:8000/api/decks/abc   # ✅ 404 {"message": "The route api/decks/abc could not be found."}
```

| Petición | Dónde se corta | Mensaje |
|---|---|---|
| `abc` | En el **router**: ninguna ruta coincide | Genérico de Laravel |
| `99` | En el **controlador**: la ruta coincide, el mazo no existe | El tuyo, con `abort()` |

El mensaje dice **en qué capa** se cortó la petición: útil para depurar.

---

## Paso 5: Recurso anidado, `CardController`

Rutas: `GET /api/decks/{deck}/cards` (fichas de un mazo) y `GET /api/decks/{deck}/cards/{card}` (una ficha **de ese mazo**). Una ficha siempre pertenece a un mazo, y la URL anidada lo expresa.

### 5.1. Cómo se accede a un array anidado

Los datos están agrupados por mazo, así que hay **dos niveles de claves**:

```
self::CARDS                    → todos los mazos con sus fichas
self::CARDS[$deck]             → las fichas de UN mazo     (nivel 1: clave = id del mazo)
self::CARDS[$deck][$card]      → UNA ficha de ese mazo     (nivel 2: clave = id de la ficha)
```

```
CARDS = [
    1 => [                       ← self::CARDS[1]      (mazo PHP)
        1 => ['id' => 1, ...],   ← self::CARDS[1][1]
        2 => ['id' => 2, ...],   ← self::CARDS[1][2]
    ],
    2 => [ ... ],                ← self::CARDS[2]      (mazo Symfony)
]
```

Errores típicos al empezar:

| Código | Qué devuelve | Por qué está mal |
|---|---|---|
| `array_values(self::CARDS)` en `index` | Las fichas de **todos** los mazos, como lista de listas | Falta bajar al nivel del mazo: `self::CARDS[$deck]` |
| `self::CARDS[$card]` en `show` | Las fichas del **mazo** cuyo id coincide con `$card` | Se salta un nivel: hay que pasar primero por `$deck` |

Cada nivel se comprueba **antes** de bajar al siguiente: primero que exista el mazo, después que la ficha exista **dentro de ese mazo**. Así `/decks/1/cards/5` da 404 aunque exista una ficha 5 en otro mazo.

### 5.2. Controlador
```php
class CardController extends Controller
{
    private const CARDS = [
        1 => [
            1 => ['id' => 1, 'deck_id' => 1, 'term' => '...', 'definition' => '...'],
            2 => ['id' => 2, 'deck_id' => 1, 'term' => '...', 'definition' => '...'],
        ],
        // ...
    ];

    public function index(int $deck): array
    {
        abort_unless(array_key_exists($deck, self::CARDS), 404, 'Deck not found');

        return array_values(self::CARDS[$deck]);          // nivel 1, como lista
    }

    public function show(int $deck, int $card): array
    {
        abort_unless(array_key_exists($deck, self::CARDS), 404, 'Deck not found');
        abort_unless(array_key_exists($card, self::CARDS[$deck]), 404, 'Card not found');

        return self::CARDS[$deck][$card];                  // nivel 2
    }
}
```

- `abort_unless(condición, 404, 'mensaje')`: "corta con 404 **salvo que** se cumpla la condición". Es el guard clause del paso 4 en una línea.
- La clave exterior (`1 =>`) y el `deck_id` de cada ficha deben coincidir, y con los ids de `DeckController` (1 PHP, 2 Symfony, 3 Laravel).
- La comprobación de "existe el mazo" está duplicada en los dos métodos y los mazos viven en otro controlador. Es deliberado: desaparece en la fase 2 con Eloquent y el **Route Model Binding** (`show(Deck $deck, Card $card)`), que hace el 404 solo.

### 5.3. Rutas agrupadas con prefijo
```php
Route::prefix('decks/{deck}/cards')
    ->whereNumber(['deck', 'card'])
    ->group(function () {
        Route::get('/', [CardController::class, 'index'])->name('cards.index');
        Route::get('/{card}', [CardController::class, 'show'])->name('cards.show');
    });
```

| Symfony | Laravel |
|---|---|
| `#[Route('/decks/{deck}/cards')]` encima de la clase | `Route::prefix('decks/{deck}/cards')->group(...)` |
| `requirements` en cada ruta | `->whereNumber([...])` en el grupo: se aplica a todas sus rutas |

- Dentro del grupo, las URLs son **relativas al prefijo** (`'/'`, `'/{card}'`).
- Los parámetros llegan al método **por nombre** (`{deck}` → `$deck`, `{card}` → `$card`).
- Se usa `{deck}` y no `{id}`: con dos parámetros `{id}` sería ambiguo, y en la fase 2 el nombre es el que activa el Route Model Binding.
- Escribir las dos rutas sueltas, cada una con su `where`, también funciona; el grupo evita repetir el prefijo y las restricciones.

**`APP_DEBUG`:** con `true` (desarrollo), el JSON de error trae además `exception`, `file`, `line` y `trace`. Con `false` (producción) solo trae `message`; y en los 500, solo `"Server Error"`, para no filtrar detalles internos.
