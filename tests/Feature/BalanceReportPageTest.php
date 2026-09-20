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
            'unit_price' => 1.99,
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
            ->where('grandTotal', 19.90)
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

    public function test_bei_ungueltigem_zeitraum_liefert_der_redirect_weiterhin_den_zuletzt_gueltigen_zeitraum(): void
    {
        $this->get(route('reports.balance.index', [
            'from' => '2026-02-01',
            'to' => '2026-02-10',
        ]))->assertOk();

        $response = $this->get(route('reports.balance.index', [
            'from' => '2026-03-31',
            'to' => '2026-03-01',
        ]));

        $response->assertSessionHasErrors('to');

        $ziel = $this->get($response->headers->get('Location'));

        $ziel->assertOk();
        $ziel->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Reports/Balance')
            ->where('from', '2026-02-01')
            ->where('to', '2026-02-10')
        );
    }

    public function test_bilanzseite_ist_fuer_gaeste_gesperrt(): void
    {
        auth()->logout();

        $this->get(route('reports.balance.index'))->assertRedirect(route('login'));
    }
}
