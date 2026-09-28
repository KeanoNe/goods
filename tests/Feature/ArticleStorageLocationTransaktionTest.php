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

    public function test_die_bestandszeile_wird_beim_entfernen_gesperrt(): void
    {
        [$article, $location] = $this->zuordnungMitBestand(40);

        $abfragen = [];
        DB::listen(function ($abfrage) use (&$abfragen) {
            $abfragen[] = strtolower($abfrage->sql);
        });

        $this->delete(route('articles.storage-locations.destroy', [$article, $location]))
            ->assertRedirect(route('articles.show', $article->id));

        $sperren = array_filter($abfragen, fn (string $sql) => str_contains($sql, 'from `stocks`') && str_contains($sql, 'for update'));

        $this->assertNotEmpty($sperren, 'Die stocks-Zeile wurde beim Entfernen ohne "for update" gelesen.');
    }

    public function test_zweites_entfernen_bucht_nicht_erneut_aus(): void
    {
        // Simuliert den Kern des Bugs: zwei Entfernungen derselben
        // Zuordnung. Ohne Sperre lasen beide dieselbe Menge und buchten
        // beide eine Ausbuchung; die zweite Löschung war ein stiller
        // No-Op auf einer bereits gelöschten Zeile. Mit der Sperre findet
        // die zweite Anfrage die Zeile bereits gelöscht vor und bucht
        // nichts.
        [$article, $location] = $this->zuordnungMitBestand(40);

        $this->delete(route('articles.storage-locations.destroy', [$article, $location]))
            ->assertRedirect(route('articles.show', $article->id));

        $this->delete(route('articles.storage-locations.destroy', [$article, $location]))
            ->assertNotFound();

        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('stock_movements', [
            'article_id' => $article->id,
            'type' => 'out',
            'quantity' => 40,
        ]);
        $this->assertSame(0, $this->journalstand($article->id));
    }

    public function test_scheitert_das_loeschen_wird_auch_die_ausbuchung_zurueckgerollt(): void
    {
        [$article, $location] = $this->zuordnungMitBestand(40);

        // Beim Entfernen wird zuerst die Ausbuchung geschrieben und danach
        // der Stock-Satz gelöscht. Nur wenn der zweite (spätere) Schritt
        // scheitert, prüft das wirklich, ob der erste Schritt zurückgerollt
        // wird — ein Fehlschlag beim Insert selbst würde diesen Test auch
        // ohne Transaktionsklammer bestehen lassen.
        DB::beforeExecuting(function ($query) {
            if (str_contains($query, 'delete from `stocks`')) {
                throw new \RuntimeException('Löschen absichtlich fehlgeschlagen');
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
        $this->assertDatabaseMissing('stock_movements', [
            'article_id' => $article->id,
            'type' => 'out',
        ]);
    }

    public function test_scheitert_die_buchung_beim_zuweisen_bleibt_kein_bestand(): void
    {
        $article = Article::factory()->create();
        $location = StorageLocation::factory()->create();

        // Beim Zuweisen wird zuerst der Stock-Satz angelegt und danach die
        // Buchung geschrieben. Der Insert in stock_movements ist hier der
        // spätere Schritt, dessen Fehlschlag den bereits angelegten
        // Stock-Satz zurückrollen muss.
        DB::beforeExecuting(function ($query) {
            if (str_contains($query, 'insert into `stock_movements`')) {
                throw new \RuntimeException('Buchung absichtlich fehlgeschlagen');
            }
        });

        try {
            $this->post(route('articles.storage-locations.store', $article), [
                'storage_location_id' => $location->id,
                'quantity' => 40,
            ]);
        } catch (\Throwable) {
            // erwartet
        }

        $this->assertDatabaseMissing('stocks', [
            'article_id' => $article->id,
            'storage_location_id' => $location->id,
        ]);
    }

    public function test_scheitert_die_buchung_bei_der_korrektur_bleibt_der_alte_bestand(): void
    {
        [$article, $location] = $this->zuordnungMitBestand(40);

        // Bei der Korrektur wird zuerst der Stock-Satz aktualisiert und
        // danach die Buchung geschrieben. Der Insert in stock_movements ist
        // hier der spätere Schritt, dessen Fehlschlag die bereits
        // geschriebene Mengenänderung zurückrollen muss.
        DB::beforeExecuting(function ($query) {
            if (str_contains($query, 'insert into `stock_movements`')) {
                throw new \RuntimeException('Buchung absichtlich fehlgeschlagen');
            }
        });

        try {
            $this->post(route('articles.storage-locations.correction', [$article, $location]), [
                'new_quantity' => 90,
            ]);
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
