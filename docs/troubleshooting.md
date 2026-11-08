# Problemas resueltos (troubleshooting)

Problemas reales que han salido montando el entorno, con la causa y la solución. Están escritos para poder reutilizarlos en cualquier proyecto con WAMP, MySQL, PHP o Laravel.

Formato de cada problema: **síntoma → causa → cómo diagnosticarlo → solución → por qué esta solución y no otra**.

Índice:
1. [MySQL: "Specified key was too long; max key length is 1000 bytes" (MyISAM)](#1-mysql-specified-key-was-too-long-max-key-length-is-1000-bytes)
2. [MySQL: "Index column size too large. The maximum column size is 767 bytes" (COMPACT)](#2-mysql-index-column-size-too-large-the-maximum-column-size-is-767-bytes)
3. [Migración fallida a medias: "table already exists"](#3-migración-fallida-a-medias-table-already-exists)
4. [`php artisan db:show` muestra tablas de otros proyectos](#4-php-artisan-dbshow-muestra-tablas-de-otros-proyectos)
5. [Necesito otra versión de PHP sin romper los otros proyectos](#5-necesito-otra-versión-de-php-sin-romper-los-otros-proyectos)
6. [Composer lanza avisos "Deprecated" con PHP nuevo](#6-composer-lanza-avisos-deprecated-con-php-nuevo)
7. [`composer create-project` crea el proyecto en una subcarpeta](#7-composer-create-project-crea-el-proyecto-en-una-subcarpeta)
8. [`git push`: "Permission denied to OtraCuenta" (403) con dos cuentas de GitHub](#8-git-push-permission-denied-to-otracuenta-403-con-dos-cuentas-de-github)

---

## 1. MySQL: "Specified key was too long; max key length is 1000 bytes"

**Síntoma** (al lanzar `php artisan migrate`):
```
SQLSTATE[42000]: Syntax error or access violation: 1071 Specified key was too long;
max key length is 1000 bytes (SQL: alter table `users` add unique `users_email_unique`(`email`))
```

**Causa:** las tablas se están creando con el motor **MyISAM**. El `my.ini` del MySQL de WAMP trae `default_storage_engine=MYISAM` (en `C:\wamp64\bin\mysql\mysql8.0.27\my.ini`, línea 64).

- MyISAM solo admite índices de hasta **1000 bytes**.
- Una columna `VARCHAR(255)` en `utf8mb4` puede ocupar 255 × 4 = **1020 bytes**, porque cada carácter usa hasta 4 bytes. No cabe.

| | MyISAM | InnoDB |
|---|---|---|
| Transacciones | ❌ | ✅ |
| Claves foráneas | ❌ | ✅ |
| Bloqueos | Tabla entera | Por fila |
| Índice máximo | 1000 bytes | 3072 bytes (con `ROW_FORMAT=DYNAMIC`) |
| Recuperación tras un fallo | Puede corromperse | Sí (log de transacciones) |

MyISAM es un motor antiguo. Cualquier aplicación moderna necesita InnoDB por las transacciones y las claves foráneas.

**Cómo diagnosticarlo:**
```sql
SHOW VARIABLES LIKE 'default_storage_engine';
SELECT table_name, engine FROM information_schema.tables WHERE table_schema = 'mi_base_de_datos';
```

**Solución (en el proyecto, no en el servidor).** En `config/database.php`, dentro de la conexión `'mysql'`:
```php
'engine' => 'InnoDB ROW_FORMAT=DYNAMIC',   // antes: 'engine' => null
```
Con `null`, Laravel no indica motor y MySQL usa el que tenga por defecto. Con un valor, Laravel añade `engine = ...` a cada `CREATE TABLE`. El `ROW_FORMAT=DYNAMIC` es para el problema 2, que aparece justo después.

Después, limpiar lo que quedó a medias (ver problema 3) y volver a migrar.

**Por qué no cambiar el `my.ini`:** cambiaría el motor por defecto para **todos** los proyectos de ese MySQL (ToysTNT incluido). Además, el proyecto seguiría dependiendo de cómo esté configurada cada máquina. Declararlo en la configuración del proyecto hace que funcione igual en cualquier servidor.

**En Symfony/Doctrine:** pasa exactamente lo mismo. Se arregla en `config/packages/doctrine.yaml`:
```yaml
doctrine:
    dbal:
        default_table_options:
            engine: InnoDB
            row_format: DYNAMIC
```

---

## 2. MySQL: "Index column size too large. The maximum column size is 767 bytes"

**Síntoma** (aparece después de arreglar el problema 1 con `'engine' => 'InnoDB'`):
```
SQLSTATE[HY000]: General error: 1709 Index column size too large.
The maximum column size is 767 bytes.
```

**Causa:** la tabla ya es InnoDB, pero con el **formato de fila `COMPACT`**. El `my.ini` de WAMP trae `innodb_default_row_format=compact` (línea 115).

| Formato de fila | Índice máximo por columna |
|---|---|
| `REDUNDANT`, `COMPACT` (antiguos) | 767 bytes |
| `DYNAMIC` (por defecto desde MySQL 5.7) | 3072 bytes |

**Cómo diagnosticarlo:**
```sql
SHOW VARIABLES LIKE 'innodb_default_row_format';
SELECT table_name, engine, row_format FROM information_schema.tables WHERE table_schema = 'mi_base_de_datos';
```

**Solución:** la misma línea del problema 1:
```php
'engine' => 'InnoDB ROW_FORMAT=DYNAMIC',
```
Es un pequeño truco: Laravel no tiene una opción propia para el *row format*, pero coloca ese texto tal cual al final del `CREATE TABLE` (`... engine = InnoDB ROW_FORMAT=DYNAMIC`). Es una práctica habitual y no hace daño en ningún MySQL moderno.

**Alternativa que da la documentación de Laravel** ("Index Lengths & MySQL / MariaDB"). En `app/Providers/AppServiceProvider.php`:
```php
use Illuminate\Support\Facades\Schema;

public function boot(): void
{
    Schema::defaultStringLength(191);   // 191 × 4 = 764 bytes < 767
}
```
Funciona, pero recorta **todas** las columnas de texto a 191 caracteres. Es un parche: no arregla la causa.

**Resultado esperado:** todas las tablas con `engine = InnoDB` y `row_format = Dynamic`.

---

## 3. Migración fallida a medias: "table already exists"

**Síntoma:** una migración falla a mitad de camino (por ejemplo, por los problemas 1 o 2). Al volver a lanzar `php artisan migrate`, sale `Table 'users' already exists`.

**Causa:** en MySQL, las sentencias de estructura (`CREATE TABLE`, `ALTER TABLE`) **no se pueden deshacer con un rollback**, porque hacen un *commit* implícito. La tabla se creó, falló el índice, y la migración no llegó a anotarse en la tabla `migrations`. La base de datos queda a medias.

(PostgreSQL sí permite DDL dentro de transacciones; MySQL no.)

**Solución en desarrollo:**
```bash
php artisan migrate:fresh
```
Borra **todas las tablas** de la base de datos de la conexión y ejecuta todas las migraciones desde cero.

⚠️ **Solo en desarrollo** y solo si no hay datos que conservar. Hazlo con un usuario de MySQL que solo tenga permisos sobre esta base de datos (ver problema 4); así, aunque te equivoques de `.env`, no puedes tocar otros proyectos.

Comandos relacionados:

| Comando | Qué hace | Equivalente en Doctrine |
|---|---|---|
| `migrate` | Ejecuta las migraciones pendientes | `doctrine:migrations:migrate` |
| `migrate:status` | Lista las migraciones y si se han ejecutado | `doctrine:migrations:status` |
| `migrate:rollback` | Deshace el último lote (*batch*) | `doctrine:migrations:migrate prev` |
| `migrate:fresh` | Borra todas las tablas y migra de nuevo | `doctrine:schema:drop --force --full-database` + `migrate` |

---

## 4. `php artisan db:show` muestra tablas de otros proyectos

**Síntoma:** `db:show` dice `Database ... studydeck`, pero muestra `Tables 139` y cientos de MB, aunque la base de datos está vacía.

**Causa:** desde Laravel 12, `db:show` cuenta las tablas de **todas las bases de datos que puede ver el usuario** de la conexión. Con `root`, el usuario ve todas las del servidor (las de ToysTNT, etc.). La conexión era correcta: la línea importante es `Database`.

**Cómo diagnosticarlo:**
```bash
php artisan db:show --json
```
Cada tabla trae un campo `schema` que dice a qué base de datos pertenece.

**Solución (buena práctica): un usuario de MySQL por proyecto** (*principio de mínimo privilegio*):
```sql
CREATE USER 'studydeck'@'127.0.0.1' IDENTIFIED BY 'una_contraseña';
GRANT ALL PRIVILEGES ON studydeck.* TO 'studydeck'@'127.0.0.1';
GRANT ALL PRIVILEGES ON studydeck_test.* TO 'studydeck'@'127.0.0.1';
```
- En MySQL, un usuario es **nombre + host desde el que se conecta**. El host tiene que coincidir con el `DB_HOST` del `.env`, en este caso `127.0.0.1`.
- `ON studydeck.*` da permisos sobre todas las tablas de esa base de datos y de ninguna más.

En el `.env`:
```env
DB_USERNAME=studydeck
DB_PASSWORD=una_contraseña
```
Ahora `db:show` muestra solo las tablas propias, y un `migrate:fresh` con un `.env` equivocado no puede borrar datos de otro proyecto.

---

## 5. Necesito otra versión de PHP sin romper los otros proyectos

**Situación:** WAMP tiene varias versiones de PHP (`C:\wamp64\bin\php\`). ToysTNT necesita PHP 8.3 y StudyDeck PHP 8.5 (Laravel 13 pide de 8.3 a 8.5).

**Causa:** al escribir `php`, Windows busca en las carpetas de la variable `PATH`, en orden, y usa el primer `php.exe` que encuentra. "Cambiar versión de PHP CLI" en WAMP cambia esa versión para **todas** las terminales.

**Solución:**
1. La versión global (WAMP → clic derecho → Herramientas → "Cambiar versión de PHP CLI") se queda en la que necesitan los proyectos de trabajo: **8.3.36.1**.
2. En la terminal de StudyDeck, se pone PHP 8.5 por delante **solo en esa sesión**:
   ```powershell
   $env:Path = "C:\wamp64\bin\php\php8.5.12.1;" + $env:Path
   php -v   # debe mostrar 8.5.x
   ```
   Al cerrar la terminal, el cambio desaparece. Composer y Artisan usan el PHP de esa terminal (`composer -V` muestra qué PHP está usando).
3. Solución definitiva: **Docker**. Cada proyecto lleva su propia versión de PHP en su contenedor (ver la pista Docker del plan).

**Extensiones:** cada versión de PHP tiene su propio `php.ini`. WAMP usa dos ficheros:
- `php.ini`, para la consola;
- `phpForApache.ini`, para la web.

Una extensión está activa si su línea **no** empieza por `;` (por ejemplo, `extension=intl`). Para comprobar qué extensiones están cargadas: `php -m`.

Para Laravel hacen falta: `openssl`, `pdo_mysql`, `mbstring`, `fileinfo`, `intl`, `zip` y `curl`.

---

## 6. Composer lanza avisos "Deprecated" con PHP nuevo

**Síntoma:** `composer -V` con PHP 8.5 muestra muchos `Deprecated: ...` y `Deprecation Notice: Constant E_STRICT is deprecated`.

**Causa:** Composer era la 2.5.5 (de 2023), anterior a PHP 8.5.

**Solución:** desde una terminal **como administrador**, porque Composer está instalado en `C:\ProgramData`:
```bash
composer self-update            # actualizar (aquí, a 2.11.1)
composer self-update --rollback # volver a la versión anterior si hiciera falta
```
Actualizar Composer **no afecta a los proyectos**: funciona con cualquier PHP desde la 7.2.5 y no cambia los `composer.lock`.

**Aviso que no es de Composer:** si ejecutas Composer dentro de un proyecto, Composer carga los plugins de ese proyecto. En ToysTNT, Symfony Flex lanza `Using null as an array offset is deprecated` con PHP 8.5. Es un aviso, no un error, y con la 8.3 no aparece.

---

## 7. `composer create-project` crea el proyecto en una subcarpeta

**Síntoma:** querías el proyecto en `C:\Roberto\StudyDeck`, pero queda en `C:\Roberto\StudyDeck\studydeck\`.

**Causa:** el último argumento de `create-project` es la carpeta de destino.
```bash
composer create-project laravel/laravel studydeck   # crea ./studydeck/
composer create-project laravel/laravel .           # instala en la carpeta actual (debe estar vacía)
```

**Solución:** subir el contenido un nivel. `-Force` hace falta para mover también los ficheros ocultos (`.env`, `.gitignore`, `.editorconfig`):
```powershell
cd C:\Roberto\StudyDeck
Get-ChildItem -Force .\studydeck | Move-Item -Destination .
Remove-Item .\studydeck
php artisan --version
```
Moverlo no rompe nada: las rutas de Laravel y de `vendor/autoload.php` son relativas a la raíz del proyecto. La única excepción sería una caché de configuración (`php artisan config:cache`), que guarda rutas absolutas; si existe, se borra con `php artisan config:clear`.

---

## 8. `git push`: "Permission denied to OtraCuenta" (403) con dos cuentas de GitHub

**Síntoma:**
```
remote: Permission to Stek-ito/studydeck.git denied to RobertoCI.
fatal: unable to access 'https://github.com/Stek-ito/studydeck.git/': The requested URL returned error: 403
```
El repositorio es de la cuenta personal (`Stek-ito`), pero GitHub dice que quien intenta subir es la cuenta del trabajo (`RobertoCI`).

**Causa:** con HTTPS, Git no envía tu `user.name`/`user.email` para autenticarse (esos solo firman los commits). Pide la credencial al **Git Credential Manager** (`credential.helper = manager-core`), que guarda **una credencial por host**: `github.com`. Como la primera cuenta que se usó en este equipo fue la del trabajo, esa es la que entrega para cualquier repositorio de `github.com`.

**Cómo diagnosticarlo:**
```bash
git config --get credential.helper   # manager-core → credenciales en el Administrador de credenciales de Windows
git remote -v                        # la URL no lleva usuario → usa la credencial "por defecto" del host
git log --format="%an <%ae>" -1      # el autor del commit es otra cosa: no influye en el permiso de push
```

**Solución:** poner el usuario en la URL del remoto. El Credential Manager guarda entonces una credencial **distinta** para `Stek-ito@github.com` y la pide la primera vez (se abre el navegador para iniciar sesión con esa cuenta):
```bash
git remote set-url origin https://Stek-ito@github.com/Stek-ito/studydeck.git
git push -u origin main
```

**Por qué esta solución y no otra:**
- **Borrar la credencial de `github.com`** en el Administrador de credenciales de Windows: funciona, pero rompe los repos del trabajo, que pedirían login de nuevo, y el problema se invierte.
- **SSH con dos claves y alias en `~/.ssh/config`** (`Host github-personal`): es la solución "de libro" para varias cuentas, pero implica crear claves y configuración global. Útil si el problema se repite en muchos repos.
- **Usuario en la URL:** es un cambio solo de este repositorio (`.git/config`), no toca nada global y cada cuenta conserva su credencial. Para cada repo personal nuevo, clonar o añadir el remoto ya con `https://Stek-ito@github.com/...`.

**Si la otra cuenta ya no se usa (lo que se hizo aquí):** quitarla del equipo, y así `github.com` queda solo para la cuenta personal.
```powershell
cmdkey /list | Select-String github -Context 0,3     # ver qué hay guardado y con qué usuario
cmdkey /delete:git:https://github.com                # credencial de Git (Git Credential Manager)
cmdkey /delete:fork:https://github.com.RobertoCI.oauth_token   # token del cliente Fork (o quitar la cuenta desde Fork)
ssh -T git@github.com                                # "Hi <usuario>!": a qué cuenta pertenece tu clave SSH
```
En el siguiente `git push`, el Credential Manager pide iniciar sesión y guarda la cuenta nueva como la de `github.com`.

**Credenciales locales frente a cuenta en GitHub:** `cmdkey` solo borra lo guardado en el PC. La cuenta y las claves SSH dadas de alta en ella viven en GitHub y se quitan desde la web (Settings → SSH and GPG keys, o Settings → Account → Delete account). Con SSH el PC no guarda credenciales de GitHub: es GitHub quien sabe a qué cuenta pertenece cada clave pública, y una misma clave solo puede estar en **una** cuenta ("Key is already in use").

**Clave SSH propia para GitHub** (sin tocar `id_rsa`, que se usa para los servidores):
```powershell
ssh-keygen -t ed25519 -C "caselles.iribarne.roberto@gmail.com" -f "$env:USERPROFILE\.ssh\id_ed25519_github"
Get-Content "$env:USERPROFILE\.ssh\id_ed25519_github.pub" | Set-Clipboard   # pegar en GitHub → Settings → SSH and GPG keys → New SSH key
```
En `~/.ssh/config`:
```
Host github.com
    User git
    IdentityFile C:\Users\rocas\.ssh\id_ed25519_github
    IdentitiesOnly yes
```
**Limpieza completa de una cuenta antigua** (la que se hizo aquí: cuenta y clave de un trabajo anterior):
1. Credenciales de Windows: los `cmdkey /delete` de arriba.
2. Aplicaciones con sesión propia: Fork (File → Accounts), GitHub Desktop (File → Options → Accounts → Sign out), VS Code (icono de cuentas, abajo a la izquierda → Sign out).
3. Clave SSH antigua: sacarla de `~/.ssh` (primero a una carpeta de copia fuera de `.ssh`, y borrarla más adelante). Si la clave privada ya no está en el PC, que siga dada de alta en la cuenta antigua de GitHub no afecta: nadie puede usarla desde este equipo.
4. Clave nueva solo para GitHub, como se explica a continuación.

`IdentitiesOnly yes` obliga a usar solo esa clave con GitHub, aunque `id_rsa` también exista. Comprobar con `ssh -T git@github.com` → `Hi Stek-ito!`. Para usar SSH en un repo: `git remote set-url origin git@github.com:Stek-ito/studydeck.git`.
