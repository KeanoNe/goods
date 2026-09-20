<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BalanceReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
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
        $dritteSpalte = array_map(fn ($zeile) => (string) ($zeile[2] ?? ''), $zeilen);

        $this->assertContains('Bestandsbilanz', $ersteSpalte);
        $this->assertContains('Artikelnummer', $ersteSpalte);
        $this->assertContains('SKU-7', $ersteSpalte);
        $this->assertContains('Ort, Datum', $ersteSpalte);
        $this->assertContains('Gesamtsumme', $zweiteSpalte);
        $this->assertContains('Unterschrift', $dritteSpalte);

        $datenzeile = collect($zeilen)->first(fn ($zeile) => ($zeile[0] ?? null) === 'SKU-7');

        $this->assertSame('Mutter', $datenzeile[1]);
        $this->assertSame('Mueller', $datenzeile[2]);
        // OpenSpout liest ganzzahlige Werte beim Zurücklesen als int zurück
        // (verifiziert: quantity 10 und unit_price 2.00 kommen als int(10)/int(2) zurück) —
        // assertSame prüft damit zugleich den Typ (numerisch, nicht als String formatiert).
        $this->assertSame(10, $datenzeile[3]);
        $this->assertSame(2, $datenzeile[4]);
        $this->assertSame(20, $datenzeile[5]);

        $summenzeile = collect($zeilen)->first(fn ($zeile) => ($zeile[1] ?? null) === 'Gesamtsumme');

        $this->assertSame(20, $summenzeile[5]);
    }

    public function test_pdf_view_ohne_bewegungen_zeigt_leerhinweis(): void
    {
        $bericht = app(BalanceReportService::class)->build(
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-01-31')
        );

        $html = view('reports.balance', $bericht)->render();

        $this->assertStringContainsString('Im gew&auml;hlten Zeitraum gibt es keine Bestandsbewegungen', $html);
    }

    public function test_xlsx_ohne_bewegungen_zeigt_leerhinweis(): void
    {
        $response = $this->get(route('reports.balance.export', [
            'from' => '2026-01-01',
            'to' => '2026-01-31',
            'format' => 'xlsx',
        ]));

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

        $this->assertContains('Im gewählten Zeitraum gibt es keine Bestandsbewegungen', $ersteSpalte);
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
