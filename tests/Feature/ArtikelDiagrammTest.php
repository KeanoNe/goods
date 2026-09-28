<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ArtikelDiagrammTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_korrekturen_veraendern_die_diagrammwerte_nicht(): void
    {
        $article = Article::factory()->create();
        $location = StorageLocation::factory()->create();

        StockMovement::factory()->create([
            'article_id' => $article->id,
            'to_storage_location_id' => $location->id,
            'quantity' => 5000,
            'type' => 'correction',
            'created_at' => now(),
        ]);

        $this->get(route('articles.show', $article))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dailyChanges', fn ($tage) => collect($tage)->sum('net_change') === 0)
            );
    }

    public function test_transfers_veraendern_die_diagrammwerte_nicht(): void
    {
        $article = Article::factory()->create();
        $vonLager = StorageLocation::factory()->create();
        $nachLager = StorageLocation::factory()->create();

        StockMovement::factory()->create([
            'article_id' => $article->id,
            'from_storage_location_id' => $vonLager->id,
            'to_storage_location_id' => $nachLager->id,
            'quantity' => 300,
            'type' => 'transfer',
            'created_at' => now(),
        ]);

        $this->get(route('articles.show', $article))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dailyChanges', fn ($tage) => collect($tage)->sum('net_change') === 0)
            );
    }

    public function test_zugang_und_abgang_werden_weiterhin_verrechnet(): void
    {
        $article = Article::factory()->create();
        $location = StorageLocation::factory()->create();

        StockMovement::factory()->create([
            'article_id' => $article->id,
            'to_storage_location_id' => $location->id,
            'quantity' => 100,
            'type' => 'in',
            'created_at' => now(),
        ]);
        StockMovement::factory()->create([
            'article_id' => $article->id,
            'from_storage_location_id' => $location->id,
            'to_storage_location_id' => null,
            'quantity' => 30,
            'type' => 'out',
            'created_at' => now(),
        ]);

        $this->get(route('articles.show', $article))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dailyChanges', fn ($tage) => collect($tage)->sum('net_change') === 70)
            );
    }
}
