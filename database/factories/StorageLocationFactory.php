<?php

namespace Database\Factories;

use App\Models\Shelf;
use App\Models\StorageLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageLocation>
 */
class StorageLocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->bothify('?#-#-##'),
            'description' => fake()->sentence(),
            'shelf_id' => Shelf::factory(),
            'notes' => null,
        ];
    }
}
