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
