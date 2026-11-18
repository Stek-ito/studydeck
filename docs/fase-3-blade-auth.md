# Fase 3: Blade, validación y autenticación

**Objetivo:** que StudyDeck tenga pantallas de verdad y usuarios: registro, login, protección de rutas, cada usuario solo con sus mazos, y la pantalla de estudio con repetición espaciada.

La autenticación se aprende por **dos caminos**:

1. **A mano, en StudyDeck** (pasos 1-11). Se construye cada pieza para entender qué hay debajo.
2. **Con el starter kit oficial** (paso 12), en un proyecto aparte. No se escribe código: se lee y se compara con lo hecho a mano.

El orden importa: después de hacerlo a mano, cada fichero del kit se reconoce ("esto es mi `Auth::attempt`").

Si algo falla, mira [troubleshooting.md](troubleshooting.md). Un commit por paso terminado.

## Pasos

### Camino 1: a mano, en StudyDeck

1. [ ] Teoría: cómo autentica Laravel (guards, providers, sesión) frente al `security.yaml` de Symfony
2. [x] Blade: layout, componentes y la página de inicio
3. [x] Registro: Form Request, `Hash::make` y `Auth::login`
4. [x] Login y logout: `Auth::attempt`, `session()->regenerate()`, middleware `auth` y `guest`
5. [ ] Rate limiting del login: `RateLimiter`, 5 intentos por email e IP
6. [ ] Verificación de email: `MustVerifyEmail`, URLs firmadas, middleware `verified` (correo con el driver `log`)
7. [ ] Recuperación de contraseña: tokens de un solo uso y `Password` broker
8. [ ] 2FA con TOTP: secreto, código QR, códigos de recuperación y el paso de "challenge" tras el login
9. [ ] `DeckPolicy`: cada usuario solo ve y edita sus mazos
10. [ ] Pantalla de estudio: `dueToday()`, `LeitnerScheduler` y "Lo sé" / "Repasar"
11. [ ] Subida de PDFs: `StoreDocumentRequest` (`mimes:pdf`, tamaño y páginas máximas)

### Camino 2: el starter kit oficial

12. [ ] Proyecto `C:\Roberto\StarterKitLab` con el kit de Livewire (usa Fortify): leer el código y documentar la tabla "mi código ↔ kit" en `fase-3b-starter-kit.md`

---

## Equivalencias con Symfony

| Laravel | Symfony |
|---|---|
| `config/auth.php`: guards y providers | `security.yaml`: firewalls y providers |
| guard `web` (sesión) | firewall con `form_login` |
| `Auth::attempt($credentials)` | el `Authenticator` de `form_login` |
| middleware `auth` / `guest` | `access_control` / `#[IsGranted('IS_AUTHENTICATED')]` |
| `Hash::make()` / `Hash::check()` | `UserPasswordHasherInterface` |
| Form Request | Form + constraints de Validator |
| Policy | Voter |
| `RateLimiter` / middleware `throttle` | `login_throttling` del firewall (RateLimiter component) |
| URLs firmadas (`URL::signedRoute`) | `UriSigner` / `symfonycasts/verify-email-bundle` |
| `Password` broker (reset) | `symfonycasts/reset-password-bundle` |
| Fortify / starter kit | `make:security:form-login` + `make:registration-form` (MakerBundle) |

## OWASP Top 10:2025 en esta fase

Edición vigente comprobada el 2026-11-18 en [top10.owasp.org](https://top10.owasp.org/2025/0x00_2025-Introduction). Antes de citarla, revisa si hay una más reciente.

| Categoría | Dónde aparece en StudyDeck | Mitigación |
|---|---|---|
| **A01 Broken Access Control** | "Mis mazos" visible sin login y con mazos ajenos; IDOR en rutas anidadas | middleware `auth` (paso 4), consultas por usuario + `DeckPolicy` (paso 9), `scopeBindings()` (fase 2) |
| **A02 Security Misconfiguration** | `APP_DEBUG=true` muestra trazas y rutas de ficheros (se vio en los 404/500 de la fase 2) | `APP_DEBUG=false` en producción |
| **A04 Cryptographic Failures** | contraseñas | bcrypt con el cast `hashed`; nunca devolver la contraseña (`old()`, `#[Hidden]`) |
| **A05 Injection** | XSS en vistas, SQL | `{{ }}` escapa; Eloquent/Query Builder usan bindings; `{!! !!}` solo con HTML de confianza |
| **A07 Authentication Failures** | login, registro, sesión, enumeración de usuarios | mensaje de error genérico (sin regla `exists:users`), `session()->regenerate()`, `invalidate()` al salir, política de contraseñas, rate limiting (paso 5), 2FA (paso 8) |
| **A09 Security Logging & Alerting Failures** | intentos de login fallidos | registrar los bloqueos por rate limiting (paso 5) |
| **A10 Mishandling of Exceptional Conditions** | errores 500 por `TypeError` (fase 2) | tipos correctos, formato de error único, sin filtrar detalles internos |

## Qué se hace a mano y qué hace el kit

| Funcionalidad | A mano (paso) | Qué aporta Laravel por debajo | Dificultad |
|---|---|---|---|
| Registro / login / logout | 3-4 | guard de sesión, `Hash`, middleware | baja |
| Rate limiting | 5 | `RateLimiter` (contadores en caché) | baja |
| Verificación de email | 6 | `MustVerifyEmail`, notificación, URLs firmadas | media |
| Recuperación de contraseña | 7 | `Password` broker y tabla `password_reset_tokens` (ya creada en la migración de users) | media |
| 2FA (TOTP) | 8 | **nada**: se usa una librería para el algoritmo TOTP (o se implementa el RFC 6238, unas 20 líneas) | alta |

El correo, al principio, con `MAIL_MAILER=log`: los mensajes se escriben en `storage/logs/laravel.log` y el enlace se copia de ahí. Más adelante, en la pista Docker, con **Mailpit** (bandeja de correo local con interfaz web).

---

## Paso 1: Cómo autentica Laravel

### 1.1. Tres piezas (`config/auth.php`)

| Pieza | Pregunta | En StudyDeck | Symfony |
|---|---|---|---|
| **Guard** | ¿cómo sé quién eres en cada petición? | `web` con driver `session` | firewall con `form_login` |
| **Provider** | ¿de dónde saco al usuario? | `eloquent`, modelo `User` | `providers: entity` |
| **Session store** | ¿dónde se guarda la sesión? | `SESSION_DRIVER=database`, tabla `sessions` | `framework.session.handler_id` |

Puede haber varios guards: `web` (sesión) para las pantallas y `sanctum` (token) para la API.

### 1.2. El login y las peticiones siguientes

```
Login:     Auth::attempt()
             1. provider: SELECT * FROM users WHERE email = ?
             2. Hash::check(password, hash)
             3. guard: guarda en la sesión SOLO el id → 'login_web_<hash>' => 1
           session()->regenerate()  → id de sesión nuevo
           cookie studydeck-session=<id>

Después:   cookie → StartSession carga la sesión de la tabla sessions
           → middleware auth: ¿hay 'login_web_…'? no → /login
           → provider: SELECT * FROM users WHERE id = 1
           → $request->user()
```

Symfony serializa el token (con el usuario) en la sesión. Laravel guarda solo el id y recarga el usuario en cada petición.

### 1.3. Grupos `web` y `api`

- `web`: cookies cifradas, `StartSession` y CSRF. Aquí van las pantallas y el login.
- `api`: stateless, sin sesión. Las peticiones a `/api/...` no crean filas en `sessions`.
- Un formulario `POST` sin `@csrf` → **419 Page Expired**.

### 1.4. Preguntas de repaso (y errores típicos)

1. **Usuarios en LDAP en vez de MySQL: ¿guard o provider?** → El **provider**, que es de dónde salen los usuarios. El guard (sesión) no cambia.
2. **¿Qué se guarda en la sesión tras el login?** → ❌ Error típico: "un token", que es como lo hace Symfony. En Laravel, **solo el id del usuario**, bajo la clave `login_web_<hash>`. No confundir con `remember_token` de la tabla `users`, que es para la cookie de "recuérdame".
3. **419 al enviar un formulario** → Falta `@csrf` en el formulario. El token sí existe en la sesión; lo que falta es que el formulario lo **envíe** (campo oculto `_token`).
4. **¿Para qué sirve `session()->regenerate()` tras el login?** → Contra **session fixation**:
   - El atacante consigue que la víctima use un id de sesión que él conoce, por ejemplo con un enlace o una cookie plantada.
   - La víctima hace login.
   - Si el id no cambiara, el atacante usaría ese mismo id y entraría con la sesión ya autenticada.
   - `regenerate()` conserva los datos de la sesión, pero le da un **id nuevo**, y el id antiguo deja de servir.
   - En Symfony lo hace automáticamente la `session_fixation_strategy: migrate`.

**Experimento:** `DB::table('sessions')->count()` da 0. Tras abrir `http://127.0.0.1:8000/` da 1: la fila tiene la IP y `user_id = null` (un *guest* con sesión). Las peticiones a `/api` no crean sesiones.

---

## Paso 2: Blade, layout y componentes

### 2.1. Blade frente a Twig

| Blade | Twig | Nota |
|---|---|---|
| `{{ $deck->name }}` | `{{ deck.name }}` | **escapa** el HTML (XSS) |
| `{!! $html !!}` | `{{ html\|raw }}` | sin escapar: solo para HTML de confianza |
| `@if ... @endif` | `{% if %}...{% endif %}` | |
| `@foreach ($decks as $deck)` | `{% for deck in decks %}` | |
| `@forelse ... @empty ... @endforelse` | `{% for %}...{% else %}...{% endfor %}` | `@empty` sale si la lista está vacía |
| `{{ route('home') }}` | `{{ path('home') }}` | |
| `@csrf` | `{{ csrf_token('...') }}` | |

Blade **es PHP**: dentro de `{{ }}` se escribe PHP normal. Se compila a PHP plano en `storage/framework/views/`.

### 2.2. Herencia frente a componentes

| | Herencia | Componentes (los que usamos) |
|---|---|---|
| Layout | `@yield('content')` | `{{ $slot }}` |
| Página | `@extends('layout')` + `@section` | `<x-layout> ... </x-layout>` |
| Twig | `{% extends %}` + `{% block %}` | `{% embed %}` / Twig Components |

- El fichero `resources/views/components/deck-card.blade.php` se usa como `<x-deck-card>`.
- **`@props([...])`** declara los atributos que recibe, como los parámetros de una función. Con valor por defecto: `@props(['title' => 'StudyDeck'])`. Sin él: `@props(['deck'])`.
- **`{{ $slot }}`** es lo que se escribe entre `<x-layout>` y `</x-layout>`.
- **`deck="$deck"`** pasa el **texto** `"$deck"`; **`:deck="$deck"`** pasa la **variable** PHP.

Un componente es como **una función que devuelve HTML**:

| Función PHP | Componente Blade |
|---|---|
| nombre `deckCard` | fichero `deck-card.blade.php` → `<x-deck-card>` |
| parámetro `Deck $deck` | `@props(['deck'])` |
| `return '<div>...'` | el HTML debajo del `@props` |
| llamada `deckCard($deck)` | `<x-deck-card :deck="$deck" />` |

### 2.3. La regla: un solo esqueleto, el resto son trozos

```
layout.blade.php                ← la ÚNICA con <html>, <head>, <body>, <nav>
└── {{ $slot }}
    └── decks/index.blade.php   ← la página "Mis mazos"
        └── @forelse
            ├── <x-deck-card>   ← solo la caja de UN mazo
            ├── <x-deck-card>
            └── <x-deck-card>
```

| Fichero | Contiene |
|---|---|
| `layout.blade.php` | el esqueleto de la página (`<html>`… `{{ $slot }}` …`</html>`). **Solo uno** en todo el proyecto |
| cualquier otro componente o página | solo su trozo de HTML, **sin** `<html>`, `<head>` ni `<body>` |

❌ **Error típico:** crear un componente copiando el layout y metiendo dentro la caja.
- Cada mazo pintaría una página HTML entera dentro de otra: tres `<html>` y tres menús dentro del `<main>`.
- Además falla al cargar: el layout copiado usa `$title`, que el componente no declara en `@props` → `Undefined variable $title`.
- En Twig sería como copiar `base.html.twig` entero en un `_deck_card.html.twig` que se incluye en un bucle.

✅ **El componente correcto** (`resources/views/components/deck-card.blade.php`):

```blade
@props(['deck'])

<div class="rounded-lg border bg-white p-4">
    <h2 class="font-bold">{{ $deck->name }}</h2>
    <p class="text-gray-600">{{ $deck->description }}</p>
    <p class="text-sm">{{ $deck->cards_count }} fichas</p>
</div>
```

`cards_count` no es una columna: la añade `withCount('cards')` en la consulta.

### 2.4. El layout y la página de inicio

```blade
{{-- resources/views/components/layout.blade.php --}}
@props(['title' => 'StudyDeck'])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · StudyDeck</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
    <nav class="border-b bg-white">
        <div class="mx-auto flex max-w-4xl gap-6 px-4 py-3">
            <a href="{{ route('home') }}" class="font-bold">StudyDeck</a>
            <a href="{{ route('decks.index') }}">Mis mazos</a>
        </div>
    </nav>
    <main class="mx-auto max-w-4xl px-4 py-8">
        {{ $slot }}
    </main>
</body>
</html>

{{-- resources/views/home.blade.php --}}
<x-layout title="Inicio">
    <h1 class="text-2xl font-bold">Bienvenido a StudyDeck</h1>
    <p class="mt-2 text-gray-600">Convierte tus PDFs en fichas y repásalas cada día.</p>
</x-layout>
```

### 2.5. Vite y Tailwind

- Laravel trae **Tailwind** compilado con **Vite** (≈ Webpack Encore / AssetMapper). `@vite([...])` inserta los `<link>` y `<script>`.
- Para trabajar: `npm install` una vez, y luego dos terminales: `php artisan serve` y `npm run dev`.
- Mientras `npm run dev` está en marcha existe el fichero `public/hot`: así sabe `@vite` que tiene que pedir los ficheros al servidor de Vite.

### 2.6. La página "Mis mazos" y sus rutas

```blade
{{-- resources/views/decks/index.blade.php --}}
<x-layout title="Mis mazos">
    <h1 class="text-2xl font-bold">Mis mazos</h1>
    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        @forelse ($decks as $deck)
            <x-deck-card :deck="$deck" />
        @empty
            <p class="text-gray-600">No tienes mazos creados.</p>
        @endforelse
    </div>
</x-layout>
```

```php
// routes/web.php
Route::view('/', 'home')->name('home');

Route::get('/decks', function () {
    return view('decks.index', ['decks' => Deck::withCount('cards')->get()]);   // provisional: paso 9
})->name('decks.index');
```

- `view('decks.index', ['decks' => ...])` → fichero `decks/index.blade.php`, variable `$decks`. Equivale a `$this->render('decks/index.html.twig', [...])`.
- `<x-deck-card ... />` se cierra solo porque no lleva contenido. `<x-layout>` tiene apertura y cierre porque lleva el `$slot`.
- **Comentarios:** `<!-- -->` llega al navegador; `{{-- --}}` lo elimina Blade (≈ `{# #}` de Twig).
- **Los nombres de ruta son globales** (web y api comparten espacio). La API pasó a `api.decks.index`, `api.decks.show`, `api.cards.index` y `api.cards.show` para no chocar con `decks.index` de la web.

### 2.7. Resultado

- `/` muestra el inicio con el menú y Tailwind; "Mis mazos" muestra 3 cajas con "10 fichas".
- **Escapado:** con `Deck::find(1)->update(['name' => '<b>PHP</b>'])`, la caja muestra el texto `<b>PHP</b>` literal. En el código fuente aparece `&lt;b&gt;PHP&lt;/b&gt;`: `{{ }}` protege de XSS.

---

## Paso 3: Registro

Tres piezas, más las rutas:

| Pieza | Fichero | Symfony |
|---|---|---|
| A. Validación | `app/Http/Requests/RegisterRequest.php` | DTO + `#[MapRequestPayload]` + `#[Assert]` / FormType |
| B. Controlador | `app/Http/Controllers/Auth/RegisterController.php` | controlador con `handleRequest()` |
| C. Vista | `resources/views/auth/register.blade.php` | plantilla con `form_start` / `form_row` |
| D. Rutas | `routes/web.php` | `#[Route]` |

### 3.1. Form Request

`php artisan make:request RegisterRequest`. Laravel valida **antes** de entrar al método del controlador. Si falla, redirige al formulario con los errores y lo que se había escrito (`old()`). Si la petición es JSON, responde 422.

```php
use Illuminate\Validation\Rules\Password;   // ⚠️ no Illuminate\Support\Facades\Password

public function authorize(): bool
{
    return true;   // por defecto false → 403 siempre
}

public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'string', 'confirmed', Password::defaults()],
    ];
}
```

| Regla | Significa | Symfony |
|---|---|---|
| `unique:users,email` | no existe una fila en `users` con ese `email`. Si se omite la columna, se usa el nombre del campo | `#[UniqueEntity('email')]` |
| `confirmed` | debe existir `password_confirmation` con el mismo valor | `RepeatedType` |
| `lowercase` | **rechaza** el valor si tiene mayúsculas, no lo convierte | — |
| `Password::defaults()` | política de contraseñas (mínimo 8). Es un objeto, por eso las reglas van en array | `#[Assert\PasswordStrength]` |

❌ **Errores corregidos:**
- `use Illuminate\Support\Facades\Password`. Es el **broker de recuperación de contraseña** (paso 7), no la regla, y daría `PasswordBrokerManager::defaults does not exist`. Regla práctica: `Facades\` son atajos a servicios (`Auth::`, `DB::`); `Validation\Rules\` son reglas.
- `'min:8'` además de `Password::defaults()`: dice lo mismo dos veces.

### 3.2. Controlador

Convención de nombres: `create()` muestra el formulario y `store()` lo guarda.

```php
public function create(): View
{
    return view('auth.register');
}

public function store(RegisterRequest $request): RedirectResponse
{
    $user = User::create($request->validated());   // solo name, email y password
    Auth::login($user);                              // guarda el id en la sesión
    $request->session()->regenerate();               // contra session fixation

    return redirect()->route('decks.index');         // ≈ redirectToRoute()
}
```

- `validated()` no incluye `password_confirmation`, porque no tiene reglas propias.
- **No hace falta `Hash::make()`:** el cast `'password' => 'hashed'` de `User` hashea al asignar. En la base de datos queda `$2y$12$...` (bcrypt).
- `Auth::login($user)` ≈ `$security->login($user)` en Symfony.

### 3.3. Vista: formularios en Blade

| Herramienta | Qué hace | Symfony |
|---|---|---|
| `@csrf` | campo oculto `_token` (sin él, 419) | `form_start()` |
| `old('email')` | el valor escrito antes del error | el form conserva los datos |
| `@error('email') {{ $message }} @enderror` | el error de ese campo | `form_errors(form.email)` |

```blade
<form method="POST" action="{{ route('register') }}">
    @csrf
    <div>
        <label for="name">Nombre</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}">
        @error('name') <p class="text-red-600">{{ $message }}</p> @enderror
    </div>
    {{-- email: igual; password: sin old(); password_confirmation: sin old() ni @error --}}
</form>
```

- El atributo **`name`** es la clave que llega al servidor y la que tiene que coincidir con `rules()`.
- Las contraseñas **nunca** llevan `old()`: no se devuelven al navegador.
- El error de `confirmed` aparece en `password`, no en `password_confirmation`.

### 3.4. Rutas y el middleware `guest`

```php
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});
```

- Misma URL, distinto método: el POST no necesita nombre, porque `route('register')` da la URL.
- `guest`: si ya hay sesión iniciada, redirige a la ruta `home`. Es lo contrario de `auth`.
- En el layout: `@guest ... @endguest` / `@auth ... @endauth` (≈ `is_granted('IS_AUTHENTICATED')`), y `auth()->user()->name` (≈ `app.user`).

### 3.5. Resultado

- Formulario vacío → errores "required". Email existente → "has already been taken". Contraseñas distintas → "confirmation does not match". Nombre y email se conservan; las contraseñas no.
- Registro correcto → redirige a "Mis mazos" con el nombre en el menú.
- Con sesión iniciada, `/register` redirige al inicio.
- En la base de datos: usuario 2 con la contraseña `$2y$12$...` y una fila en `sessions` con `user_id = 2`.

⚠️ **Problemas detectados** (se resuelven en los pasos 4 y 9):
- "Mis mazos" es accesible **sin** sesión iniciada.
- Además, muestra los mazos de **todos** los usuarios: el usuario 2 ve los 3 mazos del usuario 1.

---

## Paso 4: Login, logout y proteger rutas

### 4.1. Teoría

| | Registro | Login |
|---|---|---|
| ¿Tienes el `User`? | sí, lo acabas de crear | no, solo las credenciales |
| Método | `Auth::login($user)` | `Auth::attempt($credentials, $remember)`: busca, comprueba el hash y hace `login` |

- **Mensaje genérico** cuando falla: el mismo si el email no existe que si la contraseña es incorrecta. Evita la enumeración de usuarios (**A07:2025**). Por eso tampoco se usa la regla `exists:users,email`.
- **Logout**: `Auth::logout()` + `session()->invalidate()` (borra los datos y da un id nuevo) + `session()->regenerateToken()` (nuevo token CSRF).
- **Middleware `auth`**: sin sesión, redirige a la ruta llamada **`login`** (el nombre es obligatorio) y guarda la URL pedida. `redirect()->intended()` vuelve a ella tras el login (≈ `target_path` de Symfony).

### 4.2. `LoginRequest`

```php
'email' => ['required', 'string', 'email'],
'password' => ['required', 'string'],
```

Sin `unique` (el email debe existir), sin `Password::defaults()` (se comprueba, no se elige) y sin `exists` (enumeración). `remember` no lleva regla: se lee con `$request->boolean('remember')`.

### 4.3. `LoginController`

```php
public function store(LoginRequest $request): RedirectResponse
{
    if (! Auth::attempt($request->validated(), $request->boolean('remember'))) {
        throw ValidationException::withMessages(['email' => __('auth.failed')]);
    }

    $request->session()->regenerate();

    return redirect()->intended(route('decks.index'));
}

public function destroy(Request $request): RedirectResponse
{
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('home');
}
```

- `ValidationException::withMessages()` se comporta como una regla fallida: redirige al formulario con el error y `old()`.
- `__('auth.failed')` → *"These credentials do not match our records."*

| Línea | Protege de |
|---|---|
| `regenerate()` en el login | session fixation |
| `invalidate()` en el logout | reutilizar los datos de la sesión anterior |
| `regenerateToken()` | reutilizar el token CSRF antiguo |

### 4.4. Vista, "Recuérdame" y rutas

- La vista es la del registro sin `name` ni `password_confirmation`, más la casilla `remember`.
- **Una casilla sin marcar no se envía.** `boolean('remember')` devuelve `false` en ese caso.
- Con "Recuérdame", Laravel guarda un valor aleatorio de 60 caracteres en `users.remember_token` y envía una cookie de larga duración (≈ `remember_me` de Symfony).

```php
Route::middleware('guest')->group(function () {
    // register (GET/POST)
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/decks', ...)->name('decks.index');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
```

- Cada grupo equivale a una línea de `access_control`, pero junto a las rutas.
- **Logout por `POST` con `@csrf`, nunca un enlace `GET`:** si no, un `<img src="/logout">` en otra web cerraría tu sesión. Es CSRF (**A01:2025**). En el layout, "Salir" es un formulario con un botón.
- El menú muestra "Mis mazos", el nombre y "Salir" dentro de `@auth`, y "Entrar" y "Crear cuenta" dentro de `@guest`.

### 4.5. Resultado

1. `/decks` sin sesión → redirige a `/login`.
2. Contraseña incorrecta y email inexistente → el mismo mensaje genérico.
3. Login correcto → vuelve a `/decks` (`intended`).
4. El menú cambia según `@auth` / `@guest`.
5. "Salir" → inicio, y `/decks` vuelve a pedir el login.
6. Con "Recuérdame", `remember_token` tiene 60 caracteres aleatorios.

✅ Resuelto el primer problema del paso 3 (acceso sin login). ⚠️ Sigue pendiente el segundo (mazos de otros usuarios): paso 9.
