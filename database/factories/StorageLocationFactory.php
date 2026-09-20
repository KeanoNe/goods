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
     * Definiert den Standardzustand des Modells.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Lagerplatz '.fake()->unique()->numerify('##'),
            'description' => fake()->sentence(),
            'shelf_id' => Shelf::factory(),
            'notes' => null,
        ];
    }
}
