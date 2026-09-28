<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
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

        $this->expectException(QueryException::class);

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
