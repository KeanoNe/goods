<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleSupplierPriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_erster_lieferant_wird_automatisch_zum_standard(): void
    {
        $article = Article::factory()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->post(route('articles.suppliers.store', $article), [
            'supplier_id' => $supplier->id,
            'price' => 2.50,
        ]);

        $response->assertRedirect(route('articles.show', $article));
        $this->assertDatabaseHas('article_supplier', [
            'article_id' => $article->id,
            'supplier_id' => $supplier->id,
            'price' => 2.50,
            'is_default' => true,
        ]);
    }

    public function test_zweiter_lieferant_wird_nicht_automatisch_zum_standard(): void
    {
        $article = Article::factory()->create();
        $ersterLieferant = Supplier::factory()->create();
        $zweiterLieferant = Supplier::factory()->create();

        $this->post(route('articles.suppliers.store', $article), [
            'supplier_id' => $ersterLieferant->id,
            'price' => 2.50,
        ]);

        $this->post(route('articles.suppliers.store', $article), [
            'supplier_id' => $zweiterLieferant->id,
            'price' => 3.00,
        ]);

        $this->assertDatabaseHas('article_supplier', [
            'supplier_id' => $ersterLieferant->id,
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('article_supplier', [
            'supplier_id' => $zweiterLieferant->id,
            'is_default' => false,
        ]);
    }

    public function test_derselbe_lieferant_wird_nicht_doppelt_zugeordnet(): void
    {
        $article = Article::factory()->create();
        $supplier = Supplier::factory()->create();

        $this->post(route('articles.suppliers.store', $article), [
            'supplier_id' => $supplier->id,
            'price' => 2.50,
        ]);

        $response = $this->post(route('articles.suppliers.store', $article), [
            'supplier_id' => $supplier->id,
            'price' => 9.99,
        ]);

        $response->assertSessionHasErrors('supplier_id');
        $this->assertDatabaseCount('article_supplier', 1);
    }

    public function test_preis_wird_aktualisiert(): void
    {
        $article = Article::factory()->create();
        $supplier = Supplier::factory()->create();
        $article->suppliers()->attach($supplier->id, ['price' => 1.00, 'is_default' => true]);

        $response = $this->put(route('articles.suppliers.update', [$article, $supplier]), [
            'price' => 1.75,
            'is_default' => true,
        ]);

        $response->assertRedirect(route('articles.show', $article));
        $this->assertDatabaseHas('article_supplier', [
            'article_id' => $article->id,
            'supplier_id' => $supplier->id,
            'price' => 1.75,
        ]);
    }

    public function test_preisaenderung_ohne_is_default_laesst_standard_unveraendert(): void
    {
        $article = Article::factory()->create();
        $supplier = Supplier::factory()->create();
        $article->suppliers()->attach($supplier->id, ['price' => 1.00, 'is_default' => true]);

        $this->put(route('articles.suppliers.update', [$article, $supplier]), [
            'price' => 1.50,
        ]);

        $this->assertDatabaseHas('article_supplier', [
            'article_id' => $article->id,
            'supplier_id' => $supplier->id,
            'price' => 1.50,
            'is_default' => true,
        ]);
        $this->assertSame(1, \DB::table('article_supplier')
            ->where('article_id', $article->id)
            ->where('is_default', true)
            ->count());
    }

    public function test_neuer_standard_entzieht_dem_alten_die_markierung(): void
    {
        $article = Article::factory()->create();
        $alt = Supplier::factory()->create();
        $neu = Supplier::factory()->create();
        $article->suppliers()->attach($alt->id, ['price' => 1.00, 'is_default' => true]);
        $article->suppliers()->attach($neu->id, ['price' => 2.00, 'is_default' => false]);

        $this->put(route('articles.suppliers.update', [$article, $neu]), [
            'price' => 2.00,
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('article_supplier', ['supplier_id' => $alt->id, 'is_default' => false]);
        $this->assertDatabaseHas('article_supplier', ['supplier_id' => $neu->id, 'is_default' => true]);
        $this->assertSame(1, \DB::table('article_supplier')
            ->where('article_id', $article->id)
            ->where('is_default', true)
            ->count());
    }

    public function test_zuordnung_wird_geloest(): void
    {
        $article = Article::factory()->create();
        $supplier = Supplier::factory()->create();
        $article->suppliers()->attach($supplier->id, ['price' => 1.00, 'is_default' => true]);

        $response = $this->delete(route('articles.suppliers.destroy', [$article, $supplier]));

        $response->assertRedirect(route('articles.show', $article));
        $this->assertDatabaseCount('article_supplier', 0);
    }

    public function test_beim_loesen_des_standards_rueckt_ein_anderer_nach(): void
    {
        $article = Article::factory()->create();
        $standard = Supplier::factory()->create();
        $anderer = Supplier::factory()->create();
        $article->suppliers()->attach($standard->id, ['price' => 1.00, 'is_default' => true]);
        $article->suppliers()->attach($anderer->id, ['price' => 2.00, 'is_default' => false]);

        $this->delete(route('articles.suppliers.destroy', [$article, $standard]));

        $this->assertDatabaseHas('article_supplier', ['supplier_id' => $anderer->id, 'is_default' => true]);
    }

    public function test_negativer_preis_wird_abgelehnt(): void
    {
        $article = Article::factory()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->post(route('articles.suppliers.store', $article), [
            'supplier_id' => $supplier->id,
            'price' => -1,
        ]);

        $response->assertSessionHasErrors('price');
    }
}
