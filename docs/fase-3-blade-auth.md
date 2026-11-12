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
