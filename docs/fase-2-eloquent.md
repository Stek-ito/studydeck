# Fase 2: Eloquent ORM

**Objetivo:** pasar los mazos y las fichas de los arrays en memoria a MySQL, con migraciones, modelos, relaciones, factories y seeders. Al final, los controladores de la fase 1 consultan la base de datos y el Route Model Binding hace los 404 por nosotros.

Si algo falla, mira [troubleshooting.md](troubleshooting.md). Un commit por paso terminado.

## Pasos

1. [x] Teoría: Active Record (Eloquent) frente a Data Mapper (Doctrine) y las convenciones de Eloquent
2. [x] Migraciones de `decks` y `cards`, con clave foránea
3. [x] Modelos `Deck` y `Card`: `$fillable`, casts y relaciones
4. [x] Tinker: crear y consultar datos desde la consola
5. [x] Factories y seeders: cargar los datos de ejemplo
6. [x] Controladores con Eloquent y Route Model Binding
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

---

## Paso 2: Migraciones de `decks` y `cards`

| | Doctrine | Laravel |
|---|---|---|
| Cómo nace | `make:migration` compara entidades y BD y genera SQL | La escribes tú con el `Blueprint` (`$table->string(...)`) |
| Ejecutar / estado / deshacer | `doctrine:migrations:migrate` / `status` / `migrate prev` | `php artisan migrate` / `migrate:status` / `migrate:rollback` |
| Ver una tabla | `doctrine:schema:validate` (no equivalente exacto) | `php artisan db:table cards` |

```bash
php artisan make:model Deck -m    # modelo + migración (en este orden: decks antes que cards)
php artisan make:model Card -m
```
Las migraciones se ejecutan por el **timestamp** del nombre del fichero; `cards` tiene FK hacia `decks`, así que `decks` debe ir antes.

### 2.1. Código
```php
// create_decks_table
Schema::create('decks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->timestamps();
});

// create_cards_table
Schema::create('cards', function (Blueprint $table) {
    $table->id();
    $table->foreignId('deck_id')->constrained()->cascadeOnDelete();
    $table->string('term');
    $table->text('definition');
    $table->boolean('is_key')->default(false);
    $table->unsignedSmallInteger('source_page')->nullable();
    $table->timestamps();
});
```

| Método | SQL |
|---|---|
| `id()` | `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY` |
| `foreignId('deck_id')->constrained()->cascadeOnDelete()` | `BIGINT UNSIGNED` + FK a `decks.id` (tabla deducida del nombre) + `ON DELETE CASCADE` |
| `string()` | `VARCHAR(255)` |
| `text()` | `TEXT` (~64 KB) |
| `boolean()->default(false)` | `TINYINT(1) DEFAULT 0` |
| `unsignedSmallInteger()` | `SMALLINT UNSIGNED` (0–65.535, 2 bytes) |
| `->nullable()` | `NULL` (sin él, la columna es `NOT NULL`) |
| `timestamps()` | `created_at` y `updated_at` |

`decks.document_id` se añade en el paso 8: una FK no puede apuntar a una tabla que aún no existe.

### 2.2. Errores corregidos en la revisión: elegir el tipo de columna

**El tipo de la columna es la primera validación**: el más estricto que encaje con el dato.
- `definition` como `string()->nullable()` → `text()`. Una definición supera fácilmente 255 caracteres (*Data too long*), y una ficha sin definición no tiene sentido: que la BD lo exija.
- `source_page` como `string()` → `unsignedSmallInteger()`. Como texto ordena mal (`"1", "10", "2"`), admite basura (`"abc"`) y ocupa más.
- `is_key_term` → `is_key`: el nombre era válido, pero hay que usar **el mismo nombre en todo el proyecto** (plan, esquema de Claude, modelos).

### 2.3. Editar una migración ya ejecutada

Laravel **no vuelve a ejecutar** una migración que figura como `Ran`, aunque cambies el fichero. Para aplicar el cambio en local:
```bash
php artisan migrate:rollback     # deshace el último batch (down() en orden inverso: cards y luego decks)
php artisan migrate:status       # las dos vuelven a "Pending"
php artisan migrate
php artisan db:table cards       # comprobar tipos, índice y FK
```
- **Batch:** el grupo de migraciones ejecutadas en un mismo `migrate`. `rollback` deshace el último batch entero; `--step=1` deshace solo la última migración.
- ⚠️ **Solo vale mientras la migración no ha salido de tu máquina.** Si ya está publicada (otro desarrollador, producción), allí figura como `Ran` y tu cambio nunca se aplicaría: se crea una **migración nueva** que modifica la tabla (`->change()`, `renameColumn()`). Igual que en Doctrine.

### 2.4. Resultado (`php artisan db:table cards`)
- Motor `InnoDB`, collation `utf8mb4_unicode_ci`.
- `definition text` (sin nullable), `is_key tinyint(1)` con 0 por defecto, `source_page smallint unsigned nullable`.
- Índice `cards_deck_id_foreign` y FK `deck_id references id on decks`, `on delete cascade`.

---

## Paso 3: Modelos `Deck` y `Card`

### 3.1. Asignación masiva: `#[Fillable]`

`Deck::create([...])` y `$deck->fill([...])` solo asignan los campos declarados como permitidos. Protege de `Deck::create($request->all())` con un `user_id` inyectado en la petición.

- Laravel 13 lo declara con un **atributo PHP**: `#[Fillable(['name', 'description'])]` encima de la clase. La forma clásica `protected $fillable = [...]` sigue funcionando.
- Equivalente en Symfony: un formulario solo rellena los campos añadidos con `->add(...)`.
- **Las claves foráneas (`user_id`, `deck_id`) no van en `Fillable`**: se rellenan a través de la relación (`$user->decks()->create([...])`, `$deck->cards()->create([...])`), nunca con datos del usuario.

### 3.2. Tipos PHP: `casts()`

MySQL devuelve `TINYINT` como `0`/`1`. `casts()` convierte al leer y al escribir (≈ `type:` de `#[ORM\Column]`):
```php
protected function casts(): array
{
    return [
        'is_key' => 'boolean',
        'source_page' => 'integer',
    ];
}
```

### 3.3. Relaciones: dos tipos, siempre en pareja

```
users ──< decks ──< cards
          (user_id)  (deck_id)
```

| Relación | Modelo con la columna `_id` → `belongsTo` | El otro lado → `hasMany` | Doctrine |
|---|---|---|---|
| Usuario ↔ mazos | `Deck::user()` | `User::decks()` | `ManyToOne` / `OneToMany` |
| Mazo ↔ fichas | `Card::deck()` | `Deck::cards()` | `ManyToOne` / `OneToMany` |

Nombre del método: **singular** si devuelve uno (`user()`, `deck()`), **plural** si devuelve muchos (`decks()`, `cards()`).

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description'])]
class Deck extends Model
{
    // decks tiene user_id → pertenece a un User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);   // busca decks.user_id (nombre del método + _id)
    }

    // cards tiene deck_id → tiene muchas Card
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);     // busca cards.deck_id (nombre de esta clase + _id)
    }
}

// Card
public function deck(): BelongsTo { return $this->belongsTo(Deck::class); }

// User
public function decks(): HasMany { return $this->hasMany(Deck::class); }
```

Uso:
```php
$deck->cards                                // PROPIEDAD: ejecuta la consulta, devuelve una Collection
$deck->cards()->where('is_key', true)->get(); // MÉTODO: la relación sin ejecutar, para encadenar
$deck->cards()->create([...]);              // crea una ficha con deck_id ya puesto
```

### 3.4. Comprobación: `php artisan model:show`

```bash
php artisan model:show Deck   # ✅ Relations: user BelongsTo User, cards HasMany Card
php artisan model:show Card   # ✅ is_key fillable tinyint(1) / boolean; source_page ... / integer; deck BelongsTo Deck
php artisan model:show User   # ✅ decks HasMany Deck
```
Muestra columnas (leídas de la tabla), qué es `fillable`, el tipo SQL / el cast PHP y las relaciones. Lo más parecido a `doctrine:mapping:info`.

### 3.5. Formato del código: Laravel Pint

`laravel/pint` viene en el esqueleto (≈ PHP-CS-Fixer). Formatea según el estilo de Laravel:
```bash
vendor\bin\pint --test    # solo dice qué ficheros formatearía
vendor\bin\pint           # los formatea
```

---

## Paso 4: Tinker

Consola interactiva (REPL) con la aplicación cargada: `php artisan tinker`. Es PsySH integrado con Laravel; en Symfony no viene de serie. Se sale con `exit`. Si un modelo recién creado da `Class "X" not found`, ver [troubleshooting.md, problema 9](troubleshooting.md) (`composer dump-autoload`).

### 4.1. Crear a través de las relaciones
```php
$user = User::create(['name' => 'Roberto', 'email' => 'roberto@studydeck.test', 'password' => 'secret123']);
// sin password en la salida (#[Hidden]) y guardado como hash (cast 'hashed')

$deck = $user->decks()->create(['name' => 'PHP', 'description' => 'PHP basics']);   // user_id lo pone la relación
$deck->cards()->create(['term' => 'What is PHP?', 'definition' => '...', 'is_key' => true, 'source_page' => 3]);
```

### 4.2. Leer a través de las relaciones
```php
$deck->cards->count();          // 2
$deck->cards->first()->is_key;  // true (no 1): cast
Card::first()->deck->name;      // "PHP": relación inversa
```
Ojo: clase en **singular** (`Card`), tabla y relación de "muchos" en plural (`cards`, `$deck->cards`).

### 4.3. Dirty checking, comprobado
```php
DB::enableQueryLog();
$deck->name = 'PHP 8.5';
$deck->getDirty();          // ['name' => 'PHP 8.5']: el cambio en memoria
$deck->save();              // UPDATE decks SET name = ..., updated_at = ... WHERE id = ...
$deck->save();              // sin cambios → no hay consulta, devuelve true
count(DB::getQueryLog());   // 1
```
`DB::enableQueryLog()` + `DB::getQueryLog()` es la forma rápida de ver qué SQL lanza Eloquent (≈ la pestaña Doctrine del profiler de Symfony).

### 4.4. Fillable en acción
```php
Deck::create(['name' => 'Hack', 'user_id' => $user->id]);
// SQLSTATE[HY000]: General error: 1364 Field 'user_id' doesn't have a default value
```
El error **no** es que el campo no exista (la columna sí existe). Pasa esto:
1. `user_id` no está en `#[Fillable]`, así que Eloquent lo **descarta en silencio**.
2. El `INSERT` se lanza solo con `name` (y los timestamps).
3. MySQL lo rechaza: `user_id` es `NOT NULL` y no tiene valor por defecto.

La protección funciona, pero el descarte silencioso puede despistar. En desarrollo se puede hacer que lance una excepción clara (`MassAssignmentException`) con `Model::preventSilentlyDiscardingAttributes(! app()->isProduction());` en `AppServiceProvider::boot()` (se verá junto a `Model::shouldBeStrict()` en el paso 7).

### 4.5. Borrado en cascada
```php
$deck->delete();
Card::count();   // 0: MySQL borra las fichas por el cascadeOnDelete() de la FK
```

---

## Paso 5: Factories y seeders

**Objetivo:** llenar la base de datos con datos de prueba con un solo comando, y poder borrarlos y regenerarlos cuando haga falta.

### 5.1. Dos piezas con funciones distintas

| Pieza | Qué hace | Symfony |
|---|---|---|
| **Factory** | Sabe **cómo fabricar** un modelo con datos falsos (Faker). No decide cuántos ni cuáles | `zenstruck/foundry` |
| **Seeder** | **Decide qué datos** se cargan, usando las factories | Clase `Fixture` de DoctrineFixturesBundle |
| `php artisan db:seed` | Ejecuta `DatabaseSeeder` | `doctrine:fixtures:load` |
| `php artisan migrate:fresh --seed` | Borra **todas** las tablas, migra desde cero y siembra | `schema:drop` + `migrate` + `fixtures:load` |

⚠️ `migrate:fresh` borra todo. Solo se usa en local, **nunca en producción**.

### 5.2. Conectar modelo y factory: `HasFactory`

```bash
php artisan make:factory DeckFactory
php artisan make:factory CardFactory
```

Cada modelo necesita `use HasFactory;` dentro de la clase. Ese trait añade `Deck::factory()` y, por convención de nombres, encuentra `Database\Factories\DeckFactory`.

### 5.3. Las factories

```php
// DeckFactory
public function definition(): array
{
    return [
        'user_id' => User::factory(),            // crea un usuario si nadie indica otro
        'name' => fake()->words(2, true),        // true: texto, no array
        'description' => fake()->sentence(),
    ];
}

// CardFactory
public function definition(): array
{
    return [
        'deck_id' => Deck::factory(),
        'term' => fake()->sentence(4),
        'definition' => fake()->paragraph(),
        'is_key' => fake()->boolean(20),         // true el 20 % de las veces
        'source_page' => fake()->numberBetween(1, 300),
    ];
}
```

- **La clave foránea sí puede aparecer en una factory.** Las factories no pasan por `#[Fillable]`, porque son código nuestro y no datos que llegan de un usuario.
- **`User::factory()` como valor** es un valor "por defecto perezoso": solo se crea ese usuario si no se indica otro con `->for($user)`.

### 5.4. El seeder

```php
public function run(): void
{
    $user = User::factory()->create([
        'name' => 'Roberto',
        'email' => 'roberto@studydeck.test',   // contraseña: "password" (la pone UserFactory)
    ]);

    Deck::factory()
        ->count(3)
        ->for($user)                             // user_id, a través de la relación user()
        ->has(Card::factory()->count(10))        // deck_id, a través de la relación cards()
        ->create();
}
```

Se lee como una frase: "crea 3 mazos para `$user`, cada uno con 10 fichas". Los valores que se pasan a `create([...])` sustituyen a los de `definition()`.

### 5.5. Resultado

| Comprobación | Resultado |
|---|---|
| `User::count()` | 1 |
| `Deck::count()` | 3, todos con `user_id = 1` |
| `Card::count()` | 30 (10 por mazo, `Deck::withCount('cards')`) |
| Fichas con `is_key = true` | 4 de 30 (cerca del 20 %) |

---

## Paso 6: Controladores con Eloquent y Route Model Binding

**Objetivo:** quitar los arrays de los controladores y leer de MySQL. Laravel busca el modelo a partir de la URL y devuelve el 404 él solo.

**En Symfony:** `EntityValueResolver` (antes `ParamConverter`), con `public function show(Deck $deck)`.

### 6.1. Tres reglas

1. **El parámetro de la ruta y la variable deben llamarse igual:** `{deck}` ↔ `Deck $deck`. Si la ruta dice `{id}`, Laravel inyecta un `Deck` **vacío**, sin error.
2. **Devolver un modelo o una colección ya produce JSON** (`toJson()` automático).
3. **Rutas anidadas: `->scopeBindings()`.** Sin él, `{card}` se busca en toda la tabla y `/decks/1/cards/25` devolvería una ficha del mazo 3 (IDOR). Con él, Laravel busca a través de la relación: `$deck->cards()->findOrFail(25)`.

### 6.2. Código

```php
// routes/api.php
Route::get('/decks', [DeckController::class, 'index'])->name('decks.index');
Route::get('/decks/{deck}', [DeckController::class, 'show'])->name('decks.show')->whereNumber('deck');

Route::prefix('decks/{deck}/cards')
    ->whereNumber(['deck', 'card'])
    ->scopeBindings()
    ->group(function () {
        Route::get('/', [CardController::class, 'index'])->name('cards.index');
        Route::get('/{card}', [CardController::class, 'show'])->name('cards.show');
    });

// DeckController
public function index(): Collection { return Deck::all(); }
public function show(Deck $deck): Deck { return $deck; }

// CardController
public function index(Deck $deck): Collection { return $deck->cards; }
public function show(Deck $deck, Card $card): Card { return $card; }   // $deck hace falta para scopeBindings()
```

`Collection` es `Illuminate\Database\Eloquent\Collection`. `Illuminate\Support\Collection` también funciona, porque es la clase padre.

### 6.3. Errores corregidos en la revisión

- **`use Ramsey\Collection\Collection`**, elegido por el autocompletado del IDE. Provocaba un 500 (`TypeError: Return value must be of type Ramsey\Collection\Collection`). Cuando hay varias clases con el mismo nombre, hay que **mirar el namespace**.
- **`return $deck->cards->find($card->id)`** en `show()`. Sobra, porque `scopeBindings()` ya ha comprobado la pertenencia. Además, `$deck->cards` (propiedad) carga **todas** las fichas del mazo para usar una, y `find()` puede devolver `null`, lo que daría un `TypeError`. Basta con `return $card;`.
- **`$deck->cards`** es una colección ya cargada en PHP. **`$deck->cards()`** es un query builder que va a SQL.

### 6.4. Por qué el 404 de Route Model Binding no lleva `X-Response-Time`

Orden en el grupo `api`: `SubstituteBindings` → `MeasureResponseTime` (añadido con `append`) → controlador.

- **`abort(404)` en el controlador (fase 1):** el 404 nace **dentro** del middleware, que lo ve y le pone la cabecera.
- **El modelo no existe (ahora):** `SubstituteBindings` lanza la excepción **antes** de llamar a `$next()`. La petición nunca llega a `MeasureResponseTime`, que no se ejecuta.

**Regla:** un middleware solo ve las respuestas generadas en capas **más interiores** que él. Para medirlo todo, habría que registrarlo como middleware global (`$middleware->append(...)`).

### 6.5. Resultado

| Petición | Código |
|---|---|
| `decks`, `decks/1`, `decks/1/cards`, `decks/1/cards/5` | 200 |
| `decks/1/cards/25` (ficha del mazo 3) | 404, gracias a `scopeBindings()` |
| `decks/99` | 404 `No query results for model [App\Models\Deck] 99` |
| `decks/abc` | 404, por la restricción `whereNumber` |
