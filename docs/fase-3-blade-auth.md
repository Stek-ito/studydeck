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
2. [ ] Blade: layout, componentes y la página de inicio
3. [ ] Registro: Form Request, `Hash::make` y `Auth::login`
4. [ ] Login y logout: `Auth::attempt`, `session()->regenerate()`, middleware `auth` y `guest`
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
