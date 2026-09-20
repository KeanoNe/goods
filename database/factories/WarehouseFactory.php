<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    /**
     * Definiert den Standardzustand des Modells.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Lager '.fake()->unique()->numerify('##'),
            'description' => fake()->sentence(),
            'location' => fake()->streetAddress(),
            'notes' => null,
        ];
    }
}
