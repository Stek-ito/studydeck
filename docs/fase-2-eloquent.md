# Fase 2: Eloquent ORM

**Objetivo:** pasar los mazos y las fichas de los arrays en memoria a MySQL, con migraciones, modelos, relaciones, factories y seeders. Al final, los controladores de la fase 1 consultan la base de datos y el Route Model Binding hace los 404 por nosotros.

Si algo falla, mira [troubleshooting.md](troubleshooting.md). Un commit por paso terminado.

## Pasos

1. [x] Teoría: Active Record (Eloquent) frente a Data Mapper (Doctrine) y las convenciones de Eloquent
2. [ ] Migraciones de `decks` y `cards`, con clave foránea
3. [ ] Modelos `Deck` y `Card`: `$fillable`, casts y relaciones
4. [ ] Tinker: crear y consultar datos desde la consola
5. [ ] Factories y seeders: cargar los datos de ejemplo
6. [ ] Controladores con Eloquent y Route Model Binding
7. [ ] Eager loading y el problema N+1
8. [ ] Tablas `documents` y `card_reviews`, y el scope `dueToday()`

---

## Paso 1: Active Record frente a Data Mapper

### La diferencia de fondo

| | Doctrine (Data Mapper) | Eloquent (Active Record) |
|---|---|---|
| La entidad | Objeto PHP "puro": no sabe que existe una base de datos | El modelo **es** una fila: sabe guardarse, borrarse y consultarse |
| Guardar | `$em->persist($deck); $em->flush();` | `$deck->save();` |
| Consultar | `$repository->find(1)`, `findBy([...])`, QueryBuilder/DQL | `Deck::find(1)`, `Deck::where(...)->get()` |
| Quién conoce el mapeo | Atributos `#[ORM\Column]` en la entidad | **Nadie lo declara**: las columnas se leen de la tabla en tiempo de ejecución |
| Esquema | Se genera a partir de las entidades (`make:migration` compara entidad y BD) | Se escribe a mano en las migraciones; el modelo no define columnas |
| Unit of Work | Sí: `flush()` escribe todos los cambios pendientes en una transacción | No: cada `save()` es una consulta inmediata |
| Propiedades | Privadas, con getters y setters | "Mágicas": `$deck->name` lee el array `$attributes` interno |

Consecuencias prácticas:
- Con Eloquent se escribe **mucho menos código**: el modelo puede ser una clase casi vacía.
- A cambio, el modelo está **acoplado** a la base de datos: no se puede instanciar ni probar sin pensar en ella, y la lógica de negocio tiende a acabar en el modelo ("modelo gordo"). En StudyDeck la lógica de Leitner irá en un servicio aparte (`LeitnerScheduler`) precisamente por eso.
- No hay Unit of Work: si hay que guardar varias cosas de forma atómica, se usa `DB::transaction(function () { ... })`.

### Convenciones de Eloquent (lo que no hay que configurar)

| Convención | Ejemplo | Cómo cambiarla |
|---|---|---|
| Tabla = nombre del modelo en plural y `snake_case` | `Deck` → `decks`, `CardReview` → `card_reviews` | `protected $table = '...'` |
| Clave primaria `id` autoincremental | `$table->id()` en la migración | `$primaryKey`, `$incrementing`, `$keyType` |
| Columnas `created_at` y `updated_at`, rellenas solas | `$table->timestamps()` | `public $timestamps = false` |
| Clave foránea = modelo en singular + `_id` | `cards.deck_id` apunta a `decks.id` | segundo argumento de `hasMany` / `belongsTo` |

### Preguntas de repaso (y errores típicos)

**1. ¿Se guarda un cambio sin `save()`? ¿Qué hace `save()` sin cambios?**
- `$deck->name = 'X'` solo cambia el objeto **en memoria**. Sin `save()`, MySQL no se entera. En esto Eloquent y Doctrine se parecen: el cambio solo llega a la BD cuando lo pides (`save()` frente a `flush()`).
- `save()` **sin cambios no lanza ninguna consulta**. Eloquent guarda dos copias de los datos: `$original` (lo que vino de la BD) y `$attributes` (lo actual). `save()` compara ambas (*dirty checking*) y solo escribe las columnas que han cambiado; si no ha cambiado ninguna, devuelve `true` sin tocar la BD. Doctrine hace lo mismo en `flush()` con su `UnitOfWork`.
- Métodos para verlo: `$deck->isDirty()`, `$deck->getDirty()` (antes de guardar) y `$deck->wasChanged()` (después).
- ❌ Error típico: pensar que `save()` siempre hace un `UPDATE`.

**2. ¿Cómo sabe Eloquent qué columnas tiene `users` si el modelo no las declara?**
No consulta la estructura de la tabla: hace `SELECT * FROM users ...` y **rellena `$attributes` con las columnas que vengan en la fila**. Las propiedades "existen" porque `$user->email` pasa por el método mágico `__get()`, que busca la clave `email` en ese array. Por eso un error de escritura (`$user->emial`) no da error: devuelve `null`.

**3. Modelo `CardReview`: tabla y clave foránea hacia la ficha.**
- Tabla: `card_reviews` ✅.
- La clave foránea se nombra por la tabla **a la que apunta** (el padre), en singular + `_id`: `card_reviews.card_id` → `cards.id`.
- ❌ Error típico: `card_reviews_id`. Eso sería el nombre de una columna en **otra** tabla que apuntase a `card_reviews`.
- Regla: la FK vive en la tabla hija (la de "muchos") y lleva el nombre del padre: `cards.deck_id`, `decks.user_id`, `card_reviews.card_id`.

**4. Relación uno a uno: `Document` tiene un `Deck`.**
- La FK es `decks.document_id`: la columna está **en la tabla `decks`** y apunta a `documents.id`. `decks.document_id` ya se lee como "tabla.columna".
- En uno a uno, la FK va en el lado que **depende** del otro: el mazo sale del documento, no al revés.
- En los modelos: el lado **sin** FK usa `hasOne` (`Document hasOne Deck`) y el lado **con** FK usa `belongsTo` (`Deck belongsTo Document`). Truco: `belongsTo` siempre va en el modelo cuya tabla tiene la columna `_id`.
