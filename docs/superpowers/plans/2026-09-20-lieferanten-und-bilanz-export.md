# Lieferanten und Bilanz-Export — Implementierungsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Lieferanten als Stammdaten einführen, Artikeln Lieferanten mit Stückpreisen zuordnen, den Lieferanten beim Ein- und Ausbuchen miterfassen und daraus eine Bestandsbilanz für einen Zeitraum als PDF und XLSX exportieren.

**Architecture:** Eine zusätzliche Migration legt `suppliers`, die Pivot-Tabelle `article_supplier` und zwei neue Spalten auf `stock_movements` an. Der Preis wird beim Buchen als Snapshot (`unit_price`) auf die Bewegung kopiert, damit spätere Preisänderungen alte Bilanzen nicht verfälschen. Ein `BalanceReportService` aggregiert die Bewegungen eines Zeitraums in einer einzigen Query zu Artikel-/Lieferantenzeilen; Vorschau, PDF und XLSX rendern dasselbe Ergebnis.

**Tech Stack:** Laravel 13.32, PHP 8.5, Inertia 3 (`inertiajs/inertia-laravel` 3.3 / `@inertiajs/vue3` 3.7), Vue 3, Tailwind 4 (CSS-first), Vite 8, PHPUnit 13, MySQL. Neu: `barryvdh/laravel-dompdf` ^3.1 und `openspout/openspout` ^5.11.

**Spezifikation:** `docs/superpowers/specs/2026-09-20-lieferanten-und-bilanz-export-design.md`

## Global Constraints

- Alle UI-Texte, Flash-Messages, Fehlermeldungen und Code-Kommentare auf **Deutsch**, mit korrekten Umlauten (ä, ö, ü, ß) — niemals `ae`, `oe`, `ue`, `ss` als Ersatz.
- Nach **jeder** PHP-Änderung `vendor/bin/pint --dirty --format agent` ausführen.
- Neue Dateien immer über `php artisan make:...` mit `--no-interaction` anlegen.
- Keine Form Requests: schreibende Actions validieren inline per `$request->validate([...])`.
- Keine Policies/Gates: Autorisierung ausschließlich über die Route-Middleware `['auth:sanctum', config('jetstream.auth_session'), 'verified']`.
- Management-Controller antworten mit `redirect()->route(...)->with('message', '<deutscher Text>')`.
- Statische Routensegmente stehen **vor** parametrisierten (`/suppliers/trashed` vor `/suppliers/{supplier}`).
- Jeder Test nutzt `RefreshDatabase`. Tests laufen über `phpunit.xml` gegen die MySQL-Datenbank `goods_test`.
- Bestandsänderungen schreiben immer `stocks` **und** `stock_movements` innerhalb einer `DB::transaction()`.
- Geldbeträge: `decimal(10,2)` in der Datenbank, **keine** Eloquent-Casts. MySQL liefert Dezimalspalten als String zurück — in Tests deshalb `assertEquals` (lose Vergleiche) statt `assertSame` für Preise verwenden.
- Es gibt kein JS-Linting-Setup. Vue-Dateien am Stil der jeweiligen Nachbardatei orientieren. Beachten: `Pages/StockMovement/Index.vue` nutzt die Options API (`defineComponent`), alle anderen Seiten `<script setup>`.
- Frontend-Änderungen werden erst nach `npm run build` bzw. im laufenden `npm run dev` sichtbar.

---

### Task 1: Datenbankschema, Modelle und Factories

**Files:**
- Create: `database/migrations/<zeitstempel>_create_suppliers_and_article_supplier.php`
- Create: `app/Models/Supplier.php`
- Create: `database/factories/SupplierFactory.php`
- Create: `database/factories/ArticleFactory.php`
- Create: `database/factories/StockFactory.php`
- Create: `database/factories/StockMovementFactory.php`
- Modify: `app/Models/Article.php`
- Modify: `app/Models/StockMovement.php`
- Test: `tests/Feature/SupplierRelationTest.php`

**Interfaces:**
- Consumes: nichts (erste Task).
- Produces:
  - Tabelle `suppliers` mit `name`, `contact_person`, `email`, `phone`, `address`, `customer_number`, `notes`, `timestamps`, `softDeletes`.
  - Tabelle `article_supplier` mit `article_id`, `supplier_id`, `price` (decimal 10,2), `is_default` (bool), `timestamps`, unique `(article_id, supplier_id)`.
  - `stock_movements.supplier_id` (nullable FK) und `stock_movements.unit_price` (nullable decimal 10,2).
  - `App\Models\Supplier` mit `articles(): BelongsToMany` (`withPivot(['price','is_default'])`) und `stockMovements(): HasMany`.
  - `App\Models\Article::suppliers(): BelongsToMany` mit denselben Pivot-Feldern.
  - `App\Models\StockMovement::supplier(): BelongsTo`.
  - Factories: `Supplier::factory()`, `Article::factory()`, `Stock::factory()`, `StockMovement::factory()` (Standardzustand: `type = 'in'`, `supplier_id = null`, `unit_price = null`, `to_storage_location_id` gesetzt).

- [ ] **Step 1: Failing test schreiben**

`tests/Feature/SupplierRelationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_artikel_und_lieferant_lassen_sich_mit_preis_verknuepfen(): void
    {
        $article = Article::factory()->create();
        $supplier = Supplier::factory()->create();

        $article->suppliers()->attach($supplier->id, ['price' => 12.50, 'is_default' => true]);

        $verknuepft = $article->fresh()->suppliers->first();

        $this->assertTrue($supplier->is($verknuepft));
        $this->assertEquals(12.50, $verknuepft->pivot->price);
        $this->assertTrue((bool) $verknuepft->pivot->is_default);
    }

    public function test_derselbe_lieferant_kann_einem_artikel_nur_einmal_zugeordnet_werden(): void
    {
        $article = Article::factory()->create();
        $supplier = Supplier::factory()->create();

        $article->suppliers()->attach($supplier->id, ['price' => 1.00, 'is_default' => true]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $article->suppliers()->attach($supplier->id, ['price' => 2.00, 'is_default' => false]);
    }

    public function test_bewegung_speichert_lieferant_und_stueckpreis(): void
    {
        $supplier = Supplier::factory()->create();

        $movement = StockMovement::factory()->create([
            'supplier_id' => $supplier->id,
            'unit_price' => 3.75,
        ]);

        $frisch = $movement->fresh();

        $this->assertTrue($supplier->is($frisch->supplier));
        $this->assertEquals(3.75, $frisch->unit_price);
    }

    public function test_lieferant_kennt_seine_bewegungen(): void
    {
        $supplier = Supplier::factory()->create();
        StockMovement::factory()->count(2)->create(['supplier_id' => $supplier->id]);

        $this->assertCount(2, $supplier->stockMovements);
    }
}
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

Run: `php artisan test --compact tests/Feature/SupplierRelationTest.php`
Expected: FAIL — `Class "App\Models\Supplier" not found` bzw. `Base table or view not found: 'goods_test.suppliers'`, falls die Migration noch nicht gelaufen ist.

- [ ] **Step 3: Migration anlegen**

```bash
php artisan make:migration create_suppliers_and_article_supplier --no-interaction
```

- [ ] **Step 4: Migration füllen**

Inhalt der erzeugten Datei vollständig ersetzen:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('customer_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('article_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->decimal('price', 10, 2);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['article_id', 'supplier_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            // Bewusst ohne onDelete-Regel: der Lieferant darf nicht endgültig
            // gelöscht werden, solange Bewegungen auf ihn zeigen. Das prüft
            // SupplierManagementController::forceDelete().
            $table->foreignId('supplier_id')->nullable()->after('article_id')->constrained();
            $table->decimal('unit_price', 10, 2)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['supplier_id', 'unit_price']);
        });

        Schema::dropIfExists('article_supplier');
        Schema::dropIfExists('suppliers');
    }
};
```

- [ ] **Step 5: Supplier-Modell anlegen**

```bash
php artisan make:model Supplier --no-interaction
```

Dann `app/Models/Supplier.php` vollständig ersetzen:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'customer_number',
        'notes',
    ];

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class)
            ->withPivot(['price', 'is_default'])
            ->withTimestamps();
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
```

- [ ] **Step 6: Article- und StockMovement-Modell erweitern**

In `app/Models/Article.php` nach der Methode `stockMovements()` einfügen:

```php
    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class)
            ->withPivot(['price', 'is_default'])
            ->withTimestamps();
    }
```

In `app/Models/StockMovement.php` das `$fillable`-Array um `'supplier_id'` (direkt nach `'article_id'`) und `'unit_price'` (direkt nach `'quantity'`) ergänzen und nach `article()` einfügen:

```php
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
```

- [ ] **Step 7: Factories anlegen**

```bash
php artisan make:factory SupplierFactory --model=Supplier --no-interaction
php artisan make:factory ArticleFactory --model=Article --no-interaction
php artisan make:factory StockFactory --model=Stock --no-interaction
php artisan make:factory StockMovementFactory --model=StockMovement --no-interaction
```

`database/factories/SupplierFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Lieferant '.fake()->unique()->numerify('###'),
            'contact_person' => fake()->name(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'customer_number' => fake()->unique()->numerify('KD-#####'),
            'notes' => null,
        ];
    }
}
```

`database/factories/ArticleFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Artikel '.fake()->unique()->numerify('####'),
            'description' => fake()->sentence(),
            'sku' => fake()->unique()->numerify('SKU-#####'),
            'minimum_stock' => 0,
            'barcode' => fake()->unique()->ean13(),
            'notes' => null,
        ];
    }
}
```

`database/factories/StockFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Stock;
use App\Models\StorageLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stock>
 */
class StockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'storage_location_id' => StorageLocation::factory(),
            'quantity' => fake()->numberBetween(0, 100),
        ];
    }
}
```

`database/factories/StockMovementFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'supplier_id' => null,
            'from_storage_location_id' => null,
            'to_storage_location_id' => StorageLocation::factory(),
            'quantity' => fake()->numberBetween(1, 50),
            'unit_price' => null,
            'type' => 'in',
            'user_id' => User::factory(),
            'notes' => null,
        ];
    }
}
```

- [ ] **Step 8: Test ausführen, Erfolg bestätigen**

Run: `php artisan test --compact tests/Feature/SupplierRelationTest.php`
Expected: PASS (4 Tests). `RefreshDatabase` spielt die neue Migration automatisch mit ein.

- [ ] **Step 9: Pint und Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations app/Models database/factories tests/Feature/SupplierRelationTest.php
git commit -m "Lieferanten-Schema, Modelle und fehlende Factories anlegen"
```

---

### Task 2: Lieferantenverwaltung — Controller, Routen, Tests

**Files:**
- Create: `app/Http/Controllers/SupplierManagementController.php`
- Modify: `routes/web.php` (neue Routengruppe, nach der Artikel-Gruppe einfügen)
- Test: `tests/Feature/SupplierManagementTest.php`

**Interfaces:**
- Consumes: `App\Models\Supplier` und `Supplier::factory()` aus Task 1.
- Produces: Routennamen `suppliers.index`, `suppliers.create`, `suppliers.store`, `suppliers.edit`, `suppliers.update`, `suppliers.destroy`, `suppliers.trashed`, `suppliers.restore`, `suppliers.force-delete`. Die Inertia-Komponenten `Suppliers/Index`, `Suppliers/UpsertSupplier` und `Suppliers/Trashed` werden in Task 3 angelegt.

- [ ] **Step 1: Failing test schreiben**

`tests/Feature/SupplierManagementTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_lieferant_wird_angelegt(): void
    {
        $response = $this->post(route('suppliers.store'), [
            'name' => 'Schraubenhandel Nord',
            'contact_person' => 'Maria Groß',
            'email' => 'info@schraubenhandel-nord.test',
            'phone' => '040 123456',
            'address' => 'Hafenstraße 1, 20457 Hamburg',
            'customer_number' => 'KD-4711',
            'notes' => 'Liefert dienstags.',
        ]);

        $response->assertRedirect(route('suppliers.index'));
        $response->assertSessionHas('message', 'Lieferant erfolgreich erstellt');
        $this->assertDatabaseHas('suppliers', ['name' => 'Schraubenhandel Nord']);
    }

    public function test_lieferant_ohne_namen_wird_abgelehnt(): void
    {
        $response = $this->post(route('suppliers.store'), ['name' => '']);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('suppliers', 0);
    }

    public function test_lieferant_wird_aktualisiert(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Alt']);

        $response = $this->put(route('suppliers.update', $supplier), ['name' => 'Neu']);

        $response->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'Neu']);
    }

    public function test_lieferant_wandert_in_den_papierkorb(): void
    {
        $supplier = Supplier::factory()->create();

        $response = $this->delete(route('suppliers.destroy', $supplier));

        $response->assertRedirect(route('suppliers.index'));
        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    public function test_lieferant_wird_wiederhergestellt(): void
    {
        $supplier = Supplier::factory()->create();
        $supplier->delete();

        $response = $this->put(route('suppliers.restore', $supplier->id));

        $response->assertRedirect(route('suppliers.trashed'));
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'deleted_at' => null]);
    }

    public function test_lieferant_ohne_bewegungen_wird_endgueltig_geloescht(): void
    {
        $supplier = Supplier::factory()->create();
        $supplier->delete();

        $response = $this->delete(route('suppliers.force-delete', $supplier->id));

        $response->assertRedirect(route('suppliers.trashed'));
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_lieferant_mit_bewegungen_wird_nicht_endgueltig_geloescht(): void
    {
        $supplier = Supplier::factory()->create();
        StockMovement::factory()->create(['supplier_id' => $supplier->id]);
        $supplier->delete();

        $response = $this->delete(route('suppliers.force-delete', $supplier->id));

        $response->assertSessionHasErrors('supplier');
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    public function test_lieferantenliste_ist_fuer_gaeste_gesperrt(): void
    {
        auth()->logout();

        $this->get(route('suppliers.index'))->assertRedirect(route('login'));
    }
}
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

Run: `php artisan test --compact tests/Feature/SupplierManagementTest.php`
Expected: FAIL — `Route [suppliers.store] not defined.`

- [ ] **Step 3: Controller anlegen**

```bash
php artisan make:controller SupplierManagementController --no-interaction
```

Inhalt vollständig ersetzen:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierManagementController extends Controller
{
    public function index()
    {
        return Inertia::render('Suppliers/Index', [
            'suppliers' => Supplier::withCount('articles')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Suppliers/UpsertSupplier');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->validierungsregeln());

        Supplier::create($validated);

        return redirect()->route('suppliers.index')
            ->with('message', 'Lieferant erfolgreich erstellt');
    }

    public function edit(Supplier $supplier)
    {
        return Inertia::render('Suppliers/UpsertSupplier', [
            'supplier' => $supplier,
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate($this->validierungsregeln());

        $supplier->update($validated);

        return redirect()->route('suppliers.index')
            ->with('message', 'Lieferant erfolgreich aktualisiert');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('message', 'Lieferant erfolgreich gelöscht');
    }

    public function trashed()
    {
        return Inertia::render('Suppliers/Trashed', [
            'trashedSuppliers' => Supplier::onlyTrashed()->orderBy('name')->get(),
        ]);
    }

    public function restore($id)
    {
        $supplier = Supplier::onlyTrashed()->findOrFail($id);
        $supplier->restore();

        return redirect()->route('suppliers.trashed')
            ->with('message', 'Lieferant erfolgreich wiederhergestellt');
    }

    public function forceDelete($id)
    {
        $supplier = Supplier::onlyTrashed()->findOrFail($id);

        if ($supplier->stockMovements()->exists()) {
            return redirect()->route('suppliers.trashed')
                ->withErrors([
                    'supplier' => 'Der Lieferant kann nicht endgültig gelöscht werden, weil er in Lagerbewegungen verwendet wird.',
                ]);
        }

        $supplier->forceDelete();

        return redirect()->route('suppliers.trashed')
            ->with('message', 'Lieferant endgültig gelöscht');
    }

    /**
     * Validierungsregeln für store() und update().
     *
     * @return array<string, string>
     */
    private function validierungsregeln(): array
    {
        return [
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'customer_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
```

- [ ] **Step 4: Routen ergänzen**

In `routes/web.php` den Import ergänzen:

```php
use App\Http\Controllers\SupplierManagementController;
```

Und nach der Artikel-Routengruppe folgende Gruppe einfügen:

```php
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::get('/suppliers', [SupplierManagementController::class, 'index'])->name('suppliers.index');
    Route::get('/suppliers/create', [SupplierManagementController::class, 'create'])->name('suppliers.create');
    Route::get('/suppliers/trashed', [SupplierManagementController::class, 'trashed'])->name('suppliers.trashed');
    Route::post('/suppliers', [SupplierManagementController::class, 'store'])->name('suppliers.store');
    Route::get('/suppliers/{supplier}/edit', [SupplierManagementController::class, 'edit'])->name('suppliers.edit');
    Route::put('/suppliers/{supplier}', [SupplierManagementController::class, 'update'])->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [SupplierManagementController::class, 'destroy'])->name('suppliers.destroy');
    Route::put('/suppliers/{supplier}/restore', [SupplierManagementController::class, 'restore'])->name('suppliers.restore');
    Route::delete('/suppliers/{supplier}/force', [SupplierManagementController::class, 'forceDelete'])->name('suppliers.force-delete');
});
```

- [ ] **Step 5: Test ausführen, Erfolg bestätigen**

Run: `php artisan test --compact tests/Feature/SupplierManagementTest.php`
Expected: PASS (8 Tests). `index`, `create`, `edit` und `trashed` rendern Inertia-Komponenten, die es noch nicht gibt — das ist unkritisch, weil serverseitig nur der Komponentenname gesetzt wird und keiner dieser Tests die Seiten aufruft, außer `test_lieferantenliste_ist_fuer_gaeste_gesperrt`, der vor dem Rendern umleitet.

- [ ] **Step 6: Pint und Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/SupplierManagementController.php routes/web.php tests/Feature/SupplierManagementTest.php
git commit -m "Lieferantenverwaltung mit Papierkorb-Workflow ergänzen"
```

---

### Task 3: Lieferantenverwaltung — Vue-Seiten und Navigation

**Files:**
- Create: `resources/js/Pages/Suppliers/Index.vue`
- Create: `resources/js/Pages/Suppliers/UpsertSupplier.vue`
- Create: `resources/js/Pages/Suppliers/Trashed.vue`
- Modify: `resources/js/Layouts/AppLayout.vue` (Desktop-Navigation ab Zeile 57, Mobile-Navigation ab Zeile 359)
- Test: `tests/Feature/InertiaSeitenSmokeTest.php`

**Interfaces:**
- Consumes: Routennamen und Props aus Task 2 — `suppliers` (Array mit `id`, `name`, `contact_person`, `email`, `phone`, `customer_number`, `articles_count`), `supplier` (Objekt oder `null`), `trashedSuppliers` (Array mit `id`, `name`, `deleted_at`).
- Produces: Die Inertia-Komponenten `Suppliers/Index`, `Suppliers/UpsertSupplier`, `Suppliers/Trashed`; Navigationseinträge auf `suppliers.index`.

- [ ] **Step 1: Failing test schreiben**

In `tests/Feature/InertiaSeitenSmokeTest.php` das Array in `geschuetzteSeiten()` um drei Einträge erweitern (nach `'Artikel anlegen'`):

```php
            'Lieferanten' => ['suppliers.index', 'Suppliers/Index'],
            'Lieferant anlegen' => ['suppliers.create', 'Suppliers/UpsertSupplier'],
            'Lieferanten-Papierkorb' => ['suppliers.trashed', 'Suppliers/Trashed'],
```

Außerdem am Ende der Klasse eine neue Testmethode einfügen:

```php
    public function test_bearbeitungsseite_des_lieferanten_rendert(): void
    {
        $this->actingAs(User::factory()->create());

        $supplier = \App\Models\Supplier::factory()->create();

        $this->get(route('suppliers.edit', $supplier))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Suppliers/UpsertSupplier'));
    }
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

Run: `php artisan test --compact --filter='Lieferant' tests/Feature/InertiaSeitenSmokeTest.php`
Expected: FAIL. Ohne vorherigen `npm run build` schlägt das Rendern mit `Unable to locate file in Vite manifest` fehl, sobald die Komponenten fehlen bzw. das Manifest veraltet ist.

- [ ] **Step 3: Index-Seite anlegen**

`resources/js/Pages/Suppliers/Index.vue`:

```vue
<template>
    <AppLayout title="Lieferanten">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Lieferanten
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="mb-6 flex justify-between items-center">
                        <Link
                            :href="route('suppliers.create')"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-xs hover:bg-indigo-700"
                        >
                            Neuer Lieferant
                        </Link>
                        <Link
                            :href="route('suppliers.trashed')"
                            class="text-indigo-600 hover:text-indigo-900"
                        >
                            Papierkorb
                        </Link>
                    </div>

                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Name
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Ansprechpartner
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    E-Mail
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Kundennummer
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Artikel
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Aktionen
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="supplier in suppliers" :key="supplier.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ supplier.name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ supplier.contact_person || "-" }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ supplier.email || "-" }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ supplier.customer_number || "-" }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ supplier.articles_count }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <Link
                                        :href="route('suppliers.edit', supplier.id)"
                                        class="text-indigo-600 hover:text-indigo-900 mr-2"
                                    >
                                        Bearbeiten
                                    </Link>
                                    <button
                                        @click="openDeleteDialog(supplier)"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        Löschen
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="suppliers.length === 0">
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    Keine Lieferanten vorhanden
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <Modal :show="showDeleteDialog" @close="closeDeleteDialog">
                        <div class="p-6">
                            <h2 class="text-lg font-medium text-red-900">
                                Lieferant löschen
                            </h2>
                            <p class="mt-3 text-sm text-gray-600">
                                Der Lieferant wandert in den Papierkorb und kann
                                dort wiederhergestellt werden.
                            </p>
                            <div class="mt-6 flex justify-end space-x-3">
                                <button
                                    type="button"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-xs hover:bg-gray-50"
                                    @click="closeDeleteDialog"
                                >
                                    Abbrechen
                                </button>
                                <button
                                    type="button"
                                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-xs hover:bg-red-700"
                                    @click="confirmDelete"
                                >
                                    Löschen
                                </button>
                            </div>
                        </div>
                    </Modal>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import Modal from "@/Components/Modal.vue";

defineProps({
    suppliers: Array,
});

const showDeleteDialog = ref(false);
const supplierToDelete = ref(null);
const form = useForm({});

const openDeleteDialog = (supplier) => {
    supplierToDelete.value = supplier;
    showDeleteDialog.value = true;
};

const closeDeleteDialog = () => {
    showDeleteDialog.value = false;
    supplierToDelete.value = null;
};

const confirmDelete = () => {
    if (supplierToDelete.value) {
        form.delete(route("suppliers.destroy", supplierToDelete.value.id), {
            onSuccess: () => closeDeleteDialog(),
        });
    }
};
</script>
```

- [ ] **Step 4: Upsert-Seite anlegen**

`resources/js/Pages/Suppliers/UpsertSupplier.vue`:

```vue
<template>
    <AppLayout :title="supplier ? 'Lieferant bearbeiten' : 'Neuer Lieferant'">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ supplier ? "Lieferant bearbeiten" : "Neuer Lieferant" }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div
                    class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6"
                >
                    <form @submit.prevent="submit">
                        <!-- Name -->
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >Name</label
                            >
                            <input
                                type="text"
                                v-model="form.name"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                required
                                />
                            <div
                                v-if="form.errors.name"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ form.errors.name }}
                            </div>
                        </div>

                        <!-- Ansprechpartner -->
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >Ansprechpartner</label
                            >
                            <input
                                type="text"
                                v-model="form.contact_person"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            <div
                                v-if="form.errors.contact_person"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ form.errors.contact_person }}
                            </div>
                        </div>

                        <!-- E-Mail -->
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >E-Mail</label
                            >
                            <input
                                type="email"
                                v-model="form.email"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            <div
                                v-if="form.errors.email"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ form.errors.email }}
                            </div>
                        </div>

                        <!-- Telefon -->
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >Telefon</label
                            >
                            <input
                                type="text"
                                v-model="form.phone"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            <div
                                v-if="form.errors.phone"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ form.errors.phone }}
                            </div>
                        </div>

                        <!-- Kundennummer -->
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >Kundennummer</label
                            >
                            <input
                                type="text"
                                v-model="form.customer_number"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            <div
                                v-if="form.errors.customer_number"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ form.errors.customer_number }}
                            </div>
                        </div>

                        <!-- Adresse -->
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >Adresse</label
                            >
                            <textarea
                                v-model="form.address"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                            <div
                                v-if="form.errors.address"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ form.errors.address }}
                            </div>
                        </div>

                        <!-- Notizen -->
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >Notizen</label
                            >
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                            <div
                                v-if="form.errors.notes"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ form.errors.notes }}
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex justify-end space-x-3">
                            <Link
                                :href="route('suppliers.index')"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-xs hover:bg-gray-50"
                            >
                                Abbrechen
                            </Link>
                            <button
                                type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-xs hover:bg-indigo-700"
                                :disabled="form.processing"
                            >
                                {{ supplier ? "Speichern" : "Erstellen" }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link } from "@inertiajs/vue3";

const props = defineProps({
    supplier: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    name: props.supplier?.name ?? "",
    contact_person: props.supplier?.contact_person ?? "",
    email: props.supplier?.email ?? "",
    phone: props.supplier?.phone ?? "",
    customer_number: props.supplier?.customer_number ?? "",
    address: props.supplier?.address ?? "",
    notes: props.supplier?.notes ?? "",
});

const submit = () => {
    if (props.supplier) {
        form.put(route("suppliers.update", props.supplier.id));
    } else {
        form.post(route("suppliers.store"));
    }
};
</script>
```

- [ ] **Step 5: Papierkorb-Seite anlegen**

`resources/js/Pages/Suppliers/Trashed.vue`:

```vue
<template>
    <AppLayout title="Gelöschte Lieferanten">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Gelöschte Lieferanten
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div
                    class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6"
                >
                    <div class="mb-6">
                        <Link
                            :href="route('suppliers.index')"
                            class="text-indigo-600 hover:text-indigo-900"
                        >
                            ← Zurück zur Lieferantenverwaltung
                        </Link>
                    </div>

                    <!-- forceDelete lehnt verwendete Lieferanten mit einem
                         Validierungsfehler ab. -->
                    <div
                        v-if="$page.props.errors.supplier"
                        class="mb-4 p-4 rounded-md bg-red-50 text-red-700"
                    >
                        {{ $page.props.errors.supplier }}
                    </div>

                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    Name
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    Kundennummer
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    Gelöscht am
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    Aktionen
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr
                                v-for="supplier in trashedSuppliers"
                                :key="supplier.id"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ supplier.name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ supplier.customer_number || "-" }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ formatDate(supplier.deleted_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button
                                        @click="restore(supplier)"
                                        class="text-indigo-600 hover:text-indigo-900 mr-2"
                                    >
                                        Wiederherstellen
                                    </button>
                                    <button
                                        @click="openForceDeleteDialog(supplier)"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        Endgültig löschen
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="trashedSuppliers.length === 0">
                                <td
                                    colspan="4"
                                    class="px-6 py-4 text-center text-gray-500"
                                >
                                    Keine gelöschten Lieferanten vorhanden
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <Modal
                        :show="showForceDeleteDialog"
                        @close="closeForceDeleteDialog"
                    >
                        <div class="p-6">
                            <h2 class="text-lg font-medium text-red-900">
                                Lieferant endgültig löschen
                            </h2>
                            <p class="mt-3 text-sm text-gray-600">
                                Sind Sie sicher, dass Sie diesen Lieferanten
                                endgültig löschen möchten? Diese Aktion kann
                                nicht rückgängig gemacht werden.
                            </p>
                            <div class="mt-6 flex justify-end space-x-3">
                                <button
                                    type="button"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-xs hover:bg-gray-50"
                                    @click="closeForceDeleteDialog"
                                >
                                    Abbrechen
                                </button>
                                <button
                                    type="button"
                                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-xs hover:bg-red-700"
                                    @click="confirmForceDelete"
                                >
                                    Endgültig löschen
                                </button>
                            </div>
                        </div>
                    </Modal>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import Modal from "@/Components/Modal.vue";

defineProps({
    trashedSuppliers: Array,
});

const showForceDeleteDialog = ref(false);
const supplierToDelete = ref(null);

const form = useForm({});

const formatDate = (date) => {
    return new Date(date).toLocaleDateString("de-DE");
};

const restore = (supplier) => {
    form.put(route("suppliers.restore", supplier.id));
};

const openForceDeleteDialog = (supplier) => {
    supplierToDelete.value = supplier;
    showForceDeleteDialog.value = true;
};

const closeForceDeleteDialog = () => {
    showForceDeleteDialog.value = false;
    supplierToDelete.value = null;
};

const confirmForceDelete = () => {
    if (supplierToDelete.value) {
        form.delete(
            route("suppliers.force-delete", supplierToDelete.value.id),
            {
                onSuccess: () => closeForceDeleteDialog(),
            }
        );
    }
};
</script>
```

- [ ] **Step 6: Navigation ergänzen**

In `resources/js/Layouts/AppLayout.vue` in der Desktop-Navigation direkt nach dem `NavLink` für `warehouses.index` einfügen:

```vue
                                <NavLink
                                    :href="route('suppliers.index')"
                                    :active="
                                        route().current('suppliers.index')
                                    "
                                >
                                    Lieferanten
                                </NavLink>
```

In der Mobile-Navigation direkt nach dem `ResponsiveNavLink` für `warehouses.index` einfügen:

```vue
                        <ResponsiveNavLink
                            :href="route('suppliers.index')"
                            :active="route().current('suppliers.index')"
                        >
                            Lieferanten
                        </ResponsiveNavLink>
```

Dabei den vorhandenen Fehler direkt darüber mitkorrigieren: Der `ResponsiveNavLink` auf `route('articles.index')` trägt aktuell die Beschriftung `Dashboard`. Diese in `Artikel` ändern.

- [ ] **Step 7: Bauen und Test ausführen**

```bash
npm run build
php artisan test --compact tests/Feature/InertiaSeitenSmokeTest.php
```

Expected: PASS — alle Seiten inklusive der drei neuen Lieferantenseiten.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Pages/Suppliers resources/js/Layouts/AppLayout.vue tests/Feature/InertiaSeitenSmokeTest.php
git commit -m "Lieferantenseiten und Navigationseintrag ergänzen"
```

---

### Task 4: Artikel-Lieferanten-Zuordnung — Controller, Routen, Tests

**Files:**
- Create: `app/Http/Controllers/ArticleSupplierController.php`
- Modify: `routes/web.php` (innerhalb der bestehenden Artikel-Routengruppe, neben den `articles.storage-locations.*`-Routen)
- Test: `tests/Feature/ArticleSupplierPriceTest.php`

**Interfaces:**
- Consumes: `Article::suppliers()` aus Task 1.
- Produces: Routennamen `articles.suppliers.store`, `articles.suppliers.update`, `articles.suppliers.destroy`. Invariante: Hat ein Artikel mindestens einen Lieferanten, trägt **genau einer** `is_default = true`.

- [ ] **Step 1: Failing test schreiben**

`tests/Feature/ArticleSupplierPriceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleSupplierPriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_erster_lieferant_wird_automatisch_zum_standard(): void
    {
        $article = Article::factory()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->post(route('articles.suppliers.store', $article), [
            'supplier_id' => $supplier->id,
            'price' => 2.50,
        ]);

        $response->assertRedirect(route('articles.show', $article));
        $this->assertDatabaseHas('article_supplier', [
            'article_id' => $article->id,
            'supplier_id' => $supplier->id,
            'price' => 2.50,
            'is_default' => true,
        ]);
    }

    public function test_zweiter_lieferant_wird_nicht_automatisch_zum_standard(): void
    {
        $article = Article::factory()->create();
        $ersterLieferant = Supplier::factory()->create();
        $zweiterLieferant = Supplier::factory()->create();

        $this->post(route('articles.suppliers.store', $article), [
            'supplier_id' => $ersterLieferant->id,
            'price' => 2.50,
        ]);

        $this->post(route('articles.suppliers.store', $article), [
            'supplier_id' => $zweiterLieferant->id,
            'price' => 3.00,
        ]);

        $this->assertDatabaseHas('article_supplier', [
            'supplier_id' => $ersterLieferant->id,
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('article_supplier', [
            'supplier_id' => $zweiterLieferant->id,
            'is_default' => false,
        ]);
    }

    public function test_derselbe_lieferant_wird_nicht_doppelt_zugeordnet(): void
    {
        $article = Article::factory()->create();
        $supplier = Supplier::factory()->create();

        $this->post(route('articles.suppliers.store', $article), [
            'supplier_id' => $supplier->id,
            'price' => 2.50,
        ]);

        $response = $this->post(route('articles.suppliers.store', $article), [
            'supplier_id' => $supplier->id,
            'price' => 9.99,
        ]);

        $response->assertSessionHasErrors('supplier_id');
        $this->assertDatabaseCount('article_supplier', 1);
    }

    public function test_preis_wird_aktualisiert(): void
    {
        $article = Article::factory()->create();
        $supplier = Supplier::factory()->create();
        $article->suppliers()->attach($supplier->id, ['price' => 1.00, 'is_default' => true]);

        $response = $this->put(route('articles.suppliers.update', [$article, $supplier]), [
            'price' => 1.75,
            'is_default' => true,
        ]);

        $response->assertRedirect(route('articles.show', $article));
        $this->assertDatabaseHas('article_supplier', [
            'article_id' => $article->id,
            'supplier_id' => $supplier->id,
            'price' => 1.75,
        ]);
    }

    public function test_neuer_standard_entzieht_dem_alten_die_markierung(): void
    {
        $article = Article::factory()->create();
        $alt = Supplier::factory()->create();
        $neu = Supplier::factory()->create();
        $article->suppliers()->attach($alt->id, ['price' => 1.00, 'is_default' => true]);
        $article->suppliers()->attach($neu->id, ['price' => 2.00, 'is_default' => false]);

        $this->put(route('articles.suppliers.update', [$article, $neu]), [
            'price' => 2.00,
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('article_supplier', ['supplier_id' => $alt->id, 'is_default' => false]);
        $this->assertDatabaseHas('article_supplier', ['supplier_id' => $neu->id, 'is_default' => true]);
        $this->assertSame(1, \DB::table('article_supplier')
            ->where('article_id', $article->id)
            ->where('is_default', true)
            ->count());
    }

    public function test_zuordnung_wird_geloest(): void
    {
        $article = Article::factory()->create();
        $supplier = Supplier::factory()->create();
        $article->suppliers()->attach($supplier->id, ['price' => 1.00, 'is_default' => true]);

        $response = $this->delete(route('articles.suppliers.destroy', [$article, $supplier]));

        $response->assertRedirect(route('articles.show', $article));
        $this->assertDatabaseCount('article_supplier', 0);
    }

    public function test_beim_loesen_des_standards_rueckt_ein_anderer_nach(): void
    {
        $article = Article::factory()->create();
        $standard = Supplier::factory()->create();
        $anderer = Supplier::factory()->create();
        $article->suppliers()->attach($standard->id, ['price' => 1.00, 'is_default' => true]);
        $article->suppliers()->attach($anderer->id, ['price' => 2.00, 'is_default' => false]);

        $this->delete(route('articles.suppliers.destroy', [$article, $standard]));

        $this->assertDatabaseHas('article_supplier', ['supplier_id' => $anderer->id, 'is_default' => true]);
    }

    public function test_negativer_preis_wird_abgelehnt(): void
    {
        $article = Article::factory()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->post(route('articles.suppliers.store', $article), [
            'supplier_id' => $supplier->id,
            'price' => -1,
        ]);

        $response->assertSessionHasErrors('price');
    }
}
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

Run: `php artisan test --compact tests/Feature/ArticleSupplierPriceTest.php`
Expected: FAIL — `Route [articles.suppliers.store] not defined.`

- [ ] **Step 3: Controller anlegen**

```bash
php artisan make:controller ArticleSupplierController --no-interaction
```

Inhalt vollständig ersetzen:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArticleSupplierController extends Controller
{
    /**
     * Ordnet dem Artikel einen Lieferanten mit Stückpreis zu.
     *
     * Der erste Lieferant eines Artikels wird immer zum Standard, damit beim
     * Buchen stets eine Vorauswahl existiert.
     */
    public function store(Request $request, Article $article)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'price' => 'required|numeric|min:0',
            'is_default' => 'boolean',
        ]);

        if ($article->suppliers()->whereKey($validated['supplier_id'])->exists()) {
            return back()->withErrors([
                'supplier_id' => 'Dieser Lieferant ist dem Artikel bereits zugeordnet.',
            ]);
        }

        DB::transaction(function () use ($article, $validated) {
            $alsStandard = ($validated['is_default'] ?? false) || $article->suppliers()->doesntExist();

            if ($alsStandard) {
                $this->standardZuruecksetzen($article);
            }

            $article->suppliers()->attach($validated['supplier_id'], [
                'price' => $validated['price'],
                'is_default' => $alsStandard,
            ]);
        });

        return redirect()->route('articles.show', $article)
            ->with('message', 'Lieferant erfolgreich zugeordnet');
    }

    /**
     * Ändert Stückpreis oder Standardmarkierung einer bestehenden Zuordnung.
     */
    public function update(Request $request, Article $article, Supplier $supplier)
    {
        abort_unless($article->suppliers()->whereKey($supplier->id)->exists(), 404);

        $validated = $request->validate([
            'price' => 'required|numeric|min:0',
            'is_default' => 'boolean',
        ]);

        DB::transaction(function () use ($article, $supplier, $validated) {
            $alsStandard = (bool) ($validated['is_default'] ?? false);

            if ($alsStandard) {
                $this->standardZuruecksetzen($article);
            }

            $article->suppliers()->updateExistingPivot($supplier->id, [
                'price' => $validated['price'],
                'is_default' => $alsStandard,
            ]);
        });

        return redirect()->route('articles.show', $article)
            ->with('message', 'Lieferantenpreis erfolgreich aktualisiert');
    }

    /**
     * Löst die Zuordnung. War der entfernte Lieferant der Standard, rückt
     * der nächste verbliebene nach.
     */
    public function destroy(Article $article, Supplier $supplier)
    {
        $zuordnung = $article->suppliers()->whereKey($supplier->id)->first();

        abort_unless($zuordnung !== null, 404);

        DB::transaction(function () use ($article, $supplier, $zuordnung) {
            $warStandard = (bool) $zuordnung->pivot->is_default;

            $article->suppliers()->detach($supplier->id);

            if ($warStandard) {
                $nachfolger = $article->suppliers()->first();

                if ($nachfolger !== null) {
                    $article->suppliers()->updateExistingPivot($nachfolger->id, ['is_default' => true]);
                }
            }
        });

        return redirect()->route('articles.show', $article)
            ->with('message', 'Lieferantenzuordnung erfolgreich entfernt');
    }

    /**
     * Setzt alle Standardmarkierungen des Artikels zurück. MySQL kennt keine
     * partiellen Unique-Indizes, deshalb wird die Invariante hier erzwungen.
     */
    private function standardZuruecksetzen(Article $article): void
    {
        DB::table('article_supplier')
            ->where('article_id', $article->id)
            ->update(['is_default' => false]);
    }
}
```

- [ ] **Step 4: Routen ergänzen**

In `routes/web.php` den Import ergänzen:

```php
use App\Http\Controllers\ArticleSupplierController;
```

Innerhalb der bestehenden Artikel-Routengruppe, direkt nach der Route `articles.storage-locations.correction`, einfügen:

```php
    Route::post('/articles/{article}/suppliers', [ArticleSupplierController::class, 'store'])
        ->name('articles.suppliers.store');

    Route::put('/articles/{article}/suppliers/{supplier}', [ArticleSupplierController::class, 'update'])
        ->name('articles.suppliers.update');

    Route::delete('/articles/{article}/suppliers/{supplier}', [ArticleSupplierController::class, 'destroy'])
        ->name('articles.suppliers.destroy');
```

- [ ] **Step 5: Test ausführen, Erfolg bestätigen**

Run: `php artisan test --compact tests/Feature/ArticleSupplierPriceTest.php`
Expected: PASS (8 Tests).

- [ ] **Step 6: Pint und Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ArticleSupplierController.php routes/web.php tests/Feature/ArticleSupplierPriceTest.php
git commit -m "Artikel-Lieferanten-Zuordnung mit Preis und Standardauswahl"
```

---

### Task 5: Preispflege auf der Artikelseite

**Files:**
- Create: `resources/js/Pages/Articles/Components/SupplierPriceTable.vue`
- Modify: `app/Http/Controllers/ArticleManagementController.php` (Methode `show()`, `Inertia::render`-Aufruf)
- Modify: `resources/js/Pages/Articles/Show.vue` (neuer Abschnitt zwischen "Aktuelle Bestände" und "Diagramm mit Nettobestandsänderungen", Props-Definition)
- Test: `tests/Feature/ArticleSupplierPriceTest.php` (ergänzen)

**Interfaces:**
- Consumes: Routen `articles.suppliers.store`, `articles.suppliers.update`, `articles.suppliers.destroy` aus Task 4.
- Produces: Inertia-Props auf `Articles/Show` — `article.suppliers` (Array mit `id`, `name`, `pivot.price`, `pivot.is_default`) und `availableSuppliers` (Array mit `id`, `name`). Die Komponente `SupplierPriceTable.vue` nimmt die Props `article` (Objekt) und `availableSuppliers` (Array).

`Articles/Show.vue` ist mit 701 Zeilen bereits groß — die Tabelle kommt deshalb als eigene Komponente unter `Pages/Articles/Components/`, analog zu `Pages/Warehouses/Components/`.

- [ ] **Step 1: Failing test schreiben**

In `tests/Feature/ArticleSupplierPriceTest.php` am Ende der Klasse ergänzen:

```php
    public function test_artikelseite_liefert_lieferanten_und_auswahlliste(): void
    {
        $article = Article::factory()->create();
        $zugeordnet = Supplier::factory()->create(['name' => 'Zugeordnet']);
        $frei = Supplier::factory()->create(['name' => 'Frei']);
        $article->suppliers()->attach($zugeordnet->id, ['price' => 4.20, 'is_default' => true]);

        $response = $this->get(route('articles.show', $article));

        $response->assertOk();
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Articles/Show')
            ->has('article.suppliers', 1)
            ->where('article.suppliers.0.name', 'Zugeordnet')
            ->where('article.suppliers.0.pivot.price', '4.20')
            ->has('availableSuppliers', 2)
        );
    }
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

Run: `php artisan test --compact --filter=test_artikelseite_liefert_lieferanten_und_auswahlliste`
Expected: FAIL — `Inertia property [article.suppliers] does not exist.`

- [ ] **Step 3: Controller erweitern**

In `app/Http/Controllers/ArticleManagementController.php`, Methode `show()`, den `Inertia::render`-Aufruf anpassen. Die Zeile

```php
            'article' => $article->load(['stocks.storageLocation.shelf.rack.warehouse']),
```

wird zu

```php
            'article' => $article->load(['stocks.storageLocation.shelf.rack.warehouse', 'suppliers']),
```

und direkt nach `'availableStorageLocations' => ...` ergänzen:

```php
            'availableSuppliers' => \App\Models\Supplier::orderBy('name')->get(['id', 'name']),
```

- [ ] **Step 4: Test ausführen, Erfolg bestätigen**

Run: `php artisan test --compact --filter=test_artikelseite_liefert_lieferanten_und_auswahlliste`
Expected: PASS.

Falls der Vergleich `'4.20'` fehlschlägt, weil MySQL den Wert anders formatiert zurückgibt, stattdessen `->where('article.suppliers.0.pivot.price', fn ($preis) => (float) $preis === 4.20)` verwenden.

- [ ] **Step 5: Komponente anlegen**

`resources/js/Pages/Articles/Components/SupplierPriceTable.vue`:

```vue
<template>
    <div class="bg-white shadow-sm sm:rounded-lg mb-6">
        <div class="px-4 py-5 sm:px-6 flex justify-between items-center">
            <h3 class="text-lg font-medium leading-6 text-gray-900">
                Lieferanten &amp; Preise
            </h3>
        </div>

        <div class="px-4 pb-5 sm:px-6">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Lieferant
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Stückpreis
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Standard
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Aktionen
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="supplier in article.suppliers" :key="supplier.id">
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ supplier.name }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                :value="preise[supplier.id]"
                                @input="preise[supplier.id] = $event.target.value"
                                class="w-32 rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <input
                                type="radio"
                                :checked="Boolean(supplier.pivot.is_default)"
                                @change="alsStandardSetzen(supplier)"
                                class="text-indigo-600 focus:ring-indigo-500"
                            />
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <button
                                @click="preisSpeichern(supplier)"
                                class="text-indigo-600 hover:text-indigo-900 mr-3"
                            >
                                Preis speichern
                            </button>
                            <button
                                @click="zuordnungEntfernen(supplier)"
                                class="text-red-600 hover:text-red-900"
                            >
                                Entfernen
                            </button>
                        </td>
                    </tr>
                    <tr v-if="article.suppliers.length === 0">
                        <td colspan="4" class="px-4 py-3 text-center text-gray-500">
                            Diesem Artikel ist noch kein Lieferant zugeordnet
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="mt-4 flex items-end space-x-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Lieferant
                    </label>
                    <select
                        v-model="neuerLieferantId"
                        class="mt-1 block w-64 rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    >
                        <option value="">Bitte wählen</option>
                        <option
                            v-for="option in nichtZugeordnete"
                            :key="option.id"
                            :value="option.id"
                        >
                            {{ option.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Stückpreis
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        v-model="neuerPreis"
                        class="mt-1 block w-32 rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                </div>
                <button
                    @click="zuordnen"
                    :disabled="!neuerLieferantId"
                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-xs hover:bg-indigo-700 disabled:opacity-50"
                >
                    Zuordnen
                </button>
            </div>

            <div
                v-if="$page.props.errors.supplier_id"
                class="mt-3 text-red-500 text-sm"
            >
                {{ $page.props.errors.supplier_id }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from "vue";
import { router } from "@inertiajs/vue3";

const props = defineProps({
    article: Object,
    availableSuppliers: Array,
});

const preise = reactive(
    Object.fromEntries(
        props.article.suppliers.map((supplier) => [
            supplier.id,
            supplier.pivot.price,
        ])
    )
);

const neuerLieferantId = ref("");
const neuerPreis = ref(0);

const nichtZugeordnete = computed(() => {
    const zugeordnet = props.article.suppliers.map((supplier) => supplier.id);
    return props.availableSuppliers.filter(
        (supplier) => !zugeordnet.includes(supplier.id)
    );
});

const zuordnen = () => {
    router.post(
        route("articles.suppliers.store", { article: props.article.id }),
        {
            supplier_id: neuerLieferantId.value,
            price: neuerPreis.value,
        },
        {
            onSuccess: () => {
                neuerLieferantId.value = "";
                neuerPreis.value = 0;
            },
        }
    );
};

const preisSpeichern = (supplier) => {
    router.put(
        route("articles.suppliers.update", {
            article: props.article.id,
            supplier: supplier.id,
        }),
        {
            price: preise[supplier.id],
            is_default: Boolean(supplier.pivot.is_default),
        }
    );
};

const alsStandardSetzen = (supplier) => {
    router.put(
        route("articles.suppliers.update", {
            article: props.article.id,
            supplier: supplier.id,
        }),
        {
            price: preise[supplier.id],
            is_default: true,
        }
    );
};

const zuordnungEntfernen = (supplier) => {
    if (!confirm("Möchten Sie diese Lieferantenzuordnung wirklich entfernen?")) {
        return;
    }

    router.delete(
        route("articles.suppliers.destroy", {
            article: props.article.id,
            supplier: supplier.id,
        })
    );
};
</script>
```

- [ ] **Step 6: Komponente in Show.vue einbinden**

In `resources/js/Pages/Articles/Show.vue`:

1. Im `<template>` zwischen dem schließenden `</div>` des Blocks "Aktuelle Bestände" und dem Kommentar `<!-- Diagramm mit Nettobestandsänderungen -->` einfügen:

```vue
                <!-- Lieferanten und Preise -->
                <SupplierPriceTable
                    :article="article"
                    :available-suppliers="availableSuppliers"
                />
```

2. Im `<script setup>` den Import ergänzen:

```js
import SupplierPriceTable from "@/Pages/Articles/Components/SupplierPriceTable.vue";
```

3. In `defineProps` nach `availableStorageLocations: Array,` ergänzen:

```js
    availableSuppliers: Array,
```

- [ ] **Step 7: Bauen und Tests ausführen**

```bash
npm run build
php artisan test --compact tests/Feature/ArticleSupplierPriceTest.php
```

Expected: PASS (9 Tests).

- [ ] **Step 8: Pint und Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ArticleManagementController.php resources/js/Pages/Articles tests/Feature/ArticleSupplierPriceTest.php
git commit -m "Preispflege für Lieferanten auf der Artikelseite"
```

---

### Task 6: Buchen mit Lieferant — Backend

**Files:**
- Modify: `app/Http/Controllers/StockMovementController.php` (Methoden `getLocation()` und `update()`)
- Test: `tests/Feature/StockMovementSupplierTest.php`

**Interfaces:**
- Consumes: `Article::suppliers()` aus Task 1, Pivot-Tabelle `article_supplier` aus Task 1.
- Produces:
  - `GET /stock/api/locations/{storageLocation}` liefert je Eintrag in `location.stocks` zusätzlich `suppliers` (Liste aus `id`, `name`, `price`) und `default_supplier_id` (`int|null`).
  - `POST /stock/api/movements` akzeptiert zusätzlich `supplier_id` (`nullable`), validiert gegen `article_supplier` und schreibt `supplier_id` sowie den serverseitig gelesenen `unit_price` auf die Bewegung.

- [ ] **Step 1: Failing test schreiben**

`tests/Feature/StockMovementSupplierTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Stock;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementSupplierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_lagerort_endpunkt_liefert_lieferanten_und_vorauswahl(): void
    {
        $article = Article::factory()->create();
        $location = StorageLocation::factory()->create();
        Stock::factory()->create([
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
            'quantity' => 10,
        ]);

        $standard = Supplier::factory()->create(['name' => 'Standardlieferant']);
        $weiterer = Supplier::factory()->create(['name' => 'Zweitlieferant']);
        $article->suppliers()->attach($standard->id, ['price' => 1.50, 'is_default' => true]);
        $article->suppliers()->attach($weiterer->id, ['price' => 1.80, 'is_default' => false]);

        $response = $this->getJson(route('stock.api.location.show', $location));

        $response->assertOk();
        $response->assertJsonPath('location.stocks.0.default_supplier_id', $standard->id);
        $response->assertJsonCount(2, 'location.stocks.0.suppliers');
    }

    public function test_einbuchen_schreibt_lieferant_und_stueckpreis(): void
    {
        [$article, $location, $supplier] = $this->artikelMitLieferant(1.25);

        $response = $this->postJson(route('stock.api.movement.store'), [
            'location_id' => $location->id,
            'article_id' => $article->id,
            'quantity' => 4,
            'type' => 'add',
            'supplier_id' => $supplier->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('stock_movements', [
            'article_id' => $article->id,
            'supplier_id' => $supplier->id,
            'quantity' => 4,
            'unit_price' => 1.25,
            'type' => 'in',
        ]);
    }

    public function test_ausbuchen_schreibt_lieferant_und_stueckpreis(): void
    {
        [$article, $location, $supplier] = $this->artikelMitLieferant(2.00, bestand: 10);

        $response = $this->postJson(route('stock.api.movement.store'), [
            'location_id' => $location->id,
            'article_id' => $article->id,
            'quantity' => 3,
            'type' => 'remove',
            'supplier_id' => $supplier->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('stock_movements', [
            'article_id' => $article->id,
            'supplier_id' => $supplier->id,
            'quantity' => 3,
            'unit_price' => 2.00,
            'type' => 'out',
        ]);
    }

    public function test_spaetere_preisaenderung_laesst_die_bewegung_unveraendert(): void
    {
        [$article, $location, $supplier] = $this->artikelMitLieferant(1.00);

        $this->postJson(route('stock.api.movement.store'), [
            'location_id' => $location->id,
            'article_id' => $article->id,
            'quantity' => 2,
            'type' => 'add',
            'supplier_id' => $supplier->id,
        ])->assertOk();

        $article->suppliers()->updateExistingPivot($supplier->id, ['price' => 1.50]);

        $this->assertDatabaseHas('stock_movements', [
            'article_id' => $article->id,
            'unit_price' => 1.00,
        ]);
    }

    public function test_buchung_ohne_lieferant_ist_erlaubt(): void
    {
        [$article, $location] = $this->artikelMitLieferant(1.00);

        $response = $this->postJson(route('stock.api.movement.store'), [
            'location_id' => $location->id,
            'article_id' => $article->id,
            'quantity' => 1,
            'type' => 'add',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('stock_movements', [
            'article_id' => $article->id,
            'supplier_id' => null,
            'unit_price' => null,
        ]);
    }

    public function test_nicht_zugeordneter_lieferant_wird_abgelehnt(): void
    {
        [$article, $location] = $this->artikelMitLieferant(1.00);
        $fremder = Supplier::factory()->create();

        $response = $this->postJson(route('stock.api.movement.store'), [
            'location_id' => $location->id,
            'article_id' => $article->id,
            'quantity' => 1,
            'type' => 'add',
            'supplier_id' => $fremder->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('supplier_id');
        $this->assertDatabaseCount('stock_movements', 0);
    }

    /**
     * Legt Artikel, Lagerplatz, Bestand und einen zugeordneten Lieferanten an.
     *
     * @return array{0: Article, 1: StorageLocation, 2: Supplier}
     */
    private function artikelMitLieferant(float $preis, int $bestand = 0): array
    {
        $article = Article::factory()->create();
        $location = StorageLocation::factory()->create();
        Stock::factory()->create([
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
            'quantity' => $bestand,
        ]);

        $supplier = Supplier::factory()->create();
        $article->suppliers()->attach($supplier->id, ['price' => $preis, 'is_default' => true]);

        return [$article, $location, $supplier];
    }
}
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

Run: `php artisan test --compact tests/Feature/StockMovementSupplierTest.php`
Expected: FAIL — `Property [location.stocks.0.default_supplier_id] does not exist` beim ersten Test, `Failed asserting that a row in the table [stock_movements] matches` bei den übrigen.

- [ ] **Step 3: getLocation() erweitern**

In `app/Http/Controllers/StockMovementController.php` die Methode `getLocation()` ersetzen durch:

```php
    public function getLocation(StorageLocation $storageLocation)
    {
        $location = StorageLocation::with([
            'shelf.rack.warehouse',
            'stocks' => function ($query) {
                // Nur Stocks laden, deren verknüpfte Article nicht gelöscht sind
                $query->whereHas('article', function ($query) {
                    $query->whereNull('deleted_at');
                });
            },
            'stocks.article',
            'stocks.article.suppliers',
        ])->findOrFail($storageLocation->id);

        $stocks = $location->stocks->map(function ($stock) {
            $suppliers = $stock->article->suppliers;
            $standard = $suppliers->first(fn ($supplier) => (bool) $supplier->pivot->is_default);

            return [
                'id' => $stock->article->id,
                'name' => $stock->article->name,
                'current_stock' => $stock->quantity,
                'sku' => $stock->article->sku,
                'suppliers' => $suppliers->map(fn ($supplier) => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'price' => $supplier->pivot->price,
                ])->values(),
                'default_supplier_id' => $standard?->id,
            ];
        });

        return response()->json([
            'location' => [
                'id' => $location->id,
                'name' => sprintf(
                    '%s - %s - %s - %s',
                    $location->shelf->rack->warehouse->name,
                    $location->shelf->rack->name,
                    $location->shelf->name,
                    $location->name
                ),
                'stocks' => $stocks,
            ],
        ]);
    }
```

- [ ] **Step 4: update() erweitern**

In derselben Datei den Import ergänzen:

```php
use Illuminate\Validation\Rule;
```

Im `$request->validate([...])`-Aufruf innerhalb von `update()` nach der Zeile für `'article_id'` einfügen:

```php
                'supplier_id' => [
                    'nullable',
                    // Der Lieferant muss dem Artikel zugeordnet sein.
                    Rule::exists('article_supplier', 'supplier_id')
                        ->where('article_id', $request->input('article_id')),
                ],
```

Innerhalb der `DB::transaction()`-Closure direkt nach der Zeile `$article = Article::findOrFail($validated['article_id']);` einfügen:

```php
                // Preis-Snapshot: der Stückpreis wird serverseitig aus der
                // Pivot-Tabelle gelesen, niemals aus dem Request übernommen.
                $unitPrice = null;

                if (! empty($validated['supplier_id'])) {
                    $unitPrice = DB::table('article_supplier')
                        ->where('article_id', $article->id)
                        ->where('supplier_id', $validated['supplier_id'])
                        ->value('price');
                }
```

Im `StockMovement::create([...])`-Aufruf nach `'article_id' => $article->id,` einfügen:

```php
                    'supplier_id' => $validated['supplier_id'] ?? null,
```

und nach `'quantity' => $validated['quantity'],` einfügen:

```php
                    'unit_price' => $unitPrice,
```

- [ ] **Step 5: Test ausführen, Erfolg bestätigen**

Run: `php artisan test --compact tests/Feature/StockMovementSupplierTest.php`
Expected: PASS (6 Tests).

- [ ] **Step 6: Pint und Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/StockMovementController.php tests/Feature/StockMovementSupplierTest.php
git commit -m "Lieferant und Preis-Snapshot beim Buchen erfassen"
```

---

### Task 7: Buchen mit Lieferant — Frontend

**Files:**
- Modify: `resources/js/Pages/StockMovement/Index.vue` (Formular im `v-for` über `location.stocks`, `data()` und `methods`)

**Interfaces:**
- Consumes: `suppliers` und `default_supplier_id` je Artikel aus Task 6; der Endpunkt `stock.api.movement.store` akzeptiert `supplier_id`.
- Produces: keine neuen Schnittstellen für spätere Tasks.

Diese Datei nutzt die **Options API** (`defineComponent`), nicht `<script setup>`. Den Stil beibehalten.

- [ ] **Step 1: Auswahlfeld ins Formular einfügen**

In `resources/js/Pages/StockMovement/Index.vue` direkt nach dem `<div>` mit dem Mengenfeld (also nach dem schließenden `</div>` des Blocks mit `id="quantity"`) und vor `<div class="flex space-x-4">` einfügen:

```vue
                                    <div v-if="stock.suppliers.length">
                                        <label
                                            class="block text-sm font-medium text-gray-700"
                                            >Lieferant</label
                                        >
                                        <select
                                            v-model="suppliers[stock.id]"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        >
                                            <option :value="null">
                                                — kein Lieferant —
                                            </option>
                                            <option
                                                v-for="supplier in stock.suppliers"
                                                :key="supplier.id"
                                                :value="supplier.id"
                                            >
                                                {{ supplier.name }} ({{
                                                    supplier.price
                                                }}
                                                €)
                                            </option>
                                        </select>
                                    </div>
```

- [ ] **Step 2: Zustand für die Auswahl ergänzen**

In `data()` nach `quantities: {},` einfügen:

```js
            suppliers: {},
```

- [ ] **Step 3: Vorauswahl beim Laden setzen**

In `loadLocationData()` die vorhandene Schleife

```js
                this.location.stocks?.forEach((stock) => {
                    this.quantities[stock.id] = 1;
                });
```

ersetzen durch

```js
                this.location.stocks?.forEach((stock) => {
                    this.quantities[stock.id] = 1;
                    // Vorauswahl: der als Standard markierte Lieferant des Artikels
                    this.suppliers[stock.id] = stock.default_supplier_id ?? null;
                });
```

- [ ] **Step 4: Lieferant mitsenden**

In `submitMovement()` den `axios.post`-Aufruf um das Feld erweitern — aus

```js
                    {
                        location_id: this.location.id,
                        article_id: articleId,
                        quantity: this.quantities[articleId],
                        type: moveType,
                    }
```

wird

```js
                    {
                        location_id: this.location.id,
                        article_id: articleId,
                        quantity: this.quantities[articleId],
                        type: moveType,
                        supplier_id: this.suppliers[articleId] ?? null,
                    }
```

Außerdem im `catch`-Block die Fehlerausgabe so erweitern, dass Validierungsfehler des Feldes sichtbar werden. Aus

```js
                this.showFlash(
                    error.response?.data?.message ||
                        "Fehler bei der Bestandsänderung",
                    "error"
                );
```

wird

```js
                const validierungsfehler = error.response?.data?.errors;

                this.showFlash(
                    validierungsfehler
                        ? Object.values(validierungsfehler).flat().join(" ")
                        : error.response?.data?.message ||
                              "Fehler bei der Bestandsänderung",
                    "error"
                );
```

- [ ] **Step 5: Bauen und die betroffenen Tests ausführen**

```bash
npm run build
php artisan test --compact tests/Feature/StockMovementSupplierTest.php tests/Feature/InertiaSeitenSmokeTest.php
```

Expected: PASS. Das Frontend selbst ist nicht automatisiert getestet — es gibt kein JS-Testsetup im Projekt. Die Seite danach manuell prüfen: `composer run dev`, `/stock/movements` öffnen, einen Lagerplatz-QR-Code scannen und kontrollieren, dass die Lieferantenauswahl erscheint und der Standard vorausgewählt ist.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/StockMovement/Index.vue
git commit -m "Lieferantenauswahl im Buchungsformular"
```

---

### Task 8: BalanceReportService

**Files:**
- Create: `app/Services/BalanceReportService.php`
- Test: `tests/Feature/BalanceReportServiceTest.php`

**Interfaces:**
- Consumes: `stock_movements.supplier_id` und `stock_movements.unit_price` aus Task 1.
- Produces: `App\Services\BalanceReportService::build(CarbonInterface $from, CarbonInterface $to): array` mit der Form

```php
array{
    from: CarbonInterface,
    to: CarbonInterface,
    articles: list<array{
        sku: string,
        name: string,
        rows: list<array{supplier: string, quantity: int, unit_price: float, total: float}>,
        subtotal: float
    }>,
    grand_total: float
}
```

  Artikel sind nach `sku` sortiert, Zeilen innerhalb eines Artikels nach Lieferantenname und dann Stückpreis.

- [ ] **Step 1: Failing test schreiben**

`tests/Feature/BalanceReportServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\BalanceReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private BalanceReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BalanceReportService::class);
    }

    public function test_netto_menge_und_wert_werden_je_lieferant_berechnet(): void
    {
        $article = Article::factory()->create(['sku' => 'SKU-1', 'name' => 'Schraube']);
        $supplier = Supplier::factory()->create(['name' => 'Mueller']);

        $this->bewegung($article, $supplier, 'in', 100, 1.00, '2026-01-10');
        $this->bewegung($article, $supplier, 'out', 30, 1.00, '2026-01-20');

        $bericht = $this->service->build(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

        $this->assertCount(1, $bericht['articles']);
        $this->assertSame('SKU-1', $bericht['articles'][0]['sku']);
        $this->assertSame('Schraube', $bericht['articles'][0]['name']);
        $this->assertCount(1, $bericht['articles'][0]['rows']);
        $this->assertSame('Mueller', $bericht['articles'][0]['rows'][0]['supplier']);
        $this->assertSame(70, $bericht['articles'][0]['rows'][0]['quantity']);
        $this->assertSame(1.00, $bericht['articles'][0]['rows'][0]['unit_price']);
        $this->assertSame(70.00, $bericht['articles'][0]['rows'][0]['total']);
        $this->assertSame(70.00, $bericht['articles'][0]['subtotal']);
        $this->assertSame(70.00, $bericht['grand_total']);
    }

    public function test_preisaenderung_im_zeitraum_erzeugt_zwei_zeilen(): void
    {
        $article = Article::factory()->create(['sku' => 'SKU-1']);
        $supplier = Supplier::factory()->create(['name' => 'Mueller']);

        $this->bewegung($article, $supplier, 'in', 100, 1.00, '2026-01-05');
        $this->bewegung($article, $supplier, 'in', 50, 1.50, '2026-01-25');

        $bericht = $this->service->build(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

        $zeilen = $bericht['articles'][0]['rows'];

        $this->assertCount(2, $zeilen);
        $this->assertSame(1.00, $zeilen[0]['unit_price']);
        $this->assertSame(100, $zeilen[0]['quantity']);
        $this->assertSame(1.50, $zeilen[1]['unit_price']);
        $this->assertSame(50, $zeilen[1]['quantity']);
        $this->assertSame(175.00, $bericht['grand_total']);
    }

    public function test_zeitraumgrenzen_sind_inklusiv(): void
    {
        $article = Article::factory()->create();
        $supplier = Supplier::factory()->create();

        $this->bewegung($article, $supplier, 'in', 1, 1.00, '2025-12-31 23:59:00');
        $this->bewegung($article, $supplier, 'in', 10, 1.00, '2026-01-01 00:01:00');
        $this->bewegung($article, $supplier, 'in', 100, 1.00, '2026-01-31 23:59:00');
        $this->bewegung($article, $supplier, 'in', 1000, 1.00, '2026-02-01 00:01:00');

        $bericht = $this->service->build(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

        $this->assertSame(110.00, $bericht['grand_total']);
    }

    public function test_bewegungen_ohne_lieferant_erscheinen_als_eigene_zeile(): void
    {
        $article = Article::factory()->create();

        $this->bewegung($article, null, 'in', 5, null, '2026-01-10');

        $bericht = $this->service->build(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

        $this->assertSame('Ohne Lieferant', $bericht['articles'][0]['rows'][0]['supplier']);
        $this->assertSame(5, $bericht['articles'][0]['rows'][0]['quantity']);
        $this->assertSame(0.0, $bericht['articles'][0]['rows'][0]['total']);
    }

    public function test_transfer_und_korrektur_bleiben_draussen(): void
    {
        $article = Article::factory()->create();
        $supplier = Supplier::factory()->create();

        $this->bewegung($article, $supplier, 'transfer', 10, 1.00, '2026-01-10');
        $this->bewegung($article, $supplier, 'correction', 10, 1.00, '2026-01-10');

        $bericht = $this->service->build(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

        $this->assertSame([], $bericht['articles']);
        $this->assertSame(0.0, $bericht['grand_total']);
    }

    public function test_geloeschte_stammdaten_bleiben_in_der_bilanz(): void
    {
        $article = Article::factory()->create(['sku' => 'SKU-9', 'name' => 'Altteil']);
        $supplier = Supplier::factory()->create(['name' => 'Ehemalig']);

        $this->bewegung($article, $supplier, 'in', 2, 5.00, '2026-01-10');

        $article->delete();
        $supplier->delete();

        $bericht = $this->service->build(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

        $this->assertSame('Altteil', $bericht['articles'][0]['name']);
        $this->assertSame('Ehemalig', $bericht['articles'][0]['rows'][0]['supplier']);
    }

    public function test_negative_nettomenge_wird_ausgewiesen(): void
    {
        $article = Article::factory()->create();
        $supplier = Supplier::factory()->create();

        $this->bewegung($article, $supplier, 'out', 4, 2.00, '2026-01-10');

        $bericht = $this->service->build(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

        $this->assertSame(-4, $bericht['articles'][0]['rows'][0]['quantity']);
        $this->assertSame(-8.00, $bericht['grand_total']);
    }

    private function bewegung(
        Article $article,
        ?Supplier $supplier,
        string $typ,
        int $menge,
        ?float $preis,
        string $zeitpunkt
    ): void {
        StockMovement::factory()->create([
            'article_id' => $article->id,
            'supplier_id' => $supplier?->id,
            'type' => $typ,
            'quantity' => $menge,
            'unit_price' => $preis,
            'created_at' => Carbon::parse($zeitpunkt),
        ]);
    }
}
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

Run: `php artisan test --compact tests/Feature/BalanceReportServiceTest.php`
Expected: FAIL — `Target class [App\Services\BalanceReportService] does not exist.`

- [ ] **Step 3: Service anlegen**

```bash
php artisan make:class Services/BalanceReportService --no-interaction
```

Inhalt vollständig ersetzen:

```php
<?php

namespace App\Services;

use App\Models\Article;
use App\Models\StockMovement;
use App\Models\Supplier;
use Carbon\CarbonInterface;

class BalanceReportService
{
    /**
     * Baut die Bestandsbilanz für einen Zeitraum auf.
     *
     * Gruppiert wird nach Artikel, Lieferant und Stückpreis, damit
     * Menge × Stückpreis in jeder Zeile exakt den Gesamtwert ergibt, auch
     * wenn sich ein Preis innerhalb des Zeitraums geändert hat.
     *
     * @return array{
     *     from: CarbonInterface,
     *     to: CarbonInterface,
     *     articles: list<array{
     *         sku: string,
     *         name: string,
     *         rows: list<array{supplier: string, quantity: int, unit_price: float, total: float}>,
     *         subtotal: float
     *     }>,
     *     grand_total: float
     * }
     */
    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $aggregat = StockMovement::query()
            ->selectRaw('article_id, supplier_id, unit_price')
            ->selectRaw("sum(case when type = 'in' then quantity else 0 end) as qty_in")
            ->selectRaw("sum(case when type = 'out' then quantity else 0 end) as qty_out")
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereIn('type', ['in', 'out'])
            ->groupBy('article_id', 'supplier_id', 'unit_price')
            ->get();

        $artikelStammdaten = Article::withTrashed()
            ->whereIn('id', $aggregat->pluck('article_id')->unique())
            ->get()
            ->keyBy('id');

        $lieferantenNamen = Supplier::withTrashed()
            ->whereIn('id', $aggregat->pluck('supplier_id')->filter()->unique())
            ->pluck('name', 'id');

        $artikel = [];
        $gesamtsumme = 0.0;

        foreach ($aggregat->groupBy('article_id') as $articleId => $aggregatZeilen) {
            $stammdaten = $artikelStammdaten->get($articleId);
            $zeilen = [];
            $zwischensumme = 0.0;

            foreach ($aggregatZeilen as $aggregatZeile) {
                $menge = (int) $aggregatZeile->qty_in - (int) $aggregatZeile->qty_out;
                $stueckpreis = (float) $aggregatZeile->unit_price;
                $wert = round($menge * $stueckpreis, 2);
                $zwischensumme += $wert;

                $zeilen[] = [
                    'supplier' => $aggregatZeile->supplier_id === null
                        ? 'Ohne Lieferant'
                        : ($lieferantenNamen[$aggregatZeile->supplier_id] ?? 'Unbekannter Lieferant'),
                    'quantity' => $menge,
                    'unit_price' => $stueckpreis,
                    'total' => $wert,
                ];
            }

            usort($zeilen, fn ($a, $b) => [$a['supplier'], $a['unit_price']] <=> [$b['supplier'], $b['unit_price']]);

            $zwischensumme = round($zwischensumme, 2);
            $gesamtsumme += $zwischensumme;

            $artikel[] = [
                'sku' => $stammdaten?->sku ?? '-',
                'name' => $stammdaten?->name ?? 'Unbekannter Artikel',
                'rows' => $zeilen,
                'subtotal' => $zwischensumme,
            ];
        }

        usort($artikel, fn ($a, $b) => strcmp($a['sku'], $b['sku']));

        return [
            'from' => $from,
            'to' => $to,
            'articles' => $artikel,
            'grand_total' => round($gesamtsumme, 2),
        ];
    }
}
```

- [ ] **Step 4: Test ausführen, Erfolg bestätigen**

Run: `php artisan test --compact tests/Feature/BalanceReportServiceTest.php`
Expected: PASS (7 Tests).

- [ ] **Step 5: Pint und Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/BalanceReportService.php tests/Feature/BalanceReportServiceTest.php
git commit -m "BalanceReportService für die Bestandsbilanz"
```

---

### Task 9: Bilanzseite mit Vorschau

**Files:**
- Create: `app/Http/Controllers/BalanceReportController.php`
- Create: `resources/js/Pages/Reports/Balance.vue`
- Modify: `routes/web.php` (neue Routengruppe `reports.*`)
- Modify: `resources/js/Layouts/AppLayout.vue` (Navigationseinträge "Bilanz")
- Test: `tests/Feature/BalanceReportPageTest.php`, `tests/Feature/InertiaSeitenSmokeTest.php`

**Interfaces:**
- Consumes: `BalanceReportService::build()` aus Task 8.
- Produces:
  - Route `reports.balance.index` (`GET /reports/balance`, Query `from`, `to`), rendert `Reports/Balance` mit den Props `from` (String `Y-m-d`), `to` (String `Y-m-d`), `articles` (siehe Task 8) und `grandTotal` (float).
  - `BalanceReportController::zeitraum(Request): array{0: Carbon, 1: Carbon}` als private Helfermethode — Task 10 baut darauf auf.

- [ ] **Step 1: Failing test schreiben**

`tests/Feature/BalanceReportPageTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BalanceReportPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_seite_zeigt_standardmaessig_den_laufenden_monat(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-17 10:00:00'));

        $response = $this->get(route('reports.balance.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Reports/Balance')
            ->where('from', '2026-03-01')
            ->where('to', '2026-03-17')
        );

        Carbon::setTestNow();
    }

    public function test_seite_liefert_die_aggregierten_zeilen(): void
    {
        $article = Article::factory()->create(['sku' => 'SKU-7', 'name' => 'Mutter']);
        $supplier = Supplier::factory()->create(['name' => 'Mueller']);

        StockMovement::factory()->create([
            'article_id' => $article->id,
            'supplier_id' => $supplier->id,
            'type' => 'in',
            'quantity' => 10,
            'unit_price' => 2.00,
            'created_at' => Carbon::parse('2026-01-15'),
        ]);

        $response = $this->get(route('reports.balance.index', [
            'from' => '2026-01-01',
            'to' => '2026-01-31',
        ]));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Reports/Balance')
            ->has('articles', 1)
            ->where('articles.0.sku', 'SKU-7')
            ->where('articles.0.rows.0.supplier', 'Mueller')
            ->where('articles.0.rows.0.quantity', 10)
            ->where('grandTotal', 20.0)
        );
    }

    public function test_bis_datum_vor_von_datum_wird_abgelehnt(): void
    {
        $response = $this->get(route('reports.balance.index', [
            'from' => '2026-03-31',
            'to' => '2026-03-01',
        ]));

        $response->assertSessionHasErrors('to');
    }

    public function test_bilanzseite_ist_fuer_gaeste_gesperrt(): void
    {
        auth()->logout();

        $this->get(route('reports.balance.index'))->assertRedirect(route('login'));
    }
}
```

In `tests/Feature/InertiaSeitenSmokeTest.php` das Array `geschuetzteSeiten()` ergänzen:

```php
            'Bilanz' => ['reports.balance.index', 'Reports/Balance'],
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

Run: `php artisan test --compact tests/Feature/BalanceReportPageTest.php`
Expected: FAIL — `Route [reports.balance.index] not defined.`

- [ ] **Step 3: Controller anlegen**

```bash
php artisan make:controller BalanceReportController --no-interaction
```

Inhalt vollständig ersetzen:

```php
<?php

namespace App\Http\Controllers;

use App\Services\BalanceReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BalanceReportController extends Controller
{
    public function __construct(private BalanceReportService $balanceReport) {}

    public function index(Request $request)
    {
        [$from, $to] = $this->zeitraum($request);

        $bericht = $this->balanceReport->build($from, $to);

        return Inertia::render('Reports/Balance', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'articles' => $bericht['articles'],
            'grandTotal' => $bericht['grand_total'],
        ]);
    }

    /**
     * Ermittelt den auszuwertenden Zeitraum. Ohne Angabe gilt der laufende
     * Monat vom Monatsersten bis heute.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function zeitraum(Request $request): array
    {
        $validated = $request->validate([
            'from' => 'nullable|date|required_with:to',
            'to' => 'nullable|date|required_with:from|after_or_equal:from',
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])
            : Carbon::now()->startOfMonth();

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])
            : Carbon::now();

        return [$from->startOfDay(), $to->endOfDay()];
    }
}
```

- [ ] **Step 4: Routen ergänzen**

In `routes/web.php` den Import ergänzen:

```php
use App\Http\Controllers\BalanceReportController;
```

Am Ende der Datei einfügen:

```php
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/balance', [BalanceReportController::class, 'index'])->name('balance.index');
    });
});
```

- [ ] **Step 5: Vue-Seite anlegen**

`resources/js/Pages/Reports/Balance.vue`:

```vue
<template>
    <AppLayout title="Bilanz">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Bestandsbilanz
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="mb-6 flex flex-wrap items-end gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Von
                            </label>
                            <input
                                type="date"
                                v-model="von"
                                class="mt-1 block rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Bis
                            </label>
                            <input
                                type="date"
                                v-model="bis"
                                class="mt-1 block rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                        </div>
                        <button
                            @click="aktualisieren"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-xs hover:bg-indigo-700"
                        >
                            Anzeigen
                        </button>
                        <button
                            @click="exportieren('pdf')"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-xs hover:bg-gray-50"
                        >
                            PDF
                        </button>
                        <button
                            @click="exportieren('xlsx')"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-xs hover:bg-gray-50"
                        >
                            Excel
                        </button>
                    </div>

                    <div
                        v-if="$page.props.errors.to"
                        class="mb-4 p-4 rounded-md bg-red-50 text-red-700"
                    >
                        {{ $page.props.errors.to }}
                    </div>

                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Artikelnummer
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Bezeichnung
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Lieferant
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Menge
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Stückpreis
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Gesamtwert
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template
                                v-for="artikel in articles"
                                :key="artikel.sku"
                            >
                                <tr
                                    v-for="(zeile, index) in artikel.rows"
                                    :key="artikel.sku + '-' + index"
                                >
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ index === 0 ? artikel.sku : "" }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ index === 0 ? artikel.name : "" }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ zeile.supplier }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        {{ zeile.quantity }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        {{ waehrung(zeile.unit_price) }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        {{ waehrung(zeile.total) }}
                                    </td>
                                </tr>
                                <tr class="bg-gray-50 font-medium">
                                    <td colspan="5" class="px-4 py-2 text-right">
                                        Zwischensumme {{ artikel.name }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        {{ waehrung(artikel.subtotal) }}
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="articles.length === 0">
                                <td colspan="6" class="px-4 py-4 text-center text-gray-500">
                                    Im gewählten Zeitraum gibt es keine
                                    Bestandsbewegungen
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-300 font-semibold">
                                <td colspan="5" class="px-4 py-3 text-right">
                                    Gesamtsumme
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ waehrung(grandTotal) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";

const props = defineProps({
    from: String,
    to: String,
    articles: Array,
    grandTotal: Number,
});

const von = ref(props.from);
const bis = ref(props.to);

const aktualisieren = () => {
    router.get(
        route("reports.balance.index"),
        { from: von.value, to: bis.value },
        { preserveState: true, preserveScroll: true }
    );
};

const exportieren = (format) => {
    window.location.href = route("reports.balance.export", {
        from: von.value,
        to: bis.value,
        format,
    });
};

const waehrung = (wert) =>
    new Intl.NumberFormat("de-DE", {
        style: "currency",
        currency: "EUR",
    }).format(wert);
</script>
```

Hinweis: `exportieren()` zeigt auf `reports.balance.export`, die erst in Task 10 existiert. Bis dahin wirft Ziggy beim Klick einen Fehler — das ist gewollt, die Route folgt im nächsten Task.

- [ ] **Step 6: Navigation ergänzen**

In `resources/js/Layouts/AppLayout.vue` in der Desktop-Navigation nach dem Lieferanten-`NavLink` einfügen:

```vue
                                <NavLink
                                    :href="route('reports.balance.index')"
                                    :active="
                                        route().current('reports.balance.index')
                                    "
                                >
                                    Bilanz
                                </NavLink>
```

Und in der Mobile-Navigation nach dem Lieferanten-`ResponsiveNavLink`:

```vue
                        <ResponsiveNavLink
                            :href="route('reports.balance.index')"
                            :active="route().current('reports.balance.index')"
                        >
                            Bilanz
                        </ResponsiveNavLink>
```

- [ ] **Step 7: Bauen und Tests ausführen**

```bash
npm run build
php artisan test --compact tests/Feature/BalanceReportPageTest.php tests/Feature/InertiaSeitenSmokeTest.php
```

Expected: PASS.

- [ ] **Step 8: Pint und Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/BalanceReportController.php resources/js/Pages/Reports resources/js/Layouts/AppLayout.vue routes/web.php tests/Feature/BalanceReportPageTest.php tests/Feature/InertiaSeitenSmokeTest.php
git commit -m "Bilanzseite mit Zeitraumfilter und Vorschau"
```

---

### Task 10: PDF-Export

**Files:**
- Modify: `composer.json`, `composer.lock` (Paket `barryvdh/laravel-dompdf`)
- Create: `resources/views/reports/balance.blade.php`
- Modify: `app/Http/Controllers/BalanceReportController.php` (Methoden `export()` und `alsPdf()`)
- Modify: `routes/web.php` (Route `reports.balance.export`)
- Test: `tests/Feature/BalanceReportExportTest.php`

**Interfaces:**
- Consumes: `BalanceReportService::build()` aus Task 8, `BalanceReportController` aus Task 9.
- Produces:
  - Route `reports.balance.export` (`GET /reports/balance/export`, Query `from`, `to`, `format`), `format` ist `pdf` oder `xlsx`. In diesem Task ist nur `pdf` implementiert; `xlsx` folgt in Task 11.
  - Blade-View `reports.balance`, erwartet die Variablen `$from`, `$to`, `$articles`, `$grand_total`.

- [ ] **Step 1: Paket installieren**

```bash
composer require barryvdh/laravel-dompdf:^3.1 --no-interaction
```

Der Service Provider wird per Auto-Discovery registriert — keine manuelle Eintragung nötig.

- [ ] **Step 2: Failing test schreiben**

`tests/Feature/BalanceReportExportTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BalanceReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_pdf_wird_als_download_ausgeliefert(): void
    {
        $this->bewegungAnlegen();

        $response = $this->get(route('reports.balance.export', [
            'from' => '2026-01-01',
            'to' => '2026-01-31',
            'format' => 'pdf',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertDownload('bilanz_2026-01-01_bis_2026-01-31.pdf');
        $this->assertNotEmpty($response->getContent());
    }

    public function test_pdf_view_enthaelt_tabelle_summen_und_unterschriftenblock(): void
    {
        $this->bewegungAnlegen();

        $bericht = app(BalanceReportService::class)->build(
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-01-31')
        );

        $html = view('reports.balance', $bericht)->render();

        $this->assertStringContainsString('Bestandsbilanz', $html);
        $this->assertStringContainsString('Artikelnummer', $html);
        $this->assertStringContainsString('SKU-7', $html);
        $this->assertStringContainsString('Mueller', $html);
        $this->assertStringContainsString('Gesamtsumme', $html);
        $this->assertStringContainsString('Ort, Datum', $html);
        $this->assertStringContainsString('Unterschrift', $html);
        $this->assertStringContainsString('01.01.2026', $html);
        $this->assertStringContainsString('31.01.2026', $html);
    }

    public function test_unbekanntes_format_wird_abgelehnt(): void
    {
        $response = $this->get(route('reports.balance.export', [
            'from' => '2026-01-01',
            'to' => '2026-01-31',
            'format' => 'docx',
        ]));

        $response->assertSessionHasErrors('format');
    }

    public function test_export_ohne_zeitraum_wird_abgelehnt(): void
    {
        $response = $this->get(route('reports.balance.export', ['format' => 'pdf']));

        $response->assertSessionHasErrors(['from', 'to']);
    }

    public function test_export_ist_fuer_gaeste_gesperrt(): void
    {
        auth()->logout();

        $this->get(route('reports.balance.export', [
            'from' => '2026-01-01',
            'to' => '2026-01-31',
            'format' => 'pdf',
        ]))->assertRedirect(route('login'));
    }

    protected function bewegungAnlegen(): void
    {
        $article = Article::factory()->create(['sku' => 'SKU-7', 'name' => 'Mutter']);
        $supplier = Supplier::factory()->create(['name' => 'Mueller']);

        StockMovement::factory()->create([
            'article_id' => $article->id,
            'supplier_id' => $supplier->id,
            'type' => 'in',
            'quantity' => 10,
            'unit_price' => 2.00,
            'created_at' => Carbon::parse('2026-01-15'),
        ]);
    }
}
```

- [ ] **Step 3: Test ausführen, Fehlschlag bestätigen**

Run: `php artisan test --compact tests/Feature/BalanceReportExportTest.php`
Expected: FAIL — `Route [reports.balance.export] not defined.`

- [ ] **Step 4: Blade-View anlegen**

`resources/views/reports/balance.blade.php`:

```blade
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Bestandsbilanz</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .kopf { margin-bottom: 16px; }
        .kopf div { margin-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; border-bottom: 1px solid #333; padding: 4px; font-size: 9px; text-transform: uppercase; }
        td { padding: 4px; border-bottom: 1px solid #ddd; }
        .rechts { text-align: right; }
        .zwischensumme td { background: #f3f3f3; font-weight: bold; border-bottom: 1px solid #bbb; }
        .gesamtsumme td { border-top: 2px solid #333; border-bottom: none; font-weight: bold; font-size: 11px; }
        .unterschrift { margin-top: 60px; width: 100%; }
        .unterschrift td { border: none; padding-top: 4px; }
        .linie { border-top: 1px solid #333; width: 220px; }
        .leer { text-align: center; padding: 20px; color: #666; }
    </style>
</head>
<body>
    <div class="kopf">
        <h1>Bestandsbilanz</h1>
        <div>Zeitraum: {{ $from->format('d.m.Y') }} &ndash; {{ $to->format('d.m.Y') }}</div>
        <div>Erstellt am: {{ now()->format('d.m.Y') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Artikelnummer</th>
                <th>Bezeichnung</th>
                <th>Lieferant</th>
                <th class="rechts">Menge</th>
                <th class="rechts">St&uuml;ckpreis</th>
                <th class="rechts">Gesamtwert</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($articles as $artikel)
                @foreach ($artikel['rows'] as $index => $zeile)
                    <tr>
                        <td>{{ $index === 0 ? $artikel['sku'] : '' }}</td>
                        <td>{{ $index === 0 ? $artikel['name'] : '' }}</td>
                        <td>{{ $zeile['supplier'] }}</td>
                        <td class="rechts">{{ $zeile['quantity'] }}</td>
                        <td class="rechts">{{ number_format($zeile['unit_price'], 2, ',', '.') }} &euro;</td>
                        <td class="rechts">{{ number_format($zeile['total'], 2, ',', '.') }} &euro;</td>
                    </tr>
                @endforeach
                <tr class="zwischensumme">
                    <td colspan="5" class="rechts">Zwischensumme {{ $artikel['name'] }}</td>
                    <td class="rechts">{{ number_format($artikel['subtotal'], 2, ',', '.') }} &euro;</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="leer">Im gew&auml;hlten Zeitraum gibt es keine Bestandsbewegungen</td>
                </tr>
            @endforelse
            <tr class="gesamtsumme">
                <td colspan="5" class="rechts">Gesamtsumme</td>
                <td class="rechts">{{ number_format($grand_total, 2, ',', '.') }} &euro;</td>
            </tr>
        </tbody>
    </table>

    <table class="unterschrift">
        <tr>
            <td><div class="linie"></div></td>
            <td><div class="linie"></div></td>
        </tr>
        <tr>
            <td>Ort, Datum</td>
            <td>Unterschrift</td>
        </tr>
    </table>
</body>
</html>
```

Die Umlaute stehen hier bewusst als HTML-Entities (`&uuml;`, `&auml;`, `&euro;`), damit Dompdf sie unabhängig von der Schriftkonfiguration korrekt setzt.

- [ ] **Step 5: Controller um export() erweitern**

In `app/Http/Controllers/BalanceReportController.php` die Imports ergänzen:

```php
use Barryvdh\DomPDF\Facade\Pdf;
```

Und nach `index()` einfügen:

```php
    public function export(Request $request)
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'format' => 'required|in:pdf,xlsx',
        ]);

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();

        $bericht = $this->balanceReport->build($from, $to);

        $dateiname = sprintf(
            'bilanz_%s_bis_%s',
            $from->format('Y-m-d'),
            $to->format('Y-m-d')
        );

        return $this->alsPdf($bericht, $dateiname);
    }

    /**
     * @param array<string, mixed> $bericht
     */
    private function alsPdf(array $bericht, string $dateiname)
    {
        return Pdf::loadView('reports.balance', $bericht)
            ->setPaper('a4')
            ->download($dateiname.'.pdf');
    }
```

Der Zweig für `xlsx` folgt in Task 11.

- [ ] **Step 6: Route ergänzen**

In `routes/web.php` in der `reports`-Gruppe nach der `balance.index`-Route einfügen:

```php
        Route::get('/balance/export', [BalanceReportController::class, 'export'])->name('balance.export');
```

- [ ] **Step 7: Test ausführen, Erfolg bestätigen**

Run: `php artisan test --compact tests/Feature/BalanceReportExportTest.php`
Expected: PASS (5 Tests). Falls `test_unbekanntes_format_wird_abgelehnt` fehlschlägt, weil `format=xlsx` noch nicht existiert: Dieser Test prüft `docx`, nicht `xlsx` — `xlsx` besteht die Validierung bereits, schlägt aber in `export()` fehl, bis Task 11 den Zweig ergänzt.

- [ ] **Step 8: Pint und Commit**

```bash
vendor/bin/pint --dirty --format agent
git add composer.json composer.lock app/Http/Controllers/BalanceReportController.php resources/views/reports routes/web.php tests/Feature/BalanceReportExportTest.php
git commit -m "PDF-Export der Bestandsbilanz"
```

---

### Task 11: XLSX-Export

**Files:**
- Modify: `composer.json`, `composer.lock` (Paket `openspout/openspout`)
- Modify: `app/Http/Controllers/BalanceReportController.php` (Methoden `export()` und neu `alsXlsx()`)
- Test: `tests/Feature/BalanceReportExportTest.php` (ergänzen)

**Interfaces:**
- Consumes: `export()` und `BalanceReportService::build()` aus Task 8 und 10.
- Produces: `format=xlsx` liefert eine XLSX-Datei mit denselben sechs Spalten wie das PDF, Zwischensummen, Gesamtsumme und Unterschriftenblock.

**OpenSpout 5 — verifizierte API:** `new Writer()` ohne Argumente, `openToFile('php://output')`, `Row::fromValues(array $werte)` (zweiter Parameter ist die Zeilenhöhe, **nicht** ein Style), `Row::fromValuesWithStyle(array $werte, Style $stil)`, `(new Style())->withFontBold(true)` (unveränderlich, gibt eine neue Instanz zurück — **kein** `setFontBold()`). Leere Zeilen (`Row::fromValues([])`) werden geschrieben, vom Reader beim Zurücklesen aber übersprungen — im Test deshalb nie über Zeilenindizes, sondern über den Zelleninhalt suchen.

- [ ] **Step 1: Paket installieren**

```bash
composer require openspout/openspout:^5.11 --no-interaction
```

- [ ] **Step 2: Failing test schreiben**

In `tests/Feature/BalanceReportExportTest.php` die Imports ergänzen:

```php
use OpenSpout\Reader\XLSX\Reader;
```

Und am Ende der Klasse einfügen:

```php
    public function test_xlsx_wird_als_download_ausgeliefert(): void
    {
        $this->bewegungAnlegen();

        $response = $this->get(route('reports.balance.export', [
            'from' => '2026-01-01',
            'to' => '2026-01-31',
            'format' => 'xlsx',
        ]));

        $response->assertOk();
        $response->assertDownload('bilanz_2026-01-01_bis_2026-01-31.xlsx');
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function test_xlsx_enthaelt_tabelle_summen_und_unterschriftenblock(): void
    {
        $this->bewegungAnlegen();

        $response = $this->get(route('reports.balance.export', [
            'from' => '2026-01-01',
            'to' => '2026-01-31',
            'format' => 'xlsx',
        ]));

        $pfad = tempnam(sys_get_temp_dir(), 'bilanz').'.xlsx';
        file_put_contents($pfad, $response->streamedContent());

        $zeilen = [];
        $reader = new Reader();
        $reader->open($pfad);

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $zeilen[] = $row->toArray();
            }
        }

        $reader->close();
        unlink($pfad);

        $ersteSpalte = array_map(fn ($zeile) => (string) ($zeile[0] ?? ''), $zeilen);
        $zweiteSpalte = array_map(fn ($zeile) => (string) ($zeile[1] ?? ''), $zeilen);

        $this->assertContains('Bestandsbilanz', $ersteSpalte);
        $this->assertContains('Artikelnummer', $ersteSpalte);
        $this->assertContains('SKU-7', $ersteSpalte);
        $this->assertContains('Ort, Datum', $ersteSpalte);
        $this->assertContains('Gesamtsumme', $zweiteSpalte);

        $datenzeile = collect($zeilen)->first(fn ($zeile) => ($zeile[0] ?? null) === 'SKU-7');

        $this->assertSame('Mutter', $datenzeile[1]);
        $this->assertSame('Mueller', $datenzeile[2]);
        $this->assertEquals(10, $datenzeile[3]);
        $this->assertEquals(2.0, $datenzeile[4]);
        $this->assertEquals(20.0, $datenzeile[5]);

        $summenzeile = collect($zeilen)->first(fn ($zeile) => ($zeile[1] ?? null) === 'Gesamtsumme');

        $this->assertEquals(20.0, $summenzeile[5]);
    }
```

- [ ] **Step 3: Test ausführen, Fehlschlag bestätigen**

Run: `php artisan test --compact --filter=xlsx tests/Feature/BalanceReportExportTest.php`
Expected: FAIL — der Aufruf mit `format=xlsx` liefert weiterhin ein PDF, `assertDownload` schlägt auf den Dateinamen fehl.

- [ ] **Step 4: Controller um alsXlsx() erweitern**

In `app/Http/Controllers/BalanceReportController.php` die Imports ergänzen:

```php
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
```

In `export()` die Zeile

```php
        return $this->alsPdf($bericht, $dateiname);
```

ersetzen durch

```php
        return $validated['format'] === 'pdf'
            ? $this->alsPdf($bericht, $dateiname)
            : $this->alsXlsx($bericht, $dateiname);
```

Und nach `alsPdf()` einfügen:

```php
    /**
     * @param array<string, mixed> $bericht
     */
    private function alsXlsx(array $bericht, string $dateiname)
    {
        return response()->streamDownload(function () use ($bericht) {
            $fett = (new Style())->withFontBold(true);

            $writer = new Writer();
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValuesWithStyle(['Bestandsbilanz'], $fett));
            $writer->addRow(Row::fromValues([sprintf(
                'Zeitraum: %s - %s',
                $bericht['from']->format('d.m.Y'),
                $bericht['to']->format('d.m.Y')
            )]));
            $writer->addRow(Row::fromValues(['Erstellt am: '.now()->format('d.m.Y')]));
            $writer->addRow(Row::fromValues([]));

            $writer->addRow(Row::fromValuesWithStyle(
                ['Artikelnummer', 'Bezeichnung', 'Lieferant', 'Menge', 'Stückpreis', 'Gesamtwert'],
                $fett
            ));

            foreach ($bericht['articles'] as $artikel) {
                foreach ($artikel['rows'] as $index => $zeile) {
                    $writer->addRow(Row::fromValues([
                        0 === $index ? $artikel['sku'] : '',
                        0 === $index ? $artikel['name'] : '',
                        $zeile['supplier'],
                        $zeile['quantity'],
                        $zeile['unit_price'],
                        $zeile['total'],
                    ]));
                }

                $writer->addRow(Row::fromValuesWithStyle(
                    ['', 'Zwischensumme '.$artikel['name'], '', '', '', $artikel['subtotal']],
                    $fett
                ));
            }

            $writer->addRow(Row::fromValuesWithStyle(
                ['', 'Gesamtsumme', '', '', '', $bericht['grand_total']],
                $fett
            ));

            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['_______________________', '', '_______________________']));
            $writer->addRow(Row::fromValues(['Ort, Datum', '', 'Unterschrift']));

            $writer->close();
        }, $dateiname.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
```

**Achtung:** Der Test erwartet `'SKU-7'` in Spalte 1 der Datenzeile und `'Gesamtsumme'` in Spalte 2 der Summenzeile. Die erste Datenzeile eines Artikels trägt `sku` und `name`, Folgezeilen bleiben dort leer — genau wie im PDF.

- [ ] **Step 5: Test ausführen, Erfolg bestätigen**

Run: `php artisan test --compact tests/Feature/BalanceReportExportTest.php`
Expected: PASS (7 Tests).

- [ ] **Step 6: Gesamte Suite ausführen**

Run: `php artisan test --compact`
Expected: PASS. Übersprungene Jetstream-Tests (ApiToken*, DeleteAccount) sind erwartet und unverändert.

- [ ] **Step 7: Pint und Commit**

```bash
vendor/bin/pint --dirty --format agent
git add composer.json composer.lock app/Http/Controllers/BalanceReportController.php tests/Feature/BalanceReportExportTest.php
git commit -m "XLSX-Export der Bestandsbilanz"
```

---

## Abschluss

Nach Task 11:

- [ ] `php artisan test --compact` läuft vollständig grün.
- [ ] `vendor/bin/pint --dirty --format agent` meldet keine Änderungen mehr.
- [ ] `npm run build` ist mit dem letzten Frontend-Stand gelaufen.
- [ ] Manueller Durchlauf: Lieferant anlegen → einem Artikel mit Preis zuordnen → an einem Lagerplatz ein- und ausbuchen (Lieferant vorausgewählt) → Bilanz für den heutigen Tag öffnen → PDF und Excel herunterladen und den Unterschriftenblock prüfen.
- [ ] `CLAUDE.md` um einen kurzen Absatz zum Lieferanten-Datenmodell und zum Bilanz-Export ergänzen (Abschnitte "Datenmodell" und "Controller-Konventionen").

## Bewusst nicht enthalten

Diese Punkte sind beim Lesen des Codes aufgefallen, gehören aber nicht zu dieser Spezifikation:

- `routes/web.php`: `/articles/trashed` steht hinter `/articles/{article}` und ist unerreichbar; die Route `storage-locations.get` hat einen Tippfehler im Pfad (`/stouri: rage-locations/...`).
- `ArticleManagementController::trashed()` rendert `Articles/Trashed`, diese Vue-Komponente existiert nicht.
- Korrekturbuchungen (`type = 'correction'`) erfassen weiterhin keinen Lieferanten — so in der Spezifikation festgelegt.
