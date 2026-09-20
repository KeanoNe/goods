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
