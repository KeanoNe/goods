<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Stock;
use App\Models\StorageLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stock>
 */
class StockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'storage_location_id' => StorageLocation::factory(),
            'quantity' => fake()->numberBetween(0, 100),
        ];
    }
}
