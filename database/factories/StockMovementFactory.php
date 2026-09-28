<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'supplier_id' => null,
            'from_storage_location_id' => null,
            'to_storage_location_id' => StorageLocation::factory(),
            'quantity' => fake()->numberBetween(1, 50),
            'unit_price' => null,
            'type' => 'in',
            'user_id' => User::factory(),
            'notes' => null,
        ];
    }
}
