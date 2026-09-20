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
