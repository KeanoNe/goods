# Bestandskonsistenz — Implementierungsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `stocks.quantity` und das Bewegungsjournal wieder in Übereinstimmung bringen und dafür sorgen, dass sie nicht erneut auseinanderlaufen.

**Architecture:** Drei Codekorrekturen schließen die Lücken, durch die Bestand am Journal vorbei verändert werden kann: fehlende Transaktionen, eine nicht journalisierte Löschung und eine ungesperrte Lese-Schreib-Folge. Eine vierte Änderung bringt die Diagramme auf dieselbe Zählweise wie die Bilanz. Ein Artisan-Kommando gleicht die bereits entstandene Abweichung einmalig an, ohne Bestände zu verändern.

**Tech Stack:** Laravel 13.32, PHP 8.5, MySQL, PHPUnit 13. Keine neuen Abhängigkeiten.

**Spezifikation:** `docs/superpowers/specs/2026-09-28-bestandskonsistenz-design.md`

## Global Constraints

- Alle UI-Texte, Meldungen und Code-Kommentare auf **Deutsch mit korrekten Umlauten** (ä, ö, ü, ß). Niemals `ae`/`oe`/`ue`/`ss` als Ersatz. PHP-**Methodennamen** sind Bezeichner und bleiben ASCII — das ist Absicht.
- Nach **jeder** PHP-Änderung `vendor/bin/pint --dirty --format agent`.
- Neue Dateien über `php artisan make:...` mit `--no-interaction`.
- Jede Bestandsänderung schreibt `stocks` **und** `stock_movements`, innerhalb einer `DB::transaction()`.
- Keine Form Requests: Validierung inline per `$request->validate([...])`.
- Jeder Test nutzt `RefreshDatabase`. Tests laufen über `phpunit.xml` gegen die MySQL-Datenbank `goods_test`.
- Geldbeträge sind `decimal(10,2)` ohne Eloquent-Casts; MySQL liefert sie als String.
- `stock_movements.user_id` ist ein Pflichtfeld mit Fremdschlüssel.
- Die Glattziehung verändert **keine** Bestände. `stocks.quantity` ist die zutreffende Größe, fehlerhaft ist das Journal.
- Auf dem Produktionsserver laufen alle `artisan`-Aufrufe über `/opt/plesk/php/8.5/bin/php`, weil das Standard-`php` dort 8.3.6 ist.

---

### Task 1: ArticleStorageLocationController absichern

**Files:**
- Modify: `app/Http/Controllers/ArticleStorageLocationController.php`
- Modify: `app/Models/StockMovement.php`
- Test: `tests/Feature/ArticleStorageLocationTransaktionTest.php`

**Interfaces:**
- Consumes: `Article`, `Stock`, `StockMovement`, `StorageLocation` und deren Factories.
- Produces:
  - `destroy()` schreibt bei Restbestand > 0 eine `out`-Bewegung mit `from_storage_location_id` = entfernter Lagerplatz, `to_storage_location_id` = `null`, `quantity` = Restbestand, `notes` = `Lagerplatz-Zuordnung entfernt`. Alle drei schreibenden Methoden laufen in `DB::transaction()`.
  - `StockMovement::journalstaende(): Illuminate\Support\Collection` — Netto-Bestand je Artikel laut Journal, Schlüssel ist `article_id`. Task 4 und die Tests von Task 4 bauen darauf auf.

- [ ] **Step 1: Failing test schreiben**

`tests/Feature/ArticleStorageLocationTransaktionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArticleStorageLocationTransaktionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_entfernen_mit_restbestand_schreibt_eine_ausbuchung(): void
    {
        [$article, $location] = $this->zuordnungMitBestand(40);

        $response = $this->delete(route('articles.storage-locations.destroy', [$article, $location]));

        $response->assertRedirect(route('articles.show', $article->id));
        $this->assertDatabaseHas('stock_movements', [
            'article_id' => $article->id,
            'from_storage_location_id' => $location->id,
            'to_storage_location_id' => null,
            'quantity' => 40,
            'type' => 'out',
            'notes' => 'Lagerplatz-Zuordnung entfernt',
        ]);
        $this->assertDatabaseMissing('stocks', [
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
        ]);
    }

    public function test_nach_dem_entfernen_stimmen_journal_und_bestand_ueberein(): void
    {
        [$article, $location] = $this->zuordnungMitBestand(40);

        $this->delete(route('articles.storage-locations.destroy', [$article, $location]));

        $this->assertSame(0, $this->journalstand($article->id));
        $this->assertSame(0, (int) Stock::where('article_id', $article->id)->sum('quantity'));
    }

    public function test_entfernen_ohne_restbestand_schreibt_keine_buchung(): void
    {
        [$article, $location] = $this->zuordnungMitBestand(0);
        StockMovement::where('article_id', $article->id)->delete();

        $this->delete(route('articles.storage-locations.destroy', [$article, $location]));

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_korrektur_ohne_mengenaenderung_schreibt_keine_buchung(): void
    {
        [$article, $location] = $this->zuordnungMitBestand(40);
        StockMovement::where('article_id', $article->id)->delete();

        $this->post(route('articles.storage-locations.correction', [$article, $location]), [
            'new_quantity' => 40,
        ]);

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('stocks', [
            'article_id' => $article->id,
            'quantity' => 40,
        ]);
    }

    public function test_scheitert_die_buchung_bleibt_auch_der_bestand_unveraendert(): void
    {
        [$article, $location] = $this->zuordnungMitBestand(40);

        // Jeden Insert in stock_movements scheitern lassen, um die
        // Transaktionsklammer zu prüfen.
        DB::beforeExecuting(function ($query) {
            if (str_contains($query, 'insert into `stock_movements`')) {
                throw new \RuntimeException('Buchung absichtlich fehlgeschlagen');
            }
        });

        try {
            $this->delete(route('articles.storage-locations.destroy', [$article, $location]));
        } catch (\Throwable) {
            // erwartet
        }

        $this->assertDatabaseHas('stocks', [
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
            'quantity' => 40,
        ]);
    }

    /**
     * Legt Artikel, Lagerplatz und Bestand an und liefert beide Modelle.
     *
     * @return array{0: Article, 1: StorageLocation}
     */
    private function zuordnungMitBestand(int $menge): array
    {
        $article = Article::factory()->create();
        $location = StorageLocation::factory()->create();

        Stock::factory()->create([
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
            'quantity' => $menge,
        ]);

        if ($menge > 0) {
            StockMovement::factory()->create([
                'article_id' => $article->id,
                'to_storage_location_id' => $location->id,
                'quantity' => $menge,
                'type' => 'in',
            ]);
        }

        return [$article, $location];
    }

    private function journalstand(int $articleId): int
    {
        return (int) (StockMovement::journalstaende()[$articleId] ?? 0);
    }
}
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

Run: `php artisan test --compact tests/Feature/ArticleStorageLocationTransaktionTest.php`
Expected: FAIL — zunächst `Call to undefined method App\Models\StockMovement::journalstaende()`. Nach Schritt 3 dann der eigentliche Fehlschlag: `test_entfernen_mit_restbestand_schreibt_eine_ausbuchung` findet keine Zeile in `stock_movements`, weil `destroy()` heute keine schreibt, und `test_nach_dem_entfernen...` meldet Journalstand 40 statt 0.

- [ ] **Step 3: Gemeinsamen Journalstand-Helfer anlegen**

Die Netto-Berechnung aus dem Journal wird an drei Stellen gebraucht: in den Tests dieses Tasks, im Kommando aus Task 4 und in dessen Tests. Sie gehört deshalb einmal auf das Modell.

In `app/Models/StockMovement.php` die Imports ergänzen:

```php
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
```

Und am Ende der Klasse einfügen:

```php
    /**
     * Netto-Bestand je Artikel laut Bewegungsjournal.
     *
     * Ein transfer verschiebt nur zwischen Lagerplätzen und verändert den
     * Artikelbestand nicht. Korrekturen zählen mit, anders als in der
     * Bilanz: hier geht es darum, ob Journal und Bestand zusammenpassen,
     * nicht um den Warenfluss eines Zeitraums.
     *
     * @return Collection<int, int> Schlüssel ist die article_id
     */
    public static function journalstaende(): Collection
    {
        return DB::table('stock_movements')
            ->selectRaw('article_id')
            ->selectRaw("sum(case
                when type = 'in' then quantity
                when type = 'out' then -quantity
                when type = 'correction' and to_storage_location_id is not null then quantity
                when type = 'correction' and from_storage_location_id is not null then -quantity
                else 0 end) as netto")
            ->groupBy('article_id')
            ->pluck('netto', 'article_id');
    }
```

- [ ] **Step 4: Import im Controller ergänzen**

In `app/Http/Controllers/ArticleStorageLocationController.php` nach den bestehenden `use`-Zeilen ergänzen:

```php
use Illuminate\Support\Facades\DB;
```

- [ ] **Step 5: `destroy()` ersetzen**

Die Methode vollständig ersetzen durch:

```php
    /**
     * Entfernt die Zuordnung eines Lagerplatzes von einem Artikel.
     *
     * Ein noch vorhandener Restbestand wird zuvor ausgebucht. Ohne diese
     * Buchung verschwände er spurlos aus dem Journal, und Bestand und
     * Historie liefen auseinander.
     */
    public function destroy(Article $article, StorageLocation $storageLocation)
    {
        $stock = Stock::where('article_id', $article->id)
            ->where('storage_location_id', $storageLocation->id)
            ->first();

        if (! $stock) {
            throw new ModelNotFoundException('Die Zuordnung konnte nicht gefunden werden.');
        }

        DB::transaction(function () use ($article, $storageLocation, $stock) {
            if ($stock->quantity > 0) {
                StockMovement::create([
                    'article_id' => $article->id,
                    'from_storage_location_id' => $storageLocation->id,
                    'to_storage_location_id' => null,
                    'quantity' => $stock->quantity,
                    'type' => 'out',
                    'user_id' => Auth::id(),
                    'notes' => 'Lagerplatz-Zuordnung entfernt',
                ]);
            }

            $stock->delete();
        });

        return redirect()->route('articles.show', $article->id)
            ->with('message', 'Lagerplatz-Zuordnung erfolgreich entfernt.');
    }
```

- [ ] **Step 6: `store()` in eine Transaktion fassen**

In `store()` den Block ab `// Neuen Stock-Eintrag erstellen` bis zum schließenden `}` der `if ($quantity > 0)`-Bedingung ersetzen durch:

```php
        DB::transaction(function () use ($article, $validated, $quantity) {
            $newStock = Stock::create([
                'article_id' => $article->id,
                'storage_location_id' => $validated['storage_location_id'],
                'quantity' => $quantity,
            ]);

            // Bestand zuzuteilen ist ein Zugang, deshalb to_storage_location_id.
            if ($quantity > 0) {
                StockMovement::create([
                    'article_id' => $article->id,
                    'to_storage_location_id' => $newStock->storage_location_id,
                    'quantity' => $quantity,
                    'type' => 'in',
                    'user_id' => Auth::id(),
                    'notes' => 'Initialer Bestand beim Zuweisen des Lagerplatzes',
                ]);
            }
        });
```

- [ ] **Step 7: `correction()` in eine Transaktion fassen**

In `correction()` den Block ab `// Stock aktualisieren` bis zum Ende des `StockMovement::create([...]);` ersetzen durch:

```php
        DB::transaction(function () use ($article, $storageLocation, $stock, $newQuantity, $difference, $request) {
            $stock->update(['quantity' => $newQuantity]);

            // Ohne Mengenänderung gibt es nichts zu buchen. Bisher entstand
            // hier eine Bewegung mit Menge 0 und ohne Lagerplatz.
            if ($difference === 0) {
                return;
            }

            StockMovement::create([
                'article_id' => $article->id,
                'from_storage_location_id' => $difference < 0 ? $storageLocation->id : null,
                'to_storage_location_id' => $difference > 0 ? $storageLocation->id : null,
                'quantity' => abs($difference),
                'type' => 'correction',
                'user_id' => Auth::id(),
                'notes' => $request->input('notes', 'Bestandskorrektur'),
            ]);
        });
```

- [ ] **Step 8: Test ausführen, Erfolg bestätigen**

Run: `php artisan test --compact tests/Feature/ArticleStorageLocationTransaktionTest.php`
Expected: PASS (5 Tests).

- [ ] **Step 9: Pint und Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ArticleStorageLocationController.php tests/Feature/ArticleStorageLocationTransaktionTest.php
git commit -m "Bestandsänderungen beim Zuordnen und Entfernen journalisieren"
```

---

### Task 2: Bestandszeile beim Buchen sperren

**Files:**
- Modify: `app/Http/Controllers/StockMovementController.php` (Methode `update()`)
- Test: `tests/Feature/StockMovementSperreTest.php`

**Interfaces:**
- Consumes: die bestehende `DB::transaction()`-Klammer in `update()`.
- Produces: die `stocks`-Zeile wird innerhalb der Transaktion mit `lockForUpdate()` geladen, bevor ihre Menge gelesen wird.

- [ ] **Step 1: Failing test schreiben**

`tests/Feature/StockMovementSperreTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Stock;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockMovementSperreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_die_bestandszeile_wird_zum_schreiben_gesperrt(): void
    {
        [$article, $location] = $this->bestand(10);

        $abfragen = [];
        DB::listen(function ($abfrage) use (&$abfragen) {
            $abfragen[] = strtolower($abfrage->sql);
        });

        $this->postJson(route('stock.api.movement.store'), [
            'location_id' => $location->id,
            'article_id' => $article->id,
            'quantity' => 3,
            'type' => 'add',
        ])->assertOk();

        $sperren = array_filter($abfragen, fn (string $sql) => str_contains($sql, 'from `stocks`') && str_contains($sql, 'for update'));

        $this->assertNotEmpty($sperren, 'Die stocks-Zeile wurde ohne "for update" gelesen.');
    }

    public function test_buchen_funktioniert_unveraendert(): void
    {
        [$article, $location] = $this->bestand(10);

        $this->postJson(route('stock.api.movement.store'), [
            'location_id' => $location->id,
            'article_id' => $article->id,
            'quantity' => 4,
            'type' => 'remove',
        ])->assertOk();

        $this->assertDatabaseHas('stocks', [
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
            'quantity' => 6,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'article_id' => $article->id,
            'quantity' => 4,
            'type' => 'out',
        ]);
    }

    /**
     * @return array{0: Article, 1: StorageLocation}
     */
    private function bestand(int $menge): array
    {
        $article = Article::factory()->create();
        $location = StorageLocation::factory()->create();

        Stock::factory()->create([
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
            'quantity' => $menge,
        ]);

        return [$article, $location];
    }
}
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

Run: `php artisan test --compact tests/Feature/StockMovementSperreTest.php`
Expected: FAIL — `test_die_bestandszeile_wird_zum_schreiben_gesperrt` meldet „Die stocks-Zeile wurde ohne "for update" gelesen.". Der zweite Test besteht bereits.

- [ ] **Step 3: Sperre einbauen**

In `app/Http/Controllers/StockMovementController.php`, Methode `update()`, den Block

```php
                // Hole oder erstelle den Stock-Eintrag
                $stock = Stock::firstOrCreate(
                    [
                        'storage_location_id' => $storageLocation->id,
                        'article_id' => $article->id,
                    ],
                    ['quantity' => 0]
                );
```

ersetzen durch

```php
                // Hole oder erstelle den Stock-Eintrag
                $stock = Stock::firstOrCreate(
                    [
                        'storage_location_id' => $storageLocation->id,
                        'article_id' => $article->id,
                    ],
                    ['quantity' => 0]
                );

                // Dieselbe Zeile gesperrt neu laden. firstOrCreate kann das
                // nicht, deshalb zweistufig. Ohne die Sperre lesen zwei
                // gleichzeitige Buchungen denselben Ausgangswert und
                // überschreiben sich gegenseitig: das Journal verbucht beide,
                // der Bestand bewegt sich nur einmal.
                $stock = Stock::whereKey($stock->getKey())->lockForUpdate()->first();
```

- [ ] **Step 4: Test ausführen, Erfolg bestätigen**

Run: `php artisan test --compact tests/Feature/StockMovementSperreTest.php`
Expected: PASS (2 Tests).

- [ ] **Step 5: Bestehende Buchungstests gegenprüfen**

Run: `php artisan test --compact tests/Feature/StockMovementSupplierTest.php`
Expected: PASS (7 Tests) — die Sperre darf am Verhalten nichts ändern.

- [ ] **Step 6: Pint und Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/StockMovementController.php tests/Feature/StockMovementSperreTest.php
git commit -m "Bestandszeile beim Buchen sperren"
```

---

### Task 3: Diagramme auf die Zählweise der Bilanz bringen

**Files:**
- Modify: `app/Http/Controllers/ArticleManagementController.php` (Methode `show()`, die `switch`-Anweisung über `$move->type`)
- Test: `tests/Feature/ArtikelDiagrammTest.php`

**Interfaces:**
- Consumes: die Inertia-Props `dailyChanges` und `cumulativeStockData` der Seite `Articles/Show`.
- Produces: beide Reihen zählen nur noch `in` und `out`, identisch zu `BalanceReportService::build()`.

- [ ] **Step 1: Failing test schreiben**

`tests/Feature/ArtikelDiagrammTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ArtikelDiagrammTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_korrekturen_veraendern_die_diagrammwerte_nicht(): void
    {
        $article = Article::factory()->create();
        $location = StorageLocation::factory()->create();

        StockMovement::factory()->create([
            'article_id' => $article->id,
            'to_storage_location_id' => $location->id,
            'quantity' => 5000,
            'type' => 'correction',
            'created_at' => now(),
        ]);

        $this->get(route('articles.show', $article))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dailyChanges', fn ($tage) => collect($tage)->sum('net_change') === 0)
            );
    }

    public function test_transfers_veraendern_die_diagrammwerte_nicht(): void
    {
        $article = Article::factory()->create();
        $vonLager = StorageLocation::factory()->create();
        $nachLager = StorageLocation::factory()->create();

        StockMovement::factory()->create([
            'article_id' => $article->id,
            'from_storage_location_id' => $vonLager->id,
            'to_storage_location_id' => $nachLager->id,
            'quantity' => 300,
            'type' => 'transfer',
            'created_at' => now(),
        ]);

        $this->get(route('articles.show', $article))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dailyChanges', fn ($tage) => collect($tage)->sum('net_change') === 0)
            );
    }

    public function test_zugang_und_abgang_werden_weiterhin_verrechnet(): void
    {
        $article = Article::factory()->create();
        $location = StorageLocation::factory()->create();

        StockMovement::factory()->create([
            'article_id' => $article->id,
            'to_storage_location_id' => $location->id,
            'quantity' => 100,
            'type' => 'in',
            'created_at' => now(),
        ]);
        StockMovement::factory()->create([
            'article_id' => $article->id,
            'from_storage_location_id' => $location->id,
            'to_storage_location_id' => null,
            'quantity' => 30,
            'type' => 'out',
            'created_at' => now(),
        ]);

        $this->get(route('articles.show', $article))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dailyChanges', fn ($tage) => collect($tage)->sum('net_change') === 70)
            );
    }
}
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

Run: `php artisan test --compact tests/Feature/ArtikelDiagrammTest.php`
Expected: FAIL — die ersten beiden Tests melden 5000 beziehungsweise 300 statt 0. Der dritte besteht bereits.

- [ ] **Step 3: `switch` ersetzen**

In `app/Http/Controllers/ArticleManagementController.php`, Methode `show()`, die vollständige `switch`-Anweisung

```php
                switch ($move->type) {
                    case 'in':
                    case 'transfer':
                        $netChange += $move->quantity;
                        break;
                    case 'out':
                        $netChange -= $move->quantity;
                        break;
                    case 'correction':
                        // Bei correction entscheidet die Richtung
                        if ($move->to_storage_location_id) {
                            $netChange += $move->quantity;
                        }
                        if ($move->from_storage_location_id) {
                            $netChange -= $move->quantity;
                        }
                        break;
                }
```

ersetzen durch

```php
                // Gleiche Zählweise wie BalanceReportService::build(): nur
                // Zu- und Abgänge. Ein transfer verschiebt bloß zwischen
                // Lagerplätzen und verändert den Artikelbestand nicht,
                // correction ist eine Buchhaltungskorrektur und kein
                // Warenfluss.
                switch ($move->type) {
                    case 'in':
                        $netChange += $move->quantity;
                        break;
                    case 'out':
                        $netChange -= $move->quantity;
                        break;
                }
```

- [ ] **Step 4: Test ausführen, Erfolg bestätigen**

Run: `php artisan test --compact tests/Feature/ArtikelDiagrammTest.php`
Expected: PASS (3 Tests).

- [ ] **Step 5: Pint und Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ArticleManagementController.php tests/Feature/ArtikelDiagrammTest.php
git commit -m "Diagramme klammern Korrekturen und Transfers aus"
```

---

### Task 4: Abgleich-Kommando

**Files:**
- Create: `app/Console/Commands/LagerAbgleichen.php`
- Test: `tests/Feature/LagerAbgleichKommandoTest.php`

**Interfaces:**
- Consumes: `StockMovement::journalstaende()` aus Task 1, `stocks`, `App\Models\Article`, `App\Models\StockMovement`.
- Produces: Kommando `lager:abgleichen` mit der Option `--schreiben`. Ohne Option Probelauf ohne Schreibzugriff. Schreibt je abweichendem Artikel genau eine `correction`-Bewegung, `user_id` = 1, Notiz `Abgleich Journal und Bestand, Ursache: gelöschte Lagerplatz-Zuordnungen ohne Buchung`. Bestände bleiben unverändert.

- [ ] **Step 1: Failing test schreiben**

`tests/Feature/LagerAbgleichKommandoTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LagerAbgleichKommandoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // user_id 1 wird vom Kommando als Urheber verwendet.
        User::factory()->create(['id' => 1]);
    }

    public function test_probelauf_schreibt_nichts(): void
    {
        $this->abweichung(bestand: 175, journal: 100);

        $this->artisan('lager:abgleichen')->assertSuccessful();

        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_schreiben_gleicht_die_abweichung_aus(): void
    {
        [$article, $location] = $this->abweichung(bestand: 175, journal: 100);

        $this->artisan('lager:abgleichen --schreiben')->assertSuccessful();

        $this->assertDatabaseHas('stock_movements', [
            'article_id' => $article->id,
            'type' => 'correction',
            'to_storage_location_id' => $location->id,
            'from_storage_location_id' => null,
            'quantity' => 75,
            'user_id' => 1,
        ]);
        $this->assertSame(175, $this->journalstand($article->id));
    }

    public function test_bestaende_bleiben_unveraendert(): void
    {
        [$article, $location] = $this->abweichung(bestand: 175, journal: 100);

        $this->artisan('lager:abgleichen --schreiben')->assertSuccessful();

        $this->assertDatabaseHas('stocks', [
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
            'quantity' => 175,
        ]);
    }

    public function test_zu_niedriger_bestand_wird_als_abgang_gebucht(): void
    {
        [$article, $location] = $this->abweichung(bestand: 20, journal: 100);

        $this->artisan('lager:abgleichen --schreiben')->assertSuccessful();

        $this->assertDatabaseHas('stock_movements', [
            'article_id' => $article->id,
            'type' => 'correction',
            'from_storage_location_id' => $location->id,
            'to_storage_location_id' => null,
            'quantity' => 80,
        ]);
        $this->assertSame(20, $this->journalstand($article->id));
    }

    public function test_zweiter_lauf_findet_nichts_mehr(): void
    {
        $this->abweichung(bestand: 175, journal: 100);

        $this->artisan('lager:abgleichen --schreiben')->assertSuccessful();
        $vorher = DB::table('stock_movements')->count();

        $this->artisan('lager:abgleichen --schreiben')->assertSuccessful();

        $this->assertSame($vorher, DB::table('stock_movements')->count());
    }

    public function test_artikel_ohne_abweichung_erzeugt_keine_buchung(): void
    {
        $this->abweichung(bestand: 100, journal: 100);

        $this->artisan('lager:abgleichen --schreiben')->assertSuccessful();

        $this->assertDatabaseCount('stock_movements', 1);
    }

    /**
     * Legt einen Artikel an, dessen Bestand und Journalstand auseinanderlaufen.
     *
     * @return array{0: Article, 1: StorageLocation}
     */
    private function abweichung(int $bestand, int $journal): array
    {
        $article = Article::factory()->create();
        $location = StorageLocation::factory()->create();

        Stock::factory()->create([
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
            'quantity' => $bestand,
        ]);

        StockMovement::factory()->create([
            'article_id' => $article->id,
            'to_storage_location_id' => $location->id,
            'quantity' => $journal,
            'type' => 'in',
        ]);

        return [$article, $location];
    }

    private function journalstand(int $articleId): int
    {
        return (int) (StockMovement::journalstaende()[$articleId] ?? 0);
    }
}
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

Run: `php artisan test --compact tests/Feature/LagerAbgleichKommandoTest.php`
Expected: FAIL — `The command "lager:abgleichen" does not exist.`

- [ ] **Step 3: Kommando anlegen**

```bash
php artisan make:command LagerAbgleichen --no-interaction
```

Inhalt vollständig ersetzen:

```php
<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LagerAbgleichen extends Command
{
    protected $signature = 'lager:abgleichen {--schreiben : Korrekturbuchungen tatsächlich schreiben}';

    protected $description = 'Vergleicht Bewegungsjournal und Bestand und gleicht Abweichungen per Korrekturbuchung an';

    private const NOTIZ = 'Abgleich Journal und Bestand, Ursache: gelöschte Lagerplatz-Zuordnungen ohne Buchung';

    private const URHEBER_ID = 1;

    public function handle(): int
    {
        $journal = StockMovement::journalstaende();
        $bestand = $this->bestaende();

        $zuBuchen = [];
        $nichtAbgleichbar = [];

        foreach ($journal->keys()->merge($bestand->keys())->unique() as $artikelId) {
            $differenz = (int) ($bestand[$artikelId] ?? 0) - (int) ($journal[$artikelId] ?? 0);

            if ($differenz === 0) {
                continue;
            }

            $lagerplatzId = $this->lagerplatzFuer((int) $artikelId);

            if ($lagerplatzId === null) {
                $nichtAbgleichbar[] = (int) $artikelId;

                continue;
            }

            $zuBuchen[] = [
                'article_id' => (int) $artikelId,
                'bestand' => (int) ($bestand[$artikelId] ?? 0),
                'journal' => (int) ($journal[$artikelId] ?? 0),
                'differenz' => $differenz,
                'lagerplatz_id' => $lagerplatzId,
            ];
        }

        $this->bericht($zuBuchen, $nichtAbgleichbar);

        if (! $this->option('schreiben')) {
            $this->newLine();
            $this->info('Probelauf, es wurde nichts geschrieben. Mit --schreiben ausführen, um die Buchungen anzulegen.');

            return self::SUCCESS;
        }

        foreach ($zuBuchen as $eintrag) {
            DB::transaction(function () use ($eintrag) {
                StockMovement::create([
                    'article_id' => $eintrag['article_id'],
                    'from_storage_location_id' => $eintrag['differenz'] < 0 ? $eintrag['lagerplatz_id'] : null,
                    'to_storage_location_id' => $eintrag['differenz'] > 0 ? $eintrag['lagerplatz_id'] : null,
                    'quantity' => abs($eintrag['differenz']),
                    'type' => 'correction',
                    'user_id' => self::URHEBER_ID,
                    'notes' => self::NOTIZ,
                ]);
            });
        }

        $this->newLine();
        $this->info(count($zuBuchen).' Korrekturbuchungen geschrieben. Die Bestände wurden nicht verändert.');

        return self::SUCCESS;
    }

    /**
     * Tatsächlicher Bestand je Artikel laut stocks.
     *
     * @return Collection<int, int>
     */
    private function bestaende(): Collection
    {
        return DB::table('stocks')
            ->selectRaw('article_id')
            ->selectRaw('sum(quantity) as menge')
            ->groupBy('article_id')
            ->pluck('menge', 'article_id');
    }

    /**
     * Lagerplatz für die Korrekturbuchung: aus der jüngsten Bewegung des
     * Artikels, ersatzweise aus seinem ersten Bestandseintrag.
     */
    private function lagerplatzFuer(int $artikelId): ?int
    {
        $juengste = DB::table('stock_movements')
            ->where('article_id', $artikelId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['from_storage_location_id', 'to_storage_location_id']);

        if ($juengste !== null) {
            $id = $juengste->to_storage_location_id ?? $juengste->from_storage_location_id;

            if ($id !== null) {
                return (int) $id;
            }
        }

        $ausBestand = DB::table('stocks')
            ->where('article_id', $artikelId)
            ->value('storage_location_id');

        return $ausBestand !== null ? (int) $ausBestand : null;
    }

    /**
     * @param  list<array{article_id: int, bestand: int, journal: int, differenz: int, lagerplatz_id: int}>  $zuBuchen
     * @param  list<int>  $nichtAbgleichbar
     */
    private function bericht(array $zuBuchen, array $nichtAbgleichbar): void
    {
        if ($zuBuchen === []) {
            $this->info('Journal und Bestand stimmen bei allen Artikeln überein.');
        } else {
            $this->table(
                ['Artikel', 'Bezeichnung', 'Bestand', 'Journal', 'Differenz'],
                array_map(function (array $eintrag): array {
                    $artikel = Article::withTrashed()->find($eintrag['article_id']);

                    return [
                        $eintrag['article_id'],
                        $artikel?->name ?? 'unbekannt',
                        $eintrag['bestand'],
                        $eintrag['journal'],
                        sprintf('%+d', $eintrag['differenz']),
                    ];
                }, $zuBuchen)
            );

            $this->line(sprintf(
                'Betroffene Artikel: %d, Summe der Beträge: %d Stück',
                count($zuBuchen),
                array_sum(array_map(fn (array $e): int => abs($e['differenz']), $zuBuchen))
            ));
        }

        if ($nichtAbgleichbar !== []) {
            $this->warn(sprintf(
                'Nicht abgleichbar, weil kein Lagerplatz ermittelbar ist: %s',
                implode(', ', $nichtAbgleichbar)
            ));
        }
    }
}
```

- [ ] **Step 4: Test ausführen, Erfolg bestätigen**

Run: `php artisan test --compact tests/Feature/LagerAbgleichKommandoTest.php`
Expected: PASS (6 Tests).

- [ ] **Step 5: Gesamte Suite ausführen**

Run: `php artisan test --compact`
Expected: PASS. Ausgangswert vor diesem Plan: 105 bestanden, 10 übersprungen.

- [ ] **Step 6: Pint und Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/LagerAbgleichen.php tests/Feature/LagerAbgleichKommandoTest.php
git commit -m "Kommando zum Abgleich von Journal und Bestand"
```

---

### Task 5: Ausrollen und Glattziehen in Produktion

**Files:** keine Codeänderung. Rein betrieblicher Ablauf.

**Interfaces:**
- Consumes: `lager:abgleichen` aus Task 4, die Korrekturen aus Task 1 bis 3.
- Produces: eine Produktion, in der Journal und Bestand übereinstimmen.

Dieser Task ist **nicht** von einem Subagenten auszuführen. Er verändert Produktionsdaten und gehört in die Hand des Controllers, mit Bestätigung des Nutzers vor dem schreibenden Schritt.

- [ ] **Step 1: Nach main pushen**

```bash
git push origin main
```

- [ ] **Step 2: Vollständiges Datenbank-Backup ziehen**

Auf dem Server, Zugangsdaten aus `.env` des Projekts:

```bash
ssh -i ~/.ssh/id_rsa root@212.227.63.87 'cd /var/www/vhosts/my-goods.app/httpdocs && \
  DB=$(/opt/plesk/php/8.5/bin/php artisan tinker --execute "echo config(\"database.connections.mysql.database\");" | tail -1) && \
  mysqldump --single-transaction --routines --triggers "$DB" > /root/goods-backup-$(date +%Y-%m-%d-%H%M).sql && \
  ls -lh /root/goods-backup-*.sql | tail -1'
```

Danach prüfen: Dateigröße plausibel (> 1 MB), letzte Zeile enthält `Dump completed`. Ergebnis dem Nutzer zeigen.

- [ ] **Step 3: Quellcode und Build ausrollen**

Der Server ist kein git-Checkout, der Abgleich läuft über rsync. Node gibt es dort nicht, der Build entsteht lokal.

```bash
npm run build
rsync -a -e "ssh -i ~/.ssh/id_rsa" app/ root@212.227.63.87:/var/www/vhosts/my-goods.app/httpdocs/app/
rsync -a -e "ssh -i ~/.ssh/id_rsa" resources/js/ root@212.227.63.87:/var/www/vhosts/my-goods.app/httpdocs/resources/js/
rsync -az --delete -e "ssh -i ~/.ssh/id_rsa" public/build/ root@212.227.63.87:/var/www/vhosts/my-goods.app/httpdocs/public/build/
ssh -i ~/.ssh/id_rsa root@212.227.63.87 'cd /var/www/vhosts/my-goods.app/httpdocs && chown -R my-goods.app_lu5ns4cgk9f:psacln app resources public/build'
```

`public/build` ist gitignoriert und darf nie committet werden.

- [ ] **Step 4: Probelauf**

```bash
ssh -i ~/.ssh/id_rsa root@212.227.63.87 'cd /var/www/vhosts/my-goods.app/httpdocs && /opt/plesk/php/8.5/bin/php artisan lager:abgleichen'
```

Erwartet: rund 86 betroffene Artikel, Summe der Beträge rund 52.987 Stück. Den Bericht dem Nutzer zeigen und **auf seine Bestätigung warten**, bevor Schritt 5 läuft.

- [ ] **Step 5: Glattziehen**

```bash
ssh -i ~/.ssh/id_rsa root@212.227.63.87 'cd /var/www/vhosts/my-goods.app/httpdocs && /opt/plesk/php/8.5/bin/php artisan lager:abgleichen --schreiben'
```

- [ ] **Step 6: Nachkontrolle**

```bash
ssh -i ~/.ssh/id_rsa root@212.227.63.87 'cd /var/www/vhosts/my-goods.app/httpdocs && /opt/plesk/php/8.5/bin/php artisan lager:abgleichen'
```

Erwartet: `Journal und Bestand stimmen bei allen Artikeln überein.`

Zusätzlich stichprobenartig prüfen, dass sich kein Bestand verändert hat, etwa Karton 3 (Artikel-ID 36) weiterhin bei 175.

- [ ] **Step 7: Seite prüfen**

```bash
curl -sS -o /dev/null -w "%{http_code}\n" -L https://my-goods.app/
```

Erwartet: 200.

---

## Abschluss

- [ ] `php artisan test --compact` läuft grün.
- [ ] `vendor/bin/pint --dirty --format agent` meldet keine Änderungen.
- [ ] Produktion: Probelauf meldet keine Abweichung mehr.
- [ ] `CLAUDE.md` um einen Hinweis ergänzen, dass das Entfernen einer Lagerplatz-Zuordnung eine Ausbuchung schreibt und dass `lager:abgleichen` existiert.

## Bewusst nicht enthalten

- Die Erweiterung der Bilanz um den Bestandswert je Artikel. Sie folgt als eigene Spezifikation.
- Die beiden Routendefekte in `routes/web.php` (`/articles/trashed` hinter `/articles/{article}`, Tippfehler bei `storage-locations.get`).
- Deutsche Sprachdateien für Validierungsmeldungen.
- Die Rekonstruktion, wann welche Zuordnung entfernt wurde. Diese Information existiert nicht.
