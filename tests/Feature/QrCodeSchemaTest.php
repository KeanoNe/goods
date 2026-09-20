<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Shelf;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QrCodeSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Jedes Feld in $fillable muss eine echte Spalte sein, sonst scheitert
     * jedes create()/update() mit diesem Feld an einem "Unknown column".
     *
     * @return array<string, array{class-string<Model>}>
     */
    public static function modelProvider(): array
    {
        return [
            'Article' => [Article::class],
            'StorageLocation' => [StorageLocation::class],
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    #[DataProvider('modelProvider')]
    public function test_fillable_felder_existieren_als_spalten(string $modelClass): void
    {
        $model = new $modelClass;
        $table = $model->getTable();

        foreach ($model->getFillable() as $field) {
            $this->assertTrue(
                Schema::hasColumn($table, $field),
                "Feld '{$field}' steht in {$modelClass}::\$fillable, existiert aber nicht als Spalte in '{$table}'."
            );
        }
    }

    public function test_lagerplatz_wird_trotz_qr_code_im_request_angelegt(): void
    {
        $shelf = Shelf::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->post(route('storage-locations.store'), [
                'name' => 'A1-1-1',
                'shelf_id' => $shelf->id,
                'description' => 'Erster Lagerplatz',
                'qr_code' => 'QR123',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('storage_locations', ['name' => 'A1-1-1']);
    }

    public function test_lagerplatz_wird_trotz_qr_code_im_request_aktualisiert(): void
    {
        $storageLocation = StorageLocation::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->put(route('storage-locations.update', $storageLocation), [
                'name' => 'A1-1-2',
                'shelf_id' => $storageLocation->shelf_id,
                'qr_code' => 'QR456',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('storage_locations', [
            'id' => $storageLocation->id,
            'name' => 'A1-1-2',
        ]);
    }

    public function test_artikel_wird_trotz_qr_code_im_request_angelegt(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->post(route('articles.store'), [
                'name' => 'Schraube M4',
                'sku' => 'SKU-0001',
                'minimum_stock' => 5,
                'qr_code' => 'QR789',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('articles', ['sku' => 'SKU-0001']);
    }

    public function test_artikel_wird_trotz_qr_code_im_request_aktualisiert(): void
    {
        $article = Article::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->put(route('articles.update', $article), [
                'name' => 'Schraube M5',
                'sku' => $article->sku,
                'minimum_stock' => 3,
                'qr_code' => 'QR999',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'name' => 'Schraube M5',
        ]);
    }
}
