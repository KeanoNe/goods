# Bestandswert — Implementierungsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Die Bestandsbilanz um einen abschließenden Abschnitt erweitern, der je Artikel den vorhandenen Bestand nach Lieferant und Stückpreis aufschlüsselt und daraus den Wert des gesamten Lagers summiert.

**Architecture:** Ein eigener `BestandswertService` liest den Bestand aus `stocks` und leitet die Aufschlüsselung per FIFO aus den Eingangsbuchungen her — die jüngsten Zugänge gelten als noch vorhanden. Was sich nicht zuordnen lässt, wird als abgesetzte Zeile mit dem Preis des Standard-Lieferanten bewertet. Der `BalanceReportController` reicht das Ergebnis unverändert an Vorschau, PDF und XLSX weiter.

**Tech Stack:** Laravel 13.32, PHP 8.5, Inertia 3, Vue 3, Tailwind 4, MySQL, PHPUnit 13, Dompdf, OpenSpout 5. Keine neuen Abhängigkeiten.

**Spezifikation:** `docs/superpowers/specs/2026-09-28-bestandswert-design.md`

## Global Constraints

- Alle UI-Texte, Meldungen und Code-Kommentare auf **Deutsch mit korrekten Umlauten** (ä, ö, ü, ß). Niemals `ae`/`oe`/`ue`/`ss` als Ersatz. PHP-**Methodennamen** sind Bezeichner und bleiben ASCII — das ist Absicht.
- Nach **jeder** PHP-Änderung `vendor/bin/pint --dirty --format agent`.
- Neue Dateien über `php artisan make:...` mit `--no-interaction`.
- Keine Form Requests: Validierung inline per `$request->validate([...])`.
- Jeder Test nutzt `RefreshDatabase`. Tests laufen über `phpunit.xml` gegen die MySQL-Datenbank `goods_test`.
- Geldbeträge sind `decimal(10,2)` ohne Eloquent-Casts. MySQL liefert Dezimalspalten und `sum()`-Ergebnisse als **String** — vor jeder Rechnung und jedem Vergleich mit `(int)` beziehungsweise `(float)` casten.
- Vue-Seiten nutzen `<script setup>`. Es gibt kein JS-Linting-Setup, Dateien orientieren sich am Stil der Nachbardateien.
- `public/build` ist gitignoriert — `npm run build` ausführen, aber niemals `git add`.
- Die Blade-View für das PDF schreibt Umlaute bewusst als HTML-Entities (`&uuml;`, `&auml;`, `&euro;`), damit Dompdf sie unabhängig von der Schriftkonfiguration setzt. Im PHP- und Vue-Code dagegen die literalen Zeichen.
- Zahlen im PDF deutsch formatiert: `number_format($wert, 2, ',', '.')`. Im XLSX bleiben Mengen und Beträge **numerische Zellen**, damit Excel damit rechnen kann.
- Der Bestandswert ist eine Momentaufnahme zum Erstellungszeitpunkt und hängt **nicht** am gewählten Zeitraum. Das muss im Dokument stehen.

---

### Task 1: BestandswertService

**Files:**
- Create: `app/Services/BestandswertService.php`
- Test: `tests/Feature/BestandswertServiceTest.php`

**Interfaces:**
- Consumes: Tabellen `stocks`, `stock_movements`, `article_supplier`, `suppliers`; Modell `App\Models\Article` (nutzt `SoftDeletes`).
- Produces: `App\Services\BestandswertService::build(): array` mit der Form

```php
array{
    stichtag: \Carbon\CarbonInterface,
    articles: list<array{
        article_id: int,
        sku: string,
        name: string,
        geloescht: bool,
        rows: list<array{
            supplier: string,
            quantity: int,
            unit_price: float,
            total: float,
            ohne_lieferant: bool
        }>,
        subtotal: float
    }>,
    grand_total: float,
    ohne_preis_artikel: int,
    ohne_preis_menge: int,
    artikel_gesamt: int
}
```

  Artikel sind nach `sku` sortiert. Innerhalb eines Artikels stehen die zugeordneten Zeilen zuerst, sortiert nach Lieferantenname und dann Stückpreis; die Zeile mit `ohne_lieferant = true` steht immer zuletzt. Tasks 2 und 3 bauen darauf auf.

- [ ] **Step 1: Failing test schreiben**

`tests/Feature/BestandswertServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Services\BestandswertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BestandswertServiceTest extends TestCase
{
    use RefreshDatabase;

    private BestandswertService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BestandswertService::class);
    }

    public function test_bestand_mehrerer_lagerplaetze_wird_zusammengefasst(): void
    {
        $article = Article::factory()->create(['sku' => 'SKU-1']);
        Stock::factory()->create(['article_id' => $article->id, 'quantity' => 30]);
        Stock::factory()->create(['article_id' => $article->id, 'quantity' => 70]);

        $wert = $this->service->build();

        $this->assertCount(1, $wert['articles']);
        $this->assertSame(100, $wert['articles'][0]['rows'][0]['quantity']);
    }

    public function test_ohne_zugang_mit_lieferant_entsteht_nur_die_restzeile(): void
    {
        $article = Article::factory()->create();
        Stock::factory()->create(['article_id' => $article->id, 'quantity' => 40]);

        $wert = $this->service->build();

        $zeilen = $wert['articles'][0]['rows'];

        $this->assertCount(1, $zeilen);
        $this->assertTrue($zeilen[0]['ohne_lieferant']);
        $this->assertSame(40, $zeilen[0]['quantity']);
        $this->assertSame('Ohne Lieferant (kein Preis hinterlegt)', $zeilen[0]['supplier']);
        $this->assertSame(0.0, $zeilen[0]['unit_price']);
        $this->assertSame(0.0, $zeilen[0]['total']);
    }

    public function test_zwei_lieferanten_ergeben_zwei_zeilen(): void
    {
        $article = Article::factory()->create();
        $location = StorageLocation::factory()->create();
        Stock::factory()->create([
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
            'quantity' => 300,
        ]);

        $mueller = Supplier::factory()->create(['name' => 'Mueller']);
        $nord = Supplier::factory()->create(['name' => 'Nord']);

        $this->zugang($article, $location, $mueller, 200, 1.80, '2026-01-10');
        $this->zugang($article, $location, $nord, 100, 2.10, '2026-02-10');

        $wert = $this->service->build();
        $zeilen = $wert['articles'][0]['rows'];

        $this->assertCount(2, $zeilen);
        $this->assertSame('Mueller', $zeilen[0]['supplier']);
        $this->assertSame(200, $zeilen[0]['quantity']);
        $this->assertSame(1.80, $zeilen[0]['unit_price']);
        $this->assertSame(360.00, $zeilen[0]['total']);
        $this->assertSame('Nord', $zeilen[1]['supplier']);
        $this->assertSame(100, $zeilen[1]['quantity']);
        $this->assertSame(210.00, $zeilen[1]['total']);
        $this->assertSame(570.00, $wert['grand_total']);
    }

    public function test_fifo_ordnet_die_juengsten_zugaenge_zu(): void
    {
        $article = Article::factory()->create();
        $location = StorageLocation::factory()->create();
        Stock::factory()->create([
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
            'quantity' => 50,
        ]);

        $alt = Supplier::factory()->create(['name' => 'Alt']);
        $neu = Supplier::factory()->create(['name' => 'Neu']);

        $this->zugang($article, $location, $alt, 100, 1.00, '2026-01-01');
        $this->zugang($article, $location, $neu, 50, 3.00, '2026-06-01');

        $zeilen = $this->service->build()['articles'][0]['rows'];

        $this->assertCount(1, $zeilen);
        $this->assertSame('Neu', $zeilen[0]['supplier']);
        $this->assertSame(50, $zeilen[0]['quantity']);
        $this->assertSame(150.00, $zeilen[0]['total']);
    }

    public function test_nicht_gedeckter_bestand_wird_zur_restzeile(): void
    {
        $article = Article::factory()->create();
        $location = StorageLocation::factory()->create();
        Stock::factory()->create([
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
            'quantity' => 500,
        ]);

        $lieferant = Supplier::factory()->create(['name' => 'Mueller']);
        $article->suppliers()->attach($lieferant->id, ['price' => 2.00, 'is_default' => true]);
        $this->zugang($article, $location, $lieferant, 200, 1.80, '2026-01-10');

        $wert = $this->service->build();
        $zeilen = $wert['articles'][0]['rows'];

        $this->assertCount(2, $zeilen);
        $this->assertFalse($zeilen[0]['ohne_lieferant']);
        $this->assertSame(200, $zeilen[0]['quantity']);
        $this->assertTrue($zeilen[1]['ohne_lieferant']);
        $this->assertSame(300, $zeilen[1]['quantity']);
        $this->assertSame('Ohne Lieferant (Standardpreis Mueller)', $zeilen[1]['supplier']);
        $this->assertSame(2.00, $zeilen[1]['unit_price']);
        $this->assertSame(600.00, $zeilen[1]['total']);
        $this->assertSame(960.00, $wert['grand_total']);
    }

    public function test_zaehler_erfassen_nur_artikel_mit_unbewerteter_restmenge(): void
    {
        // Artikel A: Restmenge ohne Standardpreis -> zaehlt mit
        $ohnePreis = Article::factory()->create(['sku' => 'SKU-A']);
        Stock::factory()->create(['article_id' => $ohnePreis->id, 'quantity' => 40]);

        // Artikel B: Bestand vollstaendig zugeordnet, kein Standardpreis
        // noetig -> zaehlt NICHT mit
        $gedeckt = Article::factory()->create(['sku' => 'SKU-B']);
        $location = StorageLocation::factory()->create();
        Stock::factory()->create([
            'article_id' => $gedeckt->id,
            'storage_location_id' => $location->id,
            'quantity' => 10,
        ]);
        $lieferant = Supplier::factory()->create(['name' => 'Mueller']);
        $this->zugang($gedeckt, $location, $lieferant, 10, 5.00, '2026-01-10');

        $wert = $this->service->build();

        $this->assertSame(2, $wert['artikel_gesamt']);
        $this->assertSame(1, $wert['ohne_preis_artikel']);
        $this->assertSame(40, $wert['ohne_preis_menge']);
    }

    public function test_geloeschter_artikel_mit_bestand_erscheint_markiert(): void
    {
        $article = Article::factory()->create(['sku' => 'SKU-Z']);
        Stock::factory()->create(['article_id' => $article->id, 'quantity' => 5]);
        $article->delete();

        $wert = $this->service->build();

        $this->assertCount(1, $wert['articles']);
        $this->assertTrue($wert['articles'][0]['geloescht']);
        $this->assertSame('SKU-Z', $wert['articles'][0]['sku']);
    }

    public function test_artikel_ohne_bestand_erscheinen_nicht(): void
    {
        $mitBestand = Article::factory()->create(['sku' => 'SKU-1']);
        Stock::factory()->create(['article_id' => $mitBestand->id, 'quantity' => 10]);

        $ohneBestand = Article::factory()->create(['sku' => 'SKU-2']);
        Stock::factory()->create(['article_id' => $ohneBestand->id, 'quantity' => 0]);

        $wert = $this->service->build();

        $this->assertCount(1, $wert['articles']);
        $this->assertSame('SKU-1', $wert['articles'][0]['sku']);
    }

    public function test_artikel_sind_nach_artikelnummer_sortiert(): void
    {
        $zweiter = Article::factory()->create(['sku' => 'SKU-9']);
        Stock::factory()->create(['article_id' => $zweiter->id, 'quantity' => 1]);

        $erster = Article::factory()->create(['sku' => 'SKU-1']);
        Stock::factory()->create(['article_id' => $erster->id, 'quantity' => 1]);

        $wert = $this->service->build();

        $this->assertSame('SKU-1', $wert['articles'][0]['sku']);
        $this->assertSame('SKU-9', $wert['articles'][1]['sku']);
    }

    public function test_jede_zeile_rechnet_auf_und_die_summen_stimmen(): void
    {
        $article = Article::factory()->create();
        $location = StorageLocation::factory()->create();
        Stock::factory()->create([
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
            'quantity' => 150,
        ]);

        $lieferant = Supplier::factory()->create(['name' => 'Mueller']);
        $article->suppliers()->attach($lieferant->id, ['price' => 1.99, 'is_default' => true]);
        $this->zugang($article, $location, $lieferant, 100, 1.50, '2026-01-10');

        $wert = $this->service->build();
        $artikel = $wert['articles'][0];

        foreach ($artikel['rows'] as $zeile) {
            $this->assertSame(
                round($zeile['quantity'] * $zeile['unit_price'], 2),
                $zeile['total']
            );
        }

        $this->assertSame(
            round(array_sum(array_column($artikel['rows'], 'total')), 2),
            $artikel['subtotal']
        );
        $this->assertSame($artikel['subtotal'], $wert['grand_total']);
    }

    public function test_ohne_bestand_ist_der_bericht_leer(): void
    {
        $wert = $this->service->build();

        $this->assertSame([], $wert['articles']);
        $this->assertSame(0.0, $wert['grand_total']);
        $this->assertSame(0, $wert['artikel_gesamt']);
        $this->assertSame(0, $wert['ohne_preis_artikel']);
        $this->assertSame(0, $wert['ohne_preis_menge']);
    }

    private function zugang(
        Article $article,
        StorageLocation $location,
        Supplier $supplier,
        int $menge,
        float $preis,
        string $zeitpunkt
    ): void {
        StockMovement::factory()->create([
            'article_id' => $article->id,
            'supplier_id' => $supplier->id,
            'to_storage_location_id' => $location->id,
            'quantity' => $menge,
            'unit_price' => $preis,
            'type' => 'in',
            'created_at' => \Carbon\Carbon::parse($zeitpunkt),
        ]);
    }
}
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

Run: `php artisan test --compact tests/Feature/BestandswertServiceTest.php`
Expected: FAIL — `Target class [App\Services\BestandswertService] does not exist.`

- [ ] **Step 3: Service anlegen**

```bash
php artisan make:class Services/BestandswertService --no-interaction
```

Inhalt vollständig ersetzen:

```php
<?php

namespace App\Services;

use App\Models\Article;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class BestandswertService
{
    /**
     * Ermittelt den Wert des vorhandenen Lagers zum Aufrufzeitpunkt.
     *
     * Der Bestand selbst trägt keinen Lieferanten — stocks kennt nur
     * Artikel, Lagerplatz und Menge. Die Aufschlüsselung wird deshalb aus
     * den Eingangsbuchungen hergeleitet: nach FIFO verlässt die älteste
     * Ware das Lager zuerst, es bleiben also die jüngsten Zugänge liegen.
     * Was sich nicht zuordnen lässt, erscheint als abgesetzte Restzeile und
     * wird mit dem Preis des Standard-Lieferanten bewertet.
     *
     * @return array{
     *     stichtag: CarbonInterface,
     *     articles: list<array{
     *         article_id: int,
     *         sku: string,
     *         name: string,
     *         geloescht: bool,
     *         rows: list<array{supplier: string, quantity: int, unit_price: float, total: float, ohne_lieferant: bool}>,
     *         subtotal: float
     *     }>,
     *     grand_total: float,
     *     ohne_preis_artikel: int,
     *     ohne_preis_menge: int,
     *     artikel_gesamt: int
     * }
     */
    public function build(): array
    {
        $stichtag = now();

        $bestaende = DB::table('stocks')
            ->selectRaw('article_id')
            ->selectRaw('sum(quantity) as menge')
            ->groupBy('article_id')
            ->havingRaw('sum(quantity) > 0')
            ->pluck('menge', 'article_id');

        if ($bestaende->isEmpty()) {
            return [
                'stichtag' => $stichtag,
                'articles' => [],
                'grand_total' => 0.0,
                'ohne_preis_artikel' => 0,
                'ohne_preis_menge' => 0,
                'artikel_gesamt' => 0,
            ];
        }

        $artikelIds = $bestaende->keys()->all();

        $stammdaten = Article::withTrashed()
            ->whereIn('id', $artikelIds)
            ->get()
            ->keyBy('id');

        // Eingangsbuchungen mit Lieferant, jüngste zuerst. Der Join auf
        // suppliers läuft bewusst über den Query Builder, damit auch der
        // Name eines inzwischen gelöschten Lieferanten erhalten bleibt.
        $zugaenge = DB::table('stock_movements')
            ->join('suppliers', 'suppliers.id', '=', 'stock_movements.supplier_id')
            ->whereIn('stock_movements.article_id', $artikelIds)
            ->where('stock_movements.type', 'in')
            ->orderByDesc('stock_movements.created_at')
            ->orderByDesc('stock_movements.id')
            ->get([
                'stock_movements.article_id',
                'stock_movements.quantity',
                'stock_movements.unit_price',
                'suppliers.name as lieferant',
            ])
            ->groupBy('article_id');

        $standardpreise = DB::table('article_supplier')
            ->join('suppliers', 'suppliers.id', '=', 'article_supplier.supplier_id')
            ->whereIn('article_supplier.article_id', $artikelIds)
            ->where('article_supplier.is_default', true)
            ->get([
                'article_supplier.article_id',
                'article_supplier.price',
                'suppliers.name as lieferant',
            ])
            ->keyBy('article_id');

        $artikel = [];
        $gesamtsumme = 0.0;
        $ohnePreisArtikel = 0;
        $ohnePreisMenge = 0;

        foreach ($bestaende as $artikelId => $menge) {
            $restmenge = (int) $menge;
            $zugeordnet = [];

            foreach ($zugaenge->get($artikelId, collect()) as $zugang) {
                if ($restmenge <= 0) {
                    break;
                }

                $anteil = min((int) $zugang->quantity, $restmenge);
                $restmenge -= $anteil;

                $schluessel = $zugang->lieferant.'|'.(string) $zugang->unit_price;

                if (! isset($zugeordnet[$schluessel])) {
                    $zugeordnet[$schluessel] = [
                        'supplier' => $zugang->lieferant,
                        'quantity' => 0,
                        'unit_price' => (float) $zugang->unit_price,
                    ];
                }

                $zugeordnet[$schluessel]['quantity'] += $anteil;
            }

            $zeilen = [];

            foreach ($zugeordnet as $eintrag) {
                $zeilen[] = [
                    'supplier' => $eintrag['supplier'],
                    'quantity' => $eintrag['quantity'],
                    'unit_price' => $eintrag['unit_price'],
                    'total' => round($eintrag['quantity'] * $eintrag['unit_price'], 2),
                    'ohne_lieferant' => false,
                ];
            }

            usort($zeilen, fn (array $a, array $b) => [$a['supplier'], $a['unit_price']] <=> [$b['supplier'], $b['unit_price']]);

            if ($restmenge > 0) {
                $standard = $standardpreise->get($artikelId);
                $preis = $standard !== null ? (float) $standard->price : 0.0;

                $zeilen[] = [
                    'supplier' => $standard !== null
                        ? 'Ohne Lieferant (Standardpreis '.$standard->lieferant.')'
                        : 'Ohne Lieferant (kein Preis hinterlegt)',
                    'quantity' => $restmenge,
                    'unit_price' => $preis,
                    'total' => round($restmenge * $preis, 2),
                    'ohne_lieferant' => true,
                ];

                if ($standard === null) {
                    $ohnePreisArtikel++;
                    $ohnePreisMenge += $restmenge;
                }
            }

            $zwischensumme = round(array_sum(array_column($zeilen, 'total')), 2);
            $gesamtsumme += $zwischensumme;

            $modell = $stammdaten->get($artikelId);

            $artikel[] = [
                'article_id' => (int) $artikelId,
                'sku' => $modell?->sku ?? 'Unbekannt',
                'name' => $modell?->name ?? 'Unbekannter Artikel',
                'geloescht' => $modell?->trashed() ?? false,
                'rows' => $zeilen,
                'subtotal' => $zwischensumme,
            ];
        }

        usort($artikel, fn (array $a, array $b) => strcmp($a['sku'], $b['sku']));

        return [
            'stichtag' => $stichtag,
            'articles' => $artikel,
            'grand_total' => round($gesamtsumme, 2),
            'ohne_preis_artikel' => $ohnePreisArtikel,
            'ohne_preis_menge' => $ohnePreisMenge,
            'artikel_gesamt' => count($artikel),
        ];
    }
}
```

- [ ] **Step 4: Test ausführen, Erfolg bestätigen**

Run: `php artisan test --compact tests/Feature/BestandswertServiceTest.php`
Expected: PASS (11 Tests).

- [ ] **Step 5: Pint und Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/BestandswertService.php tests/Feature/BestandswertServiceTest.php
git commit -m "BestandswertService mit FIFO-Aufschlüsselung"
```

---

### Task 2: Bestandswert in der Vorschau

**Files:**
- Modify: `app/Http/Controllers/BalanceReportController.php` (Konstruktor und `index()`)
- Modify: `resources/js/Pages/Reports/Balance.vue`
- Test: `tests/Feature/BalanceReportPageTest.php`

**Interfaces:**
- Consumes: `BestandswertService::build()` aus Task 1.
- Produces: Die Inertia-Seite `Reports/Balance` erhält zusätzlich das Prop `bestandswert` mit den Schlüsseln `stichtag` (String `Y-m-d`), `articles`, `grandTotal`, `ohnePreisArtikel`, `ohnePreisMenge`, `artikelGesamt`.

- [ ] **Step 1: Failing test schreiben**

In `tests/Feature/BalanceReportPageTest.php` die Imports ergänzen:

```php
use App\Models\Stock;
use App\Models\Supplier;
```

Und am Ende der Klasse einfügen:

```php
    public function test_die_seite_liefert_den_bestandswert(): void
    {
        $article = Article::factory()->create(['sku' => 'SKU-7', 'name' => 'Mutter']);
        $location = StorageLocation::factory()->create();
        Stock::factory()->create([
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
            'quantity' => 50,
        ]);

        $lieferant = Supplier::factory()->create(['name' => 'Mueller']);
        $article->suppliers()->attach($lieferant->id, ['price' => 2.00, 'is_default' => true]);

        $response = $this->get(route('reports.balance.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Reports/Balance')
            ->has('bestandswert.articles', 1)
            ->where('bestandswert.articles.0.sku', 'SKU-7')
            ->where('bestandswert.articles.0.rows.0.quantity', 50)
            ->where('bestandswert.grandTotal', 100.0)
            ->where('bestandswert.ohnePreisArtikel', 0)
            ->where('bestandswert.artikelGesamt', 1)
        );
    }

    public function test_der_bestandswert_haengt_nicht_am_zeitraum(): void
    {
        $article = Article::factory()->create();
        Stock::factory()->create(['article_id' => $article->id, 'quantity' => 25]);

        // Zeitraum ohne jede Bewegung: der Bewegungsteil bleibt leer, der
        // Bestandswert zeigt das Lager trotzdem vollständig.
        $response = $this->get(route('reports.balance.index', [
            'from' => '2020-01-01',
            'to' => '2020-01-02',
        ]));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('articles', 0)
            ->has('bestandswert.articles', 1)
            ->where('bestandswert.articles.0.rows.0.quantity', 25)
        );
    }
```

Falls `StorageLocation` in der Datei noch nicht importiert ist, den Import ebenfalls ergänzen.

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

Run: `php artisan test --compact --filter=bestandswert tests/Feature/BalanceReportPageTest.php`
Expected: FAIL — `Inertia property [bestandswert.articles] does not exist.`

- [ ] **Step 3: Controller erweitern**

In `app/Http/Controllers/BalanceReportController.php` den Import ergänzen:

```php
use App\Services\BestandswertService;
```

Den Konstruktor ersetzen durch:

```php
    public function __construct(
        private BalanceReportService $balanceReport,
        private BestandswertService $bestandswert,
    ) {}
```

Und in `index()` den `Inertia::render`-Aufruf ersetzen durch:

```php
        $wert = $this->bestandswert->build();

        return Inertia::render('Reports/Balance', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'articles' => $bericht['articles'],
            'grandTotal' => $bericht['grand_total'],
            'bestandswert' => [
                'stichtag' => $wert['stichtag']->toDateString(),
                'articles' => $wert['articles'],
                'grandTotal' => $wert['grand_total'],
                'ohnePreisArtikel' => $wert['ohne_preis_artikel'],
                'ohnePreisMenge' => $wert['ohne_preis_menge'],
                'artikelGesamt' => $wert['artikel_gesamt'],
            ],
        ]);
```

- [ ] **Step 4: Test ausführen, Erfolg bestätigen**

Run: `php artisan test --compact --filter=bestandswert tests/Feature/BalanceReportPageTest.php`
Expected: PASS (2 Tests).

- [ ] **Step 5: Vorschau ergänzen**

In `resources/js/Pages/Reports/Balance.vue` das Prop ergänzen. Die `defineProps`-Definition wird zu:

```js
const props = defineProps({
    from: String,
    to: String,
    articles: Array,
    grandTotal: Number,
    bestandswert: Object,
});
```

Im Template nach dem schließenden `</div>` der bestehenden Karte — also nach der Karte mit der Bewegungstabelle, noch innerhalb von `<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">` — folgenden Abschnitt einfügen:

```vue
                <div
                    class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mt-6"
                >
                    <h3 class="text-lg font-medium text-gray-900 mb-1">
                        Bestandswert zum
                        {{ datum(bestandswert.stichtag) }}
                    </h3>
                    <p class="text-sm text-gray-500 mb-6">
                        Momentaufnahme des Lagers, unabhängig vom gewählten
                        Zeitraum.
                    </p>

                    <div class="overflow-x-auto">
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
                                        Wert
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template
                                    v-for="artikel in bestandswert.articles"
                                    :key="artikel.article_id"
                                >
                                    <tr
                                        v-for="(zeile, index) in artikel.rows"
                                        :key="artikel.article_id + '-' + index"
                                        :class="zeile.ohne_lieferant ? 'text-gray-500' : ''"
                                    >
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            {{ index === 0 ? artikel.sku : "" }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <template v-if="index === 0">
                                                {{ artikel.name }}
                                                <span
                                                    v-if="artikel.geloescht"
                                                    class="text-xs text-red-600"
                                                    >(gelöscht)</span
                                                >
                                            </template>
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
                                <tr v-if="bestandswert.articles.length === 0">
                                    <td colspan="6" class="px-4 py-4 text-center text-gray-500">
                                        Derzeit liegt kein Bestand im Lager
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-gray-300 font-semibold">
                                    <td colspan="5" class="px-4 py-3 text-right">
                                        Gesamtwert des Lagers
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        {{ waehrung(bestandswert.grandTotal) }}
                                    </td>
                                </tr>
                                <tr v-if="bestandswert.ohnePreisArtikel > 0">
                                    <td
                                        colspan="6"
                                        class="px-4 pb-3 text-right text-sm text-gray-500"
                                    >
                                        davon ohne hinterlegten Preis:
                                        {{ bestandswert.ohnePreisArtikel }} von
                                        {{ bestandswert.artikelGesamt }} Artikeln
                                        ({{ bestandswert.ohnePreisMenge }} Stück)
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
```

Im `<script setup>` neben dem bestehenden `waehrung`-Helfer ergänzen:

```js
const datum = (wert) =>
    new Intl.DateTimeFormat("de-DE").format(new Date(wert));
```

- [ ] **Step 6: Bauen und Tests ausführen**

```bash
npm run build
php artisan test --compact tests/Feature/BalanceReportPageTest.php tests/Feature/InertiaSeitenSmokeTest.php
```

Expected: PASS.

- [ ] **Step 7: Pint und Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/BalanceReportController.php resources/js/Pages/Reports/Balance.vue tests/Feature/BalanceReportPageTest.php
git commit -m "Bestandswert in der Bilanz-Vorschau"
```

---

### Task 3: Bestandswert in PDF und XLSX

**Files:**
- Modify: `app/Http/Controllers/BalanceReportController.php` (`export()`, `alsPdf()`, `alsXlsx()`)
- Modify: `resources/views/reports/balance.blade.php`
- Test: `tests/Feature/BalanceReportExportTest.php`

**Interfaces:**
- Consumes: `BestandswertService::build()` aus Task 1, das bereits injizierte `$this->bestandswert` aus Task 2.
- Produces: keine neuen Schnittstellen. PDF und XLSX enthalten denselben Abschnitt wie die Vorschau, jeweils zwischen der Bewegungstabelle und dem Unterschriftenblock.

**Achtung, zwei Schreibweisen:** Die Vorschau aus Task 2 bekommt die Schlüssel in
camelCase (`grandTotal`, `ohnePreisArtikel`, …), weil das die Konvention der
Inertia-Props ist. PDF und XLSX arbeiten dagegen direkt auf dem unveränderten
Rückgabewert des Service und verwenden deshalb snake_case (`grand_total`,
`ohne_preis_artikel`, …). Das ist Absicht, keine Unachtsamkeit — beim Schreiben
der Blade-View und des XLSX-Teils also die snake_case-Namen nutzen.

- [ ] **Step 1: Failing test schreiben**

In `tests/Feature/BalanceReportExportTest.php` die Imports ergänzen:

```php
use App\Models\Stock;
use App\Models\StorageLocation;
```

Und am Ende der Klasse einfügen:

```php
    public function test_pdf_view_enthaelt_den_bestandswert(): void
    {
        $this->bestandAnlegen();

        $bericht = app(\App\Services\BalanceReportService::class)->build(
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-01-31')
        );
        $wert = app(\App\Services\BestandswertService::class)->build();

        $html = view('reports.balance', array_merge($bericht, ['bestandswert' => $wert]))->render();

        $this->assertStringContainsString('Bestandswert zum', $html);
        $this->assertStringContainsString('zeitraumunabh', $html);
        $this->assertStringContainsString('Gesamtwert des Lagers', $html);
        $this->assertStringContainsString('SKU-B', $html);
        $this->assertStringContainsString('100,00', $html);
        $this->assertStringContainsString('davon ohne hinterlegten Preis', $html);
    }

    public function test_xlsx_enthaelt_den_bestandswert(): void
    {
        $this->bestandAnlegen();

        $response = $this->get(route('reports.balance.export', [
            'from' => '2026-01-01',
            'to' => '2026-01-31',
            'format' => 'xlsx',
        ]));

        $response->assertOk();

        $pfad = tempnam(sys_get_temp_dir(), 'bilanz');
        file_put_contents($pfad, $response->streamedContent());

        try {
            $zeilen = [];
            $reader = new Reader;
            $reader->open($pfad);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $zeilen[] = $row->toArray();
                }
            }

            $reader->close();
        } finally {
            unlink($pfad);
        }

        $ersteSpalte = array_map(fn ($zeile) => (string) ($zeile[0] ?? ''), $zeilen);
        $zweiteSpalte = array_map(fn ($zeile) => (string) ($zeile[1] ?? ''), $zeilen);

        $this->assertNotEmpty(array_filter(
            $ersteSpalte,
            fn (string $z) => str_contains($z, 'Bestandswert zum')
        ));
        $this->assertContains('SKU-B', $ersteSpalte);
        $this->assertContains('Gesamtwert des Lagers', $zweiteSpalte);

        $summenzeile = collect($zeilen)->first(
            fn ($zeile) => ($zeile[1] ?? null) === 'Gesamtwert des Lagers'
        );

        $this->assertEquals(100.0, $summenzeile[5]);
    }

    /**
     * Legt einen Artikel mit Bestand und Standardpreis an, der im
     * Bewegungsteil nicht vorkommt.
     */
    private function bestandAnlegen(): void
    {
        $article = Article::factory()->create(['sku' => 'SKU-B', 'name' => 'Bolzen']);
        $location = StorageLocation::factory()->create();

        Stock::factory()->create([
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
            'quantity' => 50,
        ]);

        $lieferant = Supplier::factory()->create(['name' => 'Mueller']);
        $article->suppliers()->attach($lieferant->id, ['price' => 2.00, 'is_default' => true]);
    }
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

Run: `php artisan test --compact --filter=bestandswert tests/Feature/BalanceReportExportTest.php`
Expected: FAIL — `Undefined variable $bestandswert` im PDF-Test, und der XLSX-Test findet keine Zeile mit `Bestandswert zum`.

- [ ] **Step 3: `export()` erweitern**

In `app/Http/Controllers/BalanceReportController.php` in `export()` nach der Zeile `$bericht = $this->balanceReport->build($from, $to);` einfügen:

```php
        $bericht['bestandswert'] = $this->bestandswert->build();
```

Die beiden Aufrufe am Ende der Methode bleiben unverändert — beide Helfer bekommen das erweiterte `$bericht`.

- [ ] **Step 4: Blade-View erweitern**

In `resources/views/reports/balance.blade.php` im `<style>`-Block ergänzen:

```css
        h2 { font-size: 13px; margin: 28px 0 2px; }
        .hinweis { font-size: 9px; color: #555; margin-bottom: 10px; }
        .fussnote td { border: none; font-size: 9px; color: #555; padding-top: 2px; }
        .rest td { color: #555; }
```

Zwischen dem schließenden `</table>` der Bewegungstabelle und der öffnenden Zeile `<table class="unterschrift">` einfügen:

```blade
    <h2>Bestandswert zum {{ $bestandswert['stichtag']->format('d.m.Y') }}</h2>
    <div class="hinweis">Momentaufnahme des Lagers, zeitraumunabh&auml;ngig.</div>

    <table>
        <thead>
            <tr>
                <th>Artikelnummer</th>
                <th>Bezeichnung</th>
                <th>Lieferant</th>
                <th class="rechts">Menge</th>
                <th class="rechts">St&uuml;ckpreis</th>
                <th class="rechts">Wert</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($bestandswert['articles'] as $artikel)
                @foreach ($artikel['rows'] as $index => $zeile)
                    <tr @class(['rest' => $zeile['ohne_lieferant']])>
                        <td>{{ $index === 0 ? $artikel['sku'] : '' }}</td>
                        <td>{{ $index === 0 ? $artikel['name'] : '' }}@if ($index === 0 && $artikel['geloescht']) (gel&ouml;scht)@endif</td>
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
                    <td colspan="6" class="leer">Derzeit liegt kein Bestand im Lager</td>
                </tr>
            @endforelse
            <tr class="gesamtsumme">
                <td colspan="5" class="rechts">Gesamtwert des Lagers</td>
                <td class="rechts">{{ number_format($bestandswert['grand_total'], 2, ',', '.') }} &euro;</td>
            </tr>
            @if ($bestandswert['ohne_preis_artikel'] > 0)
                <tr class="fussnote">
                    <td colspan="6" class="rechts">
                        davon ohne hinterlegten Preis: {{ $bestandswert['ohne_preis_artikel'] }}
                        von {{ $bestandswert['artikel_gesamt'] }} Artikeln
                        ({{ number_format($bestandswert['ohne_preis_menge'], 0, ',', '.') }} St&uuml;ck)
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
```

- [ ] **Step 5: `alsXlsx()` erweitern**

In `app/Http/Controllers/BalanceReportController.php` in `alsXlsx()` **vor** dem Block, der den Unterschriftenblock schreibt — also vor `$writer->addRow(Row::fromValues([]));` direkt nach der Gesamtsummenzeile — einfügen:

```php
            $wert = $bericht['bestandswert'];

            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValuesWithStyle(
                ['Bestandswert zum '.$wert['stichtag']->format('d.m.Y')],
                $fett
            ));
            $writer->addRow(Row::fromValues(['Momentaufnahme des Lagers, zeitraumunabhängig.']));
            $writer->addRow(Row::fromValues([]));

            $writer->addRow(Row::fromValuesWithStyle(
                ['Artikelnummer', 'Bezeichnung', 'Lieferant', 'Menge', 'Stückpreis', 'Wert'],
                $fett
            ));

            if ($wert['articles'] === []) {
                $writer->addRow(Row::fromValues(['Derzeit liegt kein Bestand im Lager']));
            }

            foreach ($wert['articles'] as $artikel) {
                foreach ($artikel['rows'] as $index => $zeile) {
                    $writer->addRow(Row::fromValues([
                        $index === 0 ? $artikel['sku'] : '',
                        $index === 0
                            ? $artikel['name'].($artikel['geloescht'] ? ' (gelöscht)' : '')
                            : '',
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
                ['', 'Gesamtwert des Lagers', '', '', '', $wert['grand_total']],
                $fett
            ));

            if ($wert['ohne_preis_artikel'] > 0) {
                $writer->addRow(Row::fromValues(['', sprintf(
                    'davon ohne hinterlegten Preis: %d von %d Artikeln (%d Stück)',
                    $wert['ohne_preis_artikel'],
                    $wert['artikel_gesamt'],
                    $wert['ohne_preis_menge']
                )]));
            }
```

Der bestehende Unterschriftenblock bleibt danach unverändert stehen.

- [ ] **Step 6: Test ausführen, Erfolg bestätigen**

Run: `php artisan test --compact tests/Feature/BalanceReportExportTest.php`
Expected: PASS (alle Tests der Datei).

- [ ] **Step 7: Gesamte Suite ausführen**

Run: `php artisan test --compact`
Expected: PASS. Ausgangswert vor diesem Plan: 127 bestanden, 10 übersprungen.

- [ ] **Step 8: Pint, Bauen und Commit**

```bash
vendor/bin/pint --dirty --format agent
npm run build
git add app/Http/Controllers/BalanceReportController.php resources/views/reports/balance.blade.php tests/Feature/BalanceReportExportTest.php
git commit -m "Bestandswert in PDF und XLSX"
```

---

## Abschluss

- [ ] `php artisan test --compact` läuft grün.
- [ ] `vendor/bin/pint --dirty --format agent` meldet keine Änderungen.
- [ ] `npm run build` ist mit dem letzten Frontend-Stand gelaufen.
- [ ] `CLAUDE.md` um einen Satz zum `BestandswertService` und zur FIFO-Annahme ergänzen.

## Bewusst nicht enthalten

- Eine andere Verbrauchsannahme als FIFO.
- Das Nachtragen von Lieferanten oder Preisen an bestehenden Bewegungen.
- Eine Bestandsbewertung zu einem zurückliegenden Stichtag.
- Änderungen am Bewegungsteil der Bilanz.
