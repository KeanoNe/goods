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
