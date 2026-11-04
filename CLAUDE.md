# StudyDeck

App de Laravel que convierte PDFs en fichas de estudio con la API de Claude y las repasa con repetición espaciada (Leitner). Proyecto personal y de portfolio con el que Roberto aprende Laravel viniendo de Symfony.

Las normas generales (español, modo profesor paso a paso, Roberto escribe el código) están en el CLAUDE.md global y se aplican aquí.

## Plan y progreso
- Plan completo y estado de cada fase: [docs/PLAN.md](docs/PLAN.md). Léelo al empezar y actualízalo al cerrar cada paso.
- Explica cada concepto con su equivalente en Symfony (tabla de equivalencias en el plan).

## Entorno
- Laravel 13 con **PHP 8.5** (`C:\wamp64\bin\php\php8.5.12.1`). La consola global usa PHP 8.3: en cada terminal nueva, antes de `php`, `composer` o `artisan`:
  `$env:Path = "C:\wamp64\bin\php\php8.5.12.1;" + $env:Path`
- MySQL 8.0 de WAMP en `127.0.0.1:3306`, usuario propio `studydeck` (solo ve `studydeck` y `studydeck_test`; su contraseña está en `.env`, no la leas ni la muestres). Bases de datos: `studydeck` (app) y `studydeck_test` (tests).
- El MySQL de WAMP crea tablas MyISAM/COMPACT por defecto; por eso `config/database.php` fuerza `'engine' => 'InnoDB ROW_FORMAT=DYNAMIC'`. No lo quites.
- Documentación: guía de cada fase en `docs/fase-N-*.md` y problemas resueltos en `docs/troubleshooting.md`. Al resolver un problema nuevo, añádelo allí.
- La pista Docker usará el puerto 3308 para MySQL (3306 = MySQL de WAMP, 3307 = MariaDB).

## Convenciones
- Código, tablas, rutas y commits en inglés. Documentación en español.
- Las tablas se crean siempre con migraciones.
- Laravel Boost no está instalado; se valorará en la fase 6. `AGENTS.md` es el fichero que trae el esqueleto de Laravel y no se ha adaptado.
