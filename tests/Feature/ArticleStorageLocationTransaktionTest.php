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

class ArticleStorageLocationTransaktionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_entfernen_mit_restbestand_schreibt_eine_ausbuchung(): void
    {
        [$article, $location] = $this->zuordnungMitBestand(40);

        $response = $this->delete(route('articles.storage-locations.destroy', [$article, $location]));

        $response->assertRedirect(route('articles.show', $article->id));
        $this->assertDatabaseHas('stock_movements', [
            'article_id' => $article->id,
            'from_storage_location_id' => $location->id,
            'to_storage_location_id' => null,
            'quantity' => 40,
            'type' => 'out',
            'notes' => 'Lagerplatz-Zuordnung entfernt',
        ]);
        $this->assertDatabaseMissing('stocks', [
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
        ]);
    }

    public function test_nach_dem_entfernen_stimmen_journal_und_bestand_ueberein(): void
    {
        [$article, $location] = $this->zuordnungMitBestand(40);

        $this->delete(route('articles.storage-locations.destroy', [$article, $location]));

        $this->assertSame(0, $this->journalstand($article->id));
        $this->assertSame(0, (int) Stock::where('article_id', $article->id)->sum('quantity'));
    }

    public function test_entfernen_ohne_restbestand_schreibt_keine_buchung(): void
    {
        [$article, $location] = $this->zuordnungMitBestand(0);
        StockMovement::where('article_id', $article->id)->delete();

        $this->delete(route('articles.storage-locations.destroy', [$article, $location]));

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_korrektur_ohne_mengenaenderung_schreibt_keine_buchung(): void
    {
        [$article, $location] = $this->zuordnungMitBestand(40);
        StockMovement::where('article_id', $article->id)->delete();

        $this->post(route('articles.storage-locations.correction', [$article, $location]), [
            'new_quantity' => 40,
        ]);

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('stocks', [
            'article_id' => $article->id,
            'quantity' => 40,
        ]);
    }

    public function test_scheitert_die_buchung_bleibt_auch_der_bestand_unveraendert(): void
    {
        [$article, $location] = $this->zuordnungMitBestand(40);

        // Jeden Insert in stock_movements scheitern lassen, um die
        // Transaktionsklammer zu prüfen.
        DB::beforeExecuting(function ($query) {
            if (str_contains($query, 'insert into `stock_movements`')) {
                throw new \RuntimeException('Buchung absichtlich fehlgeschlagen');
            }
        });

        try {
            $this->delete(route('articles.storage-locations.destroy', [$article, $location]));
        } catch (\Throwable) {
            // erwartet
        }

        $this->assertDatabaseHas('stocks', [
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
            'quantity' => 40,
        ]);
    }

    /**
     * Legt Artikel, Lagerplatz und Bestand an und liefert beide Modelle.
     *
     * @return array{0: Article, 1: StorageLocation}
     */
    private function zuordnungMitBestand(int $menge): array
    {
        $article = Article::factory()->create();
        $location = StorageLocation::factory()->create();

        Stock::factory()->create([
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
            'quantity' => $menge,
        ]);

        if ($menge > 0) {
            StockMovement::factory()->create([
                'article_id' => $article->id,
                'to_storage_location_id' => $location->id,
                'quantity' => $menge,
                'type' => 'in',
            ]);
        }

        return [$article, $location];
    }

    private function journalstand(int $articleId): int
    {
        return (int) (StockMovement::journalstaende()[$articleId] ?? 0);
    }
}
