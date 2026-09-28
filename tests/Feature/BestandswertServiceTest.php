<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Services\BestandswertService;
use Carbon\Carbon;
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

    public function test_name_eines_geloeschten_lieferanten_bleibt_erhalten(): void
    {
        $article = Article::factory()->create();
        $location = StorageLocation::factory()->create();
        Stock::factory()->create([
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
            'quantity' => 30,
        ]);

        $lieferant = Supplier::factory()->create(['name' => 'Mueller']);
        $this->zugang($article, $location, $lieferant, 30, 1.50, '2026-01-10');
        $lieferant->delete();

        $zeilen = $this->service->build()['articles'][0]['rows'];

        $this->assertCount(1, $zeilen);
        $this->assertSame('Mueller', $zeilen[0]['supplier']);
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
            'created_at' => Carbon::parse($zeitpunkt),
        ]);
    }
}
