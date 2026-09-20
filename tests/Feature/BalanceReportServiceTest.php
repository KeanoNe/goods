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
